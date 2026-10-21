<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Dynamic broadcasting configuration from database settings
        try {
            if (Schema::hasTable('settings')) {
                $broadcastSettings = Setting::withoutGlobalScope('expedition')
                    ->whereNull('expedition_id')
                    ->whereIn('key', [
                        'broadcast_driver',
                        'pusher_app_id', 'pusher_app_key', 'pusher_app_secret', 'pusher_app_cluster', 'pusher_scheme',
                        'reverb_app_id', 'reverb_app_key', 'reverb_app_secret', 'reverb_host', 'reverb_port', 'reverb_scheme'
                    ])
                    ->pluck('value', 'key');

                $driver = $broadcastSettings['broadcast_driver'] ?? env('BROADCAST_CONNECTION', 'log');

                $pusherKey = $broadcastSettings['pusher_app_key'] ?? env('PUSHER_APP_KEY', '');
                $pusherSecret = $broadcastSettings['pusher_app_secret'] ?? env('PUSHER_APP_SECRET', '');
                $pusherAppId = $broadcastSettings['pusher_app_id'] ?? env('PUSHER_APP_ID', '');
                $pusherCluster = $broadcastSettings['pusher_app_cluster'] ?? env('PUSHER_APP_CLUSTER', 'ap1');
                $pusherScheme = $broadcastSettings['pusher_scheme'] ?? env('PUSHER_SCHEME', 'https');

                $reverbKey = $broadcastSettings['reverb_app_key'] ?? env('REVERB_APP_KEY', '');
                $reverbSecret = $broadcastSettings['reverb_app_secret'] ?? env('REVERB_APP_SECRET', '');
                $reverbAppId = $broadcastSettings['reverb_app_id'] ?? env('REVERB_APP_ID', '');
                $reverbHost = $broadcastSettings['reverb_host'] ?? env('REVERB_HOST', 'localhost');
                $reverbPort = $broadcastSettings['reverb_port'] ?? env('REVERB_PORT', '8080');
                $reverbScheme = $broadcastSettings['reverb_scheme'] ?? env('REVERB_SCHEME', 'https');

                config([
                    'broadcasting.connections.pusher.key' => $pusherKey,
                    'broadcasting.connections.pusher.secret' => $pusherSecret,
                    'broadcasting.connections.pusher.app_id' => $pusherAppId,
                    'broadcasting.connections.pusher.options.cluster' => $pusherCluster,
                    'broadcasting.connections.pusher.options.host' => 'api-' . ($pusherCluster ?: 'ap1') . '.pusher.com',
                    'broadcasting.connections.pusher.options.scheme' => $pusherScheme,
                    'broadcasting.connections.pusher.options.useTLS' => ($pusherScheme === 'https'),

                    'broadcasting.connections.reverb.key' => $reverbKey,
                    'broadcasting.connections.reverb.secret' => $reverbSecret,
                    'broadcasting.connections.reverb.app_id' => $reverbAppId,
                    'broadcasting.connections.reverb.options.host' => $reverbHost,
                    'broadcasting.connections.reverb.options.port' => (int) $reverbPort,
                    'broadcasting.connections.reverb.options.scheme' => $reverbScheme,
                    'broadcasting.connections.reverb.options.useTLS' => ($reverbScheme === 'https'),
                ]);

                // Fall back to log if driver keys are missing to prevent runtime errors
                if ($driver === 'pusher' && empty($pusherKey)) {
                    $driver = 'log';
                } elseif ($driver === 'reverb' && empty($reverbKey)) {
                    $driver = 'log';
                }

                config(['broadcasting.default' => $driver]);
            }
        } catch (\Throwable $e) {
            // Silently fall back during migrations / setup
        }
    }
}
