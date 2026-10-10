<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mappers;

use App\Domain\Entities\User;
use App\Domain\ValueObjects\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

final class UserMapper
{
    public static function toDomain(UserModel $model): User
    {
        return new User(
            id: (string) $model->id,
            username: (string) $model->username,
            passwordHash: (string) $model->password_hash,
            role: UserRole::fromString((string) $model->role)
        );
    }

    public static function toModel(User $domain, ?UserModel $model = null): UserModel
    {
        $model = $model ?? new UserModel();

        $model->id = $domain->id();
        $model->username = $domain->username();
        $model->password_hash = $domain->passwordHash();
        $model->role = $domain->role()->value;

        return $model;
    }
}
