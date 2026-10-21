import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { registerPlugin } from '@capacitor/core';
const BackgroundGeolocation = registerPlugin('BackgroundGeolocation');

export function useLocationTracking(token) {
    let watcherId = null;
    let browserWatchId = null;

    const isTracking = ref(false);
    const hasSynced = ref(false);
    const lastSyncTime = ref(null);
    const trackingError = ref(null);

    const sendLocation = (lat, lng) => {
        if (!token) return;
        console.log('📡 Mengirim lokasi GPS:', lat, lng);
        axios.post('/api/driver/location', {
            token: token,
            lat: lat,
            lng: lng,
        }).then(() => {
            lastSyncTime.value = new Date();
            trackingError.value = null;
            hasSynced.value = true;
        }).catch(e => {
            console.error('Failed to sync location', e);
            trackingError.value = 'Gagal kirim: ' + (e?.response?.status || e?.message || 'unknown');
        });
    };

    const startBrowserGeolocation = () => {
        if (typeof navigator !== 'undefined' && 'geolocation' in navigator) {
            // Ambil posisi awal segera
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    trackingError.value = null;
                    sendLocation(pos.coords.latitude, pos.coords.longitude);
                },
                (err) => {
                    console.warn('Geolocation initial error:', err.message);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );

            // Pantau pergerakan secara real-time di browser / PWA
            browserWatchId = navigator.geolocation.watchPosition(
                (pos) => {
                    trackingError.value = null;
                    sendLocation(pos.coords.latitude, pos.coords.longitude);
                },
                (err) => {
                    console.warn('Geolocation watch error:', err.message);
                    if (err.code === 1) {
                        trackingError.value = 'Izin lokasi GPS belum diaktifkan';
                    }
                },
                { enableHighAccuracy: true, maximumAge: 10000 }
            );
        }
    };

    const startTracking = async () => {
        if (!token) {
            trackingError.value = 'Token tidak ditemukan';
            return;
        }

        trackingError.value = null;
        isTracking.value = true;

        // 1. Coba gunakan BackgroundGeolocation Capacitor (jika berjalan di Native Android/iOS App)
        let nativeStarted = false;
        try {
            let dynamicDistance = 10;
            try {
                const res = await axios.get('/api/settings/tracking-distance');
                if (res.data && res.data.distance) {
                    dynamicDistance = parseInt(res.data.distance);
                }
            } catch (err) {}

            watcherId = await BackgroundGeolocation.addWatcher(
                {
                    backgroundMessage: "Aplikasi sedang melacak lokasi armada Anda.",
                    backgroundTitle: "Melacak Lokasi Supir",
                    requestPermissions: true,
                    stale: false,
                    distanceFilter: dynamicDistance
                },
                function (location, error) {
                    if (error) {
                        if (error.code === "NOT_AUTHORIZED") {
                            trackingError.value = "Izin lokasi ditolak";
                        }
                        return;
                    }

                    if (location) {
                        trackingError.value = null;
                        sendLocation(location.latitude, location.longitude);
                    }
                }
            );
            nativeStarted = true;
        } catch (e) {
            // BackgroundGeolocation native tidak tersedia (misal di web browser / PWA)
            nativeStarted = false;
        }

        // 2. Selalu aktifkan HTML5 Geolocation browser sebagai fallback andalan agar supir yang buka via link WA / Chrome langsung terlacak
        startBrowserGeolocation();
    };

    const stopTracking = () => {
        if (watcherId !== null) {
            try {
                BackgroundGeolocation.removeWatcher({ id: watcherId });
            } catch (e) {}
            watcherId = null;
        }

        if (browserWatchId !== null && typeof navigator !== 'undefined' && navigator.geolocation) {
            navigator.geolocation.clearWatch(browserWatchId);
            browserWatchId = null;
        }

        isTracking.value = false;
        hasSynced.value = false;
    };

    onMounted(() => {
        startTracking();
    });

    onUnmounted(() => {
        stopTracking();
    });

    return {
        startTracking,
        stopTracking,
        isTracking,
        hasSynced,
        lastSyncTime,
        trackingError
    };
}
