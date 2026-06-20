<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function register(Request $request) {
        if (User::where('email', $request->email)->exists()) {
            return $this->apiResponse(false, 'CONFLICT', 'Email sudah terdaftar', [], 409);
        }

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|string|email', 
            'password' => 'required|string|min:8|confirmed|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/',
            'role' => 'required|in:farmer,exporter,buyer',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        $user->refresh();

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->apiResponse(true, 'SUCCESS_CREATE', 'Akun berhasil dibuat', [
            'user' => $user,
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 900
        ], 201);
    }

    public function login(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember_me' => 'boolean'
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->apiResponse(false, 'UNAUTHORIZED', 'Email atau password salah', [], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->apiResponse(true, 'SUCCESS', 'Login berhasil', [
            'user' => $user,
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 900,
            'remember_token' => $request->boolean('remember_me') ? ($user->getRememberToken() ?? Str::random(10)) : null
        ]);
    }

    public function refresh(Request $request) 
    {
        $request->validate([
        'refresh_token' => 'required|string'
        ]);
    
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $newToken = $user->createToken('auth_token')->plainTextToken;

        return $this->apiResponse(true, 'SUCCESS', 'Token berhasil diperbarui', [
            'access_token' => $newToken,
            'refresh_token' => $newToken, 
            'token_type' => 'Bearer',
            'expires_in' => 900
        ]);
    }

    public function forgotPassword(Request $request) 
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->apiResponse(true, 'SUCCESS', 'Instruksi reset password telah dikirim ke email Anda');
        }

        $token = Str::random(60);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => hash('sha256', $token), 'created_at' => now()]
        );

        return $this->apiResponse(true, 'SUCCESS', 'Instruksi reset password telah dikirim ke email Anda', [
            'email' => $request->email,
            'expires_at' => now()->addMinutes(120)->toIso8601String()
        ]);
    }

    public function resetPassword(Request $request) 
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $resetRecord = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$resetRecord || !hash_equals($resetRecord->token, hash('sha256', $request->token))) {
            return $this->apiResponse(false, 'NOT_FOUND', 'Token reset password tidak valid atau sudah kedaluwarsa', [], 404);
        }

        $user = User::where('email', $request->email)->first();
        if ($user && Hash::check($request->password, $user->password)) {
            return $this->apiErrorResponse('CONFLICT', 'Password baru tidak boleh sama dengan password lama', 409);
        }

        User::where('email', $request->email)->update(['password' => Hash::make($request->password)]);
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        if ($user) {
            $user->tokens()->delete();
        }

      
        return $this->apiResponse(true, 'SUCCESS', 'Password berhasil diperbarui', [
        'email' => $request->email,
        'reset_at' => now()->toIso8601String() 
    ]);
    }

    public function changePassword(Request $request) 
    {
        $request->validate([
            'old_password' => 'required|current_password',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

     
        if (Hash::check($request->new_password, $request->user()->password)) {
            return $this->apiErrorResponse('CONFLICT', 'Password baru tidak boleh sama dengan password lama', 409);
        }

        $request->user()->update(['password' => Hash::make($request->new_password)]);

    
        $request->user()->tokens()->delete();

        return $this->apiResponse(true, 'SUCCESS_UPDATE', 'Password berhasil diubah', [
            'message' => 'Semua sesi aktif akan diakhiri. Silakan login kembali.'
        ]);
    }

    public function logout(Request $request) {
        $request->user()->currentAccessToken()->delete();
        return $this->apiResponse(true, 'SUCCESS', 'Logout berhasil');
    }

    public function me(Request $request) {
        return $this->apiResponse(true, 'SUCCESS', 'Profil berhasil diambil', ['user' => $request->user()]);
    }
}