<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::create([
    'name' => 'Admin',
    'email' => 'admin@demo.com',
    'password' => bcrypt('password'),
    'is_active' => true,
]);

$user->assignRole('Admin');

echo "User created: admin@demo.com / password\n";