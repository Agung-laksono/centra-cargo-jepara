<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverExpedition;
use App\Models\Expedition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DriverOnboardingController extends Controller
{
    /**
     * Tampilkan formulir pendaftaran mandiri supir.
     */
    public function create(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $token = $request->query('token');
        $expedition = null;
        if ($token) {
            $expedition = Expedition::where('partnership_token', $token)->first();
        }

        return Inertia::render('Auth/RegisterDriver', [
            'pairingToken' => $token,
            'expedition' => $expedition ? [
                'id' => $expedition->id,
                'name' => $expedition->name,
                'phone' => $expedition->phone,
            ] : null,
        ]);
    }

    /**
     * Simpan pendaftaran supir baru secara mandiri.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:25',
            'vehicle_name' => 'required|string|max:255',
            'role' => 'required|in:DRIVER_LINTAS,DRIVER_PICKUP',
            'password' => 'required|string|min:6|confirmed',
            'pairing_token' => 'nullable|string',
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'phone.required' => 'Nomor WhatsApp / HP wajib diisi.',
            'vehicle_name.required' => 'Jenis & nomor plat armada wajib diisi.',
            'password.min' => 'Kata sandi / PIN minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $cleanPhone = preg_replace('/\D/', '', $request->phone);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }

        $email = $request->email;
        if (!$email) {
            $email = $cleanPhone . '@supir.cargo';
        }

        // Cek apakah nomor atau email sudah terdaftar di users
        $existingUser = User::where('email', $email)
            ->orWhere('phone', $request->phone)
            ->orWhere('phone', $cleanPhone)
            ->first();

        if ($existingUser) {
            return back()->withErrors(['phone' => 'Nomor HP atau email ini sudah terdaftar. Silakan langsung masuk.']);
        }

        // Cari ekspedisi jika menyertakan token pairing
        $expedition = null;
        if ($request->filled('pairing_token')) {
            $expedition = Expedition::where('partnership_token', $request->pairing_token)->first();
        }

        // Buat user akun login
        $user = User::create([
            'name' => $request->name,
            'email' => $email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'expedition_id' => $expedition?->id,
        ]);

        // Buat data profil driver
        $driver = Driver::create([
            'name' => $request->name,
            'phone_number' => $request->phone,
            'vehicle_name' => $request->vehicle_name,
            'user_id' => $user->id,
            'expedition_id' => $expedition?->id,
        ]);

        // Buat relasi kemitraan jika mendaftar lewat pairing token ekspedisi
        if ($expedition) {
            DriverExpedition::create([
                'driver_id' => $driver->id,
                'user_id' => $user->id,
                'expedition_id' => $expedition->id,
                'role' => $user->role,
                'status' => 'ACTIVE',
                'handshake_code' => $expedition->partnership_token,
                'partnered_at' => now(),
            ]);
        }

        // Otomatis login supir
        Auth::login($user, remember: true);

        // Arahkan ke antarmuka operasional sesuai peran
        $redirectUrl = $user->role === 'DRIVER_PICKUP' ? '/supir/pickup' : '/supir/lintas';

        return redirect($redirectUrl)->with('success', 'Pendaftaran mandiri berhasil! Selamat bertugas.');
    }

    /**
     * Ambil daftar ekspedisi mitra untuk supir yang sedang aktif.
     */
    public function getPartnerships(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['partnerships' => []], 401);
        }

        $driver = $this->resolveDriver($user);
        if (!$driver) {
            return response()->json(['partnerships' => []]);
        }

        $partnerships = $driver->partnerExpeditions()
            ->select('expeditions.id', 'expeditions.name', 'expeditions.phone', 'expeditions.address', 'expeditions.theme_color', 'driver_expeditions.partnered_at', 'driver_expeditions.status')
            ->get();

        return response()->json([
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'vehicle_name' => $driver->vehicle_name,
                'phone' => $driver->phone_number,
            ],
            'partnerships' => $partnerships,
        ]);
    }

    /**
     * Handshake scan: hubungkan supir dengan ekspedisi via scan QR / token.
     */
    public function connectHandshake(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Sesi tidak sah. Silakan masuk terlebih dahulu.'], 401);
        }

        $driver = $this->resolveDriver($user);
        if (!$driver) {
            // Jika akun belum punya record driver, buatkan otomatis
            $driver = Driver::create([
                'name' => $user->name,
                'phone_number' => $user->phone,
                'vehicle_name' => 'Armada ' . $user->name,
                'user_id' => $user->id,
            ]);
        }

        // Parsing token (bisa berupa teks kode raw 'EXP-XXXX' atau URL utuh '...token=EXP-XXXX')
        $rawToken = trim($request->token);
        if (str_contains($rawToken, 'token=')) {
            $parsedUrl = parse_url($rawToken);
            parse_str($parsedUrl['query'] ?? '', $query);
            $rawToken = $query['token'] ?? $rawToken;
        }

        $expedition = Expedition::where('partnership_token', $rawToken)
            ->orWhere('receipt_code', strtoupper($rawToken))
            ->orWhere('id', is_numeric($rawToken) ? (int)$rawToken : -1)
            ->first();

        if (!$expedition) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kode atau QR Kemitraan tidak dikenali. Pastikan scan QR resmi ekspedisi terdaftar.',
            ], 404);
        }

        // Cek apakah sudah pernah bermitra
        $existing = DriverExpedition::where('driver_id', $driver->id)
            ->where('expedition_id', $expedition->id)
            ->first();

        if ($existing) {
            if ($existing->status !== 'ACTIVE') {
                $existing->update(['status' => 'ACTIVE', 'partnered_at' => now()]);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Armada Anda sudah terdaftar sebagai mitra aktif di ' . $expedition->name . '.',
                'expedition' => $expedition,
            ]);
        }

        // Buat relasi kemitraan baru
        DriverExpedition::create([
            'driver_id' => $driver->id,
            'user_id' => $user->id,
            'expedition_id' => $expedition->id,
            'role' => $user->role ?: 'DRIVER_LINTAS',
            'status' => 'ACTIVE',
            'handshake_code' => $expedition->partnership_token,
            'partnered_at' => now(),
        ]);

        // Jika supir belum memiliki ekspedisi default, set ke ekspedisi ini
        if (!$user->expedition_id) {
            $user->update(['expedition_id' => $expedition->id]);
        }
        if (!$driver->expedition_id) {
            $driver->update(['expedition_id' => $expedition->id]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Handshake Kemitraan Berhasil! Anda resmi terhubung dengan armada ' . $expedition->name . '.',
            'expedition' => [
                'id' => $expedition->id,
                'name' => $expedition->name,
                'phone' => $expedition->phone,
                'theme_color' => $expedition->theme_color,
            ],
        ]);
    }

    /**
     * Dapatkan data QR Kemitraan ekspedisi untuk ditampilkan di gudang/admin ekspedisi.
     */
    public function getExpeditionQr(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error'], 401);
        }

        $expedition = $user->expedition;
        if (!$expedition && $request->filled('expedition_id')) {
            $expedition = Expedition::find($request->expedition_id);
        }

        if (!$expedition) {
            return response()->json(['status' => 'error', 'message' => 'Data ekspedisi tidak ditemukan.'], 404);
        }

        // Buat token jika belum ada
        if (!$expedition->partnership_token) {
            $expedition->partnership_token = 'EXP-' . strtoupper(Str::random(8));
            $expedition->save();
        }

        $qrValue = route('register.driver', ['token' => $expedition->partnership_token]);

        return response()->json([
            'status' => 'success',
            'expedition' => [
                'id' => $expedition->id,
                'name' => $expedition->name,
                'phone' => $expedition->phone,
                'partnership_token' => $expedition->partnership_token,
                'qr_value' => $qrValue,
            ],
        ]);
    }

    /**
     * Helper untuk menemukan data driver terkait user saat ini.
     */
    protected function resolveDriver(User $user): ?Driver
    {
        if ($user->driver) {
            return $user->driver;
        }

        return Driver::where(function ($q) use ($user) {
            $q->where('user_id', $user->id);
            if ($user->phone) {
                $q->orWhere('phone_number', $user->phone);
            }
            $q->orWhere('name', 'like', "%{$user->name}%");
        })->first();
    }
}
