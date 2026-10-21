<?php

use App\Http\Controllers\ExpeditionController;
use App\Http\Controllers\BiometricAuthController;
use App\Http\Controllers\DriverOnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SenderController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WilayahController;
use App\Models\Driver;
use App\Models\Expedition;
use App\Models\Koli;
use App\Models\Manifest;
use App\Models\Sender;
use App\Models\Shipment;
use App\Models\TrackingLog;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'apkDownloadUrl' => route('download.apk'),
    ]);
})->name('welcome');

// Rute Gateway Khusus Aplikasi Mobile Lapangan (Supir & Gudang)
Route::get('/app', function (Request $request) {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login', ['mode' => 'app']);
})->name('mobile.gateway');

Route::get('/supir', function () {
    return redirect()->route('mobile.gateway');
});

// Pendaftaran Mandiri Supir & Handshake Kemitraan
Route::get('/register-driver', [DriverOnboardingController::class, 'create'])->name('register.driver');
Route::post('/register-driver', [DriverOnboardingController::class, 'store'])->name('register.driver.store');

// Biometric & Screen Lock Authentication
Route::post('/biometric/login', [BiometricAuthController::class, 'login'])->name('biometric.login');
Route::middleware('auth')->group(function () {
    Route::post('/biometric/register', [BiometricAuthController::class, 'register'])->name('biometric.register');
    Route::get('/biometric/status', [BiometricAuthController::class, 'status'])->name('biometric.status');
    Route::post('/biometric/disable', [BiometricAuthController::class, 'disable'])->name('biometric.disable');

    // Handshake Kemitraan Supir & Ekspedisi
    Route::get('/api/driver/partnerships', [DriverOnboardingController::class, 'getPartnerships'])->name('driver.partnerships');
    Route::post('/api/driver/handshake', [DriverOnboardingController::class, 'connectHandshake'])->name('driver.handshake');
    Route::post('/api/driver/claim-manifest', [DriverOnboardingController::class, 'claimManifest'])->name('driver.claim.manifest');
    Route::get('/api/expedition/handshake-qr', [DriverOnboardingController::class, 'getExpeditionQr'])->name('expedition.handshake.qr');
    Route::post('/api/expedition/reset-qr', [DriverOnboardingController::class, 'resetExpeditionQr'])->name('expedition.reset.qr');
    Route::get('/api/expedition/partner-requests', [DriverOnboardingController::class, 'getPartnerRequests'])->name('expedition.partner.requests');
    Route::post('/api/expedition/partner-requests/{id}/status', [DriverOnboardingController::class, 'updatePartnerStatus'])->name('expedition.partner.update_status');
});

// Endpoint publik handshake manifest (bisa diakses dengan session aktif atau auto-login via token surat jalan)
Route::post('/api/driver/claim-manifest-guest', [DriverOnboardingController::class, 'claimManifest'])->name('driver.claim.manifest.guest');

// Direct Download APK Supir & Gudang
Route::get('/download-apk', function () {
    $path = public_path('downloads/jepara-cargo.apk');
    if (file_exists($path)) {
        return response()->download($path, 'JeparaCargo-Driver.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Disposition' => 'attachment; filename="JeparaCargo-Driver.apk"',
        ]);
    }

    return redirect('https://drive.usercontent.google.com/download?id=1E3QiYXrB-xVQv4GkrHrjLK52012tOIyM&export=download&authuser=0');
})->name('download.apk');

// Public Resi Tracking Endpoint
Route::get('/track-resi/{resi}', function ($resi) {
    $cleanResi = trim(strtoupper($resi));

    $shipment = Shipment::with([
        'sender',
        'kolis',
        'items',
        'trackingLogs' => function ($q) {
            $q->orderBy('created_at', 'desc');
        },
        'currentWarehouse',
        'expedition',
    ])->where('tracking_number', $cleanResi)->first();

    if (! $shipment) {
        return response()->json([
            'found' => false,
            'message' => 'Nomor resi "'.$cleanResi.'" tidak ditemukan. Mohon periksa kembali nomor resi Anda.',
        ], 404);
    }

    // Format destination address
    $destParts = array_filter([
        $shipment->destination_village,
        $shipment->destination_district,
        $shipment->destination_city,
        $shipment->destination_province,
    ]);

    // Build human-friendly timeline
    $timeline = [];

    // If there are recorded tracking logs, use them
    if ($shipment->trackingLogs && $shipment->trackingLogs->count() > 0) {
        foreach ($shipment->trackingLogs as $log) {
            $timeline[] = [
                'status' => $log->status,
                'location' => $log->location_desc ?? 'Operasional Kargo',
                'time' => $log->created_at ? $log->created_at->format('d M Y, H:i') : null,
                'proof_image' => $log->proof_image_url ?? null,
            ];
        }
    } else {
        // Fallback synthetic milestone based on current status & timestamps
        $timeline[] = [
            'status' => $shipment->current_status,
            'location' => $shipment->currentWarehouse ? $shipment->currentWarehouse->name : 'Armada Operasional Jepara',
            'time' => $shipment->updated_at ? $shipment->updated_at->format('d M Y, H:i') : $shipment->created_at->format('d M Y, H:i'),
            'proof_image' => null,
        ];

        if ($shipment->created_at) {
            $timeline[] = [
                'status' => 'Resi Dibuat & Terdaftar',
                'location' => $shipment->sender ? ($shipment->sender->city ?? 'Jepara') : 'Jepara',
                'time' => $shipment->created_at->format('d M Y, H:i'),
                'proof_image' => null,
            ];
        }
    }

    return response()->json([
        'found' => true,
        'shipment' => [
            'tracking_number' => $shipment->tracking_number,
            'current_status' => $shipment->current_status,
            'sender_name' => $shipment->sender ? ($shipment->sender->business_name ?? $shipment->sender->owner_name) : 'Pengrajin Jepara',
            'sender_city' => $shipment->sender ? ($shipment->sender->city ?? 'Jepara') : 'Jepara',
            'recipient_name' => $shipment->recipient_name,
            'destination' => count($destParts) > 0 ? implode(', ', $destParts) : ($shipment->destination_city ?? '-'),
            'total_koli' => $shipment->kolis ? $shipment->kolis->count() : 0,
            'kolis' => $shipment->kolis ? $shipment->kolis->map(function ($k) {
                return [
                    'koli_number' => $k->koli_number,
                    'total_koli' => $k->total_koli,
                    'barcode_id' => $k->barcode_id,
                    'description' => $k->description,
                    'physical_status' => $k->physical_status,
                ];
            }) : [],
            'items' => $shipment->items ? $shipment->items->map(function ($it) {
                return [
                    'name' => $it->name,
                    'qty' => $it->qty,
                    'unit' => $it->unit,
                ];
            }) : [],
            'payment_type' => $shipment->payment_type,
            'timeline' => $timeline,
            'expedition' => $shipment->expedition ? [
                'name' => $shipment->expedition->name,
                'phone' => $shipment->expedition->phone,
            ] : null,
        ],
    ]);
})->name('public.track.resi');

// Magic Links / Dashboard untuk Supir Pickup
Route::get('/supir/pickup', function (Request $request) {
    $token = $request->query('token');

    // Jika tanpa parameter token (misal supir login dari aplikasi mobile)
    if (! $token) {
        if (auth()->check()) {
            $user = auth()->user();
            // Cari data driver berdasarkan nomor telepon atau kesamaan nama
            $driver = Driver::where(function ($q) use ($user) {
                if ($user->phone) {
                    $q->where('phone_number', $user->phone);
                }
                $q->orWhere('name', 'like', "%{$user->name}%");
            })->first();

            if ($driver) {
                // Hanya muat otomatis tugas yang statusnya AKTIF BERJALAN (IN_TRANSIT / sudah di-handshake).
                // Manifest yang masih 'PREPARING' / 'PENDING' belum diserahterimakan, supir tetap harus scan QR (Handshake).
                $manifest = Manifest::where('driver_id', $driver->id)
                    ->where('manifest_type', 'PICKUP')
                    ->where('status', 'IN_TRANSIT')
                    ->latest()
                    ->first();

                if ($manifest && $manifest->magic_link_token) {
                    return redirect('/supir/pickup?token='.$manifest->magic_link_token);
                }
            }

            // Jika belum ada penugasan aktif, tampilkan antarmuka supir dalam status STANDBY
            return Inertia::render('DriverPickup', [
                'manifest' => [
                    'id' => null,
                    'status' => 'STANDBY',
                    'driver_name' => $user->name,
                    'expedition_name' => $user->expedition?->name,
                    'token' => null,
                ],
                'pickups' => [],
            ]);
        }

        abort(404, 'Tautan penjemputan tidak valid atau belum ada tugas aktif.');
    }

    $manifest = Manifest::with(['driver.user', 'expedition', 'kolis.shipment.sender'])
        ->where('magic_link_token', $token)
        ->where('manifest_type', 'PICKUP')
        ->firstOrFail();

    // Pastikan supir otomatis terautentikasi agar bisa menggunakan biometrik / kunci layar
    if (!Auth::check() && $manifest->driver) {
        $driverUser = $manifest->driver->user;
        if (!$driverUser && $manifest->driver->phone_number) {
            $cleanPhone = preg_replace('/\D/', '', $manifest->driver->phone_number);
            $email = ($cleanPhone ?: 'driver'.$manifest->driver->id) . '@supir.cargo';
            $driverUser = User::where('email', $email)->orWhere('phone', $manifest->driver->phone_number)->first();
            if (!$driverUser) {
                $driverUser = User::create([
                    'name' => $manifest->driver->name,
                    'email' => $email,
                    'phone' => $manifest->driver->phone_number,
                    'password' => Hash::make(Str::random(16)),
                    'role' => 'DRIVER_PICKUP',
                    'expedition_id' => $manifest->expedition_id,
                ]);
            }
            $manifest->driver->update(['user_id' => $driverUser->id]);
        }
        if ($driverUser) {
            Auth::login($driverUser, remember: true);
        }
    }

    // Group kolis by sender for pickup view
    $pickups = $manifest->kolis->groupBy(function ($koli) {
        return $koli->shipment->sender_id;
    })->map(function ($kolis, $senderId) {
        $sender = $kolis->first()->shipment->sender;
        $parts = array_filter([
            $sender->address ?? null,
            $sender->village ?? null,
            $sender->district ?? null,
            $sender->city ?? null,
            $sender->province ?? null,
        ]);

        return [
            'id' => $senderId,
            'nama_pengirim' => $sender ? ($sender->business_name ?? $sender->owner_name) : 'Tidak Diketahui',
            'telepon' => $sender->phone ?? null,
            'alamat' => count($parts) > 0 ? implode(', ', $parts) : 'Tidak ada alamat detail',
            'maps_url' => $sender->maps_url ?? null,
            'total_koli' => $kolis->count(),
            // Daftar barcode individual setiap koli untuk verifikasi scan
            'koli_barcodes' => $kolis->pluck('barcode_id')->filter()->values(),
            'shipments' => $kolis->groupBy(function ($k) {
                return $k->shipment->tracking_number;
            })->map(function ($shipmentKolis, $resi) {
                // Ambil nama barang (buang keterangan (Bagian x/y) untuk ringkasan)
                $items = $shipmentKolis->pluck('description')->map(function ($desc) {
                    return trim(preg_replace('/(\s+#.*|\s+\(Bagian.*)/', '', $desc));
                })->unique()->implode(', ');

                return [
                    'resi' => $resi,
                    'total_koli' => $shipmentKolis->count(),
                    'items' => $items,
                    'status' => $shipmentKolis->first()->shipment->current_status,
                ];
            })->values(),
        ];
    })->values();

    return Inertia::render('DriverPickup', [
        'manifest' => [
            'id' => $manifest->id,
            'status' => $manifest->status,
            'driver_name' => $manifest->driver->name ?? 'Supir',
            'expedition_name' => $manifest->expedition->name ?? null,
            'token' => $token,
        ],
        'pickups' => $pickups,
    ]);
});

Route::post('/supir/pickup/complete', function (Request $request) {
    $request->validate([
        'token' => 'required|string',
        'sender_id' => 'required',
        'force' => 'boolean',
        'force_reason' => 'nullable|string|max:500',
        'scanned_barcodes' => 'array',
    ]);

    $manifest = Manifest::where('magic_link_token', $request->token)->firstOrFail();

    // Temukan semua koli yang terkait dengan manifest ini dan berasal dari sender_id yang dimaksud
    $koliIds = $manifest->kolis()->whereHas('shipment', function ($q) use ($request) {
        $q->where('sender_id', $request->sender_id);
    })->pluck('kolis.id');

    // Temukan shipment yang berelasi
    $shipmentIds = Koli::whereIn('id', $koliIds)->pluck('shipment_id')->unique();

    // Ubah status shipment & status fisik koli
    Shipment::whereIn('id', $shipmentIds)->update(['current_status' => 'Diterima']);
    Koli::whereIn('id', $koliIds)->update(['physical_status' => Koli::STATUS_DI_ANGKUT]);

    // Catat audit log ke manifest
    $isForce = $request->boolean('force', false);
    $scannedCount = count($request->input('scanned_barcodes', []));
    $totalKoli = $koliIds->count();

    $notes = $isForce
        ? "[PAKSA SELESAI] Ter-scan: {$scannedCount}/{$totalKoli} koli. Alasan: ".($request->force_reason ?? 'Tidak disebutkan')
        : "[SELESAI NORMAL] Semua {$scannedCount} koli ter-scan dan diverifikasi.";

    foreach ($shipmentIds as $shipmentId) {
        TrackingLog::create([
            'shipment_id' => $shipmentId,
            'status' => 'Diterima',
            'location' => 'Penjemputan Armada ('.($manifest->driver->name ?? 'Supir').')',
            'notes' => 'Barang telah dimuat ke armada penjemputan. '.$notes,
            'actor_type' => 'Driver',
            'actor_name' => $manifest->driver->name ?? 'Supir',
        ]);
    }

    $manifest->update([
        'is_force_completed' => $isForce,
        'scanned_koli_count' => $scannedCount,
        'pickup_notes' => ($manifest->pickup_notes ? $manifest->pickup_notes."\n" : '').now()->format('d/m/Y H:i').' — Sender #'.$request->sender_id.': '.$notes,
    ]);

    return back()->with('success', 'Barang berhasil diangkut ke mobil!');
});

// Magic Links / Dashboard untuk Supir Lintas
Route::get('/supir/lintas', function (Request $request) {
    $token = $request->query('token');

    // Jika tanpa parameter token (misal supir lintas login dari aplikasi mobile)
    if (! $token) {
        if (auth()->check()) {
            $user = auth()->user();
            // Cari data driver berdasarkan nomor telepon atau kesamaan nama
            $driver = Driver::where(function ($q) use ($user) {
                if ($user->phone) {
                    $q->where('phone_number', $user->phone);
                }
                $q->orWhere('name', 'like', "%{$user->name}%");
            })->first();

            if ($driver) {
                // Hanya muat otomatis tugas yang statusnya AKTIF BERJALAN (IN_TRANSIT / sudah di-handshake).
                // Manifest yang masih 'PREPARING' / 'PENDING' belum diserahterimakan, supir tetap harus scan QR (Handshake).
                $manifest = Manifest::where('driver_id', $driver->id)
                    ->where('manifest_type', 'DELIVERY')
                    ->where('status', 'IN_TRANSIT')
                    ->latest()
                    ->first();

                if ($manifest && $manifest->magic_link_token) {
                    return redirect('/supir/lintas?token='.$manifest->magic_link_token);
                }
            }

            // Jika belum ada surat jalan aktif, tampilkan antarmuka supir dalam status STANDBY
            return Inertia::render('DriverLintas', [
                'manifest' => [
                    'id' => null,
                    'status' => 'STANDBY',
                    'driver_name' => $user->name,
                    'token' => null,
                ],
                'deliveries' => [],
            ]);
        }

        abort(404, 'Tautan surat jalan tidak valid atau belum ada manifest aktif.');
    }

    $manifest = Manifest::with(['driver.user', 'kolis.shipment.sender'])
        ->where('magic_link_token', $token)
        ->where('manifest_type', 'DELIVERY')
        ->firstOrFail();

    // Pastikan supir otomatis terautentikasi agar bisa menggunakan biometrik / kunci layar
    if (!Auth::check() && $manifest->driver) {
        $driverUser = $manifest->driver->user;
        if (!$driverUser && $manifest->driver->phone_number) {
            $cleanPhone = preg_replace('/\D/', '', $manifest->driver->phone_number);
            $email = ($cleanPhone ?: 'driver'.$manifest->driver->id) . '@supir.cargo';
            $driverUser = User::where('email', $email)->orWhere('phone', $manifest->driver->phone_number)->first();
            if (!$driverUser) {
                $driverUser = User::create([
                    'name' => $manifest->driver->name,
                    'email' => $email,
                    'phone' => $manifest->driver->phone_number,
                    'password' => Hash::make(Str::random(16)),
                    'role' => 'DRIVER_LINTAS',
                    'expedition_id' => $manifest->expedition_id,
                ]);
            }
            $manifest->driver->update(['user_id' => $driverUser->id]);
        }
        if ($driverUser) {
            Auth::login($driverUser, remember: true);
        }
    }

    $deliveries = $manifest->kolis->groupBy('shipment_id')->map(function ($kolis) {
        $shipment = $kolis->first()->shipment;
        $cityName = is_array($shipment->destination_city)
            ? ($shipment->destination_city['name'] ?? '-')
            : ($shipment->destination_city ?? '-');

        $districtName = is_array($shipment->destination_district)
            ? ($shipment->destination_district['name'] ?? '-')
            : ($shipment->destination_district ?? '-');

        return [
            'id' => $shipment->id, // Use shipment ID as the unique identifier for the card
            'resi' => $shipment->tracking_number,
            'penerima' => $shipment->recipient_name,
            'telepon' => $shipment->recipient_phone,
            'tujuan' => $cityName.', '.$districtName,
            'alamat_lengkap' => $shipment->recipient_address,
            'status' => $shipment->current_status,
            'isCod' => $shipment->payment_type === 'COD',
            'tagihanCod' => $shipment->total_cod ?? 0,
            'kolis' => $kolis->map(function ($k) {
                return [
                    'id' => $k->id,
                    'barcode' => $k->barcode_id, // Gunakan barcode_id asli dari database
                    'deskripsi' => $k->description,
                ];
            })->values(),
        ];
    })->values();

    return Inertia::render('DriverLintas', [
        'manifest' => [
            'id' => $manifest->id,
            'status' => $manifest->status,
            'driver_name' => $manifest->driver->name ?? 'Supir',
            'token' => $token,
        ],
        'deliveries' => $deliveries,
    ]);
});

Route::post('/api/driver/location', function (Request $request) {
    $request->validate([
        'token' => 'required|string',
        'lat' => 'required|numeric',
        'lng' => 'required|numeric',
    ]);

    $manifest = Manifest::where('magic_link_token', $request->token)->first();
    if ($manifest && $manifest->driver_id) {
        Driver::where('id', $manifest->driver_id)->update([
            'last_lat' => $request->lat,
            'last_lng' => $request->lng,
            'last_location_update' => now(),
        ]);

        return response()->json(['status' => 'success']);
    }

    return response()->json(['status' => 'not_found'], 404);
});

Route::get('/dashboard', function (Request $request) {
    $user = $request->user();

    // Auto-redirect jika pengguna adalah Petugas Gudang atau Supir Lapangan
    if ($user) {
        if ($user->role === 'GUDANG') {
            return redirect()->route('inbound');
        } elseif ($user->role === 'DRIVER_PICKUP') {
            return redirect('/supir/pickup');
        } elseif ($user->role === 'DRIVER_LINTAS') {
            return redirect('/supir/lintas');
        }
    }

    $isSuperAdmin = $user && $user->expedition_id === null;

    // JIKA SUPER ADMIN (PLATFORM AGREGATOR)
    if ($isSuperAdmin) {
        $totalExpeditions = Expedition::count();
        $activeExpeditions = Expedition::where('is_active', true)->count();

        $totalUsers = User::count();
        $totalShipmentsMonth = Shipment::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $totalKoliMonth = Koli::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $activeDrivers = User::whereIn('role', ['DRIVER_PICKUP', 'DRIVER_LINTAS'])->count();

        // Data statistik per ekspedisi mitra
        $expeditionsSummary = Expedition::withCount([
            'users',
            'warehouses',
            'shipments',
            'shipments as shipments_month_count' => function ($q) {
                $q->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
            },
            'shipments as completed_shipments_count' => function ($q) {
                $q->where('current_status', 'Selesai');
            },
        ])->get()->map(function ($exp) {
            return [
                'id' => $exp->id,
                'name' => $exp->name,
                'domain_slug' => $exp->domain_slug,
                'receipt_code' => $exp->receipt_code ?: 'EXP',
                'theme_color' => $exp->theme_color ?: '#10b981',
                'is_active' => (bool) $exp->is_active,
                'users_count' => $exp->users_count,
                'warehouses_count' => $exp->warehouses_count,
                'total_shipments' => $exp->shipments_count,
                'shipments_month' => $exp->shipments_month_count,
                'completed_shipments' => $exp->completed_shipments_count,
            ];
        });

        // 7 Hari Terakhir: Tren Pengiriman Seluruh Agregator
        $daysLabels = [];
        $shipmentsTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $daysLabels[] = $date->translatedFormat('D');
            $shipmentsTrend[] = Shipment::whereDate('created_at', $date->toDateString())->count();
        }

        // Aktivitas / Feed terbaru di ekosistem
        $recentUsers = User::with('expedition')->latest()->take(5)->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'title' => "Pengguna baru: {$u->name} ({$u->role})",
                'subtitle' => $u->expedition ? $u->expedition->name : 'Pusat / Agregator',
                'time' => $u->created_at->diffForHumans(),
                'type' => 'user',
                'icon' => 'ri-user-add-line',
                'color' => 'text-blue-500',
                'bg' => 'bg-blue-50',
            ];
        });

        return Inertia::render('Dashboard', [
            'is_super_admin' => true,
            'super_stats' => [
                'total_expeditions' => $totalExpeditions,
                'active_expeditions' => $activeExpeditions,
                'total_users' => $totalUsers,
                'total_shipments_month' => $totalShipmentsMonth,
                'total_koli_month' => $totalKoliMonth,
                'active_drivers' => $activeDrivers,
            ],
            'expeditions_summary' => $expeditionsSummary,
            'platform_chart' => [
                'labels' => $daysLabels,
                'data' => $shipmentsTrend,
            ],
            'recent_platform_activities' => $recentUsers,
        ]);
    }

    // JIKA ADMIN EKSPEDISI / STAFF OPERASIONAL (TENANT)
    $search = $request->query('search');
    $status = $request->query('status');
    $payment = $request->query('payment');

    $query = Shipment::with('sender', 'kolis')->orderBy('created_at', 'desc');

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('tracking_number', 'like', "%{$search}%")
                ->orWhereHas('sender', function ($q) use ($search) {
                    $q->where('business_name', 'like', "%{$search}%")
                        ->orWhere('owner_name', 'like', "%{$search}%");
                });
        });
    }

    if ($status) {
        if ($status === 'Diterima') {
            $query->where(function ($q) {
                $q->whereIn('current_status', ['Diterima', 'MENUNGGU_BARANG'])->orWhereNull('current_status');
            });
        } else {
            $query->where('current_status', $status);
        }
    }

    if ($payment) {
        $query->where('payment_type', $payment);
    }

    $paginator = $query->paginate(5)->withQueryString();

    $paginator->getCollection()->transform(function ($shipment) {
        $city = is_array($shipment->destination_city)
            ? ($shipment->destination_city['name'] ?? null)
            : $shipment->destination_city;

        $items = $shipment->kolis->pluck('description')->take(2)->implode(', ');
        if ($shipment->kolis->count() > 2) {
            $items .= ' dll';
        }

        return [
            'tracking_number' => $shipment->tracking_number,
            'sender' => $shipment->sender ? ($shipment->sender->business_name ?? $shipment->sender->owner_name) : null,
            'alamat' => $shipment->sender ? $shipment->sender->address : 'Tidak ada alamat',
            'items' => $items,
            'city' => $city,
            'total_koli' => $shipment->kolis->count(),
            'payment_type' => $shipment->payment_type,
            'total_cod' => $shipment->total_cod,
            'current_status' => $shipment->current_status ?? 'Diterima',
            'pickup_type' => $shipment->pickup_type,
        ];
    });

    $menungguPickup = Shipment::whereIn('current_status', ['Diterima', 'MENUNGGU_BARANG'])
        ->orWhereNull('current_status')
        ->count();

    $diGudang = Koli::whereHas('shipment', function ($q) {
        $q->where('current_status', 'Diterima')->orWhereNull('current_status');
    })->count();

    $dalamPerjalanan = Shipment::where('current_status', 'Dalam Perjalanan')->count();

    $terkirimBulanIni = Shipment::where('current_status', 'Selesai')
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();

    $uangCodDiSupir = Shipment::where('current_status', 'Dalam Perjalanan')
        ->where('payment_type', 'COD')
        ->sum('total_cod');

    return Inertia::render('Dashboard', [
        'is_super_admin' => false,
        'shipments' => $paginator,
        'filters' => [
            'search' => $search,
            'status' => $status,
            'payment' => $payment,
        ],
        'stats' => [
            'menunggu_pickup' => $menungguPickup,
            'di_gudang' => $diGudang,
            'dalam_perjalanan' => $dalamPerjalanan,
            'terkirim_bulan_ini' => $terkirimBulanIni,
            'uang_cod_di_supir' => $uangCodDiSupir,
        ],
        'drivers_location' => Driver::whereNotNull('last_lat')->whereNotNull('last_lng')->get(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/lokasi-supir', function () {
    return Inertia::render('LokasiSupir', [
        'drivers_location' => Driver::whereNotNull('last_lat')->whereNotNull('last_lng')->get(),
    ]);
})->middleware(['auth', 'verified'])->name('lokasi-supir');

Route::get('/shipments/{trackingNumber}/print', [ShipmentController::class, 'print'])->name('shipments.print');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/buat-resi', function () {
        $recentShipments = Shipment::with(['sender', 'kolis'])
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->map(function ($shipment) {
                $cityName = is_array($shipment->destination_city)
                    ? ($shipment->destination_city['name'] ?? '-')
                    : ($shipment->destination_city ?? '-');

                return [
                    'id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'sender_name' => $shipment->sender ? ($shipment->sender->business_name ?? $shipment->sender->owner_name) : 'Tidak diketahui',
                    'recipient_name' => $shipment->recipient_name,
                    'destination' => $cityName,
                    'total_koli' => $shipment->kolis->count(),
                    'current_status' => $shipment->current_status ?? 'Diterima',
                    'created_at_label' => $shipment->created_at->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('BuatResi', [
            'senders' => Sender::all(),
            'recipients' => Shipment::select('recipient_name', 'recipient_phone', 'recipient_address', 'destination_province', 'destination_city', 'destination_district', 'destination_village')->distinct()->get(),
            'suggestedItems' => Koli::pluck('description')->map(function ($desc) {
                return ucwords(strtolower(trim(preg_replace('/(\s+#.*|\s+\(Bagian.*)/', '', $desc))));
            })->unique()->values(),
            'units' => Unit::pluck('name'),
            'recentShipments' => $recentShipments,
        ]);
    })->name('buat-resi');

    Route::get('/api/recent-shipments', function (Request $request) {
        $offset = $request->query('offset', 0);
        $limit = $request->query('limit', 20);

        $shipments = Shipment::with(['sender', 'kolis'])
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($shipment) {
                $cityName = is_array($shipment->destination_city)
                    ? ($shipment->destination_city['name'] ?? '-')
                    : ($shipment->destination_city ?? '-');

                return [
                    'id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'sender_name' => $shipment->sender ? ($shipment->sender->business_name ?? $shipment->sender->owner_name) : 'Tidak diketahui',
                    'recipient_name' => $shipment->recipient_name,
                    'destination' => $cityName,
                    'total_koli' => $shipment->kolis->count(),
                    'current_status' => $shipment->current_status ?? 'Diterima',
                    'created_at_label' => $shipment->created_at->format('d/m/Y H:i'),
                ];
            });

        return response()->json(['shipments' => $shipments]);
    })->name('api.recent-shipments');

    // Route untuk memproses form simpan resi
    Route::post('/shipments', [ShipmentController::class, 'store'])->name('shipments.store');

    // Route Pengrajin
    Route::post('/senders', [SenderController::class, 'store'])->name('senders.store');

    Route::get('/pickup', function () {
        // Ambil koli_id yang sudah masuk dalam manifest PICKUP yang masih aktif (bukan selesai)
        $assignedKoliIds = Manifest::where('manifest_type', 'PICKUP')
            ->whereNotIn('status', ['SELESAI', 'CANCELLED'])
            ->with('kolis')
            ->get()
            ->flatMap(fn ($m) => $m->kolis->pluck('id'))
            ->unique();

        $shipments = Shipment::with('sender', 'kolis')
            ->where(function ($q) {
                $q->whereIn('current_status', ['Diterima', 'MENUNGGU_BARANG'])
                    ->orWhereNull('current_status');
            })
            ->where('pickup_type', 'Minta Dijemput Armada')
            // Kecualikan shipment yang seluruh kolinya sudah ada di manifest aktif
            ->whereDoesntHave('kolis', function ($q) use ($assignedKoliIds) {
                $q->whereIn('id', $assignedKoliIds);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = $shipments->groupBy('sender_id')->map(function ($group) {
            $sender = $group->first()->sender;

            $totalKoli = 0;
            $itemsArr = [];
            $resiList = [];
            $subShipments = [];

            foreach ($group as $shipment) {
                $totalKoli += $shipment->kolis->count();
                $koliDescs = [];
                $itemGroups = [];
                foreach ($shipment->kolis as $koli) {
                    $desc = $koli->description;
                    $itemsArr[] = $desc; // Store for summary
                    $koliDescs[] = $desc;

                    $baseName = trim(preg_replace('/(\s+#\d+|\s+\(Bagian.*\))/i', '', $desc));
                    if (! isset($itemGroups[$baseName])) {
                        $itemGroups[$baseName] = ['name' => $baseName, 'units' => [], 'koli_count' => 0];
                    }

                    preg_match('/#(\d+)/', $desc, $matches);
                    $unitId = $matches[1] ?? '1';

                    if (! in_array($unitId, $itemGroups[$baseName]['units'])) {
                        $itemGroups[$baseName]['units'][] = $unitId;
                    }
                    $itemGroups[$baseName]['koli_count']++;
                }

                $resiList[] = $shipment->tracking_number;

                $parsedItems = array_values(array_map(function ($g) {
                    return [
                        'name' => ucwords(strtolower($g['name'])),
                        'qty' => count($g['units']),
                        'koli' => $g['koli_count'],
                    ];
                }, $itemGroups));

                $subShipments[] = [
                    'id' => $shipment->id,
                    'resi' => $shipment->tracking_number,
                    'koli' => $shipment->kolis->count(),
                    'koli_ids' => $shipment->kolis->pluck('id')->toArray(),
                    'koli_barcodes' => $shipment->kolis->pluck('barcode_id')->filter()->values()->toArray(),
                    'item_details' => $parsedItems,
                    'destination' => is_array($shipment->destination_city) ? ($shipment->destination_city['name'] ?? '-') : $shipment->destination_city,
                    'selected' => false,
                ];
            }

            $uniqueItemDescs = collect($itemsArr)->map(function ($desc) {
                return trim(preg_replace('/(\s+#\d+|\s+\(Bagian.*\))/i', '', $desc));
            })->unique()->values();
            $items = $uniqueItemDescs->take(2)->implode(', ');
            if ($uniqueItemDescs->count() > 2) {
                $items .= ' dll';
            }

            $fullAddress = 'Tidak ada alamat';
            if ($sender) {
                $parts = array_filter([
                    $sender->address,
                    $sender->village,
                    $sender->district,
                    $sender->city,
                    $sender->province,
                ]);
                $fullAddress = count($parts) > 0 ? implode(', ', $parts) : 'Jepara (Alamat detail tidak tersedia)';
            }

            $kecamatan = 'Lainnya';
            if ($sender) {
                if ($sender->district) {
                    $kecamatan = $sender->district;
                } elseif ($sender->address) {
                    $addrStr = str_ireplace(['Jl.', 'Jalan'], '', $sender->address);
                    $partsStr = explode(' ', trim($addrStr));
                    $kecamatan = $partsStr[0] ?? 'Lainnya';
                }
            }

            return [
                'id' => $sender ? $sender->id : 'unknown-'.$group->first()->id,
                'resi' => collect($resiList)->take(2)->implode(', ').(count($resiList) > 2 ? ' ...' : ''),
                'nama' => $sender ? ($sender->business_name ?? $sender->owner_name) : 'Tidak Diketahui',
                'alamat' => $fullAddress,
                'kecamatan' => ucfirst(strtolower($kecamatan)),
                'created_at' => $group->first()->created_at,
                'maps_url' => $sender ? $sender->maps_url : null,
                'koli' => $totalKoli,
                'item' => $items,
                'selected' => false,
                'shipments' => $subShipments,
            ];
        })->values();

        $drivers = Driver::all();

        // Ambil SEMUA manifest PICKUP (aktif dan non-aktif) untuk riwayat
        $allManifests = Manifest::where('manifest_type', 'PICKUP')
            ->with(['driver', 'kolis.shipment.sender'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($manifest) {
                $shipments = $manifest->kolis
                    ->groupBy(fn ($koli) => $koli->shipment?->tracking_number)
                    ->map(function ($kolis, $resi) {
                        $shipment = $kolis->first()->shipment;

                        return [
                            'resi' => $resi,
                            'sender' => $shipment?->sender
                                ? ($shipment->sender->business_name ?? $shipment->sender->owner_name)
                                : '-',
                            'koli' => $kolis->count(),
                        ];
                    })->values();

                $magicUrl = url('/supir/pickup?token='.$manifest->magic_link_token);
                $driver = $manifest->driver;
                $waLink = null;
                if ($driver?->phone_number) {
                    $phone = preg_replace('/\D/', '', $driver->phone_number);
                    if (str_starts_with($phone, '0')) {
                        $phone = '62'.substr($phone, 1);
                    }
                    $appUrl = 'jeparacargo://supir/pickup?token='.$manifest->magic_link_token;
                    $pesan = "Halo *{$driver->name}*,\n\nIni adalah link tugas penjemputan Anda.\n\n📱 *Buka dari Aplikasi:*\n{$appUrl}\n\n🌐 *Buka dari Web:*\n{$magicUrl}\n\nHati-hati di jalan! 🚛💨";
                    $waLink = "https://wa.me/{$phone}?text=".urlencode($pesan);
                }

                $isActive = ! in_array($manifest->status, ['SELESAI', 'CANCELLED']);

                return [
                    'id' => $manifest->id,
                    'status' => $manifest->status,
                    'is_active' => $isActive,
                    'created_at' => $manifest->created_at->format('Y-m-d'),
                    'created_at_label' => $manifest->created_at->format('d/m/Y H:i'),
                    'driver' => [
                        'name' => $driver?->name ?? 'Tidak diketahui',
                        'vehicle_name' => $driver?->vehicle_name ?? '-',
                        'avatar_path' => $driver?->avatar_path,
                    ],
                    'total_resi' => $shipments->count(),
                    'total_koli' => $manifest->kolis->count(),
                    'shipments' => $shipments,
                    'magic_url' => $magicUrl,
                    'wa_link' => $waLink,
                    'token' => $manifest->magic_link_token,
                    'type' => 'PICKUP',
                ];
            });

        return Inertia::render('Penjemputan', [
            'pickupList' => $grouped,
            'drivers' => $drivers,
            'allManifests' => $allManifests,
        ]);
    })->name('pickup');

    Route::post('/pickup/assign', function (Request $request) {
        $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'shipment_ids' => 'required|array',
            'koli_ids' => 'required|array',
        ]);

        $token = Str::random(48);

        $manifest = Manifest::create([
            'manifest_type' => 'PICKUP',
            'driver_id' => $request->driver_id,
            'magic_link_token' => $token,
            'status' => 'PREPARING',
        ]);

        foreach ($request->koli_ids as $koliId) {
            $manifest->kolis()->attach($koliId);
        }

        Shipment::whereIn('id', $request->shipment_ids)
            ->update(['current_status' => 'MENUNGGU_BARANG']); // Asumsikan status pickup

        Koli::whereIn('id', $request->koli_ids)
            ->update(['physical_status' => Koli::STATUS_MENUNGGU_PICKUP]);

        $driver = Driver::find($request->driver_id);
        $waLink = null;
        if ($driver && $driver->phone_number) {
            $phone = preg_replace('/\D/', '', $driver->phone_number);
            if (str_starts_with($phone, '0')) {
                $phone = '62'.substr($phone, 1);
            }
            $magicUrl = url('/supir/pickup?token='.$token);
            $appUrl = 'jeparacargo://supir/pickup?token='.$token;
            $pesan = "Halo *{$driver->name}*,\n\nAda tugas penjemputan barang (pick-up) baru.\n\n📱 *Buka dari Aplikasi:*\n{$appUrl}\n\n🌐 *Buka dari Web:*\n{$magicUrl}\n\nHati-hati di jalan! 🚛💨";
            $waLink = "https://wa.me/{$phone}?text=".urlencode($pesan);
        }

        return back()->with([
            'success' => 'Supir penjemputan berhasil ditugaskan!',
            'magic_url' => $magicUrl,
        ]);
    })->name('pickup.assign');

    Route::post('/drivers', function (Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'vehicle_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:255',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        Driver::create($data);

        return back()->with('success', 'Supir berhasil ditambahkan!');
    })->name('drivers.store');

    Route::get('/manifest', function () {
        // Ambil semua driver
        $drivers = Driver::all();

        // Resi yang sudah di gudang dan siap dimuat ke truk (belum dalam perjalanan)
        $shipments = Shipment::with(['sender', 'kolis'])
            ->whereIn('current_status', ['Diterima', 'Di Gudang', 'MENUNGGU_BARANG'])
            ->orWhereNull('current_status')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($shipment) {
                $cityName = is_array($shipment->destination_city)
                    ? ($shipment->destination_city['name'] ?? '-')
                    : ($shipment->destination_city ?? '-');
                $provName = is_array($shipment->destination_province)
                    ? ($shipment->destination_province['name'] ?? '-')
                    : ($shipment->destination_province ?? '-');
                $districtName = is_array($shipment->destination_district)
                    ? ($shipment->destination_district['name'] ?? '')
                    : ($shipment->destination_district ?? '');

                return [
                    'id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'sender' => $shipment->sender
                        ? ($shipment->sender->business_name ?? $shipment->sender->owner_name)
                        : 'Tidak Diketahui',
                    'recipient_name' => $shipment->recipient_name,
                    'destination' => $cityName.($provName && $provName !== $cityName ? ', '.$provName : ''),
                    'provinsi' => $provName,
                    'kota' => $cityName,
                    'kecamatan' => $districtName,
                    'total_koli' => $shipment->kolis->count(),
                    'kolis' => $shipment->kolis->map(fn ($k) => [
                        'id' => $k->id,
                        'barcode' => $k->barcode_id,
                        'barcode_id' => $k->barcode_id,
                        'koli_number' => $k->koli_number,
                        'total_koli' => $k->total_koli,
                        'description' => $k->description,
                        'physical_status' => $k->physical_status,
                    ])->values(),
                ];
            });

        $manifests = Manifest::with(['driver', 'kolis'])
            ->where('manifest_type', 'DELIVERY')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($m) {
                $waLink = null;
                if ($m->driver && $m->driver->phone_number) {
                    $phone = preg_replace('/\D/', '', $m->driver->phone_number);
                    if (str_starts_with($phone, '0')) {
                        $phone = '62'.substr($phone, 1);
                    }
                    $magicUrl = url('/supir/lintas?token='.$m->magic_link_token);
                    $appUrl = 'jeparacargo://supir/lintas?token='.$m->magic_link_token;
                    $pesan = "Halo *{$m->driver->name}*,\n\nSurat jalan manifest pengiriman sudah siap.\n\n📱 *Buka dari Aplikasi:*\n{$appUrl}\n\n🌐 *Buka dari Web:*\n{$magicUrl}\n\nHati-hati di jalan! 🚛💨";
                    $waLink = "https://wa.me/{$phone}?text=".urlencode($pesan);
                }

                return [
                    'id' => $m->id,
                    'token' => $m->magic_link_token,
                    'type' => $m->manifest_type,
                    'status' => $m->status,
                    'driver' => $m->driver ? $m->driver->name : 'Vendor',
                    'vehicle' => $m->driver ? $m->driver->vehicle_name : ($m->vendor_name ?? '-'),
                    'resi_vendor' => $m->vendor_resi,
                    'total_koli' => $m->kolis->count(),
                    'created_at' => $m->created_at->format('Y-m-d H:i:s'),
                    'wa_link' => $waLink,
                ];
            });

        $warehouses = Warehouse::all();

        return Inertia::render('Manifest', [
            'drivers' => $drivers,
            'shipments' => $shipments,
            'manifests' => $manifests,
            'warehouses' => $warehouses,
        ]);
    })->name('manifest');

    Route::get('/api/manifests', function (Request $request) {
        $offset = $request->query('offset', 0);
        $limit = $request->query('limit', 10);

        $manifests = Manifest::with(['driver', 'kolis'])
            ->where('manifest_type', 'DELIVERY')
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($m) {
                $waLink = null;
                if ($m->driver && $m->driver->phone_number) {
                    $phone = preg_replace('/\D/', '', $m->driver->phone_number);
                    if (str_starts_with($phone, '0')) {
                        $phone = '62'.substr($phone, 1);
                    }
                    $magicUrl = url('/supir/lintas?token='.$m->magic_link_token);
                    $appUrl = 'jeparacargo://supir/lintas?token='.$m->magic_link_token;
                    $pesan = "Halo *{$m->driver->name}*,\n\nSurat jalan manifest pengiriman sudah siap.\n\n📱 *Buka dari Aplikasi:*\n{$appUrl}\n\n🌐 *Buka dari Web:*\n{$magicUrl}\n\nHati-hati di jalan! 🚛💨";
                    $waLink = "https://wa.me/{$phone}?text=".urlencode($pesan);
                }

                return [
                    'id' => $m->id,
                    'token' => $m->magic_link_token,
                    'type' => $m->manifest_type,
                    'status' => $m->status,
                    'driver' => $m->driver ? $m->driver->name : 'Vendor',
                    'vehicle' => $m->driver ? $m->driver->vehicle_name : ($m->vendor_name ?? '-'),
                    'resi_vendor' => $m->vendor_resi,
                    'total_koli' => $m->kolis->count(),
                    'created_at' => $m->created_at->format('Y-m-d H:i:s'),
                    'wa_link' => $waLink,
                ];
            });

        return response()->json(['manifests' => $manifests]);
    })->name('api.manifests');

    Route::get('/manifest/{id}/print', function ($id) {
        $manifest = \App\Models\Manifest::with([
            'driver',
            'expedition',
            'originWarehouse',
            'destinationWarehouse',
            'kolis.shipment.sender',
            'kolis.shipment.items',
        ])->findOrFail($id);

        $shipments = $manifest->kolis
            ->groupBy(fn ($koli) => $koli->shipment_id)
            ->map(function ($kolis) {
                $shipment = $kolis->first()->shipment;
                return [
                    'shipment' => $shipment,
                    'kolis' => $kolis,
                    'total_koli' => $kolis->count(),
                ];
            })->values();

        return view('print-manifest', [
            'manifest' => $manifest,
            'shipments' => $shipments,
        ]);
    })->name('manifest.print');

    Route::post('/manifest', function (Request $request, TrackingService $trackingService) {
        $request->validate([
            'driver_id' => 'nullable|exists:drivers,id',
            'vendor_name' => 'nullable|string|max:255',
            'vendor_resi' => 'nullable|string|max:255',
            'ekspedisi' => 'required|in:Internal,Vendor',
            'rute' => 'required|string',
            'koli_ids' => 'required|array|min:1',
            'koli_ids.*' => 'integer|exists:kolis,id',
            'destination_warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $token = Str::random(48);

        $manifest = Manifest::create([
            'manifest_type' => 'DELIVERY',
            'driver_id' => $request->driver_id,
            'vendor_name' => $request->vendor_name,
            'vendor_resi' => $request->vendor_resi,
            'magic_link_token' => $token,
            'status' => 'PREPARING',
            'origin_warehouse_id' => auth()->user()->warehouse_id,
            'destination_warehouse_id' => $request->destination_warehouse_id,
        ]);

        // Simpan koli yang dimuat
        foreach ($request->koli_ids as $koliId) {
            $manifest->kolis()->attach($koliId);
        }

        Koli::whereIn('id', $request->koli_ids)
            ->update(['physical_status' => Koli::STATUS_DALAM_PERJALANAN]);

        // Update status shipment terkait menjadi 'Dalam Perjalanan' atau 'Dalam Perjalanan ke Gudang'
        $shipmentIds = Koli::whereIn('id', $request->koli_ids)
            ->pluck('shipment_id')
            ->unique();

        $status = $request->destination_warehouse_id ? 'Dalam Perjalanan ke Gudang' : 'Dalam Perjalanan';

        Shipment::whereIn('id', $shipmentIds)
            ->update(['current_status' => $status]);

        foreach ($shipmentIds as $sId) {
            $trackingService->logStatus($sId, $status, "Barang telah dimuat ke armada pengiriman ({$status}).");
        }

        // Kirim WA ke supir jika internal
        $driver = $request->driver_id ? Driver::find($request->driver_id) : null;
        $waLink = null;
        if ($driver && $driver->phone_number) {
            $phone = preg_replace('/\D/', '', $driver->phone_number);
            if (str_starts_with($phone, '0')) {
                $phone = '62'.substr($phone, 1);
            }
            $magicUrl = url('/supir/lintas?token='.$token);
            $appUrl = 'jeparacargo://supir/lintas?token='.$token;
            $pesan = "Halo *{$driver->name}*,\n\nSurat jalan manifest pengiriman *{$request->rute}* sudah siap.\n\n📱 *Buka dari Aplikasi:*\n{$appUrl}\n\n🌐 *Buka dari Web:*\n{$magicUrl}\n\nHati-hati di jalan! 🚛💨";
            $waLink = "https://wa.me/{$phone}?text=".urlencode($pesan);
        }

        return back()->with([
            'success' => 'Manifest berhasil dibuat!',
            'wa_link' => $waLink,
            'manifest' => $manifest->id,
        ]);
    })->name('manifest.store');

    Route::get('/inbound', function () {
        // Tampilkan daftar resi yang masih memiliki koli belum masuk gudang atau sedang aktif inbound
        $shipments = Shipment::with(['sender', 'kolis'])
            ->where(function ($q) {
                // 1. Resi yang masih punya koli yang belum di gudang
                $q->whereHas('kolis', function ($kq) {
                    $kq->where('physical_status', '!=', \App\Models\Koli::STATUS_DI_GUDANG);
                })
                // 2. ATAU resi yang status prosesnya sedang aktif inbound / penjemputan
                ->orWhereIn('current_status', [
                    'Diterima',
                    'MENUNGGU_BARANG',
                    'Sebagian di Gudang',
                    'SEBAGIAN_DI_GUDANG',
                    'DI_ANGKUT',
                    'Dalam Perjalanan',
                    'Minta Dijemput Armada',
                ])
                ->orWhereNull('current_status')
                // 3. ATAU resi yang baru saja lengkap di-inbound dalam 24 jam terakhir
                ->orWhere(function ($sq) {
                    $sq->whereIn('current_status', ['Di Gudang', 'DI_GUDANG'])
                       ->whereDate('updated_at', '>=', now()->subDays(1));
                });
            })
            ->whereNotIn('current_status', ['SELESAI', 'Terkirim', 'Batal'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($shipment) {
                return [
                    'id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'sender' => $shipment->sender
                        ? ($shipment->sender->business_name ?? $shipment->sender->owner_name)
                        : 'Tidak Diketahui',
                    'total_koli' => $shipment->kolis->count(),
                    'current_status' => $shipment->current_status ?? 'Diterima',
                    'kolis' => $shipment->kolis->map(fn ($k) => [
                        'id' => $k->id,
                        'barcode' => $k->barcode_id,
                        'barcode_id' => $k->barcode_id,
                        'koli_number' => $k->koli_number,
                        'total_koli' => $k->total_koli,
                        'description' => $k->description,
                        'physical_status' => $k->physical_status,
                    ])->values(),
                ];
            });

        return Inertia::render('Inbound', [
            'shipments' => $shipments,
        ]);
    })->name('inbound');

    Route::post('/inbound/scan', function (Request $request, TrackingService $trackingService) {
        $request->validate([
            'koli_ids' => 'required|array|min:1',
            'koli_ids.*' => 'integer|exists:kolis,id',
        ]);

        Koli::whereIn('id', $request->koli_ids)
            ->update(['physical_status' => Koli::STATUS_DI_GUDANG]);

        $shipmentIds = Koli::whereIn('id', $request->koli_ids)
            ->pluck('shipment_id')
            ->unique();

        foreach ($shipmentIds as $shipmentId) {
            $shipment = Shipment::with('kolis')->find($shipmentId);
            if ($shipment) {
                $allInWarehouse = $shipment->kolis->every(fn ($k) => in_array($k->physical_status, [Koli::STATUS_DI_GUDANG, Koli::STATUS_DALAM_PERJALANAN, Koli::STATUS_TERKIRIM]));
                $newStatus = $allInWarehouse ? 'Di Gudang' : 'Sebagian di Gudang';
                $shipment->update(['current_status' => $newStatus]);
                $trackingService->logStatus($shipment->id, $newStatus, 'Barang telah tiba dan diverifikasi masuk Gudang Utama.');
            }
        }

        return back()->with('success', 'Barang berhasil di-scan dan masuk gudang!');
    })->name('inbound.scan');
});
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/dokumentasi', function () {
        return Inertia::render('Dokumentasi');
    })->name('dokumentasi');

    Route::get('/settings/company', function () {
        return redirect()->route('settings.index');
    });
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings/company', [SettingController::class, 'updateCompany'])->name('settings.company.update');
    Route::post('/settings/app', [SettingController::class, 'updateApp'])->name('settings.app.update');

    Route::post('/settings/users', [UserController::class, 'store'])->name('settings.users.store');
    Route::put('/settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update');
    Route::delete('/settings/users/{user}', [UserController::class, 'destroy'])->name('settings.users.destroy');

    Route::post('/settings/warehouses', [WarehouseController::class, 'store'])->name('settings.warehouses.store');
    Route::put('/settings/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('settings.warehouses.update');
    Route::delete('/settings/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('settings.warehouses.destroy');

    // Manajemen Ekspedisi (Super Admin)
    Route::resource('expeditions', ExpeditionController::class);

    // Pembaruan Sistem Manual dari GitHub (Super Admin)
    Route::post('/settings/update/check', [\App\Http\Controllers\SystemUpdateController::class, 'check'])->name('settings.update.check');
    Route::post('/settings/update/config', [\App\Http\Controllers\SystemUpdateController::class, 'saveConfig'])->name('settings.update.config');
    Route::post('/settings/update/step', [\App\Http\Controllers\SystemUpdateController::class, 'executeStep'])->name('settings.update.step');
    Route::post('/settings/update/emergency-up', [\App\Http\Controllers\SystemUpdateController::class, 'emergencyUp'])->name('settings.update.emergency-up');

    // Manajemen Cadangan & Pemulihan Sistem (Super Admin)
    Route::get('/settings/backups', [\App\Http\Controllers\BackupController::class, 'index'])->name('settings.backups.index');
    Route::post('/settings/backups/create', [\App\Http\Controllers\BackupController::class, 'create'])->name('settings.backups.create');
    Route::post('/settings/backups/upload', [\App\Http\Controllers\BackupController::class, 'upload'])->name('settings.backups.upload');
    Route::get('/settings/backups/{name}/download', [\App\Http\Controllers\BackupController::class, 'download'])->name('settings.backups.download');
    Route::post('/settings/backups/restore', [\App\Http\Controllers\BackupController::class, 'restore'])->name('settings.backups.restore');
    Route::delete('/settings/backups/{name}', [\App\Http\Controllers\BackupController::class, 'destroy'])->name('settings.backups.destroy');
});

require __DIR__.'/auth.php';

// API Khusus untuk Quick Add Unit
Route::post('/api/units', function (Request $request) {
    $request->validate(['name' => 'required|string']);
    $unit = Unit::firstOrCreate(['name' => ucwords(strtolower(trim($request->name)))]);

    return response()->json(['unit' => $unit->name]);
});

Route::get('/api/wilayah/search', [WilayahController::class, 'search'])->name('api.wilayah.search');
Route::get('/api/wilayah/provinces', [WilayahController::class, 'provinces'])->name('api.wilayah.provinces');
Route::get('/api/wilayah/regencies/{provinceId}', [WilayahController::class, 'regencies'])->name('api.wilayah.regencies');
Route::get('/api/wilayah/districts/{regencyId}', [WilayahController::class, 'districts'])->name('api.wilayah.districts');
Route::get('/api/wilayah/villages/{districtId}', [WilayahController::class, 'villages'])->name('api.wilayah.villages');

// API untuk Pengaturan Tracking GPS
Route::post('/api/settings/tracking-distance', function (Request $request) {
    $request->validate(['distance' => 'required|integer|min:5|max:1000']);
    Cache::put('tracking_distance', $request->distance);

    return response()->json(['success' => true]);
});

Route::get('/api/settings/tracking-distance', function () {
    return response()->json(['distance' => Cache::get('tracking_distance', 10)]);
});
