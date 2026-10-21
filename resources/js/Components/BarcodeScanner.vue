<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import { BarcodeScanner as NativeBarcodeScanner, BarcodeFormat } from '@capacitor-mlkit/barcode-scanning';
import { Capacitor } from '@capacitor/core';
import { stopAllActiveCameraStreams } from '@/Composables/useCameraManager';

const props = defineProps({
    showClose: {
        type: Boolean,
        default: true
    },
    initialMode: {
        type: String,
        default: 'koli' // 'koli' (1D barcode only) | 'resi' (QR code only) | 'all' (all formats)
    },
    allowModeSwitch: {
        type: Boolean,
        default: true
    },
    cleanView: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['scan', 'error', 'close']);

const CAMERA_CACHE_KEY = 'jepara_preferred_camera_id';

const getSavedCameraId = () => {
    try {
        if (typeof window !== 'undefined' && window.localStorage) {
            return localStorage.getItem(CAMERA_CACHE_KEY) || '';
        }
    } catch (e) {}
    return '';
};

const saveCameraId = (id) => {
    try {
        if (typeof window !== 'undefined' && window.localStorage) {
            if (id) {
                localStorage.setItem(CAMERA_CACHE_KEY, id);
            } else {
                localStorage.removeItem(CAMERA_CACHE_KEY);
            }
        }
    } catch (e) {
        console.warn('Gagal menyimpan cache kamera:', e);
    }
};

const isNative = ref(Capacitor.isNativePlatform());
const isCameraLoading = ref(true);
const availableCameras = ref([]);
const selectedCameraId = ref(getSavedCameraId());
const scanError = ref('');
const scanMode = ref(props.initialMode || 'koli'); // 'koli' | 'resi' | 'all'
let html5QrCode = null;
let lastScannedText = '';
let isAlive = true;
let webScannerTimer = null;

// Helper untuk mematikan SEMUA hardware camera stream browser secara tuntas
const forceStopHardwareCameraTracks = () => {
    stopAllActiveCameraStreams();
};

const getNativeFormats = () => {
    if (scanMode.value === 'koli') {
        return [
            BarcodeFormat.Code128,
            BarcodeFormat.Code39,
            BarcodeFormat.Ean13,
            BarcodeFormat.Ean8,
            BarcodeFormat.UpcA,
            BarcodeFormat.UpcE
        ];
    } else if (scanMode.value === 'resi') {
        return [BarcodeFormat.QrCode];
    }
    return [
        BarcodeFormat.Code128,
        BarcodeFormat.Code39,
        BarcodeFormat.QrCode,
        BarcodeFormat.Ean13,
        BarcodeFormat.Ean8,
        BarcodeFormat.UpcA,
        BarcodeFormat.UpcE
    ];
};

const getWebFormats = () => {
    if (scanMode.value === 'koli') {
        // KHUSUS KOLI: HANYA barcode garis 1D! KELUARKAN QR Code agar tidak salah sambar ke QR nomor resi pada label
        return [
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E,
            Html5QrcodeSupportedFormats.CODE_93,
            Html5QrcodeSupportedFormats.ITF
        ];
    } else if (scanMode.value === 'resi') {
        return [Html5QrcodeSupportedFormats.QR_CODE];
    }
    return [
        Html5QrcodeSupportedFormats.CODE_128,
        Html5QrcodeSupportedFormats.CODE_39,
        Html5QrcodeSupportedFormats.QR_CODE,
        Html5QrcodeSupportedFormats.EAN_13,
        Html5QrcodeSupportedFormats.EAN_8,
        Html5QrcodeSupportedFormats.UPC_A,
        Html5QrcodeSupportedFormats.UPC_E
    ];
};

const switchMode = (mode) => {
    if (scanMode.value === mode) return;
    scanMode.value = mode;
    startHtml5QrCode();
};

// ==========================================
// 1. NATIVE ANDROID / IOS (Google ML Kit Single Shot if needed)
// ==========================================
const startNativeScanner = async () => {
    isCameraLoading.value = true;
    scanError.value = '';

    try {
        const { camera } = await NativeBarcodeScanner.requestPermissions();
        if (camera !== 'granted' && camera !== 'limited') {
            scanError.value = 'Izin kamera ditolak. Harap izinkan akses kamera di pengaturan HP.';
            isCameraLoading.value = false;
            return;
        }

        try {
            await NativeBarcodeScanner.installGoogleBarcodeScannerModule();
        } catch (e) {}

        isCameraLoading.value = false;
        
        // Hanya scan format yang ditargetkan agar tidak salah sasaran ke kode lain di resi
        const targetFormats = getNativeFormats();
        const { barcodes } = await NativeBarcodeScanner.scan({
            formats: targetFormats,
        });

        if (barcodes && barcodes.length > 0) {
            const code = barcodes[0].displayValue || barcodes[0].rawValue;
            if (code) {
                emit('scan', code);
                if (navigator.vibrate) navigator.vibrate(100);
            }
        }
        emit('close');
    } catch (err) {
        const errStr = (err?.message || '').toLowerCase();
        if (errStr.includes('canceled') || errStr.includes('cancelled') || errStr.includes('cancel')) {
            emit('close');
            return;
        }
        console.warn('Fallback to HTML5 web scanner...', err);
        startWebScanner();
    }
};

// Algoritma pendeteksi Kamera Belakang Utama (Main 1x Camera)
const findMainBackCamera = (devices) => {
    if (!devices || devices.length === 0) return null;

    // Filter kamera belakang (singkirkan front/user/selfie/depan)
    const backCameras = devices.filter(d => {
        const label = (d.label || '').toLowerCase();
        return !label.includes('front') && 
               !label.includes('user') && 
               !label.includes('selfie') && 
               !label.includes('depan');
    });

    if (backCameras.length === 0) return devices[0];
    if (backCameras.length === 1) return backCameras[0];

    // Scoring untuk mencari kamera utama (Main 1x Wide)
    const scored = backCameras.map(d => {
        const label = (d.label || '').toLowerCase();
        let score = 10;

        // Penalti keras untuk lensa yang BUKAN kamera utama (ultra-wide, macro, depth, telephoto)
        if (label.includes('ultra') || label.includes('wide-angle') || label.includes('ultrawide') || label.includes('0.5x') || label.includes('0.6x')) {
            score -= 50;
        }
        if (label.includes('macro') || label.includes('makro')) {
            score -= 60;
        }
        if (label.includes('depth') || label.includes('tof') || label.includes('tele') || label.includes('telephoto') || label.includes('periscope')) {
            score -= 40;
        }

        // Skor positif untuk kamera utama
        if (label.includes('main') || label.includes('utama') || label.includes('primary')) {
            score += 40;
        }
        // Di Android Camera2 API, '0, facing back' atau 'camera 0' atau 'back 0' adalah sensor utama 1x
        if (label.includes('0, facing back') || label.includes('camera 0') || label.includes('back 0') || label.includes('camera2 0') || label.includes('id 0')) {
            score += 30;
        }
        if (label.includes('wide') && !label.includes('ultra')) {
            score += 15;
        }
        if (label.includes('1x') || label.includes('standard')) {
            score += 25;
        }

        return { device: d, score };
    });

    scored.sort((a, b) => b.score - a.score);
    return scored[0].device;
};

const formatCameraLabel = (label, idx) => {
    if (!label) return `Lensa Kamera ${idx + 1}`;
    const lower = label.toLowerCase();
    if (lower.includes('front') || lower.includes('user') || lower.includes('depan')) {
        return `Kamera Depan (${label})`;
    }
    if (lower.includes('ultra') || lower.includes('0.5x') || lower.includes('0.6x')) {
        return `Lensa Ultra-Wide (${label})`;
    }
    if (lower.includes('macro') || lower.includes('makro')) {
        return `Lensa Macro (${label})`;
    }
    if (lower.includes('0, facing back') || lower.includes('main') || lower.includes('primary') || lower.includes('camera 0') || lower.includes('back 0')) {
        return `★ Kamera Utama 1x (${label})`;
    }
    return `Kamera Belakang (${label})`;
};

// ==========================================
// 2. WEB BROWSER / PWA / ANDROID WEBVIEW (HTML5 QrCode Inline Box)
// ==========================================
const startWebScanner = async () => {
    isCameraLoading.value = true;
    scanError.value = '';
    
    try {
        const devices = await Html5Qrcode.getCameras();
        if (devices && devices.length) {
            availableCameras.value = devices;
            const savedId = getSavedCameraId();
            if (savedId) {
                const isValid = savedId === 'user' || devices.some(d => d.id === savedId);
                if (isValid) {
                    selectedCameraId.value = savedId;
                } else {
                    saveCameraId('');
                    selectedCameraId.value = '';
                }
            }
            
            // Jika belum ada kamera tersimpan, otomatis pilih KAMERA UTAMA (1x Back Camera)
            if (!selectedCameraId.value) {
                const mainCam = findMainBackCamera(devices);
                if (mainCam && mainCam.id) {
                    selectedCameraId.value = mainCam.id;
                    console.log("📷 [BarcodeScanner] Auto-selected Main Camera:", mainCam.label || mainCam.id);
                }
            }
        }
    } catch (err) {
        console.error("Error getting cameras", err);
    }
    
    clearTimeout(webScannerTimer);
    webScannerTimer = setTimeout(() => {
        if (isAlive) {
            startHtml5QrCode();
        }
    }, 150);
};

const stopHtml5Camera = async () => {
    if (html5QrCode) {
        try {
            if (html5QrCode.isScanning) {
                await html5QrCode.stop();
            }
        } catch (e) {
            console.warn('Html5Qrcode stop error:', e);
        }
        try {
            if (html5QrCode._localMediaStream && typeof html5QrCode._localMediaStream.getTracks === 'function') {
                html5QrCode._localMediaStream.getTracks().forEach(t => {
                    t.stop();
                    t.enabled = false;
                });
            }
            if (html5QrCode.localMediaStream && typeof html5QrCode.localMediaStream.getTracks === 'function') {
                html5QrCode.localMediaStream.getTracks().forEach(t => {
                    t.stop();
                    t.enabled = false;
                });
            }
            html5QrCode.clear();
        } catch (e) {}
        html5QrCode = null;
    }
    // Pastikan hardware stream mati total (lampu kamera/indikator hijau mati)
    stopAllActiveCameraStreams();
};

const startHtml5QrCode = async () => {
    if (!isAlive) return;
    isCameraLoading.value = true;
    await stopHtml5Camera();
    if (!isAlive) return;
    initiateWebScan();
};

const initiateWebScan = () => {
    if (!isAlive) return;
    html5QrCode = new Html5Qrcode("barcode-scanner-reader", {
        formatsToSupport: getWebFormats(),
        verbose: false
    });
    
    let cameraConfig;
    if (selectedCameraId.value === 'user') {
        cameraConfig = { facingMode: "user" };
    } else if (selectedCameraId.value) {
        cameraConfig = selectedCameraId.value;
    } else {
        cameraConfig = { facingMode: "environment" };
    }
        
    const qrbox = (viewfinderWidth, viewfinderHeight) => {
        if (scanMode.value === 'koli') {
            const width = Math.min(Math.floor(viewfinderWidth * 0.90), 340);
            const height = Math.min(Math.floor(viewfinderHeight * 0.50), 120);
            return { width, height };
        }
        const edgeSize = Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.65);
        return { width: edgeSize, height: edgeSize };
    };

    html5QrCode.start(
        cameraConfig,
        {
            fps: 15,
            qrbox: qrbox
        },
        (decodedText) => {
            console.log("📷 SCANNED BARCODE:", decodedText);
            if (lastScannedText !== decodedText) {
                lastScannedText = decodedText;
                emit('scan', decodedText);
                if (navigator.vibrate) navigator.vibrate(100);
                
                setTimeout(() => {
                    lastScannedText = '';
                }, 1500);
            }
        },
        () => {}
    ).then(async () => {
        // Jika scanner sudah ditutup saat stream baru saja siap, matikan seketika!
        if (!isAlive) {
            console.log("🛑 Scanner sudah ditutup saat stream baru aktif, matikan stream...");
            await stopHtml5Camera();
            stopAllActiveCameraStreams();
            return;
        }

        isCameraLoading.value = false;
        if (availableCameras.value.length <= 1) {
            try {
                const refreshed = await Html5Qrcode.getCameras();
                if (refreshed && refreshed.length > 1) {
                    availableCameras.value = refreshed;
                }
            } catch (e) {}
        }
    }).catch((err) => {
        if (!isAlive) {
            stopAllActiveCameraStreams();
            return;
        }
        console.error("Camera error", err);
        // Fallback cerdas: Jika kamera kustom/tersimpan gagal dibuka, coba kembalikan ke kamera default
        if (selectedCameraId.value) {
            console.warn("Kamera tersimpan gagal diakses, reset ke kamera belakang default...", err);
            saveCameraId('');
            selectedCameraId.value = '';
            return;
        }

        isCameraLoading.value = false;
        scanError.value = "Kamera tidak dapat diakses. Pastikan Anda memberi izin kamera di HP/browser.";
        emit('error', scanError.value);
    });
};

const startScanner = () => {
    startWebScanner();
};

const handleClose = async () => {
    isAlive = false;
    clearTimeout(webScannerTimer);
    await stopHtml5Camera();
    stopAllActiveCameraStreams();
    emit('close');
};

watch(selectedCameraId, (newId, oldId) => {
    if (newId !== oldId && isAlive) {
        saveCameraId(newId);
        startHtml5QrCode();
    }
});

onMounted(async () => {
    isAlive = true;
    // Jika di Capacitor Android APK, pastikan izin kamera hardware sudah aktif
    if (isNative.value) {
        try {
            await NativeBarcodeScanner.requestPermissions();
        } catch (e) {
            console.warn('Native permission check:', e);
        }
    }
    // Langsung buka video kamera di dalam kotak!
    startWebScanner();
});

onUnmounted(async () => {
    isAlive = false;
    clearTimeout(webScannerTimer);
    await stopHtml5Camera();
    stopAllActiveCameraStreams();
});
</script>

<template>
    <div class="relative w-full h-full bg-black flex flex-col overflow-hidden">
        <!-- Close Button (Pojok Kanan Atas) -->
        <button 
            v-if="showClose" 
            @click="handleClose" 
            type="button" 
            class="absolute top-2 right-2.5 z-50 bg-black/75 hover:bg-black text-white w-8 h-8 rounded-full flex items-center justify-center transition-all backdrop-blur-md border border-white/20 shadow-lg active:scale-90 pointer-events-auto cursor-pointer"
            title="Tutup & Matikan Kamera"
        >
            <i class="ri-close-line text-lg leading-none"></i>
        </button>

        <!-- Camera Selector Button (Pojok Kiri Atas - HANYA ICON KAMERA SAJA) -->
        <div 
            v-if="availableCameras.length > 0" 
            class="absolute top-2 left-2.5 z-50 pointer-events-auto"
            title="Ganti Lensa Kamera"
        >
            <div class="relative w-8 h-8 rounded-full bg-black/75 hover:bg-black text-white flex items-center justify-center transition-all backdrop-blur-md border border-white/20 shadow-lg active:scale-90 cursor-pointer overflow-hidden">
                <i class="ri-camera-switch-line text-sm leading-none pointer-events-none"></i>
                <!-- Native select transparan tak terlihat agar saat disentuh langsung membuka picker kamera HP -->
                <select 
                    v-model="selectedCameraId" 
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer text-base"
                    title="Pilih Kamera"
                >
                    <option value="" class="bg-gray-900 text-white">★ Kamera Utama (Belakang)</option>
                    <option value="user" class="bg-gray-900 text-white">Kamera Depan</option>
                    <option v-for="(camera, idx) in availableCameras" :key="camera.id" :value="camera.id" class="bg-gray-900 text-white">
                        {{ formatCameraLabel(camera.label, idx) }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Target Mode Selector (Hanya jika BUKAN cleanView DAN allowModeSwitch diizinkan) -->
        <div v-if="!cleanView && allowModeSwitch" class="absolute top-2 left-12 z-40 flex items-center gap-1 pointer-events-none">
            <div class="pointer-events-auto flex items-center bg-black/75 backdrop-blur-md p-0.5 rounded-xl border border-white/20 shadow-lg text-[10px] font-bold">
                <button 
                    type="button" 
                    @click="switchMode('koli')"
                    :class="[
                        'px-2.5 py-1 rounded-lg transition-all flex items-center gap-1',
                        scanMode === 'koli' ? 'bg-primary text-white shadow font-extrabold' : 'text-gray-300 hover:text-white'
                    ]"
                >
                    <i class="ri-barcode-line"></i>
                    <span>Barcode Koli (1D)</span>
                </button>
                <button 
                    type="button" 
                    @click="switchMode('resi')"
                    :class="[
                        'px-2.5 py-1 rounded-lg transition-all flex items-center gap-1',
                        scanMode === 'resi' ? 'bg-primary text-white shadow font-extrabold' : 'text-gray-300 hover:text-white'
                    ]"
                >
                    <i class="ri-qr-code-line"></i>
                    <span>QR Resi</span>
                </button>
                <button 
                    type="button" 
                    @click="switchMode('all')"
                    :class="[
                        'px-2 py-1 rounded-lg transition-all flex items-center gap-1',
                        scanMode === 'all' ? 'bg-primary text-white shadow font-extrabold' : 'text-gray-400 hover:text-white'
                    ]"
                >
                    <span>Semua</span>
                </button>
            </div>
        </div>

        <!-- Loading Indicator (Skeleton Loader) -->
        <div v-if="isCameraLoading" class="absolute inset-0 z-10 flex items-center justify-center bg-gray-900 overflow-hidden">
            <div class="absolute inset-0 bg-gray-800 animate-pulse"></div>
            <div class="relative w-64 h-24 sm:w-80 sm:h-28 border-2 border-dashed border-gray-600 rounded-2xl flex flex-col items-center justify-center animate-pulse z-20">
                <i class="ri-barcode-line text-3xl text-gray-600 mb-1"></i>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                    Menghubungkan Kamera...
                </p>
            </div>
        </div>

        <!-- Error Message -->
        <div v-if="scanError" class="absolute inset-0 z-40 flex flex-col items-center justify-center bg-black/90 p-4 text-center">
            <i class="ri-error-warning-fill text-red-500 text-3xl mb-2"></i>
            <p class="text-white text-xs mb-3 max-w-xs font-medium leading-relaxed">{{ scanError }}</p>
            <div class="flex items-center gap-2">
                <button @click="startScanner" class="bg-primary text-white px-4 py-1.5 rounded-full font-bold text-xs shadow-lg active:scale-95 transition-all cursor-pointer">Coba Lagi</button>
                <button @click="handleClose" class="bg-white/20 text-white px-4 py-1.5 rounded-full font-bold text-xs active:scale-95 transition-all cursor-pointer">Tutup</button>
            </div>
        </div>

        <!-- Scanner Div (Berlaku untuk Web & APK Android - Live Video Kamera di dalam Kotak) -->
        <div class="w-full h-full flex-1 bg-black relative flex items-center justify-center overflow-hidden">
            <div id="barcode-scanner-reader" class="w-full h-full absolute inset-0 overflow-hidden flex items-center justify-center"></div>
            
            <!-- Viewfinder Overlay & Kotak Bidik Presisi -->
            <div v-if="!isCameraLoading && !scanError" class="absolute inset-0 z-20 pointer-events-none flex flex-col items-center justify-center px-3 pt-5 pb-2">
                
                <!-- Kotak Bidik / Bingkai Pemindai (Tepat di Area Fokus Sensor) -->
                <div class="relative w-[90%] max-w-[340px] h-[95px] sm:h-[110px] flex items-center justify-center">
                    <!-- 4 Sudut Bingkai Bidik (Corner Brackets) -->
                    <div class="absolute -top-1 -left-1 w-5 h-5 border-t-[3px] border-l-[3px] border-primary rounded-tl-md shadow-sm"></div>
                    <div class="absolute -top-1 -right-1 w-5 h-5 border-t-[3px] border-r-[3px] border-primary rounded-tr-md shadow-sm"></div>
                    <div class="absolute -bottom-1 -left-1 w-5 h-5 border-b-[3px] border-l-[3px] border-primary rounded-bl-md shadow-sm"></div>
                    <div class="absolute -bottom-1 -right-1 w-5 h-5 border-b-[3px] border-r-[3px] border-primary rounded-br-md shadow-sm"></div>
                    
                    <!-- Bingkai halus & latar redup lembut untuk kontras barcode -->
                    <div class="w-full h-full border border-white/25 rounded-md bg-white/[0.04]"></div>

                    <!-- Garis Laser Horizontal yang Menyapu HANYA di Dalam Kotak Bidik -->
                    <div class="absolute left-2 right-2 h-[2px] bg-primary shadow-[0_0_10px_rgba(234,88,12,0.95)] animate-scan-line pointer-events-none"></div>

                    <!-- Label Sasaran Scan jika bukan cleanView -->
                    <div v-if="!cleanView" class="absolute -top-6 left-1/2 -translate-x-1/2 px-2.5 py-0.5 bg-black/70 backdrop-blur rounded-full text-white/90 text-[10px] font-semibold flex items-center gap-1 shadow whitespace-nowrap">
                        <span class="w-1.5 h-1.5 rounded-full bg-primary animate-ping"></span>
                        <span>{{ scanMode === 'koli' ? 'Arahkan Barcode Koli (1D) ke Kotak' : scanMode === 'resi' ? 'Arahkan QR Code ke Kotak' : 'Arahkan Barcode ke Kotak' }}</span>
                    </div>
                </div>

                <!-- Slot untuk Area Input Manual TEPAT DI BAWAH Kotak Bidik (DI DALAM Camera Preview) -->
                <div class="pointer-events-auto w-full max-w-[340px] mx-auto mt-2 z-30 flex justify-center shrink-0">
                    <slot name="below-frame"></slot>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
#barcode-scanner-reader {
    width: 100% !important;
    height: 100% !important;
    position: relative !important;
    overflow: hidden !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: none !important;
}
#barcode-scanner-reader video {
    position: absolute !important;
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -50%) !important;
    min-width: 100% !important;
    min-height: 100% !important;
    width: auto !important;
    height: auto !important;
    max-width: none !important;
    max-height: none !important;
    display: block !important;
}
#qr-shaded-region {
    border-color: rgba(0,0,0,0.6) !important;
    display: none !important;
}

@keyframes scanLine {
    0% { top: 4px; opacity: 0; }
    15% { opacity: 1; }
    85% { opacity: 1; }
    100% { top: calc(100% - 4px); opacity: 0; }
}
.animate-scan-line {
    animation: scanLine 1.8s ease-in-out infinite;
}
</style>
