<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiParserService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-2.0-flash');
        $this->baseUrl = config('services.gemini.api_url');
    }

    /**
     * Parse order text — AI first, fallback to manual regex
     */
    public function parseOrderText(string $text): array
    {
        // Coba AI dulu (kalau quota masih ada)
        $aiResult = $this->tryAiParse($text);
        if ($aiResult !== null && !isset($aiResult['error'])) {
            return $aiResult;
        }

        // Fallback: manual regex parser (tidak perlu AI)
        Log::info('AI quota habis, using manual parser');
        return $this->manualParse($text);
    }

    /**
     * Parse image — AI only, kalau gagal return error
     */
    public function parseOrderImage(string $base64Image, string $mimeType = 'image/jpeg'): array
    {
        return $this->callGeminiWithImage($base64Image, $mimeType);
    }

    /**
     * Coba AI parse, return null kalau gagal quota
     */
    protected function tryAiParse(string $text): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $prompt = <<<PROMPT
Kamu parser ERP. Extract pesanan jadi JSON.

Input bebas: "sabun mandi 5 harga 42rb", "sabun/5/42000", "5 pcs sabun 42.000"
Rules:
- 42rb, 42.000, 42000 → 42000
- 5 pcs, 5 → 5
- Harga tidak disebut → 0
- Catatan tambahan → "notes"

Return JSON: {"items":[{"name":"string","qty":5,"price":42000}],"notes":"string"}
PROMPT;

        $result = $this->callGemini($prompt . "\n\nInput:\n" . $text);
        
        // Kalau quota habis, return null supaya fallback
        if (isset($result['error']) && str_contains($result['error'], '429')) {
            return null;
        }

        return $result;
    }

    /**
     * Manual parser — tidak perlu AI, pakai regex
     */
    protected function manualParse(string $text): array
    {
        $items = [];
        $notes = '';
        $lines = array_filter(array_map('trim', explode("\n", $text)));

        foreach ($lines as $line) {
            // Format: Nama/Qty/Harga atau Nama/Qty
            if (str_contains($line, '/')) {
                $parts = array_map('trim', explode('/', $line));
                if (count($parts) >= 2) {
                    $items[] = [
                        'name' => $parts[0],
                        'qty' => $this->parseQty($parts[1]),
                        'price' => isset($parts[2]) ? $this->parseHarga($parts[2]) : 0,
                    ];
                }
                continue;
            }

            // Format bebas: "sabun mandi 5 harga 42rb"
            // Pattern: nama (huruf/spasi) + angka (qty) + [harga/dengan harga/@] + angka/harga
            if (preg_match('/^(.+?)\s+(\d+)\s*(?:pcs|pc|buah|biji|item)?\s*(?:harga|dengan harga|@|rp)?\s*([\d\.,]+[krb]?)?$/iu', $line, $matches)) {
                $items[] = [
                    'name' => trim($matches[1]),
                    'qty' => (int) $matches[2],
                    'price' => isset($matches[3]) ? $this->parseHarga($matches[3]) : 0,
                ];
                continue;
            }

            // Format: "5 sabun mandi 42000"
            if (preg_match('/^(\d+)\s+(.+?)\s+([\d\.,]+[krb]?)$/', $line, $matches)) {
                $items[] = [
                    'name' => trim($matches[2]),
                    'qty' => (int) $matches[1],
                    'price' => $this->parseHarga($matches[3]),
                ];
                continue;
            }

            // Kalau tidak match, masukkan ke notes
            $notes .= $line . ' ';
        }

        return [
            'items' => $items,
            'notes' => trim($notes),
        ];
    }

    protected function parseQty(string $text): int
    {
        return (int) preg_replace('/\D/', '', $text);
    }

    protected function parseHarga(string $text): int
    {
        $text = strtolower(trim($text));
        
        // 42rb, 42k → 42000
        if (str_contains($text, 'rb') || str_contains($text, 'k')) {
            $num = (float) preg_replace('/[^0-9.,]/', '', $text);
            return (int) ($num * 1000);
        }
        
        // 42.000, 42,000, 42000 → 42000
        $clean = str_replace(['.', ','], ['', ''], $text);
        $clean = preg_replace('/\D/', '', $clean);
        
        return (int) $clean;
    }

    protected function callGemini(string $fullPrompt): array
    {
        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(15)->post($url, [
                'contents' => [['role' => 'user', 'parts' => [['text' => $fullPrompt]]]],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 1024,
                    'responseMimeType' => 'application/json',
                ],
            ]);

            if ($response->status() === 429) {
                return ['error' => '429 Quota exceeded'];
            }

            if (!$response->successful()) {
                return ['error' => 'Gemini API: ' . $response->status()];
            }

            $jsonText = $response->json('candidates.0.content.parts.0.text', '');
            $jsonText = preg_replace('/^```json\s*/', '', $jsonText);
            $jsonText = preg_replace('/\s*```$/', '', $jsonText);

            $parsed = json_decode($jsonText, true);
            return $parsed ?: ['error' => 'JSON parse failed'];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    protected function callGeminiWithImage(string $base64Image, string $mimeType): array
    {
        if (empty($this->apiKey)) {
            return ['error' => 'GEMINI_API_KEY kosong'];
        }

        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";
        $prompt = 'OCR struk pesanan. Extract item,qty,harga. Return JSON: {"items":[{"name":"x","qty":5,"price":42000}],"notes":""}';

        try {
            $response = Http::timeout(30)->post($url, [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Image]]
                    ]
                ]],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 2048,
                    'responseMimeType' => 'application/json',
                ],
            ]);

            if ($response->status() === 429) {
                return ['error' => '429 Quota exceeded. Coba lagi nanti atau gunakan input text.'];
            }

            if (!$response->successful()) {
                return ['error' => 'Gemini API: ' . $response->body()];
            }

            $jsonText = $response->json('candidates.0.content.parts.0.text', '');
            $jsonText = preg_replace('/^```json\s*/', '', $jsonText);
            $jsonText = preg_replace('/\s*```$/', '', $jsonText);

            $parsed = json_decode($jsonText, true);
            return $parsed ?: ['error' => 'JSON parse failed'];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}