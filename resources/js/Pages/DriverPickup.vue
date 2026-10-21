<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import BarcodeScanner from '@/Components/BarcodeScanner.vue';
import localforage from 'localforage';
import axios from 'axios';
import { useLocationTracking } from '@/Composables/useLocationTracking';
import { useBiometricAuth } from '@/Composables/useBiometricAuth';
import { Fullscreen } from '@boengli/capacitor-fullscreen';
import { StatusBar } from '@capacitor/status-bar';
import DriverProfileModal from '@/Components/DriverProfileModal.vue';
import { BarcodeScanner as NativeBarcodeScanner, BarcodeFormat } from '@capacitor-mlkit/barcode-scanning';
import { Capacitor } from '@capacitor/core';

const props = defineProps({
    manifest: Object,
    pickups: Array,
});

const isProfileModalOpen = ref(false);

// Audio Beep Effect via Web Audio API
const playBeep = (type = 'success') => {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);

        if (type === 'success') {
            osc.frequency.setValueAtTime(880, audioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1320, audioCtx.currentTime + 0.12);
            gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.15);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.15);
        } else if (type === 'duplicate') {
            osc.frequency.setValueAtTime(440, audioCtx.currentTime);
            osc.frequency.setValueAtTime(330, audioCtx.currentTime + 0.1);
            gain.gain.setValueAtTime(0.25, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.25);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.25);
        } else {
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(220, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.3);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.3);
        }
    } catch (e) {
        // Fallback jika audio dicegah browser
    }
};

// Identifikasi status Standby (belum ada penugasan pickup aktif atau kosong)
const isStandby = computed(() => {
    return props.manifest?.status === 'STANDBY' || !props.manifest?.id || (!props.pickups || props.pickups.length === 0);
});

const initialPickups = computed(() => {
    if (props.pickups && props.pickups.length > 0) return props.pickups;
    return [];
});

const titikJemput = ref([...initialPickups.value]);
const DB_STORE_KEY = 'pickup_deliveries';
const DB_QUEUE_KEY = 'pickup_sync_queue';

// Fitur Handshake Manifest & Supir
const isManifestScannerOpen = ref(false);
const showManualManifestInput = ref(false);
const manualManifestCode = ref('');
const isClaimingManifest = ref(false);
const claimManifestError = ref('');
const claimSuccessMessage = ref('');

const openManifestScanner = async () => {
    claimManifestError.value = '';

    // Khusus Android / iOS Native Capacitor: Gunakan QR Scanner Native langsung tanpa buka web modal
    if (Capacitor.isNativePlatform()) {
        try {
            const perm = await NativeBarcodeScanner.checkPermissions();
            if (perm.camera !== 'granted') {
                const req = await NativeBarcodeScanner.requestPermissions();
                if (req.camera !== 'granted' && req.camera !== 'limited') {
                    alert('Izin kamera ditolak. Harap izinkan akses kamera di pengaturan HP.');
                    return;
                }
            }

            try {
                const { available } = await NativeBarcodeScanner.isGoogleBarcodeScannerModuleAvailable();
                if (!available) {
                    await NativeBarcodeScanner.installGoogleBarcodeScannerModule();
                }
            } catch (e) {}

            const { barcodes } = await NativeBarcodeScanner.scan({
                formats: [BarcodeFormat.QrCode],
            });

            if (barcodes && barcodes.length > 0) {
                const scannedCode = barcodes[0].displayValue || barcodes[0].rawValue;
                if (scannedCode) {
                    onManifestQrScanned(scannedCode);
                }
            }
            return;
        } catch (err) {
            const errMsg = (err?.message || '').toLowerCase();
            if (errMsg.includes('cancel') || errMsg.includes('canceled') || errMsg.includes('cancelled') || errMsg.includes('dismissed') || errMsg.includes('abort')) {
                return;
            }
            console.warn('Native QR scanner error, fallback ke web scanner:', err);
        }
    }

    // Fallback jika dibuka di browser web desktop
    isManifestScannerOpen.value = true;
};

const handleClaimManifest = async (scannedCode) => {
    const code = (scannedCode || manualManifestCode.value).trim();
    if (!code) return;

    try {
        isClaimingManifest.value = true;
        claimManifestError.value = '';

        const res = await axios.post('/api/driver/claim-manifest', {
            token: code,
        });

        if (res.data.status === 'success') {
            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
            claimSuccessMessage.value = res.data.message || 'Surat Tugas berhasil diambil!';
            isManifestScannerOpen.value = false;
            showManualManifestInput.value = false;
            manualManifestCode.value = '';
            
            setTimeout(() => {
                if (res.data.redirect_url) {
                    window.location.href = res.data.redirect_url;
                } else {
                    window.location.reload();
                }
            }, 800);
        } else {
            claimManifestError.value = res.data.message || 'Gagal mengambil surat tugas.';
            if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
        }
    } catch (err) {
        claimManifestError.value = err.response?.data?.message || err.message || 'Kode Surat Tugas tidak valid atau tidak cocok.';
        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
    } finally {
        isClaimingManifest.value = false;
    }
};

const onManifestQrScanned = (scannedText) => {
    isManifestScannerOpen.value = false;
    handleClaimManifest(scannedText);
};

const initializeOfflineDB = async () => {
    if (isStandby.value) {
        titikJemput.value = [];
        return;
    }

    const localData = await localforage.getItem(DB_STORE_KEY);
    if (localData && Array.isArray(localData) && localData.length > 0) {
        titikJemput.value = localData;
    } else {
        titikJemput.value = props.pickups && props.pickups.length > 0 ? props.pickups : [];
        await localforage.setItem(DB_STORE_KEY, titikJemput.value);
    }
    syncToServer();
};

let savePickupDebounce = null;
watch(titikJemput, () => {
    clearTimeout(savePickupDebounce);
    savePickupDebounce = setTimeout(() => {
        localforage.setItem(DB_STORE_KEY, titikJemput.value);
    }, 1000);
}, { deep: true });

const queueSyncJob = async (action, data) => {
    let queue = await localforage.getItem(DB_QUEUE_KEY) || [];
    queue.push({ action, data, timestamp: new Date().getTime() });
    await localforage.setItem(DB_QUEUE_KEY, queue);
    syncToServer();
};

const syncToServer = async () => {
    if (!navigator.onLine) return;
    
    let queue = await localforage.getItem(DB_QUEUE_KEY) || [];
    if (queue.length === 0) return;
    
    let failedQueue = [];
    for (let job of queue) {
        try {
            await axios.post('/api/pickup/sync', job);
        } catch (error) {
            failedQueue.push(job);
        }
    }
    await localforage.setItem(DB_QUEUE_KEY, failedQueue);
};

const activeManifest = computed(() => {
    if (props.manifest) return props.manifest;
    return {
        id: null,
        driver_name: page.props.auth?.user?.name || 'Supir Pickup',
        token: null,
        status: 'STANDBY'
    };
});

// Tracking Lokasi Supir tetap berjalan di background jika ada token
const { isTracking, hasSynced, lastSyncTime, trackingError } = useLocationTracking(activeManifest.value?.token);

const isSticky = ref(false);

const handleScroll = () => {
    isSticky.value = window.scrollY > 100;
};

const page = usePage();
const {
    isSupported: isBiometricSupported,
    isRegistering: isBiometricRegistering,
    registeredUser: biometricRegisteredUser,
    checkSupport: checkBiometricSupport,
    loadRegisteredDevice: loadBiometricDevice,
    registerDevice: registerBiometricDevice,
} = useBiometricAuth();

const showBiometricPrompt = ref(false);
const biometricSuccessToast = ref(null);

const handleActivateBiometric = async () => {
    try {
        const user = page.props.auth?.user;
        if (!user) {
            alert('Sesi supir belum terhubung.');
            return;
        }
        await registerBiometricDevice(user);
        showBiometricPrompt.value = false;
        biometricSuccessToast.value = 'Kunci layar HP berhasil dihubungkan! Tugas berikutnya bisa dibuka instan.';
        setTimeout(() => {
            biometricSuccessToast.value = null;
        }, 4500);
    } catch (e) {
        if (e.message && !e.message.includes('dibatalkan')) {
            alert(e.message || 'Gagal mengaktifkan kunci layar.');
        }
    }
};

const dismissBiometricPrompt = () => {
    showBiometricPrompt.value = false;
    sessionStorage.setItem('driver_biometric_dismissed', 'true');
};

onMounted(() => {
    window.addEventListener('online', syncToServer);
    window.addEventListener('scroll', handleScroll, { passive: true });
    initializeOfflineDB();

    // Periksa dukungan kunci layar HP / Biometrik & tampilkan tawaran jika belum aktif
    checkBiometricSupport().then(async (supported) => {
        if (supported) {
            const registered = await loadBiometricDevice();
            const isDismissed = sessionStorage.getItem('driver_biometric_dismissed') === 'true';
            if (!registered && !isDismissed && page.props.auth?.user) {
                setTimeout(() => {
                    showBiometricPrompt.value = true;
                }, 1800);
            }
        }
    });

    if (typeof window !== 'undefined' && window.Capacitor?.isNativePlatform?.()) {
        StatusBar.setOverlaysWebView({ overlay: false }).catch(() => {});
        StatusBar.show().catch(() => {});
    } else if (typeof window !== 'undefined' && !window.Capacitor?.isNativePlatform?.()) {
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        if (token && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
            window.location.href = `jeparacargo://${window.location.pathname.substring(1)}?token=${token}`;
        }
    }
});

onUnmounted(() => {
    window.removeEventListener('online', syncToServer);
    window.removeEventListener('scroll', handleScroll);
    
    if (typeof window !== 'undefined' && window.Capacitor?.isNativePlatform?.()) {
        StatusBar.show().catch(() => {});
    }
});

// Status & Ringkasan Titik
const selesaiList = computed(() => {
    return titikJemput.value.filter(t => t.shipments.every(s => s.status === 'Diterima' || s.status === 'Selesai'));
});
const antreanList = computed(() => {
    return titikJemput.value.filter(t => !t.shipments.every(s => s.status === 'Diterima' || s.status === 'Selesai'));
});

const totalTitikSemua = computed(() => titikJemput.value.length);
const totalKoliSemua = computed(() => titikJemput.value.reduce((acc, t) => acc + (t.total_koli || 0), 0));
const totalKoliSelesai = computed(() => selesaiList.value.reduce((acc, t) => acc + (t.total_koli || 0), 0));

// Modal Scanner State
const isModalOpen = ref(false);
const selectedTitik = ref(null);
const isCameraOpen = ref(false);
const barcodeInput = ref('');
const scannedBarcodes = ref([]);
const isSubmitting = ref(false);
const scanMessage = ref(null);
const showForceConfirm = ref(false);
const forceReason = ref('');

const expectedBarcodes = computed(() => selectedTitik.value?.koli_barcodes ?? []);
const totalKoli = computed(() => selectedTitik.value?.total_koli ?? 0);
const scanCount = computed(() => scannedBarcodes.value.length);
const progressPct = computed(() => totalKoli.value > 0 ? Math.round((scanCount.value / totalKoli.value) * 100) : 0);
const allScanned = computed(() => totalKoli.value > 0 && scanCount.value >= totalKoli.value);

watch(allScanned, (done) => {
    if (done) {
        if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
        setTimeout(() => selesaikanJemput(true), 1200); 
    }
});

const bukaModal = (titik) => {
    selectedTitik.value = titik;
    isModalOpen.value = true;
    isCameraOpen.value = false; // Scanner kamera tidak langsung aktif, supir bisa membuka kamera saat sudah siap
    barcodeInput.value = '';
    scannedBarcodes.value = [];
    scanMessage.value = null;
    showForceConfirm.value = false;
    forceReason.value = '';
};

const tutupModal = () => {
    isModalOpen.value = false;
    isCameraOpen.value = false;
    scanMessage.value = null;
    showForceConfirm.value = false;
    forceReason.value = '';
};

const toggleManualKoli = (bc) => {
    if (scannedBarcodes.value.includes(bc)) {
        scannedBarcodes.value = scannedBarcodes.value.filter(k => k !== bc);
        scanMessage.value = { type: 'duplicate', text: `Dibatalkan: ${bc}` };
    } else {
        scannedBarcodes.value.push(bc);
        playBeep('success');
        if (navigator.vibrate) navigator.vibrate(60);
        scanMessage.value = { type: 'ok', text: `Dicatat: ${bc}` };
    }
};

const prosesBarcode = (kode) => {
    const val = (kode || barcodeInput.value).trim();
    if (!val) return;
    barcodeInput.value = '';

    // 1. Cek apakah barcode koli ini sudah di-scan
    if (scannedBarcodes.value.includes(val)) {
        playBeep('duplicate');
        scanMessage.value = { type: 'duplicate', text: `⚠️ Barcode koli "${val}" sudah dicatat.` };
        return;
    }

    // 2. Cek apakah kode cocok dengan salah satu barcode koli yang diharapkan
    if (expectedBarcodes.value.includes(val)) {
        scannedBarcodes.value.push(val);
        playBeep('success');
        scanMessage.value = { type: 'ok', text: `✅ Berhasil discan: ${val}` };
        if (navigator.vibrate) navigator.vibrate(60);
        return;
    }

    // 2b. Cerdas: Cek apakah kode cocok dengan akhiran 3 huruf barcode koli (e.g. "KW9")
    const matchingSuffixBarcode = expectedBarcodes.value.find(bc => {
        const parts = bc.split('-');
        const suffix = parts[parts.length - 1];
        return suffix && suffix.toUpperCase() === val.toUpperCase();
    });

    if (matchingSuffixBarcode) {
        if (scannedBarcodes.value.includes(matchingSuffixBarcode)) {
            playBeep('duplicate');
            scanMessage.value = { type: 'duplicate', text: `⚠️ Barcode koli "${matchingSuffixBarcode}" sudah dicatat.` };
            return;
        }
        scannedBarcodes.value.push(matchingSuffixBarcode);
        playBeep('success');
        scanMessage.value = { type: 'ok', text: `✅ Berhasil discan (${val}): ${matchingSuffixBarcode}` };
        if (navigator.vibrate) navigator.vibrate(60);
        return;
    }

    // 3. Jika kode yang ter-scan adalah NOMOR RESI (Bukan Koli)
    const matchingShipment = selectedTitik.value?.shipments?.find(s => 
        s.resi.toUpperCase() === val.toUpperCase() ||
        val === s.resi.toUpperCase()
    );

    if (matchingShipment) {
        playBeep('error');
        scanMessage.value = { type: 'error', text: `⚠️ Ini Nomor Resi (${matchingShipment.resi})! Wajib scan Barcode Garis Koli fisik per paket.` };
        if (navigator.vibrate) navigator.vibrate([150, 80, 150]);
        return;
    }

    // 4. Jika bukan koli dan bukan resi pengirim ini
    playBeep('error');
    scanMessage.value = { type: 'error', text: `❌ Kode "${val}" tidak cocok dengan koli maupun resi pengirim ini.` };
    if (navigator.vibrate) navigator.vibrate([150, 80, 150]);
};

const handleScan = (decodedText) => {
    prosesBarcode(decodedText);
};

const getWaLink = (titik) => {
    if (!titik.telepon) return null;
    let cleanPhone = titik.telepon.replace(/\D/g, '');
    if (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.substring(1);
    const text = encodeURIComponent(`Halo ${titik.nama_pengirim}, saya supir ekspedisi yang bertugas menjemput barang mebel hari ini. Apakah barang sudah siap dimuat?`);
    return `https://wa.me/${cleanPhone}?text=${text}`;
};

const getMapsLink = (titik) => {
    if (titik.maps_url && titik.maps_url.startsWith('http')) return titik.maps_url;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(titik.alamat + ', Jepara')}`;
};

const selesaikanJemput = (auto = false, force = false) => {
    if (!selectedTitik.value || isSubmitting.value) return;
    
    if (!auto && !force && !allScanned.value) {
        showForceConfirm.value = true;
        return;
    }

    isSubmitting.value = true;
    showForceConfirm.value = false;

    if (!props.manifest) {
        setTimeout(() => {
            selectedTitik.value.shipments.forEach(s => s.status = 'Diterima');
            tutupModal();
            isSubmitting.value = false;
        }, 800);
        return;
    }

    selectedTitik.value.shipments.forEach(s => s.status = 'Diterima');

    queueSyncJob('selesaikan_pickup', {
        token: activeManifest.value.token,
        sender_id: selectedTitik.value.id,
        scanned_barcodes: scannedBarcodes.value,
        force: force || (!auto && !allScanned.value),
        force_reason: forceReason.value || null,
    }).then(() => {
        tutupModal();
        isSubmitting.value = false;
    });
};
</script>

<template>
    <Head title="Tugas Penjemputan Supir" />

    <!-- Latar Belakang Status Bar Android / iOS (Top Notch) Sesuai Warna Theme -->
    <div class="fixed top-0 left-0 right-0 h-[35px] transition-colors duration-300 z-[100]" :class="isSticky ? 'bg-primary' : 'bg-transparent'"></div>

    <div class="bg-[#f8f9fc] min-h-[100dvh] font-sans antialiased text-gray-800 pb-[95px] max-w-md mx-auto shadow-2xl relative selection:bg-primary/20">
        
        <!-- Header Khas Supir Pickup (Distinctive dari Supir Lintas) -->
        <header class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white px-4 pb-6 rounded-b-[1.75rem] shadow-xl relative z-10 overflow-hidden border-b border-primary/20" style="padding-top: calc(35px + 1.25rem);">
            <!-- Glow Accent Theme -->
            <div class="absolute -top-12 -right-10 w-44 h-44 bg-primary/25 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-8 -left-8 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>

            <!-- Top Row: Navigation, Title & Badge -->
            <div class="flex justify-between items-center mb-3 relative z-10">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center bg-primary/20 text-primary border border-primary/40 shadow-inner">
                        <i class="ri-box-3-fill text-lg"></i>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest block">{{ activeManifest.expedition_name || $page.props.company?.name || 'Aplikasi Cargo' }}</span>
                        <h1 class="font-extrabold text-sm text-white tracking-wide">Pickup Armada</h1>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Badge Status Pickup -->
                    <div class="flex items-center gap-1.5 bg-primary/20 text-primary px-2.5 py-1 rounded-full font-black text-[10px] tracking-wider uppercase border border-primary/30 backdrop-blur-md">
                        <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                        <span>{{ isStandby ? 'Siap Muat' : 'Jemput Toko' }}</span>
                    </div>

                    <!-- Tombol Scan Handshake Surat Tugas -->
                    <button 
                        type="button" 
                        @click="openManifestScanner"
                        class="flex items-center gap-1 bg-white/10 hover:bg-white/20 px-2.5 py-1.5 rounded-xl border border-white/20 text-white text-xs font-bold active:scale-95 transition-all shadow-sm"
                        title="Ambil / Ganti Surat Tugas (Scan QR)"
                    >
                        <i class="ri-qr-scan-2-line text-primary"></i>
                        <span class="hidden sm:inline">Ambil Tugas</span>
                    </button>

                    <!-- Single Tombol Profil & Logout Supir -->
                    <button 
                        type="button" 
                        @click="isProfileModalOpen = true"
                        class="flex items-center gap-1.5 bg-white/10 hover:bg-white/20 px-2.5 py-1.5 rounded-xl border border-white/20 text-white text-xs font-bold active:scale-95 transition-all shadow-sm"
                        title="Profil Supir & Kunci Layar HP"
                    >
                        <i class="ri-user-star-fill text-primary"></i>
                        <span class="max-w-[100px] truncate">{{ activeManifest.driver_name }}</span>
                        <i v-if="biometricRegisteredUser" class="ri-fingerprint-line text-emerald-400 text-xs ml-0.5" title="Kunci Layar HP Aktif"></i>
                    </button>
                </div>
            </div>
            
            <!-- Bottom Row: Progres Penjemputan Toko (Opsi 2) -->
            <div class="relative z-10 mt-2.5 flex flex-col items-start">
                <div class="flex items-center gap-1.5 text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-0.5">
                    <i class="ri-store-2-line text-primary"></i>
                    <span>Tugas Penjemputan Hari Ini</span>
                    <span v-if="activeManifest.id" class="text-gray-500 font-mono text-[9px] ml-1">#{{ activeManifest.id }}</span>
                </div>
                
                <h2 class="text-xl sm:text-2xl font-black tracking-tight text-white">
                    <template v-if="isStandby || totalTitikSemua === 0">
                        Standby (Siap Muat Toko)
                    </template>
                    <template v-else-if="antreanList.length === 0">
                        Semua Toko Selesai Dimuat! 🎉
                    </template>
                    <template v-else-if="selesaiList.length === 0">
                        {{ totalTitikSemua }} Toko Siap Dijemput
                    </template>
                    <template v-else>
                        Sisa {{ antreanList.length }} dari {{ totalTitikSemua }} Toko Jemput
                    </template>
                </h2>

                <div v-if="!isStandby && totalTitikSemua > 0" class="flex flex-wrap items-center gap-1.5 mt-2">
                    <div class="flex items-center gap-1.5 text-xs font-bold bg-white/10 px-2.5 py-1 rounded-lg backdrop-blur-sm border border-white/15 text-gray-200">
                        <i class="ri-checkbox-circle-fill text-emerald-400 text-xs"></i>
                        <span>{{ selesaiList.length }}/{{ totalTitikSemua }} Toko Selesai</span>
                    </div>

                    <div class="flex items-center gap-1.5 text-xs font-bold bg-primary/20 text-primary px-2.5 py-1 rounded-lg border border-primary/30 backdrop-blur-sm">
                        <i class="ri-box-3-fill text-xs"></i>
                        <span>{{ totalKoliSemua }} Koli Muatan</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- TAMPILAN 1: STATUS STANDBY / SIAP JEMPUT (Jika belum ada surat tugas aktif) -->
        <div v-if="isStandby || titikJemput.length === 0" class="px-4 py-8">
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 max-w-sm mx-auto text-center">
                <!-- Icon Animasi Scan -->
                <div class="relative w-24 h-24 mx-auto mb-4 flex items-center justify-center">
                    <div class="absolute inset-0 rounded-full bg-primary/10 animate-ping opacity-30"></div>
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary/15 to-primary/5 border-2 border-dashed border-primary/40 flex items-center justify-center text-primary shadow-inner">
                        <i class="ri-qr-scan-2-line text-4xl"></i>
                    </div>
                </div>

                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-extrabold uppercase mb-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Armada Siap Jemput Toko
                </div>

                <h3 class="text-lg font-black text-gray-900 leading-snug">
                    Belum Ada Tugas Penjemputan
                </h3>
                
                <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">
                    Surat Tugas Pickup belum ditugaskan. Ambil surat penjemputan dari gudang ekspedisi dengan scan QR.
                </p>

                <!-- Pesan Error / Sukses Handshake jika ada -->
                <div v-if="claimManifestError" class="mt-4 p-3 rounded-xl bg-red-50 text-red-700 border border-red-200 text-xs font-semibold flex items-center gap-2 text-left">
                    <i class="ri-error-warning-fill text-lg shrink-0 text-red-500"></i>
                    <span>{{ claimManifestError }}</span>
                </div>

                <div v-if="claimSuccessMessage" class="mt-4 p-3 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold flex items-center gap-2 text-left">
                    <i class="ri-checkbox-circle-fill text-lg shrink-0 text-emerald-500"></i>
                    <span>{{ claimSuccessMessage }}</span>
                </div>

                <!-- Action CTA Buttons -->
                <div class="mt-6 space-y-2.5">
                    <button
                        type="button"
                        @click="openManifestScanner"
                        :disabled="isClaimingManifest"
                        class="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-r from-primary to-primaryDark hover:opacity-95 text-white font-extrabold text-sm flex items-center justify-center gap-2.5 shadow-lg shadow-primary/25 active:scale-[0.98] transition-all disabled:opacity-50"
                    >
                        <i v-if="isClaimingManifest" class="ri-loader-4-line animate-spin text-lg"></i>
                        <i v-else class="ri-qr-scan-2-line text-lg"></i>
                        <span>{{ isClaimingManifest ? 'Memverifikasi...' : 'Scan QR Surat Tugas (Handshake)' }}</span>
                    </button>

                    <button
                        type="button"
                        @click="showManualManifestInput = true"
                        class="w-full py-2.5 px-4 rounded-xl bg-gray-50 hover:bg-gray-100 text-gray-700 font-bold text-xs flex items-center justify-center gap-2 border border-gray-200 active:scale-[0.98] transition-all"
                    >
                        <i class="ri-keyboard-line text-sm text-gray-400"></i>
                        <span>Ketik Kode / ID Penjemputan</span>
                    </button>
                </div>

                <!-- Info Kemitraan Ekspedisi -->
                <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between text-left">
                    <div>
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Multi-Ekspedisi</p>
                        <p class="text-xs font-extrabold text-gray-800">Bisa Ambil dari Semua Mitra</p>
                    </div>
                    <button 
                        type="button"
                        @click="isProfileModalOpen = true"
                        class="text-xs font-bold text-primary hover:underline flex items-center gap-0.5"
                    >
                        <span>Cek Profil</span>
                        <i class="ri-arrow-right-s-line"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- TAMPILAN 2: DAFTAR TITIK PENJEMPUTAN AKTIF -->
        <template v-else>
            <!-- Progress Summary Sticky (Persis Gaya Supir Lintas) -->
            <div class="relative z-40 transition-all duration-300 sticky top-[35px]" :class="isSticky ? 'px-0 pt-0' : 'px-4 -mt-4'">
                <div class="bg-white/95 shadow-lg flex justify-between items-center border border-gray-100/50 backdrop-blur-xl transition-all duration-300 text-gray-800" :class="isSticky ? 'rounded-none py-2 px-3 shadow-sm' : 'rounded-xl py-3 px-3'">
                    <div class="flex-1 border-r border-gray-100 transition-all duration-300" :class="isSticky ? 'flex justify-center items-center gap-1.5' : 'text-center'">
                        <p class="font-bold text-gray-400 uppercase tracking-widest transition-all duration-300 text-[9px] mb-0.5">Titik</p>
                        <p class="font-black text-gray-800 transition-all duration-300" :class="isSticky ? 'text-sm' : 'text-lg'">{{ totalTitikSemua }}</p>
                    </div>
                    <div class="flex-1 border-r border-gray-100 transition-all duration-300" :class="isSticky ? 'flex justify-center items-center gap-1.5' : 'text-center'">
                        <p class="font-bold text-gray-400 uppercase tracking-widest transition-all duration-300 text-[9px] mb-0.5">Koli</p>
                        <p class="font-black text-gray-800 transition-all duration-300" :class="isSticky ? 'text-sm' : 'text-lg'">{{ totalKoliSemua }}</p>
                    </div>
                    <div class="flex-1 transition-all duration-300" :class="isSticky ? 'flex justify-center items-center gap-1.5' : 'text-center'">
                        <p class="font-bold text-gray-400 uppercase tracking-widest transition-all duration-300 text-[9px] mb-0.5">Selesai</p>
                        <p class="font-black text-green-500 transition-all duration-300" :class="isSticky ? 'text-sm' : 'text-lg'">{{ selesaiList.length }} <span class="text-gray-300 transition-all duration-300 text-xs">/ {{ totalTitikSemua }}</span></p>
                    </div>
                </div>
            </div>

        <!-- MAIN CONTENT / LIST TITIK JEMPUT -->
        <main class="px-3 pt-3 pb-4 mt-1 space-y-3">
            
            <!-- Quick Helper / Tips -->
            <div class="bg-white rounded-xl p-3 border border-gray-100 shadow-sm flex items-start gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">
                    <i class="ri-lightbulb-flash-line text-sm"></i>
                </div>
                <div class="text-xs leading-relaxed text-gray-600">
                    <span class="font-bold text-gray-800">Panduan Supir:</span> Hubungi pengirim via WA sebelum tiba di lokasi. Scan barcode tiap koli saat menaikkan ke mobil.
                </div>
            </div>

            <!-- SECTION: ANTREAN JEMPUT -->
            <div class="space-y-2.5">
                <div class="flex items-center justify-between text-xs font-bold text-gray-500 px-1">
                    <span class="uppercase tracking-wider">Antrean Jemput ({{ antreanList.length }})</span>
                    <span class="text-[11px] text-primary">Prioritas Hari Ini</span>
                </div>

                <div 
                    v-for="(titik, index) in antreanList" 
                    :key="'a'+titik.id"
                    class="bg-white rounded-xl shadow-sm hover:shadow-md border border-gray-100 overflow-hidden transition-all duration-300 relative group"
                >
                    <!-- Border Aksen Kiri Sesuai Gaya Supir Lintas -->
                    <div class="absolute left-0 top-0 w-1 h-full bg-primary"></div>

                    <!-- Header Card: Urutan & Pengirim -->
                    <div class="border-b border-gray-50 p-3 flex justify-between items-start">
                        <div>
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="w-4 h-4 rounded flex items-center justify-center text-[9px] font-black bg-primary/10 text-primary shadow-sm">
                                    {{ index + 1 }}
                                </span>
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                    Titik Penjemputan
                                </span>
                            </div>
                            <h2 class="text-sm font-extrabold text-gray-800 leading-snug">
                                {{ titik.nama_pengirim }}
                            </h2>
                        </div>
                        <span class="text-xs font-extrabold text-primary bg-primary/10 px-2 py-0.5 rounded-lg border border-primary/20">
                            {{ titik.total_koli }} Koli
                        </span>
                    </div>

                    <!-- Alamat Pengirim -->
                    <div class="px-3 pt-2.5">
                        <div class="flex items-start gap-2 text-xs text-gray-600 bg-gray-50/80 p-2.5 rounded-lg border border-gray-100">
                            <i class="ri-map-pin-2-fill text-primary text-sm shrink-0 mt-0.5"></i>
                            <span class="leading-relaxed">{{ titik.alamat }}</span>
                        </div>
                    </div>

                    <!-- Detail Resi & Barang -->
                    <div class="p-3 space-y-1.5">
                        <div 
                            v-for="(shipment, sIdx) in titik.shipments" 
                            :key="sIdx"
                            class="bg-white border border-gray-100 rounded-lg p-2 flex items-center justify-between gap-2 text-xs"
                        >
                            <div class="min-w-0">
                                <div class="font-mono font-bold text-gray-800 text-xs">{{ shipment.resi }}</div>
                                <div class="text-[11px] text-gray-400 truncate mt-0.5">{{ shipment.items || 'Barang Mebel Jepara' }}</div>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="font-bold text-gray-700 text-xs bg-gray-100 px-1.5 py-0.5 rounded">
                                    {{ shipment.total_koli }} koli
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi Cepat: Maps, WA, & Scan -->
                    <div class="px-3 pb-3 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <a 
                                :href="getMapsLink(titik)" 
                                target="_blank"
                                class="py-2 px-3 rounded-lg bg-gray-50 hover:bg-gray-100 text-gray-700 font-bold text-xs flex items-center justify-center gap-1.5 border border-gray-200 transition-all active:scale-95"
                            >
                                <i class="ri-navigation-fill text-sm text-primary"></i> Rute Maps
                            </a>

                            <a 
                                v-if="getWaLink(titik)"
                                :href="getWaLink(titik)" 
                                target="_blank"
                                class="py-2 px-3 rounded-lg bg-green-50 hover:bg-green-100 text-green-700 font-bold text-xs flex items-center justify-center gap-1.5 border border-green-200 transition-all active:scale-95"
                            >
                                <i class="ri-whatsapp-line text-sm text-green-600"></i> Hubungi WA
                            </a>
                            <div 
                                v-else
                                class="py-2 px-3 rounded-lg bg-gray-50 text-gray-400 font-medium text-xs flex items-center justify-center gap-1.5 border border-gray-100"
                            >
                                <i class="ri-phone-line text-sm"></i> No WA -
                            </div>
                        </div>

                        <!-- Tombol Utama: Scan & Angkut -->
                        <button 
                            @click="bukaModal(titik)"
                            class="w-full py-2.5 rounded-lg bg-primary hover:bg-primaryDark text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition-all active:scale-[0.98]"
                        >
                            <i class="ri-qr-scan-2-line text-sm"></i> Scan & Angkut Muatan
                        </button>
                    </div>
                </div>
            </div>

            <!-- SECTION: SELESAI DIJEMPUT -->
            <div v-if="selesaiList.length > 0" class="pt-3 space-y-2.5">
                <div class="flex items-center gap-1.5 text-xs font-bold text-green-600 px-1">
                    <i class="ri-checkbox-circle-fill"></i>
                    <span class="uppercase tracking-wider">Selesai Diangkut ({{ selesaiList.length }})</span>
                </div>

                <div 
                    v-for="titik in selesaiList" 
                    :key="'s'+titik.id"
                    class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 opacity-80 hover:opacity-100 transition-opacity relative overflow-hidden"
                >
                    <div class="absolute left-0 top-0 w-1 h-full bg-green-400"></div>
                    <div class="flex items-center justify-between gap-2 pl-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-green-50 text-green-600 border border-green-200">Selesai</span>
                                <h3 class="text-xs font-bold text-gray-600 line-through decoration-gray-400 truncate">
                                    {{ titik.nama_pengirim }}
                                </h3>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-0.5 truncate">{{ titik.alamat }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="text-xs font-bold text-green-600">{{ titik.total_koli }} koli</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State jika semua selesai -->
            <div v-if="antreanList.length === 0 && selesaiList.length > 0" class="text-center py-8 bg-green-50 rounded-2xl border border-dashed border-green-200 p-6 space-y-2">
                <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto text-xl">
                    <i class="ri-check-double-line"></i>
                </div>
                <h3 class="text-sm font-extrabold text-gray-800">Semua Barang Telah Diangkut!</h3>
                <p class="text-xs text-gray-500 max-w-xs mx-auto">Tugas penjemputan selesai. Armada siap menuju gudang utama untuk penimbangan & sortasi.</p>
            </div>

        </main>
        </template>

        <!-- MODAL SCANNER & VERIFIKASI KOLI -->
        <Transition name="fade">
            <div v-if="isModalOpen" class="fixed inset-0 z-[150] flex items-end justify-center sm:items-center pb-[40px] sm:pb-0">
                <!-- Overlay Backdrop -->
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="tutupModal"></div>

                <!-- Modal Panel -->
                <div class="bg-white w-full max-w-md rounded-t-[1.5rem] sm:rounded-2xl overflow-hidden shadow-2xl relative z-10 flex flex-col max-h-[85vh] animate-slide-up-fast">
                    
                    <!-- Modal Header -->
                    <div class="p-4 bg-gray-900 text-white flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded bg-primary/20 text-primary border border-primary/30">Scan Koli</span>
                                <span class="text-xs text-gray-300 truncate">{{ selectedTitik?.nama_pengirim }}</span>
                            </div>
                            <h3 class="text-sm font-bold text-white mt-0.5">
                                Ter-scan: <span class="text-green-400 font-black">{{ scanCount }}</span> / {{ totalKoli }} Koli
                            </h3>
                        </div>
                        <button @click="tutupModal" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
                            <i class="ri-close-line text-lg"></i>
                        </button>
                    </div>

                    <!-- Progress Bar Koli -->
                    <div class="w-full bg-gray-200 h-2 relative overflow-hidden">
                        <div 
                            class="h-full transition-all duration-300"
                            :class="allScanned ? 'bg-green-500' : 'bg-primary'"
                            :style="{ width: progressPct + '%' }"
                        ></div>
                    </div>

                    <!-- Modal Scrollable Body -->
                    <div class="p-4 space-y-3 overflow-y-auto flex-1 bg-gray-50/60">
                        
                        <!-- Banner Flash Message Scan -->
                        <Transition name="slide-down">
                            <div 
                                v-if="scanMessage" 
                                :class="[
                                    'px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2 border',
                                    scanMessage.type === 'ok' ? 'bg-green-50 text-green-700 border-green-200' :
                                    scanMessage.type === 'duplicate' ? 'bg-yellow-50 text-yellow-700 border-yellow-200' :
                                    'bg-red-50 text-red-700 border-red-200'
                                ]"
                            >
                                <span>{{ scanMessage.text }}</span>
                                <button @click="scanMessage = null" class="text-sm opacity-70 hover:opacity-100"><i class="ri-close-line"></i></button>
                            </div>
                        </Transition>

                        <!-- Kamera Scanner Component -->
                        <div v-if="isCameraOpen" class="w-full h-52 rounded-xl overflow-hidden relative border border-gray-200 bg-black shadow-inner">
                            <BarcodeScanner 
                                :showClose="false"
                                initialMode="koli"
                                :allowModeSwitch="false"
                                :clean-view="true"
                                @scan="handleScan" 
                                @close="isCameraOpen = false" 
                            />
                            <button 
                                @click="isCameraOpen = false" 
                                class="absolute top-2 right-2 z-40 bg-black/60 hover:bg-black/90 text-white text-xs px-2.5 py-1 rounded-lg backdrop-blur flex items-center gap-1"
                            >
                                <i class="ri-camera-off-line"></i> Tutup Kamera
                            </button>
                        </div>

                        <button 
                            v-else
                            @click="isCameraOpen = true"
                            class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-gray-900 to-slate-800 hover:from-black hover:to-gray-900 text-white text-xs font-bold flex items-center justify-center gap-2 transition-all shadow-md active:scale-[0.99] border border-white/10"
                        >
                            <div class="w-6 h-6 rounded-lg bg-primary/20 text-primary flex items-center justify-center text-sm">
                                <i class="ri-qr-scan-2-line"></i>
                            </div>
                            <span>Buka Kamera Scanner Barcode</span>
                        </button>

                        <!-- Manual Input Barcode -->
                        <form @submit.prevent="prosesBarcode()" class="relative">
                            <input
                                type="text"
                                v-model="barcodeInput"
                                placeholder="Ketik barcode jika stiker rusak..."
                                class="w-full pl-9 pr-20 py-2 bg-white border border-gray-300 rounded-lg text-xs font-mono text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                            >
                            <i class="ri-barcode-line absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                            <button
                                type="submit"
                                class="absolute right-1 top-1 bottom-1 px-3 bg-primary hover:bg-primaryDark text-white rounded-md text-xs font-bold flex items-center gap-1 transition-colors"
                            >
                                Input
                            </button>
                        </form>

                        <!-- Checklist Koli Interaktif -->
                        <div class="space-y-1.5 pt-1">
                            <div class="flex items-center justify-between text-[11px] font-bold text-gray-500 px-1">
                                <span>DAFTAR KOLI (Ketuk untuk centang manual)</span>
                                <span class="text-green-600">{{ scanCount }} / {{ totalKoli }}</span>
                            </div>

                            <div v-if="expectedBarcodes.length > 0" class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                <div 
                                    v-for="bc in expectedBarcodes" 
                                    :key="bc"
                                    @click="toggleManualKoli(bc)"
                                    :class="[
                                        'px-3 py-2 rounded-lg border text-xs font-mono flex items-center justify-between cursor-pointer transition-all select-none',
                                        scannedBarcodes.includes(bc)
                                            ? 'bg-green-50 border-green-300 text-green-700 font-bold'
                                            : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300'
                                    ]"
                                >
                                    <div class="flex items-center gap-2">
                                        <i 
                                            :class="scannedBarcodes.includes(bc) ? 'ri-checkbox-circle-fill text-green-600' : 'ri-checkbox-blank-circle-line text-gray-400'" 
                                            class="text-base"
                                        ></i>
                                        <span>{{ bc }}</span>
                                    </div>
                                    <span class="text-[10px] uppercase font-bold" :class="scannedBarcodes.includes(bc) ? 'text-green-600' : 'text-gray-400'">
                                        {{ scannedBarcodes.includes(bc) ? 'Ter-scan' : 'Belum' }}
                                    </span>
                                </div>
                            </div>

                            <div v-else class="text-center py-6 bg-white rounded-lg border border-dashed border-gray-300 text-gray-400 text-xs">
                                <i class="ri-inbox-line text-lg mb-1 block"></i>
                                Tidak ada daftar barcode individual. Anda dapat langsung menyelesaikan penjemputan.
                            </div>
                        </div>

                        <!-- Dialog Konfirmasi Paksa Selesai -->
                        <div v-if="showForceConfirm" class="bg-red-50 border border-red-200 p-3 rounded-xl space-y-2">
                            <div class="flex items-start gap-2">
                                <i class="ri-alert-fill text-red-500 text-base shrink-0 mt-0.5"></i>
                                <div>
                                    <div class="text-xs font-bold text-red-700">Belum Semua Koli Ter-scan!</div>
                                    <div class="text-[11px] text-red-600 leading-relaxed">
                                        Baru ter-scan {{ scanCount }} dari {{ totalKoli }} koli. Yakin ingin memuat sebagian / tanpa barcode?
                                    </div>
                                </div>
                            </div>
                            <input 
                                v-model="forceReason"
                                type="text"
                                placeholder="Alasan (misal: 1 koli menyusul / stiker hilang)"
                                class="w-full px-3 py-1.5 bg-white border border-red-300 rounded-lg text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-red-500"
                            >
                            <div class="flex gap-2">
                                <button 
                                    @click="showForceConfirm = false" 
                                    class="flex-1 py-1.5 bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 rounded-lg text-xs font-bold"
                                >
                                    Batal
                                </button>
                                <button 
                                    @click="selesaikanJemput(false, true)"
                                    :disabled="isSubmitting"
                                    class="flex-1 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold flex items-center justify-center gap-1"
                                >
                                    <i v-if="isSubmitting" class="ri-loader-4-line animate-spin"></i>
                                    <span>Paksa Selesai</span>
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Modal Footer Action -->
                    <div class="p-3.5 pb-4 bg-white border-t border-gray-100 shrink-0">
                        <button 
                            v-if="!showForceConfirm"
                            @click="selesaikanJemput(false)"
                            :disabled="isSubmitting"
                            :class="[
                                'w-full py-3.5 rounded-xl text-white font-bold text-sm flex items-center justify-center gap-2 shadow transition-all active:scale-[0.98]',
                                allScanned 
                                    ? 'bg-green-600 hover:bg-green-700' 
                                    : 'bg-primary hover:bg-primaryDark'
                            ]"
                        >
                            <i v-if="isSubmitting" class="ri-loader-4-line animate-spin text-sm"></i>
                            <template v-else>
                                <i :class="allScanned ? 'ri-checkbox-circle-fill' : 'ri-truck-fill'" class="text-base"></i>
                                <span>{{ allScanned ? 'Selesai & Angkut ke Mobil' : scanCount > 0 ? `Selesaikan (${scanCount}/${totalKoli} Koli)` : 'Selesaikan Tanpa Scan' }}</span>
                            </template>
                        </button>
                    </div>

                </div>
            </div>
        </Transition>

    </div>

    <!-- Modal Profil & Akun Supir -->
    <DriverProfileModal
        :show="isProfileModalOpen"
        :manifest="activeManifest"
        driver-role="DRIVER_PICKUP"
        @close="isProfileModalOpen = false"
    />

    <!-- Floating Banner Tawaran Kunci Layar HP / Biometrik Supir -->
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="translate-y-12 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-12 opacity-0"
    >
        <div 
            v-if="showBiometricPrompt && isBiometricSupported && !biometricRegisteredUser" 
            class="fixed bottom-12 left-3 right-3 max-w-md mx-auto z-[95] bg-gradient-to-br from-slate-900 to-gray-900 text-white p-4 rounded-2xl shadow-2xl border border-primary/30 backdrop-blur-md"
        >
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/20 border border-primary/40 flex items-center justify-center shrink-0 text-primary shadow-inner">
                    <i class="ri-fingerprint-line text-2xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-primary/20 text-primary border border-primary/30">
                            Login Kilat
                        </span>
                        <button @click="dismissBiometricPrompt" class="text-gray-400 hover:text-white p-1">
                            <i class="ri-close-line text-lg"></i>
                        </button>
                    </div>
                    <h4 class="font-extrabold text-sm text-white mt-1">Aktifkan Sidik Jari / Kunci Layar HP?</h4>
                    <p class="text-xs text-gray-300 mt-0.5 leading-snug">
                        Tugas pickup berikutnya cukup dibuka tempel sidik jari atau PIN tanpa repot ketik kata sandi.
                    </p>
                    <div class="mt-3 flex items-center gap-2">
                        <button
                            @click="handleActivateBiometric"
                            :disabled="isBiometricRegistering"
                            class="flex-1 bg-primary hover:bg-primaryDark text-white text-xs font-bold py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 transition active:scale-[0.98] shadow-md shadow-primary/25 disabled:opacity-50"
                        >
                            <i v-if="isBiometricRegistering" class="ri-loader-4-line animate-spin"></i>
                            <i v-else class="ri-shield-keyhole-line text-sm"></i>
                            <span>{{ isBiometricRegistering ? 'Memproses...' : 'Aktifkan di HP Ini' }}</span>
                        </button>
                        <button
                            @click="dismissBiometricPrompt"
                            class="text-xs text-gray-400 hover:text-white py-2.5 px-3 rounded-xl hover:bg-white/5 transition"
                        >
                            Nanti
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>

    <!-- Toast Sukses Aktivasi Biometrik -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-4"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-4"
    >
        <div 
            v-if="biometricSuccessToast" 
            class="fixed top-14 left-4 right-4 max-w-md mx-auto z-[150] bg-emerald-600 text-white p-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-emerald-400"
        >
            <i class="ri-checkbox-circle-fill text-xl text-emerald-200 shrink-0"></i>
            <p class="text-xs font-bold leading-snug">{{ biometricSuccessToast }}</p>
        </div>
    </Transition>

    <!-- Modal Scanner QR Surat Tugas (Handshake Manifest) -->
    <Teleport to="body">
        <div v-if="isManifestScannerOpen" class="fixed inset-0 z-[250] bg-black flex flex-col">
            <div class="p-4 bg-gray-900/95 text-white flex items-center justify-between z-10 border-b border-gray-800">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-primary/20 text-primary border border-primary/40 flex items-center justify-center font-bold">
                        <i class="ri-qr-scan-2-line"></i>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-sm text-white">Scan Surat Tugas Pickup</h4>
                        <p class="text-[10px] text-gray-400">Arahkan kamera ke QR code surat tugas dari gudang</p>
                    </div>
                </div>
                <button @click="isManifestScannerOpen = false" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-gray-300 hover:text-white flex items-center justify-center">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div class="flex-1 relative bg-black flex flex-col justify-center">
                <BarcodeScanner
                    initial-mode="resi"
                    :allow-mode-switch="false"
                    :clean-view="true"
                    :show-close="false"
                    @scan="onManifestQrScanned"
                    @close="isManifestScannerOpen = false"
                />
            </div>
        </div>
    </Teleport>

    <!-- Modal Input Kode Surat Tugas Manual -->
    <Teleport to="body">
        <div v-if="showManualManifestInput" class="fixed inset-0 z-[240] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="showManualManifestInput = false">
            <div class="bg-white w-full max-w-sm rounded-3xl p-5 shadow-2xl border border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                            <i class="ri-keyboard-line"></i>
                        </div>
                        <div>
                            <h4 class="font-extrabold text-sm text-gray-900">Ketik Kode Surat Tugas</h4>
                            <p class="text-[10px] text-gray-500">Contoh: PICKUP-001, #12, atau token</p>
                        </div>
                    </div>
                    <button @click="showManualManifestInput = false" class="text-gray-400 hover:text-gray-600 p-1">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>

                <div class="mt-3">
                    <input
                        v-model="manualManifestCode"
                        type="text"
                        placeholder="Contoh: PICKUP-001 atau token..."
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm font-mono focus:border-primary focus:ring-1 focus:ring-primary uppercase"
                        @keyup.enter="handleClaimManifest()"
                    />
                </div>

                <div v-if="claimManifestError" class="mt-2.5 p-2 rounded-lg bg-red-50 text-red-700 text-xs font-semibold flex items-center gap-1.5">
                    <i class="ri-error-warning-fill"></i>
                    <span>{{ claimManifestError }}</span>
                </div>

                <div class="mt-4 flex gap-2">
                    <button
                        type="button"
                        @click="showManualManifestInput = false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="handleClaimManifest()"
                        :disabled="isClaimingManifest || !manualManifestCode.trim()"
                        class="flex-1 py-2.5 rounded-xl bg-primary hover:bg-primaryDark text-white text-xs font-bold shadow-md shadow-primary/20 disabled:opacity-50 flex items-center justify-center gap-1.5"
                    >
                        <i v-if="isClaimingManifest" class="ri-loader-4-line animate-spin"></i>
                        <span>{{ isClaimingManifest ? 'Memeriksa...' : 'Ambil Tugas' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Toast Sukses Ambil Manifest -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-4"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-4"
    >
        <div 
            v-if="claimSuccessMessage" 
            class="fixed top-14 left-4 right-4 max-w-md mx-auto z-[260] bg-emerald-600 text-white p-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-emerald-400"
        >
            <i class="ri-checkbox-circle-fill text-xl text-emerald-200 shrink-0"></i>
            <p class="text-xs font-bold leading-snug">{{ claimSuccessMessage }}</p>
        </div>
    </Transition>
</template>

<style scoped>
.animate-slide-up-fast {
    animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes slideUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
