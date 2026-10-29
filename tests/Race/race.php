<?php

/**
 * Race Condition Test Script
 * 
 * Prasyarat:
 * - Server Laravel berjalan di BASE_URL (php artisan serve / Nginx)
 * - Database MySQL nyata (bukan SQLite)
 * - User test sudah ada di DB (email/password di .env atau hardcode)
 * 
 * Cara jalan:
 * php tests/Race/race.php
 */

$BASE_URL = 'http://localhost:8000/api';
$EMAIL    = 'test@example.com';
$PASSWORD = 'password123';

function logMsg(string $msg): void
{
    $line = "[" . date('H:i:s') . "] $msg\n";
    echo $line;
    file_put_contents(__DIR__ . '/race_debug.log', $line, FILE_APPEND);
}

function logErr(string $msg): void
{
    $line = "[" . date('H:i:s') . "] ERROR: $msg\n";
    echo $line;
    file_put_contents(__DIR__ . '/race_debug.log', $line, FILE_APPEND);
}

function curlGet(string $url, ?string $token = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            $token ? "Authorization: Bearer $token" : '',
        ],
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $response ? json_decode($response, true) : null];
}

function curlPost(string $url, array $data, ?string $token = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            $token ? "Authorization: Bearer $token" : '',
        ],
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $response ? json_decode($response, true) : null];
}

function curlMultiPost(array $requests): array
{
    $mh = curl_multi_init();
    $handles = [];

    foreach ($requests as $i => $req) {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($req['data']),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                $req['token'] ? "Authorization: Bearer {$req['token']}" : '',
            ],
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
    } while ($running);

    $results = [];
    foreach ($handles as $i => $ch) {
        $results[$i] = [
            'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body' => json_decode(curl_multi_getcontent($ch), true),
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $results;
}

// ===== 1. LOGIN =====
logMsg("Login sebagai $EMAIL...");
$login = curlPost("$BASE_URL/login", [
    'email' => $EMAIL,
    'password' => $PASSWORD,
]);
if ($login['code'] !== 200 || !$login['body']['token']) {
    die("LOGIN GAGAL: " . json_encode($login));
}
$token = $login['body']['token'];
logMsg("Login OK, token: " . substr($token, 0, 20) . "...");

// ===== 2. BUAT EVENT & SESI UNTUK TEST =====
logMsg("Membuat event test...");
$eventRes = curlPost("$BASE_URL/events", ['nama' => 'Race Test Event', 'tanggal' => '2026-12-31', 'lokasi' => 'Test'], $token);
if ($eventRes['code'] !== 201) die("BUAT EVENT GAGAL: " . json_encode($eventRes));
$eventId = $eventRes['body']['id'];
logMsg("Event ID: $eventId");

// C1: Sesi quota 1
$sessionC1 = curlPost("$BASE_URL/events/$eventId/sessions", [
    'nama' => 'Sesi Quota 1',
    'harga' => 100000,
    'kuota' => 1,
], $token);
if ($sessionC1['code'] !== 201) die("BUAT SESI C1 GAGAL");
$sessionC1Id = $sessionC1['body']['id'];
logMsg("Sesi C1 (quota=1) ID: $sessionC1Id");

// C2/C3: 3 sesi quota 10
$sessionIds = [];
for ($i = 1; $i <= 3; $i++) {
    $s = curlPost("$BASE_URL/events/$eventId/sessions", [
        'nama' => "Sesi Multi $i",
        'harga' => 100000,
        'kuota' => 10,
    ], $token);
    $sessionIds[] = $s['body']['id'];
}
logMsg("Sesi C2/C3 IDs: " . implode(', ', $sessionIds));

// ===== 3. TEST C1: 10 REQUEST BERSAMAAN UNTUK SESI QUOTA 1 =====
try {
    logMsg("\n=== C1: 10 request simultan ke sesi quota 1 ===");
    $requests = [];
    for ($i = 0; $i < 10; $i++) {
        $requests[] = [
            'url'   => "$BASE_URL/events/$eventId/invoices",
            'data'  => [
                'nama_peserta' => "Race User C1-$i",
                'items' => [['event_session_id' => $sessionC1Id, 'qty' => 1]],
            ],
            'token' => $token,
        ];
    }

    $start = microtime(true);
    $resultsC1 = curlMultiPost($requests);
    $elapsed = microtime(true) - $start;

    $success = 0; $fail = 0;
    foreach ($resultsC1 as $r) {
        if ($r['code'] === 201) $success++;
        elseif ($r['code'] === 422) $fail++;
        else logMsg("  Kode tidak terduga: {$r['code']}");
    }
    logMsg("Hasil C1: Sukses=$success, Gagal(422)=$fail, Waktu={$elapsed}s");

    // Verifikasi DB via API
    $verify = curlGet("$BASE_URL/events/$eventId/sessions", $token);
    $sesiC1 = array_filter($verify['body'], fn($s) => $s['id'] == $sessionC1Id);
    $sesiC1 = array_values($sesiC1)[0] ?? null;
    if ($sesiC1) {
        logMsg("Verifikasi DB: terjual={$sesiC1['terjual']}, sisa={$sesiC1['sisa']}, kuota={$sesiC1['kuota']}");
        if ($sesiC1['terjual'] <= $sesiC1['kuota']) {
            logMsg("✅ C1 LULUS: terjual <= kuota");
        } else {
            logMsg("❌ C1 GAGAL: terjual > kuota (RACE CONDITION!)");
        }
    }
    logMsg(">>> C1 SELESAI, lanjut ke C2...");
} catch (Throwable $e) {
    logErr("C1 ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    throw $e;
}

// ===== 4. TEST C2: 20 REQUEST BERSAMAAN UNTUK EVENT YANG SAMA (SESI BEDA) =====
try {
    logMsg("\n=== C2: 20 request simultan buat invoice (sesi beda) ===");
    $requests = [];
    for ($i = 0; $i < 20; $i++) {
        $sid = $sessionIds[$i % count($sessionIds)];
        $requests[] = [
            'url'   => "$BASE_URL/events/$eventId/invoices",
            'data'  => [
                'nama_peserta' => "Race User C2-$i",
                'items' => [['event_session_id' => $sid, 'qty' => 1]],
            ],
            'token' => $token,
        ];
    }

    $start = microtime(true);
    $resultsC2 = curlMultiPost($requests);
    $elapsed = microtime(true) - $start;

    $success = 0; $codes = [];
    foreach ($resultsC2 as $r) {
        $codes[] = $r['code'];
        if ($r['code'] === 201) $success++;
    }
    logMsg("Hasil C2: Sukses=$success/20, Kode: " . implode(', ', $codes) . ", Waktu={$elapsed}s");

    // Cek unik kode & nomor invoice
    $invList = curlGet("$BASE_URL/events/$eventId/invoices", $token);
    $nomors = array_column($invList['body'], 'nomor');
    $kodes  = array_column($invList['body'], 'kode');
    if (count($nomors) === count(array_unique($nomors)) && count($kodes) === count(array_unique($kodes))) {
        logMsg("✅ C2 LULUS: nomor & kode invoice unik");
    } else {
        logMsg("❌ C2 GAGAL: ada nomor/kode kembar");
    }
    logMsg(">>> C2 SELESAI, lanjut ke C3...");
} catch (Throwable $e) {
    logErr("C2 ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    throw $e;
}

// ===== 5. TEST C3: EDIT INVOICE YANG SAMA DARI 2 REQUEST BERSAMAAN =====
try {
    logMsg("\n=== C3: Edit invoice bersamaan (2 request) ===");
    // Buat invoice awal
    $initInv = curlPost("$BASE_URL/events/$eventId/invoices", [
        'nama_peserta' => 'Race Init',
        'items' => [['event_session_id' => $sessionIds[0], 'qty' => 1]],
    ], $token);
    if ($initInv['code'] !== 201) die("BUAT INVOICE AWAL GAGAL");
    $invId = $initInv['body']['id'];
    logMsg("Invoice awal ID: $invId (total={$initInv['body']['total']})");

    // 2 request edit bersamaan: satu naik qty, satu ganti sesi
    // Need PUT method for invoice update
    function curlPut(string $url, array $data, ?string $token = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                $token ? "Authorization: Bearer $token" : '',
            ],
        ]);
        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => $response ? json_decode($response, true) : null];
    }

    function curlMultiPut(array $requests): array
    {
        $mh = curl_multi_init();
        $handles = [];

        foreach ($requests as $i => $req) {
            $ch = curl_init($req['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'PUT',
                CURLOPT_POSTFIELDS     => json_encode($req['data']),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    $req['token'] ? "Authorization: Bearer {$req['token']}" : '',
                ],
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running);

        $results = [];
        foreach ($handles as $i => $ch) {
            $results[$i] = [
                'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                'body' => json_decode(curl_multi_getcontent($ch), true),
            ];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $results;
    }

    $req1 = [
        'url'   => "$BASE_URL/invoices/$invId",
        'data'  => [
            'nama_peserta' => 'Race Edit 1',
            'items' => [['event_session_id' => $sessionIds[0], 'qty' => 3]], // qty 3
        ],
        'token' => $token,
    ];
    $req2 = [
        'url'   => "$BASE_URL/invoices/$invId",
        'data'  => [
            'nama_peserta' => 'Race Edit 2',
            'items' => [['event_session_id' => $sessionIds[1], 'qty' => 2]], // ganti sesi, qty 2
        ],
        'token' => $token,
    ];

    $resultsC3 = curlMultiPut([$req1, $req2]);
    foreach ($resultsC3 as $i => $r) {
        logMsg("  Request " . ($i+1) . ": code={$r['code']}" . ($r['code']===200 ? " total={$r['body']['total']}" : " err=" . json_encode($r['body'])));
    }

    // Verifikasi akhir
    $final = curlPost("$BASE_URL/invoices/$invId", [], $token);
    if ($final['code'] === 200) {
        $items = $final['body']['items'];
        $total = $final['body']['total'];
        $calc  = array_sum(array_column($items, 'subtotal'));
        logMsg("Final: items=" . count($items) . ", total=$total, sum(subtotal)=$calc");
        if ($total === $calc) {
            logMsg("✅ C3 LULUS: total konsisten dengan subtotal item");
        } else {
            logMsg("❌ C3 GAGAL: total != sum(subtotal)");
        }
    }
    logMsg(">>> C3 SELESAI...");
} catch (Throwable $e) {
    logErr("C3 ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    throw $e;
}

// ===== 6. QUERY INTEGRITY CHECK =====
try {
    logMsg("\n=== Query Integrity Check (via API) ===");
    $sessions = curlGet("$BASE_URL/events/$eventId/sessions", $token);
    $quotaViolations = 0;
    foreach ($sessions['body'] as $s) {
        if ($s['terjual'] > $s['kuota']) {
            logMsg("❌ Kuota jebol: {$s['nama']} terjual={$s['terjual']} > kuota={$s['kuota']}");
            $quotaViolations++;
        }
    }
    if ($quotaViolations === 0) {
        logMsg("✅ Tidak ada kuota jebol");
    }
    logMsg("\n=== SELESAI ===");
} catch (Throwable $e) {
    logErr("INTEGRITY CHECK ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    throw $e;
}

// Dump full log
$logContent = file_get_contents(__DIR__ . '/race_debug.log');
echo "\n=== FULL LOG ===\n$logContent";