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

        // Buat relasi kemitraan jika mendaftar lewat pairing token ekspedisi (Menunggu persetujuan gudang)
        if ($expedition) {
            DriverExpedition::create([
                'driver_id' => $driver->id,
                'user_id' => $user->id,
                'expedition_id' => $expedition->id,
                'role' => $user->role,
                'status' => 'PENDING',
                'handshake_code' => $expedition->partnership_token,
                'partnered_at' => null,
            ]);
        }

        // Otomatis login supir
        Auth::login($user, remember: true);

        // Arahkan ke antarmuka operasional sesuai peran
        $redirectUrl = $user->role === 'DRIVER_PICKUP' ? '/supir/pickup' : '/supir/lintas';

        return redirect($redirectUrl)->with('success', 'Pendaftaran mandiri berhasil! Permohonan kemitraan dikirim ke ' . ($expedition ? $expedition->name : 'pusat') . '.');
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
            ->select(
                'expeditions.id',
                'expeditions.name',
                'expeditions.phone',
                'expeditions.address',
                'expeditions.theme_color',
                'driver_expeditions.id as partnership_id',
                'driver_expeditions.partnered_at',
                'driver_expeditions.status'
            )
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
                'message' => 'Kode atau QR Kemitraan tidak cocok atau sudah kadaluarsa (di-reset). Silakan minta QR terbaru di gudang ekspedisi.',
            ], 404);
        }

        // Cek apakah sudah pernah bermitra
        $existing = DriverExpedition::where('driver_id', $driver->id)
            ->where('expedition_id', $expedition->id)
            ->first();

        if ($existing) {
            if ($existing->status === 'ACTIVE') {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Armada Anda sudah terdaftar sebagai mitra aktif di ' . $expedition->name . '.',
                    'expedition' => $expedition,
                ]);
            }

            if ($existing->status === 'PENDING') {
                return response()->json([
                    'status' => 'pending',
                    'message' => 'Permohonan kemitraan Anda masih menunggu persetujuan dari petugas gudang ' . $expedition->name . '.',
                    'expedition' => $expedition,
                ]);
            }

            // Jika status REJECTED atau INACTIVE, ajukan ulang ke status PENDING
            $existing->update([
                'status' => 'PENDING',
                'handshake_code' => $expedition->partnership_token,
                'updated_at' => now(),
            ]);

            return response()->json([
                'status' => 'pending',
                'message' => 'Permohonan kemitraan berhasil diajukan ulang ke gudang ' . $expedition->name . '. Menunggu persetujuan staf.',
                'expedition' => $expedition,
            ]);
        }

        // Buat relasi kemitraan baru dengan status PENDING
        DriverExpedition::create([
            'driver_id' => $driver->id,
            'user_id' => $user->id,
            'expedition_id' => $expedition->id,
            'role' => $user->role ?: 'DRIVER_LINTAS',
            'status' => 'PENDING',
            'handshake_code' => $expedition->partnership_token,
            'partnered_at' => null,
        ]);

        return response()->json([
            'status' => 'pending',
            'message' => 'Permohonan kemitraan terkirim ke ' . $expedition->name . '! Menunggu verifikasi dan persetujuan staf gudang.',
            'expedition' => [
                'id' => $expedition->id,
                'name' => $expedition->name,
                'phone' => $expedition->phone,
                'theme_color' => $expedition->theme_color,
            ],
        ]);
    }

    /**
     * Dapatkan data QR Kemitraan ekspedisi dan ringkasan permohonan supir.
     */
    public function getExpeditionQr(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error'], 401);
        }

        $expedition = $this->resolveExpeditionForUser($user, $request->query('expedition_id'));

        if (!$expedition) {
            return response()->json(['status' => 'error', 'message' => 'Data ekspedisi tidak ditemukan.'], 404);
        }

        // Buat token jika belum ada
        if (!$expedition->partnership_token) {
            $expedition->partnership_token = 'EXP-' . strtoupper(Str::random(8));
            $expedition->save();
        }

        $qrValue = route('register.driver', ['token' => $expedition->partnership_token]);

        $pendingCount = DriverExpedition::where('expedition_id', $expedition->id)->where('status', 'PENDING')->count();
        $activeCount = DriverExpedition::where('expedition_id', $expedition->id)->where('status', 'ACTIVE')->count();

        return response()->json([
            'status' => 'success',
            'expedition' => [
                'id' => $expedition->id,
                'name' => $expedition->name,
                'phone' => $expedition->phone,
                'partnership_token' => $expedition->partnership_token,
                'qr_value' => $qrValue,
            ],
            'stats' => [
                'pending_count' => $pendingCount,
                'active_count' => $activeCount,
            ],
        ]);
    }

    /**
     * Perbarui / Reset Kode QR Kemitraan ekspedisi.
     * Kode lama tidak bisa lagi di-scan, sedangkan supir yang sudah terhubung tetap aman.
     */
    public function resetExpeditionQr(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Sesi tidak sah.'], 401);
        }

        $expedition = $this->resolveExpeditionForUser($user, $request->input('expedition_id'));
        if (!$expedition) {
            return response()->json(['status' => 'error', 'message' => 'Ekspedisi tidak ditemukan.'], 404);
        }

        // Buat token baru yang unik
        do {
            $newToken = 'EXP-' . strtoupper(Str::random(8));
        } while (Expedition::where('partnership_token', $newToken)->exists());

        $expedition->partnership_token = $newToken;
        $expedition->save();

        $qrValue = route('register.driver', ['token' => $expedition->partnership_token]);

        return response()->json([
            'status' => 'success',
            'message' => 'Kode QR Kemitraan berhasil diperbarui! QR atau poster lama tidak dapat digunakan lagi oleh pendaftar baru.',
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
     * Ambil daftar permohonan kemitraan supir (Pending, Aktif, Ditolak).
     */
    public function getPartnerRequests(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Sesi tidak sah.'], 401);
        }

        $expedition = $this->resolveExpeditionForUser($user, $request->query('expedition_id'));
        if (!$expedition) {
            return response()->json(['status' => 'error', 'message' => 'Ekspedisi tidak ditemukan.'], 404);
        }

        $partnerships = DriverExpedition::where('expedition_id', $expedition->id)
            ->with(['driver', 'user'])
            ->orderByRaw("CASE WHEN status = 'PENDING' THEN 1 WHEN status = 'ACTIVE' THEN 2 WHEN status = 'INACTIVE' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($p) {
                $driverName = $p->driver?->name ?? $p->user?->name ?? 'Supir Armada';
                $phone = $p->driver?->phone_number ?? $p->user?->phone ?? '-';
                $vehicle = $p->driver?->vehicle_name ?? 'Armada Standar';

                // Format wa link jika ada nomor
                $cleanPhone = preg_replace('/\D/', '', $phone);
                if (str_starts_with($cleanPhone, '0')) {
                    $cleanPhone = '62' . substr($cleanPhone, 1);
                }
                $waUrl = $cleanPhone ? "https://wa.me/{$cleanPhone}" : null;

                return [
                    'id' => $p->id,
                    'driver_id' => $p->driver_id,
                    'user_id' => $p->user_id,
                    'name' => $driverName,
                    'phone' => $phone,
                    'wa_url' => $waUrl,
                    'vehicle_name' => $vehicle,
                    'role' => $p->role,
                    'role_label' => $p->role === 'DRIVER_PICKUP' ? 'Supir Penjemputan (Lokal)' : 'Supir Pengantaran (Lintas Kota)',
                    'status' => $p->status,
                    'created_at' => $p->created_at ? $p->created_at->format('d/m/Y H:i') : '-',
                    'created_at_human' => $p->created_at ? $p->created_at->diffForHumans() : '-',
                    'partnered_at' => $p->partnered_at ? $p->partnered_at->format('d/m/Y H:i') : null,
                ];
            });

        $pendingCount = $partnerships->where('status', 'PENDING')->count();
        $activeCount = $partnerships->where('status', 'ACTIVE')->count();

        return response()->json([
            'status' => 'success',
            'expedition' => [
                'id' => $expedition->id,
                'name' => $expedition->name,
            ],
            'requests' => $partnerships,
            'stats' => [
                'total' => $partnerships->count(),
                'pending_count' => $pendingCount,
                'active_count' => $activeCount,
            ],
        ]);
    }

    /**
     * Setujui (Approve), Tolak (Reject), atau Nonaktifkan (Inactive) kemitraan supir.
     */
    public function updatePartnerStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:ACTIVE,REJECTED,INACTIVE,PENDING',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Sesi tidak sah.'], 401);
        }

        $partnership = DriverExpedition::with(['driver', 'user', 'expedition'])->findOrFail($id);

        // Pastikan user memiliki otoritas terhadap ekspedisi ini (Super Admin atau staf ekspedisi bersangkutan)
        $isAuthorized = ($user->expedition_id === null) || ($user->expedition_id === $partnership->expedition_id);
        if (!$isAuthorized) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk ekspedisi ini.'], 403);
        }

        $newStatus = $request->input('status');
        $partnership->status = $newStatus;

        if ($newStatus === 'ACTIVE') {
            $partnership->partnered_at = now();

            // Set default expedition supir jika belum punya
            if ($partnership->user && !$partnership->user->expedition_id) {
                $partnership->user->update(['expedition_id' => $partnership->expedition_id]);
            }
            if ($partnership->driver && !$partnership->driver->expedition_id) {
                $partnership->driver->update(['expedition_id' => $partnership->expedition_id]);
            }
        }

        $partnership->save();

        $statusLabels = [
            'ACTIVE' => 'disetujui sebagai mitra aktif',
            'REJECTED' => 'ditolak permohonan kemitraannya',
            'INACTIVE' => 'dinonaktifkan kemitraannya',
            'PENDING' => 'dikembalikan ke status permohonan',
        ];

        $driverName = $partnership->driver?->name ?? 'Supir';

        return response()->json([
            'status' => 'success',
            'message' => "Supir {$driverName} berhasil {$statusLabels[$newStatus]}.",
            'partnership' => [
                'id' => $partnership->id,
                'status' => $partnership->status,
                'partnered_at' => $partnership->partnered_at ? $partnership->partnered_at->format('d/m/Y H:i') : null,
            ],
        ]);
    }

    /**
     * Helper untuk menentukan objek ekspedisi yang dikelola oleh user saat ini.
     */
    protected function resolveExpeditionForUser(User $user, $requestedExpeditionId = null): ?Expedition
    {
        if ($user->expedition) {
            return $user->expedition;
        }

        if ($requestedExpeditionId) {
            return Expedition::find($requestedExpeditionId);
        }

        if ($user->expedition_id === null) {
            // Jika Super Admin tanpa parameter khusus, kembalikan ekspedisi pertama
            return Expedition::first();
        }

        return null;
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
