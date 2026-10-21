import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echoInstance = null;
const connectionState = ref('disconnected'); // 'disconnected' | 'connecting' | 'connected' | 'unavailable' | 'failed'

/**
 * Inisialisasi atau ambil instance Laravel Echo
 * @param {Object|null} customConfig - Opsional: konfigurasi manual (misal saat tes koneksi di Settings)
 */
export function useEcho(customConfig = null) {
    const page = usePage();

    const initEcho = (overrideConfig = null) => {
        const config = overrideConfig || customConfig || page?.props?.broadcasting;

        if (!config || !config.driver || config.driver === 'log' || !config.key) {
            connectionState.value = 'disconnected';
            return null;
        }

        if (echoInstance) {
            try {
                echoInstance.disconnect();
            } catch (e) {
                console.error('[Echo] Error disconnecting previous instance:', e);
            }
            echoInstance = null;
        }

        try {
            connectionState.value = 'connecting';

            if (config.driver === 'reverb') {
                echoInstance = new Echo({
                    broadcaster: 'reverb',
                    key: config.key,
                    wsHost: config.host || window.location.hostname,
                    wsPort: config.port ? parseInt(config.port) : 8080,
                    wssPort: config.port ? parseInt(config.port) : 443,
                    forceTLS: (config.scheme ?? 'https') === 'https',
                    enabledTransports: ['ws', 'wss'],
                });
            } else {
                // Pusher Channels
                echoInstance = new Echo({
                    broadcaster: 'pusher',
                    key: config.key,
                    cluster: config.cluster || 'ap1',
                    forceTLS: (config.scheme ?? 'https') === 'https',
                });
            }

            // Monitor status koneksi Pusher underlying client
            if (echoInstance.connector && echoInstance.connector.pusher) {
                const pusherConnection = echoInstance.connector.pusher.connection;
                pusherConnection.bind('state_change', (states) => {
                    connectionState.value = states.current;
                });
                pusherConnection.bind('connected', () => {
                    connectionState.value = 'connected';
                });
                pusherConnection.bind('error', (err) => {
                    console.warn('[Echo] Connection error:', err);
                    connectionState.value = 'failed';
                });
                pusherConnection.bind('unavailable', () => {
                    connectionState.value = 'unavailable';
                });
            }

            return echoInstance;
        } catch (err) {
            console.error('[Echo] Gagal menginisialisasi Echo:', err);
            connectionState.value = 'failed';
            return null;
        }
    };

    const getEcho = () => {
        if (!echoInstance) {
            return initEcho();
        }
        return echoInstance;
    };

    const disconnect = () => {
        if (echoInstance) {
            try {
                echoInstance.disconnect();
            } catch (e) {}
            echoInstance = null;
            connectionState.value = 'disconnected';
        }
    };

    return {
        echo: getEcho(),
        getEcho,
        initEcho,
        disconnect,
        connectionState,
    };
}
