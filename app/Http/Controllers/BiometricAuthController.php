<?php

namespace App\Http\Controllers;

use App\Models\UserBiometric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BiometricAuthController extends Controller
{
    /**
     * Mendaftarkan kredensial kunci layar/sidik jari perangkat untuk pengguna yang sedang login.
     */
    public function register(Request $request)
    {
        $request->validate([
            'credential_id' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Sesi tidak sah.'], 401);
        }

        $authToken = Str::random(64);

        $biometric = UserBiometric::updateOrCreate(
            [
                'user_id' => $user->id,
                'credential_id' => $request->credential_id,
            ],
            [
                'device_name' => $request->device_name ?? $request->header('User-Agent', 'Perangkat Mobile'),
                'auth_token' => $authToken,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Kunci layar & Sidik jari berhasil dihubungkan!',
            'auth_token' => $authToken,
            'credential_id' => $biometric->credential_id,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Autentikasi dan login menggunakan kunci layar / sidik jari yang tersimpan.
     */
    public function login(Request $request)
    {
        $request->validate([
            'credential_id' => 'required|string',
            'auth_token' => 'required|string',
        ]);

        $biometric = UserBiometric::with('user')
            ->where('credential_id', $request->credential_id)
            ->where('auth_token', $request->auth_token)
            ->first();

        if (!$biometric || !$biometric->user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kunci layar tidak cocok atau telah kedaluwarsa. Silakan masuk menggunakan kata sandi.',
            ], 401);
        }

        $user = $biometric->user;
        $biometric->update(['last_used_at' => now()]);

        // Login pengguna dan kunci sesi jangka panjang
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Tentukan rute tujuan otomatis berdasarkan peran lapangan
        $redirectUrl = route('dashboard', absolute: false);
        if ($user->role === 'GUDANG') {
            $redirectUrl = route('inbound', absolute: false);
        } elseif ($user->role === 'DRIVER_PICKUP') {
            $redirectUrl = '/supir/pickup';
        } elseif ($user->role === 'DRIVER_LINTAS') {
            $redirectUrl = '/supir/lintas';
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Selamat datang kembali, ' . $user->name . '!',
            'redirect_url' => $redirectUrl,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Memeriksa status biometrik pengguna saat ini.
     */
    public function status()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['enabled' => false]);
        }

        $hasBiometrics = UserBiometric::where('user_id', $user->id)->exists();

        return response()->json([
            'enabled' => $hasBiometrics,
        ]);
    }

    /**
     * Menonaktifkan kunci layar dari akun ini.
     */
    public function disable(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            UserBiometric::where('user_id', $user->id)->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Kunci layar / Sidik jari berhasil dinonaktifkan.',
        ]);
    }
}
