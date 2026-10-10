<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->attributes->get('auth_user_role');

        if ($userRole === null || !in_array($userRole, $roles, true)) {
            return response()->json([
                'code' => 'FORBIDDEN_ROLE',
                'message' => 'No tiene permisos para realizar esta acción.',
                'details' => [],
            ], 403);
        }

        return $next($request);
    }
}
