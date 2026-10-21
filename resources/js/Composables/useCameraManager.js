/**
 * Camera Stream Manager
 * Memastikan semua MediaStream hardware kamera dihentikan secara total
 * sehingga indikator privasi / titik hijau di HP Android & iOS langsung mati
 * saat scanner ditutup.
 */
import { BarcodeScanner as NativeBarcodeScanner } from '@capacitor-mlkit/barcode-scanning';

// Global registry untuk mencatat semua MediaStream yang pernah dibuka
if (typeof window !== 'undefined' && window.navigator && window.navigator.mediaDevices) {
    if (!window.__activeCameraStreams) {
        window.__activeCameraStreams = new Set();
        
        try {
            const originalGUM = window.navigator.mediaDevices.getUserMedia.bind(window.navigator.mediaDevices);
            window.navigator.mediaDevices.getUserMedia = async function (constraints) {
                const stream = await originalGUM(constraints);
                if (stream) {
                    window.__activeCameraStreams.add(stream);
                    
                    // Bersihkan jika track berakhir dengan sendirinya
                    stream.getTracks().forEach(track => {
                        track.addEventListener('ended', () => {
                            const hasActive = stream.getTracks().some(t => t.readyState === 'live');
                            if (!hasActive) {
                                window.__activeCameraStreams.delete(stream);
                            }
                        });
                    });
                }
                return stream;
            };
        } catch (e) {
            console.warn('Gagal memasang interceptor getUserMedia:', e);
        }
    }
}

/**
 * Matikan SEMUA hardware camera stream browser & native secara tuntas
 */
export function stopAllActiveCameraStreams() {
    console.log('🛑 [CameraManager] Mematikan seluruh stream kamera aktif...');

    // 1. Matikan semua stream dari global registry
    if (typeof window !== 'undefined' && window.__activeCameraStreams) {
        window.__activeCameraStreams.forEach(stream => {
            try {
                if (stream && stream.getTracks) {
                    stream.getTracks().forEach(track => {
                        try {
                            track.stop();
                            track.enabled = false;
                            console.log(`🛑 [CameraManager] Track '${track.label || track.kind}' dimatikan.`);
                        } catch (err) {}
                    });
                }
            } catch (err) {}
        });
        window.__activeCameraStreams.clear();
    }

    // 2. Matikan dari semua elemen <video> di DOM (jika ada yang terlewat)
    if (typeof document !== 'undefined') {
        try {
            const videos = document.querySelectorAll('video');
            videos.forEach(video => {
                try {
                    if (video.srcObject && typeof video.srcObject.getTracks === 'function') {
                        video.srcObject.getTracks().forEach(track => {
                            try {
                                track.stop();
                                track.enabled = false;
                            } catch (e) {}
                        });
                        video.srcObject = null;
                    }
                    video.pause();
                    video.removeAttribute('src');
                    video.load();
                } catch (e) {}
            });
        } catch (e) {}
    }

    // 3. Matikan scanner bawaan Capacitor jika berjalan di APK Android
    try {
        NativeBarcodeScanner.stopScan().catch(() => {});
    } catch (e) {}
}
