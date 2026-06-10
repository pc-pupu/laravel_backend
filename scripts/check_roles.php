<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

foreach (['housing_approver', 'housing_competent_authority', 'housing_supervisor', 'housing_hod', 'housing_cms', 'kolsouthsubdiv4', '2008008416'] as $n) {
    $u = DB::table('users')->where('name', $n)->first();
    if (!$u) {
        echo "{$n}: not found\n";
        continue;
    }
    $r = DB::table('user_role')->where('uid', $u->uid)->orderBy('rid')->pluck('rid');
    echo "{$n} uid={$u->uid} roles=" . $r->implode(',') . "\n";
}
