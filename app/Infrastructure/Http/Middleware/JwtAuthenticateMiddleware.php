<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Application\Ports\Outbound\TokenIssuerInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class JwtAuthenticateMiddleware
{
    public function __construct(
        private readonly TokenIssuerInterface $tokenIssuer
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if ($header === null || !str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Token de autenticación inválido o ausente.',
                'details' => [],
            ], 401);
        }

        $token = substr($header, 7);
        try {
            $payload = $this->tokenIssuer->verify($token);
        } catch (\Throwable) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Token de autenticación inválido o ausente.',
                'details' => [],
            ], 401);
        }

        $request->attributes->set('auth_user_id', $payload['sub'] ?? null);
        $request->attributes->set('auth_user_role', $payload['role'] ?? null);

        return $next($request);
    }
}
