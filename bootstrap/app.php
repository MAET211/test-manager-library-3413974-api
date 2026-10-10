<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.jwt' => \App\Infrastructure\Http\Middleware\JwtAuthenticateMiddleware::class,
            'role' => \App\Infrastructure\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
                'details' => $e->getDetails(),
            ], $e->getHttpStatusCode());
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e) {
            $details = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $details[] = [
                        'field' => $field,
                        'issue' => $msg,
                    ];
                }
            }
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Error de validación en los datos enviados.',
                'details' => $details,
            ], 422);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Recurso no encontrado.',
                'details' => [],
            ], 404);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return response()->json([
                'code' => 'FORBIDDEN_ROLE',
                'message' => 'No tiene permisos para realizar esta acción.',
                'details' => [],
            ], 403);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e) {
            return response()->json([
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'Método HTTP no permitido.',
                'details' => [],
            ], 405);
        });

        $exceptions->render(function (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Unhandled exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'code' => 'INTERNAL_SERVER_ERROR',
                'message' => 'Ocurrió un error inesperado en el servidor.',
                'details' => [],
            ], 500);
        });
    })->create();
