<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // 1. Pengaturan Utama Platform / Aplikasi (Global / Owner)
        $appName = config('app.name', 'Jepara Cargo Network');
        $appTagline = 'Sistem Ekspedisi & Kargo Terpadu';
        $appTheme = '#8B5A2B';
        $appContact = '';

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $appSettings = \App\Models\Setting::withoutGlobalScope('expedition')
                    ->whereNull('expedition_id')
                    ->whereIn('key', ['app_name', 'app_tagline', 'app_theme', 'app_contact', 'company_name', 'company_tagline', 'company_theme', 'company_contact'])
                    ->pluck('value', 'key')->toArray();

                $appName = $appSettings['app_name'] ?? ($appSettings['company_name'] ?? $appName);
                $appTagline = $appSettings['app_tagline'] ?? ($appSettings['company_tagline'] ?? $appTagline);
                $appTheme = $appSettings['app_theme'] ?? ($appSettings['company_theme'] ?? $appTheme);
                $appContact = $appSettings['app_contact'] ?? ($appSettings['company_contact'] ?? '');
            }
        } catch (\Exception $e) {}

        // 2. Pengaturan Ekspedisi / Perusahaan (Tenant)
        $companyName = $appName;
        $companyContact = $appContact;
        $companyTagline = $appTagline;
        $companyTheme = $appTheme;

        try {
            $user = $request->user();
            if ($user && $user->expedition_id) {
                $expedition = \App\Models\Expedition::find($user->expedition_id);
                if ($expedition) {
                    $companyName = $expedition->name;
                    $companyContact = $expedition->phone ?? '';
                    $companyTagline = $expedition->tagline ?? 'Sistem Operasional Terpadu';
                    $companyTheme = $expedition->theme_color ?? '#8B5A2B';
                }
            } elseif ($request->filled('token') && ($request->is('supir*') || $request->is('tracking*'))) {
                $manifest = \App\Models\Manifest::with('expedition')->where('magic_link_token', $request->token)->first();
                if ($manifest && $manifest->expedition) {
                    $companyName = $manifest->expedition->name;
                    $companyContact = $manifest->expedition->phone ?? '';
                    $companyTagline = $manifest->expedition->tagline ?? 'Sistem Operasional Terpadu';
                    $companyTheme = $manifest->expedition->theme_color ?? '#8B5A2B';
                }
            }
        } catch (\Exception $e) {}

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? $request->user()->load('expedition') : null,
                'is_super_admin' => $request->user() ? ($request->user()->expedition_id === null) : false,
            ],
            'app' => [
                'name' => $appName,
                'tagline' => $appTagline,
                'theme' => $appTheme,
                'contact' => $appContact,
            ],
            'company' => [
                'name' => $companyName,
                'contact' => $companyContact,
                'tagline' => $companyTagline,
                'theme' => $companyTheme,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'print_url' => fn () => $request->session()->get('print_url'),
                'tracking_number' => fn () => $request->session()->get('tracking_number'),
                'wa_link' => fn () => $request->session()->get('wa_link'),
                'magic_url' => fn () => $request->session()->get('magic_url'),
            ],
        ];
    }
}
