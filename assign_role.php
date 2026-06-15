<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::where('email', 'admin@demo.com')->first();

if (!$user) {
    echo "User not found!\n";
    exit;
}

$user->assignRole('Admin');

echo "Role assigned! User: admin@demo.com / password\n";