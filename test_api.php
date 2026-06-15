<?php

$baseUrl = 'http://127.0.0.1:8000/api';

echo "=== TEST API ERP SYSTEM ===\n\n";

// Cek server jalan
echo "Cek server... ";
$ch = curl_init('http://127.0.0.1:8000');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP {$httpCode}\n\n";

if ($httpCode == 0) {
    echo "ERROR: Server tidak jalan di http://127.0.0.1:8000\n";
    echo "Jalankan dulu: php artisan serve\n";
    exit;
}

// 1. LOGIN
echo "1. LOGIN ke {$baseUrl}/login\n";
$loginData = json_encode([
    'email' => 'admin@demo.com',
    'password' => 'password',
    'device_name' => 'flutter_test'
]);

$ch = curl_init("{$baseUrl}/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $loginData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response:\n";
echo $response . "\n\n";

// Parse JSON (hapus header)
$lines = explode("\n", $response);
$jsonLine = '';
foreach ($lines as $line) {
    $trimmed = trim($line);
    if (!empty($trimmed) && ($trimmed[0] == '{' || $trimmed[0] == '[')) {
        $jsonLine = $trimmed;
        break;
    }
}

$loginResult = json_decode($jsonLine, true);

if (!isset($loginResult['token'])) {
    echo "LOGIN FAILED! Response tidak valid.\n";
    exit;
}

$token = $loginResult['token'];
echo "Token: " . substr($token, 0, 50) . "...\n\n";

// Helper
function apiGet($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}", "Content-Type: application/json"]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function apiPost($url, $token, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}", "Content-Type: application/json"]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// 2. DASHBOARD
echo "2. DASHBOARD SUMMARY\n";
echo apiGet("{$baseUrl}/dashboard", $token) . "\n\n";

// 3. PRODUCTS
echo "3. LIST PRODUCTS\n";
echo apiGet("{$baseUrl}/products", $token) . "\n\n";

// 4. CUSTOMERS
echo "4. LIST CUSTOMERS\n";
echo apiGet("{$baseUrl}/customers", $token) . "\n\n";

echo "=== TEST COMPLETE ===\n";