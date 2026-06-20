<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
        {
            // 1. Pastikan user sudah login
            if (!$request->user()) {
                return response()->json([
                    'success' => false,
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Silakan login terlebih dahulu',
                    'details' => null,
                    'timestamp' => now()->toIso8601String(),
                ], 401);
            }

            // 2. Cek apakah role user sesuai dengan yang diminta
            if ($request->user()->role !== $role) {
                return response()->json(
                    \App\Helpers\ApiResponse::format(false, 'FORBIDDEN', "Akses khusus $role"), 
                    403
                );
            }

            return $next($request);
    }
}
