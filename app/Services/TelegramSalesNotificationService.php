<?php
// app/Services/TelegramSalesNotificationService.php

namespace App\Services;

use App\Models\SalesOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramSalesNotificationService
{
    private string $botToken;
    private string $chatId;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
    }

    public function notifyStageChange(SalesOrder $so, string $stage, string $status): void
    {
        if (empty($this->botToken) || empty($this->chatId)) return;

        $emoji = match($status) {
            'completed' => '✅',
            'current' => '🔄',
            'failed' => '❌',
            default => '⏳',
        };

        $messages = [
            'so' => "📋 *Sales Order Update*\n",
            'do' => "🚚 *Delivery Update*\n",
            'invoice' => "🧾 *Invoice Update*\n",
            'payment' => "💰 *Payment Update*\n",
        ];

        $text = $messages[$stage] ?? "📦 *Order Update*\n";
        $text .= "{$emoji} *{$so->customer->name}*\n";
        $text .= "SO: `{$so->order_number}`\n";
        $text .= "Stage: " . strtoupper($stage) . "\n";
        $text .= "Status: " . strtoupper($status) . "\n";
        $text .= "Amount: Rp " . number_format($so->total_amount ?? 0) . "\n";
        $text .= "Time: " . now()->format('d M Y H:i') . "\n";

        // Tambahkan info overdue
        if ($stage === 'payment' && $status === 'current') {
            $invoice = $so->salesInvoices->where('status', 'POSTED')->first();
            if ($invoice && $invoice->due_date && $invoice->due_date < now()) {
                $text .= "\n⚠️ *INVOICE OVERDUE* - Jatuh tempo: " . $invoice->due_date->format('d M Y');
            }
        }

        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram notification failed: ' . $e->getMessage());
        }
    }

    public function notifyOverdue(SalesOrder $so): void
    {
        if (empty($this->botToken) || empty($this->chatId)) return;

        $invoice = $so->salesInvoices->where('status', 'POSTED')->first();
        if (!$invoice) return;

        $remaining = $invoice->total_amount - $invoice->cashIns->sum('amount');

        $text = "⚠️ *OVERDUE ALERT*\n\n";
        $text .= "Customer: *{$so->customer->name}*\n";
        $text .= "Invoice: `{$invoice->invoice_number}`\n";
        $text .= "Due Date: *{$invoice->due_date->format('d M Y')}*\n";
        $text .= "Remaining: Rp " . number_format($remaining) . "\n";
        $text .= "SO: `{$so->order_number}`\n";

        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram overdue notification failed: ' . $e->getMessage());
        }
    }
}