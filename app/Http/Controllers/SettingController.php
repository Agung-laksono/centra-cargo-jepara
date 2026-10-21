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

        $githubData = [
            'repo' => $githubSettings['github_repo'] ?? env('GITHUB_REPO', ''),
            'branch' => $githubSettings['github_branch'] ?? env('GITHUB_BRANCH', 'main'),
            'current_sha' => $githubSettings['system_current_sha'] ?? null,
        ];

        return Inertia::render('Settings/Index', [
            'app' => $appData,
            'company' => $companyData,
            'users' => $users,
            'expeditions' => $expeditions,
            'warehouses' => $warehouses,
            'currentExpedition' => $expedition,
            'github' => $githubData,
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
}
