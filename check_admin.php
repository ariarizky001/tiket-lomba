<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$u = \App\Models\User::where('role','admin')->first();
echo $u ? $u->email : 'none';
echo "\n";
if ($u) {
    echo "Has view_invoices: " . ($u->hasPermission('view_invoices') ? 'YES' : 'NO');
    echo "\n";
    echo "Has view_sessions: " . ($u->hasPermission('view_sessions') ? 'YES' : 'NO');
    echo "\n";
}