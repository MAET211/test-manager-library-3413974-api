<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Ports\Outbound\PasswordHasherInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Console\Command;

final class ResetAdminCommand extends Command
{
    protected $signature = 'admin:reset';
    protected $description = 'Restablece la contraseña del usuario admin a admin123456';

    public function handle(PasswordHasherInterface $hasher): int
    {
        $passwordHash = $hasher->hash('admin123456');
        $user = UserModel::where('username', 'admin')->first();

        if ($user) {
            $user->password_hash = $passwordHash;
            $user->role = 'admin';
            $user->save();
            $this->info("Usuario 'admin' actualizado con éxito en library.user.");
        } else {
            UserModel::create([
                'id' => '00000000-0000-0000-0000-000000000001',
                'username' => 'admin',
                'password_hash' => $passwordHash,
                'role' => 'admin',
            ]);
            $this->info("Usuario 'admin' creado con éxito en library.user.");
        }

        return 0;
    }
}
