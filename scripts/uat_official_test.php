<?php

/**
 * Official UAT smoke test — run: php scripts/uat_official_test.php
 */
$base = 'http://127.0.0.1:8000/api';
$password = 'Housing12#$';

$users = [
    'housing_approver',
    'admin',
    'housing_cms',
    'housing_competent_authority',
    'housing_hod',
    'housing_supervisor',
    'kolsouthsubdiv4',
];

$protectedRoutes = [
    ['GET', '/dashboard?uid={uid}&username={name}'],
    ['GET', '/sidebar-menus'],
    ['GET', '/admin/application-registration-list'],
    ['GET', '/view-application-list/dashboard?status=applied&entity=new-apply'],
    ['GET', '/waiting-list/flat-type?flat_type_id=1'],
    ['GET', '/unauthorized-occupants'],
    ['GET', '/retirement-list'],
    ['GET', '/auto-cancellation-list?uid={uid}'],
    ['GET', '/flat-wise-applicant-details/helpers'],
    ['GET', '/complaints/helpers'],
    ['GET', '/ddo'],
];

function req(string $method, string $url, ?string $token = null, array $body = []): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($method === 'POST' && $body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return ['code' => $code, 'body' => $raw ?: $err, 'json' => json_decode($raw ?: 'null', true)];
}

echo "=== Housing Official UAT (API) ===\n\n";

// HRMS manual login (form on /hrms-login)
echo "--- HRMS applicant (2008008416) manual login ---\n";
$hrmsManual = req('POST', $base . '/hrms-login-manual', null, ['hrms_id' => '2008008416']);
echo "  hrms-login-manual HTTP {$hrmsManual['code']}: " . (($hrmsManual['json']['status'] ?? '') === 'success' ? 'token received' : substr($hrmsManual['body'], 0, 150)) . "\n\n";

$failures = [];

foreach ($users as $username) {
    echo "--- User: {$username} ---\n";
    $login = req('POST', $base . '/login', null, ['name' => $username, 'password' => $password]);
    if ($login['code'] !== 200 || ($login['json']['status'] ?? '') !== 'success') {
        $msg = $login['json']['message'] ?? $login['body'];
        echo "  LOGIN FAIL ({$login['code']}): {$msg}\n\n";
        $failures[] = "{$username}: login - {$msg}";
        continue;
    }
    $token = $login['json']['token'] ?? '';
    $user = $login['json']['user'] ?? [];
    $uid = $user['uid'] ?? 0;
    $role = $user['role'] ?? '?';
    echo "  LOGIN OK uid={$uid} role={$role}\n";

    foreach ($protectedRoutes as [$method, $path]) {
        $path = str_replace(['{uid}', '{name}'], [(string) $uid, $username], $path);
        $r = req($method, $base . $path, $token);
        $ok = $r['code'] >= 200 && $r['code'] < 300;
        $status = $login['json']['status'] ?? ($r['json']['status'] ?? '');
        echo '  ' . ($ok ? 'OK' : 'FAIL') . " {$method} {$path} => HTTP {$r['code']}";
        if (!$ok) {
            $snippet = substr($r['body'], 0, 120);
            echo " — {$snippet}";
            $failures[] = "{$username}: {$method} {$path} HTTP {$r['code']}";
        }
        echo "\n";
    }
    echo "\n";
}

echo "=== Summary ===\n";
if (empty($failures)) {
    echo "All tests passed.\n";
} else {
    echo count($failures) . " failure(s):\n";
    foreach ($failures as $f) {
        echo " - {$f}\n";
    }
    exit(1);
}
