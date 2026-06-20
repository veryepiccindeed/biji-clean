<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
            $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class, 
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
            $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validasi input gagal',
                    'details' => $e->errors(), 
                    'timestamp' => now()->toIso8601String(),
                ], 422);
            }
        });

            $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Token tidak valid atau sesi telah berakhir',
                    'details' => null,
                    'timestamp' => now()->toIso8601String(),
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'FORBIDDEN',
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Anda tidak memiliki akses',
                    'details' => null,
                    'timestamp' => now()->toIso8601String(),
                ], 403);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'NOT_FOUND',
                    'message' => 'Resource tidak ditemukan',
                    'details' => null,
                    'timestamp' => now()->toIso8601String(),
                ], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'code' => 'METHOD_NOT_ALLOWED',
                    'message' => 'HTTP method tidak diizinkan',
                    'details' => null,
                    'timestamp' => now()->toIso8601String(),
                ], 405);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, $request) {
            if ($request->is('api/*')) {
                $statusCode = $e->getStatusCode();

                $code = match ($statusCode) {
                    401 => 'UNAUTHORIZED',
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    409 => 'CONFLICT',
                    default => $statusCode >= 500 ? 'INTERNAL_ERROR' : 'INTERNAL_ERROR',
                };

                return response()->json([
                    'success' => false,
                    'code' => $code,
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Terjadi kesalahan',
                    'details' => null,
                    'timestamp' => now()->toIso8601String(),
                ], $statusCode);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(
                    \App\Helpers\ApiResponse::format(false, 'UNAUTHORIZED', 'Silakan login dulu'), 
                    401
                );
            }
        });
    })->create();
