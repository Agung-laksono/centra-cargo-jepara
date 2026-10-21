<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts & Icons -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
        
        <!-- PWA Manifest & Meta Tags -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#ffffff">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Galang Furniture Cargo') }}">
        <link rel="apple-touch-icon" href="/icon-192.png">

        @php
            $isPublicPage = request()->is('/') || request()->is('login*') || request()->is('register*') || request()->is('password*');
            $user = auth()->user();

            if (request()->filled('token') && (request()->is('supir*') || request()->is('tracking*'))) {
                $manifest = \App\Models\Manifest::where('magic_link_token', request('token'))->first();
                $themeColor = $manifest?->expedition?->theme_color
                    ?? (\App\Models\Setting::withoutGlobalScope('expedition')->whereNull('expedition_id')->where('key', 'app_theme')->value('value') ?? '#8B5A2B');
            } elseif ($isPublicPage || !$user || !$user->expedition_id) {
                // Halaman Welcome, Login, atau Super Admin: selalu ikut ke aplikasi utama
                $themeColor = \Illuminate\Support\Facades\Schema::hasTable('settings')
                    ? (\App\Models\Setting::withoutGlobalScope('expedition')
                        ->whereNull('expedition_id')
                        ->where('key', 'app_theme')
                        ->value('value')
                        ?? \App\Models\Setting::withoutGlobalScope('expedition')
                            ->whereNull('expedition_id')
                            ->where('key', 'company_theme')
                            ->value('value')
                        ?? '#8B5A2B')
                    : '#8B5A2B';
            } else {
                // Tenant / Ekspedisi memiliki tema warna sendiri
                $expedition = \App\Models\Expedition::find($user->expedition_id);
                $themeColor = $expedition?->theme_color 
                    ?? \App\Models\Setting::withoutGlobalScope('expedition')
                        ->where('expedition_id', $user->expedition_id)
                        ->where('key', 'company_theme')
                        ->value('value') 
                    ?? '#8B5A2B';
            }
            
            $hex = str_replace('#', '', $themeColor);
            if(strlen($hex) == 3) {
                $r = hexdec(substr($hex,0,1).substr($hex,0,1));
                $g = hexdec(substr($hex,1,1).substr($hex,1,1));
                $b = hexdec(substr($hex,2,1).substr($hex,2,1));
            } else {
                $r = hexdec(substr($hex,0,2));
                $g = hexdec(substr($hex,2,2));
                $b = hexdec(substr($hex,4,2));
            }
            
            $lr = min(255, $r + 60);
            $lg = min(255, $g + 60);
            $lb = min(255, $b + 60);
            
            $dr = max(0, $r - 50);
            $dg = max(0, $g - 50);
            $db = max(0, $b - 50);
            
            $primaryRgb = "$r $g $b";
            $primaryLightRgb = "$lr $lg $lb";
            $primaryDarkRgb = "$dr $dg $db";
        @endphp
        <style>
            :root {
                --color-primary: {{ $primaryRgb }};
                --color-primary-light: {{ $primaryLightRgb }};
                --color-primary-dark: {{ $primaryDarkRgb }};
            }
        </style>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        
        <!-- Floating Offline Status -->
        <div id="offline-badge" class="fixed top-4 md:top-6 left-1/2 transform -translate-x-1/2 z-[10000] transition-all duration-500 ease-out opacity-0 -translate-y-full scale-90 flex items-center gap-2 bg-red-500/90 backdrop-blur-sm text-white px-5 py-2.5 rounded-full shadow-lg shadow-red-500/30 border border-red-400 font-bold text-xs md:text-sm pointer-events-none whitespace-nowrap">
            <i class="ri-wifi-off-line text-lg animate-pulse"></i> Anda Sedang Offline
        </div>
        <script>
            // Cek preferensi Toggle Offline Cache (PWA)
            const isOfflineCacheActive = localStorage.getItem('pwa_offline_cache_enabled') !== 'false';

            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    if (isOfflineCacheActive) {
                        navigator.serviceWorker.register('/sw.js')
                            .then((reg) => console.log('Service Worker PWA aktif (Offline-First)', reg))
                            .catch((err) => console.log('Gagal mendaftar Service Worker', err));
                    } else {
                        // Jika dinonaktifkan: unregister service worker & bersihkan cache seketika
                        navigator.serviceWorker.getRegistrations().then((registrations) => {
                            for (let registration of registrations) {
                                registration.unregister();
                            }
                        });
                        if ('caches' in window) {
                            caches.keys().then((keys) => {
                                keys.forEach((key) => caches.delete(key));
                            });
                        }
                        console.log('Mode Live Network aktif: Service Worker & Cache PWA dinonaktifkan.');
                    }
                });
            }
            
            // Global Offline Floating Status & Active Ping
            const offlineBadge = document.getElementById('offline-badge');
            let isReallyOnline = navigator.onLine;
            let pingInterval;

            const updateBadgeUI = () => {
                if (!isReallyOnline) {
                    offlineBadge.classList.remove('opacity-0', '-translate-y-full', 'scale-90');
                    offlineBadge.classList.add('opacity-100', 'translate-y-0', 'scale-100');
                } else {
                    offlineBadge.classList.remove('opacity-100', 'translate-y-0', 'scale-100');
                    offlineBadge.classList.add('opacity-0', '-translate-y-full', 'scale-90');
                }
            };

            // "DNS/HTTP Ping" ke endpoint bawaan Laravel (/up) untuk memastikan internet benar-benar ada
            const checkRealConnectivity = async () => {
                if (!navigator.onLine) {
                    if (isReallyOnline) {
                        isReallyOnline = false;
                        updateBadgeUI();
                    }
                    return;
                }
                
                try {
                    // Tambahkan timestamp agar tidak kena cache browser
                    const res = await fetch('/up?ts=' + new Date().getTime(), { 
                        method: 'HEAD', 
                        cache: 'no-store' 
                    });
                    
                    const isConnected = res.ok;
                    if (isReallyOnline !== isConnected) {
                        isReallyOnline = isConnected;
                        updateBadgeUI();
                        if (isConnected) window.dispatchEvent(new Event('real-online'));
                    }
                } catch (err) {
                    // Fetch gagal total = jaringan putus/gangguan ISP
                    if (isReallyOnline) {
                        isReallyOnline = false;
                        updateBadgeUI();
                    }
                }
            };

            // Cek saat ada event dari browser
            window.addEventListener('online', checkRealConnectivity);
            window.addEventListener('offline', () => {
                isReallyOnline = false;
                updateBadgeUI();
            });

            // Cek rutin setiap 15 detik untuk mengatasi "Lie-Fi" (WiFi nyala tapi tidak ada internet)
            pingInterval = setInterval(checkRealConnectivity, 15000);
            
            // Cek awal saat buka aplikasi
            checkRealConnectivity();
        </script>
    </body>
</html>
