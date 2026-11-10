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

$data = json_decode($response, true);
$token = $data['token'] ?? null;
echo "Login: $code\n";
echo "Role: " . ($data['role'] ?? 'none') . "\n";

// Get /me
$ch = curl_init('http://127.0.0.1:8000/api/me');
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
echo "/me: $code\n";
$me = json_decode($response, true);
echo "User: " . ($me['name'] ?? 'none') . ", Role: " . ($me['role'] ?? 'none') . "\n";
echo "Permissions: " . json_encode($me['permissions'] ?? []) . "\n\n";

// Get event
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
$event = json_decode($response, true);
echo "Event: " . ($event['nama'] ?? 'none') . "\n\n";

// Get invoices
$ch = curl_init('http://127.0.0.1:8000/api/events/159/invoices');
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
echo "Invoices API: $code\n";
$invoices = json_decode($response, true);
echo "Count: " . count($invoices) . "\n\n";

// Get sessions
$ch = curl_init('http://127.0.0.1:8000/api/events/159/sessions');
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
echo "Sessions API: $code\n";
$sessions = json_decode($response, true);
echo "Count: " . count($sessions) . "\n\n";

// Get report
$ch = curl_init('http://127.0.0.1:8000/api/events/159/report');
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
echo "Report API: $code\n\n";

// Get expenses
$ch = curl_init('http://127.0.0.1:8000/api/events/159/expenses');
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
echo "Expenses API: $code\n";