<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // 1. Ambil pengaturan umum Aplikasi (Owner / Global Platform)
        $appKeys = ['app_name', 'app_tagline', 'app_theme', 'app_contact', 'app_description'];
        $appSettings = Setting::withoutGlobalScope('expedition')
            ->whereNull('expedition_id')
            ->whereIn('key', $appKeys)
            ->pluck('value', 'key')->toArray();

        $appData = [
            'name' => $appSettings['app_name'] ?? config('app.name', 'Jepara Cargo Network'),
            'tagline' => $appSettings['app_tagline'] ?? 'Sistem Ekspedisi & Kargo Terpadu',
            'theme' => $appSettings['app_theme'] ?? '#8B5A2B',
            'contact' => $appSettings['app_contact'] ?? '',
            'description' => $appSettings['app_description'] ?? 'Platform Agregator Logistik & Ekspedisi Terintegrasi',
        ];

        // 2. Ambil pengaturan khusus Ekspedisi (Tenant)
        $expedition = $user && $user->expedition_id ? \App\Models\Expedition::find($user->expedition_id) : null;
        $keys = ['company_name', 'company_contact', 'company_receipt_code', 'company_address', 'company_tagline', 'company_theme'];
        
        $settingsQuery = Setting::whereIn('key', $keys);
        if ($user && $user->expedition_id) {
            $settingsQuery->where('expedition_id', $user->expedition_id);
        } else {
            $settingsQuery->whereNull('expedition_id');
        }
        $settings = $settingsQuery->pluck('value', 'key')->toArray();

        $companyData = [
            'name' => $settings['company_name'] ?? ($expedition?->name ?? 'Jepara Cargo'),
            'contact' => $settings['company_contact'] ?? ($expedition?->phone ?? ''),
            'receipt_code' => $settings['company_receipt_code'] ?? ($expedition?->receipt_code ?? 'GFC'),
            'address' => $settings['company_address'] ?? ($expedition?->address ?? ''),
            'tagline' => $settings['company_tagline'] ?? ($expedition?->tagline ?? 'Sistem Operasional Terpadu'),
            'theme' => $settings['company_theme'] ?? ($expedition?->theme_color ?? '#8B5A2B'),
        ];

        $isSuperAdmin = $user && $user->expedition_id === null;

        $usersQuery = \App\Models\User::with(['warehouse', 'expedition']);
        if (!$isSuperAdmin && $user) {
            $usersQuery->where('expedition_id', $user->expedition_id);
        }
        $users = $usersQuery->latest()->get();

        $warehousesQuery = \App\Models\Warehouse::query();
        if (!$isSuperAdmin && $user) {
            $warehousesQuery->where('expedition_id', $user->expedition_id);
        }
        $warehouses = $warehousesQuery->get();
        $expeditions = \App\Models\Expedition::where('is_active', true)->get();

        // 3. Ambil konfigurasi GitHub Repository & Versi SHA
        $githubSettings = Setting::withoutGlobalScope('expedition')
            ->whereNull('expedition_id')
            ->whereIn('key', ['github_repo', 'github_branch', 'system_current_sha'])
            ->pluck('value', 'key')->toArray();

        $githubData = $isSuperAdmin ? [
            'repo' => $githubSettings['github_repo'] ?? env('GITHUB_REPO', ''),
            'branch' => $githubSettings['github_branch'] ?? env('GITHUB_BRANCH', 'main'),
            'current_sha' => $githubSettings['system_current_sha'] ?? null,
        ] : null;

        // 4. Ambil konfigurasi Integrasi Real-time (Pusher / Reverb)
        $integrationKeys = [
            'broadcast_driver',
            'pusher_app_id', 'pusher_app_key', 'pusher_app_secret', 'pusher_app_cluster', 'pusher_scheme',
            'reverb_app_id', 'reverb_app_key', 'reverb_app_secret', 'reverb_host', 'reverb_port', 'reverb_scheme',
        ];
        $integrationSettings = Setting::withoutGlobalScope('expedition')
            ->whereNull('expedition_id')
            ->whereIn('key', $integrationKeys)
            ->pluck('value', 'key')->toArray();

        $integrationData = $isSuperAdmin ? [
            'driver' => $integrationSettings['broadcast_driver'] ?? env('BROADCAST_CONNECTION', 'pusher'),
            'pusher_app_id' => $integrationSettings['pusher_app_id'] ?? env('PUSHER_APP_ID', ''),
            'pusher_app_key' => $integrationSettings['pusher_app_key'] ?? env('PUSHER_APP_KEY', ''),
            'pusher_app_secret' => $integrationSettings['pusher_app_secret'] ?? env('PUSHER_APP_SECRET', ''),
            'pusher_app_cluster' => $integrationSettings['pusher_app_cluster'] ?? env('PUSHER_APP_CLUSTER', 'ap1'),
            'pusher_scheme' => $integrationSettings['pusher_scheme'] ?? env('PUSHER_SCHEME', 'https'),
            'reverb_app_id' => $integrationSettings['reverb_app_id'] ?? env('REVERB_APP_ID', ''),
            'reverb_app_key' => $integrationSettings['reverb_app_key'] ?? env('REVERB_APP_KEY', ''),
            'reverb_app_secret' => $integrationSettings['reverb_app_secret'] ?? env('REVERB_APP_SECRET', ''),
            'reverb_host' => $integrationSettings['reverb_host'] ?? env('REVERB_HOST', 'localhost'),
            'reverb_port' => $integrationSettings['reverb_port'] ?? env('REVERB_PORT', '8080'),
            'reverb_scheme' => $integrationSettings['reverb_scheme'] ?? env('REVERB_SCHEME', 'https'),
        ] : null;

        $backups = $isSuperAdmin ? app(\App\Services\BackupService::class)->getBackupsList() : [];

        return Inertia::render('Settings/Index', [
            'app' => $appData,
            'company' => $companyData,
            'users' => $users,
            'expeditions' => $expeditions,
            'warehouses' => $warehouses,
            'currentExpedition' => $expedition,
            'github' => $githubData,
            'integration' => $integrationData,
            'backups' => $backups,
        ]);
    }

    /**
     * Simpan pembaruan Data Aplikasi Utama (Platform Owner / Super Admin)
     */
    public function updateApp(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->expedition_id === null, 403, 'Hanya Owner/Super Admin yang dapat mengubah informasi aplikasi.');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'contact' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'theme' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
        ]);

        $settingsToSave = [
            'app_name' => $validated['name'],
            'app_tagline' => $validated['tagline'] ?? '',
            'app_contact' => $validated['contact'] ?? '',
            'app_description' => $validated['description'] ?? '',
            'app_theme' => $validated['theme'],
        ];

        foreach ($settingsToSave as $key => $value) {
            Setting::withoutGlobalScope('expedition')->updateOrCreate(
                [
                    'key' => $key,
                    'expedition_id' => null,
                ],
                ['value' => $value]
            );
        }

        return redirect()->back()->with('success', 'Informasi Aplikasi (Platform Utama) berhasil diperbarui!');
    }

    /**
     * Simpan pembaruan Data Perusahaan
     */
    public function updateCompany(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact' => 'required|string|max:255',
            'receipt_code' => 'required|string|max:10',
            'address' => 'required|string',
            'tagline' => 'nullable|string|max:255',
            'theme' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
        ]);

        $user = auth()->user();
        $expeditionId = $user ? $user->expedition_id : null;

        $settingsToSave = [
            'company_name' => $validated['name'],
            'company_contact' => $validated['contact'],
            'company_receipt_code' => strtoupper($validated['receipt_code']),
            'company_address' => $validated['address'],
            'company_tagline' => $validated['tagline'] ?? '',
            'company_theme' => $validated['theme'],
        ];

        foreach ($settingsToSave as $key => $value) {
            Setting::updateOrCreate(
                [
                    'key' => $key,
                    'expedition_id' => $expeditionId,
                ],
                ['value' => $value]
            );
        }

        // Jika user adalah admin ekspedisi, update juga data master tabel expeditions
        if ($expeditionId) {
            \App\Models\Expedition::where('id', $expeditionId)->update([
                'name' => $validated['name'],
                'phone' => $validated['contact'],
                'receipt_code' => strtoupper($validated['receipt_code']),
                'address' => $validated['address'],
                'tagline' => $validated['tagline'] ?? '',
                'theme_color' => $validated['theme'],
            ]);
        }

        return redirect()->back()->with('success', 'Data Perusahaan berhasil diperbarui!');
    }

    /**
     * Simpan pembaruan Konfigurasi Integrasi Real-time (Pusher / Reverb)
     */
    public function updateIntegration(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->expedition_id === null, 403, 'Hanya Owner/Super Admin yang dapat mengubah konfigurasi integrasi.');

        $validated = $request->validate([
            'driver' => 'required|in:pusher,reverb,log',
            'pusher_app_id' => 'nullable|string',
            'pusher_app_key' => 'nullable|string',
            'pusher_app_secret' => 'nullable|string',
            'pusher_app_cluster' => 'nullable|string',
            'pusher_scheme' => 'nullable|in:https,http',
            'reverb_app_id' => 'nullable|string',
            'reverb_app_key' => 'nullable|string',
            'reverb_app_secret' => 'nullable|string',
            'reverb_host' => 'nullable|string',
            'reverb_port' => 'nullable|string',
            'reverb_scheme' => 'nullable|in:https,http',
        ]);

        $settingsToSave = [
            'broadcast_driver' => $validated['driver'],
            'pusher_app_id' => $validated['pusher_app_id'] ?? '',
            'pusher_app_key' => $validated['pusher_app_key'] ?? '',
            'pusher_app_secret' => $validated['pusher_app_secret'] ?? '',
            'pusher_app_cluster' => $validated['pusher_app_cluster'] ?? 'ap1',
            'pusher_scheme' => $validated['pusher_scheme'] ?? 'https',
            'reverb_app_id' => $validated['reverb_app_id'] ?? '',
            'reverb_app_key' => $validated['reverb_app_key'] ?? '',
            'reverb_app_secret' => $validated['reverb_app_secret'] ?? '',
            'reverb_host' => $validated['reverb_host'] ?? 'localhost',
            'reverb_port' => $validated['reverb_port'] ?? '8080',
            'reverb_scheme' => $validated['reverb_scheme'] ?? 'https',
        ];

        foreach ($settingsToSave as $key => $value) {
            Setting::withoutGlobalScope('expedition')->updateOrCreate(
                [
                    'key' => $key,
                    'expedition_id' => null,
                ],
                ['value' => $value]
            );
        }

        // Sinkronkan juga ke file .env
        $this->updateEnvFile([
            'BROADCAST_CONNECTION' => $validated['driver'],
            'PUSHER_APP_ID' => $validated['pusher_app_id'] ?? '',
            'PUSHER_APP_KEY' => $validated['pusher_app_key'] ?? '',
            'PUSHER_APP_SECRET' => $validated['pusher_app_secret'] ?? '',
            'PUSHER_APP_CLUSTER' => $validated['pusher_app_cluster'] ?? 'ap1',
            'PUSHER_SCHEME' => $validated['pusher_scheme'] ?? 'https',
            'REVERB_APP_ID' => $validated['reverb_app_id'] ?? '',
            'REVERB_APP_KEY' => $validated['reverb_app_key'] ?? '',
            'REVERB_APP_SECRET' => $validated['reverb_app_secret'] ?? '',
            'REVERB_HOST' => $validated['reverb_host'] ?? 'localhost',
            'REVERB_PORT' => $validated['reverb_port'] ?? '8080',
            'REVERB_SCHEME' => $validated['reverb_scheme'] ?? 'https',
        ]);

        return redirect()->back()->with('success', 'Konfigurasi Integrasi Real-time berhasil diperbarui!');
    }

    /**
     * Kirim broadcast uji coba untuk mengetes koneksi WebSocket
     */
    public function testBroadcast(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->expedition_id === null, 403, 'Akses ditolak.');

        try {
            $settings = Setting::withoutGlobalScope('expedition')
                ->whereNull('expedition_id')
                ->whereIn('key', [
                    'broadcast_driver',
                    'pusher_app_id', 'pusher_app_key', 'pusher_app_secret', 'pusher_app_cluster', 'pusher_scheme',
                    'reverb_app_id', 'reverb_app_key', 'reverb_app_secret', 'reverb_host', 'reverb_port', 'reverb_scheme'
                ])
                ->pluck('value', 'key')->toArray();

            $driver = $request->input('driver') ?: ($settings['broadcast_driver'] ?? 'pusher');

            if ($driver === 'pusher') {
                $appId = $request->input('pusher_app_id') ?: ($settings['pusher_app_id'] ?? env('PUSHER_APP_ID', ''));
                $key = $request->input('pusher_app_key') ?: ($settings['pusher_app_key'] ?? env('PUSHER_APP_KEY', ''));
                $secret = $request->input('pusher_app_secret') ?: ($settings['pusher_app_secret'] ?? env('PUSHER_APP_SECRET', ''));
                $cluster = $request->input('pusher_app_cluster') ?: ($settings['pusher_app_cluster'] ?? env('PUSHER_APP_CLUSTER', 'ap1'));
                $scheme = $request->input('pusher_scheme') ?: ($settings['pusher_scheme'] ?? env('PUSHER_SCHEME', 'https'));

                if (empty($appId) || empty($key) || empty($secret)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'App ID, App Key, dan App Secret Pusher wajib diisi sebelum melakukan tes.',
                    ], 422);
                }

                config([
                    'broadcasting.default' => 'pusher',
                    'broadcasting.connections.pusher.driver' => 'pusher',
                    'broadcasting.connections.pusher.key' => $key,
                    'broadcasting.connections.pusher.secret' => $secret,
                    'broadcasting.connections.pusher.app_id' => $appId,
                    'broadcasting.connections.pusher.options.cluster' => $cluster,
                    'broadcasting.connections.pusher.options.host' => 'api-' . ($cluster ?: 'ap1') . '.pusher.com',
                    'broadcasting.connections.pusher.options.scheme' => $scheme,
                    'broadcasting.connections.pusher.options.useTLS' => ($scheme === 'https'),
                ]);
            } elseif ($driver === 'reverb') {
                $appId = $request->input('reverb_app_id') ?: ($settings['reverb_app_id'] ?? env('REVERB_APP_ID', ''));
                $key = $request->input('reverb_app_key') ?: ($settings['reverb_app_key'] ?? env('REVERB_APP_KEY', ''));
                $secret = $request->input('reverb_app_secret') ?: ($settings['reverb_app_secret'] ?? env('REVERB_APP_SECRET', ''));
                $host = $request->input('reverb_host') ?: ($settings['reverb_host'] ?? env('REVERB_HOST', 'localhost'));
                $port = $request->input('reverb_port') ?: ($settings['reverb_port'] ?? env('REVERB_PORT', 8080));
                $scheme = $request->input('reverb_scheme') ?: ($settings['reverb_scheme'] ?? env('REVERB_SCHEME', 'https'));

                config([
                    'broadcasting.default' => 'reverb',
                    'broadcasting.connections.reverb.driver' => 'reverb',
                    'broadcasting.connections.reverb.key' => $key,
                    'broadcasting.connections.reverb.secret' => $secret,
                    'broadcasting.connections.reverb.app_id' => $appId,
                    'broadcasting.connections.reverb.options.host' => $host,
                    'broadcasting.connections.reverb.options.port' => (int) $port,
                    'broadcasting.connections.reverb.options.scheme' => $scheme,
                    'broadcasting.connections.reverb.options.useTLS' => ($scheme === 'https'),
                ]);
            } else {
                return response()->json([
                    'status' => 'warning',
                    'message' => 'Driver saat ini adalah "log". Untuk menguji WebSocket real-time, pilih Pusher atau Reverb.',
                ]);
            }

            // Reset driver cache agar instance baru dibuat dengan kredensial yang baru
            app('Illuminate\Broadcasting\BroadcastManager')->forgetDrivers();

            broadcast(new \App\Events\TestBroadcastEvent("Koneksi {$driver} Berhasil! Waktu: " . now()->format('H:i:s') . ' WIB'));

            return response()->json([
                'status' => 'success',
                'message' => "Event tes berhasil dikirim ke Pusher (Cluster: " . ($cluster ?? '-') . ")! Memeriksa respon balik di browser...",
                'driver' => $driver,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim event broadcast ke Pusher: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper untuk memperbarui nilai di file .env
     */
    protected function updateEnvFile(array $data)
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            return;
        }

        $envContent = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $value = trim($value ?? '');
            if (preg_match('/\s/', $value)) {
                $formattedValue = '"' . addcslashes($value, '"') . '"';
            } else {
                $formattedValue = $value;
            }

            if (preg_match("/^{$key}=.*/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$formattedValue}", $envContent);
            } else {
                $envContent .= "\n{$key}={$formattedValue}";
            }
        }

        @file_put_contents($envPath, $envContent);
    }
}
