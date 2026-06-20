<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'layouts.app');

Route::fallback(function (Request $request) {
    // API routes should return 404 JSON
    if ($request->is('api/*') || $request->expectsJson()) {
        return response()->json([
            'success' => false,
            'code' => 'NOT_FOUND',
            'message' => 'Endpoint tidak ditemukan',
            'data' => null,
            'timestamp' => now()->toIso8601String(),
        ], 404);
    }

    return view('layouts.app');
});
