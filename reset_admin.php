<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Application\Ports\Outbound\PasswordHasherInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/** @var PasswordHasherInterface $hasher */
$hasher = $app->make(PasswordHasherInterface::class);
$passwordHash = $hasher->hash('admin123456');

$user = UserModel::where('username', 'admin')->first();

if ($user) {
    $user->password_hash = $passwordHash;
    $user->role = 'admin';
    $user->save();
    echo "Usuario 'admin' actualizado con exito en library.user.\n";
} else {
    $id = '00000000-0000-0000-0000-000000000001';
    UserModel::create([
        'id' => $id,
        'username' => 'admin',
        'password_hash' => $passwordHash,
        'role' => 'admin',
    ]);
    echo "Usuario 'admin' creado con exito en library.user.\n";
}
