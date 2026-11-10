<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test login as admin
$ch = curl_init('http://127.0.0.1:8000/api/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'email' => 'admin@example.com',
        'password' => 'password'
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login: $code\n";
$data = json_decode($response, true);
$token = $data['token'] ?? null;
echo "Token: " . ($token ? substr($token, 0, 30) . '...' : 'none') . "\n\n";

// Test event API with token
if ($token) {
    $ch = curl_init('http://127.0.0.1:8000/api/events/159');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            "Authorization: Bearer $token",
        ],
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Event API: $code\n";
    echo substr($response, 0, 500) . "\n";
}