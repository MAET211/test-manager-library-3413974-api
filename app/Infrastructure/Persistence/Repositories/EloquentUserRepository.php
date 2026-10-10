<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Application\Ports\Outbound\UserRepositoryInterface;
use App\Domain\Entities\User;
use App\Domain\Exceptions\UsernameTakenException;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Mappers\UserMapper;
use Illuminate\Database\QueryException;

final class EloquentUserRepository implements UserRepositoryInterface
{
    public function save(User $user): void
    {
        try {
            $model = UserModel::find($user->id());
            $model = UserMapper::toModel($user, $model);
            $model->save();
        } catch (QueryException $e) {
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'uq_user_username')) {
                throw new UsernameTakenException($user->username());
            }
            throw $e;
        }
    }

    public function findById(string $id): ?User
    {
        $model = UserModel::find($id);

        return $model !== null ? UserMapper::toDomain($model) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $normalized = strtolower(trim($username));
        $model = UserModel::where('username', $normalized)->first();

        return $model !== null ? UserMapper::toDomain($model) : null;
    }
}
