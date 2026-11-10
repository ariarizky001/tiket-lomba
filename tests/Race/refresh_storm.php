<?php

/**
 * Refresh Storm Test Script (D1)
 * 
 * Tests parallel requests against the application to diagnose
 * the "data not showing after refresh" issue.
 * 
 * Usage:
 * php tests/Race/refresh_storm.php --base=http://localhost:8000 --event=1 --email=admin@test.com --password=password123 --rounds=5 --concurrency=30
 * 
 * Arguments:
 * --base       Base URL of the application
 * --event      Event ID to test
 * --email      Login email
 * --password   Login password
 * --rounds     Number of test rounds
 * --concurrency Number of parallel requests per round
 */

$args = getopt('', ['base::', 'event::', 'email::', 'password::', 'rounds::', 'concurrency::']);

$BASE_URL = $args['base'] ?? 'http://localhost:8000';
$EVENT_ID = $args['event'] ?? 1;
$EMAIL = $args['email'] ?? 'admin@test.com';
$PASSWORD = $args['password'] ?? 'password123';
$ROUNDS = (int)($args['rounds'] ?? 5);
$CONCURRENCY = (int)($args['concurrency'] ?? 30);

$ENDPOINTS = [
    '/api/me' => 'me',
    '/api/events' => 'events',
    "/api/events/{$EVENT_ID}" => 'event_detail',
    "/api/events/{$EVENT_ID}/sessions" => 'sessions',
    "/api/events/{$EVENT_ID}/invoices" => 'invoices',
    "/api/events/{$EVENT_ID}/report" => 'report',
    "/api/events/{$EVENT_ID}/expenses" => 'expenses',
];

function logMsg(string $msg): void
{
    $line = "[" . date('H:i:s') . "] $msg\n";
    echo $line;
    file_put_contents(__DIR__ . '/refresh_storm.log', $line, FILE_APPEND);
}

function logErr(string $msg): void
{
    $line = "[" . date('H:i:s') . "] ERROR: $msg\n";
    echo $line;
    file_put_contents(__DIR__ . '/refresh_storm.log', $line, FILE_APPEND);
}

function curlRequest(string $url, string $method = 'GET', ?array $data = null, ?string $token = null): array
{
    $ch = curl_init($url);
    $headers = [
        'Accept: application/json',
        $token ? "Authorization: Bearer $token" : '',
    ];
    
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ];
    
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $err = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $code,
        'body' => $response ? json_decode($response, true) : null,
        'time' => $totalTime,
        'error' => $err,
    ];
}

function curlMulti(array $requests): array
{
    $mh = curl_multi_init();
    $handles = [];
    $results = [];
    
    foreach ($requests as $i => $req) {
        $ch = curl_init($req['url']);
        $headers = [
            'Accept: application/json',
            $req['token'] ? "Authorization: Bearer {$req['token']}" : '',
        ];
        
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];
        
        if ($req['method'] === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($req['data'] ?? []);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        
        curl_setopt_array($ch, $opts);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }
    
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.1);
    } while ($running > 0);
    
    foreach ($handles as $i => $ch) {
        $results[$i] = [
            'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body' => json_decode(curl_multi_getcontent($ch), true),
            'time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME),
            'error' => curl_error($ch),
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    
    return $results;
}

function calculateStats(array $times): array
{
    if (empty($times)) {
        return ['min' => 0, 'median' => 0, 'p95' => 0, 'max' => 0, 'avg' => 0];
    }
    sort($times);
    $count = count($times);
    return [
        'min' => $times[0],
        'median' => $times[floor($count / 2)],
        'p95' => $times[ceil($count * 0.95) - 1] ?? $times[$count - 1],
        'max' => $times[$count - 1],
        'avg' => array_sum($times) / $count,
    ];
}

// ===== LOGIN =====
logMsg("Logging in as $EMAIL...");
$login = curlRequest("$BASE_URL/api/login", 'POST', ['email' => $EMAIL, 'password' => $PASSWORD]);
if ($login['code'] !== 200 || !$login['body']['token']) {
    logErr("LOGIN FAILED: " . json_encode($login));
    exit(1);
}
$token = $login['body']['token'];
logMsg("Login OK, token: " . substr($token, 0, 20) . "...");

// ===== TEST ROUNDS =====
$allResults = [];

for ($round = 1; $round <= $ROUNDS; $round++) {
    logMsg("\n=== ROUND $round/$ROUNDS ===");
    
    // Prepare parallel requests for this round
    $requests = [];
    $endpointKeys = array_keys($ENDPOINTS);
    
    for ($i = 0; $i < $CONCURRENCY; $i++) {
        $endpointKey = $endpointKeys[$i % count($endpointKeys)];
        $endpoint = str_replace('{$EVENT_ID}', $EVENT_ID, $endpointKey);
        $label = $ENDPOINTS[$endpointKey];
        
        $requests[] = [
            'url' => "$BASE_URL$endpoint",
            'method' => 'GET',
            'token' => $token,
            'label' => $label,
            'endpoint' => $endpointKey,
        ];
    }
    
    logMsg("Sending $CONCURRENCY parallel requests...");
    $start = microtime(true);
    $results = curlMulti($requests);
    $elapsed = microtime(true) - $start;
    
    // Aggregate results
    $roundResults = [];
    foreach ($results as $i => $r) {
        $label = $requests[$i]['label'];
        $endpoint = $requests[$i]['endpoint'];
        
        if (!isset($roundResults[$label])) {
            $roundResults[$label] = [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'timeout' => 0,
                'times' => [],
                'codes' => [],
                'errors' => [],
            ];
        }
        
        $roundResults[$label]['total']++;
        $roundResults[$label]['codes'][] = $r['code'];
        
        if ($r['code'] === 200) {
            $roundResults[$label]['success']++;
            if ($r['time'] > 0) {
                $roundResults[$label]['times'][] = $r['time'];
            }
        } elseif ($r['code'] === 0 || $r['error']) {
            $roundResults[$label]['timeout']++;
            $roundResults[$label]['errors'][] = $r['error'] ?? 'timeout';
        } else {
            $roundResults[$label]['failed']++;
            $roundResults[$label]['errors'][] = "HTTP {$r['code']}";
        }
    }
    
    // Print round summary
    foreach ($roundResults as $label => $stats) {
        $statsCalc = calculateStats($stats['times']);
        $status = $stats['failed'] > 0 || $stats['timeout'] > 0 ? '❌' : '✅';
        logMsg("  $status $label: OK={$stats['success']}/{$stats['total']}, Fail={$stats['failed']}, Timeout={$stats['timeout']}, " .
               "Min={$statsCalc['min']}s, Median={$statsCalc['median']}s, P95={$statsCalc['p95']}s, Max={$statsCalc['max']}s");
        
        if (!empty($stats['errors'])) {
            logMsg("    Errors: " . implode(', ', array_slice($stats['errors'], 0, 5)));
        }
        
        // Aggregate across rounds
        if (!isset($allResults[$label])) {
            $allResults[$label] = [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'timeout' => 0,
                'times' => [],
            ];
        }
        $allResults[$label]['total'] += $stats['total'];
        $allResults[$label]['success'] += $stats['success'];
        $allResults[$label]['failed'] += $stats['failed'];
        $allResults[$label]['timeout'] += $stats['timeout'];
        $allResults[$label]['times'] = array_merge($allResults[$label]['times'], $stats['times']);
    }
    
    logMsg("Round $round completed in {$elapsed}s");
}

// ===== FINAL SUMMARY =====
logMsg("\n=== FINAL SUMMARY ({$ROUNDS} rounds × {$CONCURRENCY} concurrency) ===");
$exitCode = 0;

foreach ($allResults as $label => $stats) {
    $statsCalc = calculateStats($stats['times']);
    $status = $stats['failed'] > 0 || $stats['timeout'] > 0 ? '❌' : '✅';
    
    logMsg("$status $label: Total={$stats['total']}, OK={$stats['success']}, Fail={$stats['failed']}, Timeout={$stats['timeout']}");
    logMsg("  Time: Min={$statsCalc['min']}s, Median={$statsCalc['median']}s, P95={$statsCalc['p95']}s, Max={$statsCalc['max']}s, Avg={$statsCalc['avg']}s");
    
    if ($stats['failed'] > 0 || $stats['timeout'] > 0) {
        $exitCode = 1;
    }
    
    // Check p95 threshold (3 seconds)
    if ($statsCalc['p95'] > 3) {
        logMsg("  ⚠️ P95 ({$statsCalc['p95']}s) exceeds 3s threshold");
        $exitCode = 1;
    }
}

// Check for hanging requests
logMsg("\n=== CHECKING FOR HANGING TRANSACTIONS ===");
logMsg("Run this in MySQL after test: SELECT * FROM information_schema.innodb_trx;");
logMsg("And: SHOW FULL PROCESSLIST;");

exit($exitCode);