<?php
$base = 'http://127.0.0.1:8000/api';
$password = 'Housing12#$';

function login($name) {
    global $base, $password;
    $ch = curl_init($base . '/login');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['name' => $name, 'password' => $password]),
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    return json_decode($raw, true);
}

foreach (['housing_approver', 'housing_cms'] as $name) {
    $auth = login($name);
    $uid = $auth['user']['uid'];
    $ch = curl_init($base . "/dashboard?uid={$uid}&username={$name}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $auth['token'], 'Accept: application/json'],
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $j = json_decode($raw, true);
    echo "=== {$name} ===\n";
    echo json_encode([
        'user_role' => $j['data']['user_role'] ?? null,
        'redirect' => $j['data']['redirect'] ?? null,
    ], JSON_PRETTY_PRINT) . "\n\n";
}
