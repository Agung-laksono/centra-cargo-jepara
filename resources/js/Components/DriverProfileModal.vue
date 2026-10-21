<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useEcho } from '@/Composables/useEcho';
import { useBiometricAuth } from '@/Composables/useBiometricAuth';
import BarcodeScanner from '@/Components/BarcodeScanner.vue';
import { BarcodeScanner as NativeBarcodeScanner, BarcodeFormat } from '@capacitor-mlkit/barcode-scanning';
import { Capacitor } from '@capacitor/core';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    manifest: {
        type: Object,
        default: () => ({}),
    },
    driverRole: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || null);

// Identitas Supir
const driverName = computed(() => {
    return props.manifest?.driver_name || currentUser.value?.name || 'Supir Cargo';
});

const driverPhone = computed(() => {
    return props.manifest?.driver_phone || currentUser.value?.phone || 'Belum diisi';
});

const vehicleName = computed(() => {
    return props.manifest?.vehicle_name || 'Armada Standar';
});

const expeditionName = computed(() => {
    return props.manifest?.expedition_name || currentUser.value?.expedition?.name || page.props.company?.name || 'Ekspedisi Kemitraan';
});

const activeExpeditionName = computed(() => {
    return props.manifest?.expedition_name || currentUser.value?.expedition?.name || page.props.company?.name || null;
});

const activePartnerships = computed(() => {
    return partnerships.value.filter(p => p.status === 'ACTIVE' || !p.status);
});

const isMultiExpedition = computed(() => {
    return activePartnerships.value.length > 1;
});

const roleLabel = computed(() => {
    const role = props.driverRole || currentUser.value?.role;
    if (role === 'DRIVER_PICKUP') return 'Supir Penjemputan (Lokal)';
    if (role === 'DRIVER_LINTAS') return 'Supir Pengantaran (Lintas Kota)';
    return 'Staf Lapangan';
});

// Biometric & Kunci Layar
const {
    isSupported: isBiometricSupported,
    isRegistering: isBiometricRegistering,
    registeredUser: biometricRegisteredUser,
    checkSupport: checkBiometricSupport,
    loadRegisteredDevice: loadBiometricDevice,
    registerDevice: registerBiometricDevice,
    removeBiometric: removeBiometricDevice,
} = useBiometricAuth();

const biometricMsg = ref(null);

// Handshake & Kemitraan Ekspedisi
const partnerships = ref([]);
const isLoadingPartnerships = ref(false);
const isHandshakeScannerOpen = ref(false);
const showManualInput = ref(false);
const manualTokenInput = ref('');
const isSubmittingHandshake = ref(false);
const handshakeMsg = ref(null);

const { getEcho } = useEcho();
let driverEchoChannel = null;

const setupDriverEcho = () => {
    cleanupDriverEcho();
    const echo = getEcho();
    if (!echo || !currentUser.value) return;

    const channelName = `user.${currentUser.value.id}`;
    driverEchoChannel = echo.channel(channelName);

    const handleStatusUpdate = (e) => {
        if (navigator.vibrate) navigator.vibrate([200, 100, 200, 100, 300]);

        handshakeMsg.value = {
            type: e.status === 'ACTIVE' ? 'success' : 'pending',
            text: e.message || 'Status kemitraan armada telah diperbarui!'
        };

        fetchPartnerships();
        setTimeout(() => {
            if (handshakeMsg.value?.text === e.message) {
                handshakeMsg.value = null;
            }
        }, 7000);
    };

    driverEchoChannel.listen('.partnership.status_updated', handleStatusUpdate);
    driverEchoChannel.listen('partnership.status_updated', handleStatusUpdate);
};

const cleanupDriverEcho = () => {
    if (driverEchoChannel) {
        try {
            driverEchoChannel.stopListening('.partnership.status_updated');
            driverEchoChannel.stopListening('partnership.status_updated');
        } catch (err) {}
        driverEchoChannel = null;
    }
};

watch(() => props.show, (isOpen) => {
    if (isOpen) {
        fetchPartnerships();
        setupDriverEcho();
    } else {
        cleanupDriverEcho();
    }
});

onBeforeUnmount(() => {
    cleanupDriverEcho();
});

const handleConnectHandshake = async (tokenCode) => {
    const cleanToken = (tokenCode || manualTokenInput.value).trim();
    if (!cleanToken) return;

    try {
        isSubmittingHandshake.value = true;
        handshakeMsg.value = null;

        const res = await axios.post('/api/driver/handshake', {
            token: cleanToken,
        });

        if (res.data.status === 'success' || res.data.status === 'pending') {
            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
            handshakeMsg.value = {
                type: res.data.status === 'success' ? 'success' : 'pending',
                text: res.data.message
            };
            isHandshakeScannerOpen.value = false;
            manualTokenInput.value = '';
            showManualInput.value = false;
            await fetchPartnerships();
            setTimeout(() => { handshakeMsg.value = null; }, 6000);
        } else {
            handshakeMsg.value = { type: 'error', text: res.data.message || 'Gagal menghubungkan kemitraan.' };
        }
    } catch (err) {
        const errorText = err.response?.data?.message || err.message || 'Kode kemitraan tidak cocok.';
        handshakeMsg.value = { type: 'error', text: errorText };
        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
    } finally {
        isSubmittingHandshake.value = false;
    }
};

const openHandshakeScanner = async () => {
    handshakeMsg.value = null;

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
                    onHandshakeQrScanned(scannedCode);
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

    // Fallback jika dibuka di desktop browser
    isHandshakeScannerOpen.value = true;
};

const onHandshakeQrScanned = (scannedText) => {
    isHandshakeScannerOpen.value = false;
    handleConnectHandshake(scannedText);
};

onMounted(async () => {
    await checkBiometricSupport();
    await loadBiometricDevice();
    await fetchPartnerships();
});

watch(() => props.show, (isOpen) => {
    if (isOpen) {
        fetchPartnerships();
    }
});

const handleActivateBiometric = async () => {
    try {
        if (!currentUser.value) {
            biometricMsg.value = { type: 'error', text: 'Sesi supir belum terhubung. Silakan login atau buka dari link tugas resmi.' };
            setTimeout(() => { biometricMsg.value = null; }, 4000);
            return;
        }
        await registerBiometricDevice(currentUser.value);
        biometricMsg.value = { type: 'success', text: 'Kunci layar HP berhasil dihubungkan! Masuk berikutnya tanpa sandi.' };
        setTimeout(() => { biometricMsg.value = null; }, 3500);
    } catch (e) {
        if (e.message && !e.message.includes('dibatalkan')) {
            biometricMsg.value = { type: 'error', text: e.message || 'Gagal mengaktifkan kunci layar.' };
            setTimeout(() => { biometricMsg.value = null; }, 4000);
        }
    }
};

const handleRemoveBiometric = async () => {
    if (confirm('Lepas kunci layar dari HP ini? Anda tetap bisa masuk dengan sandi.')) {
        await removeBiometricDevice();
        biometricMsg.value = { type: 'success', text: 'Kunci layar dinonaktifkan.' };
        setTimeout(() => { biometricMsg.value = null; }, 3000);
    }
};

// Logout Handler
const isLoggingOut = ref(false);
const showLogoutConfirm = ref(false);

const handleLogout = () => {
    isLoggingOut.value = true;
    router.post(route('logout'), {}, {
        onFinish: () => {
            isLoggingOut.value = false;
            emit('close');
        }
    });
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="show" 
                class="fixed inset-0 z-[200] flex flex-col justify-end sm:justify-center sm:items-center bg-black/60 backdrop-blur-sm p-0 sm:p-4"
                @click.self="emit('close')"
            >
                <div 
                    class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh] animate-slide-up"
                    @click.stop
                >
                    <!-- Header Modal -->
                    <div class="px-5 py-4 bg-gradient-to-r from-gray-900 to-slate-800 text-white flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-primary/20 text-primary border border-primary/40 flex items-center justify-center font-bold text-sm">
                                <i class="ri-user-star-fill"></i>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-sm text-white">Profil & Akun Supir</h3>
                                <p class="text-[10px] text-gray-400">Jepara Cargo Mobile</p>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            @click="emit('close')"
                            class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-gray-300 hover:text-white flex items-center justify-center transition-colors"
                        >
                            <i class="ri-close-line text-lg"></i>
                        </button>
                    </div>

                    <!-- Scrollable Content -->
                    <div class="p-5 space-y-4 overflow-y-auto flex-1 bg-gray-50/70 text-gray-800">
                        
                        <!-- Kartu Profil Utama -->
                        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex items-start gap-3.5">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary to-primaryDark text-white font-black text-xl flex items-center justify-center shrink-0 shadow-md shadow-primary/20">
                                {{ driverName.substring(0, 2).toUpperCase() }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-primary/10 text-primary border border-primary/20 mb-1">
                                    <i class="ri-steering-2-line"></i> {{ roleLabel }}
                                </span>
                                <h4 class="font-bold text-base text-gray-900 truncate leading-snug">{{ driverName }}</h4>
                                
                                <!-- Tampilan Kemitraan Ekspedisi (Mendukung Multi-Ekspedisi) -->
                                <div v-if="isMultiExpedition" class="mt-1 space-y-1">
                                    <div class="flex items-center gap-1 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                                        <i class="ri-community-line text-primary"></i>
                                        <span>{{ activePartnerships.length }} Mitra Ekspedisi:</span>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <span 
                                            v-for="p in activePartnerships" 
                                            :key="p.id"
                                            class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-lg border transition-all"
                                            :class="p.name === activeExpeditionName 
                                                ? 'bg-primary/10 text-primary border-primary/40 shadow-xs' 
                                                : 'bg-gray-100 text-gray-600 border-gray-200'"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: p.theme_color || '#8B5A2B' }"></span>
                                            <span class="truncate max-w-[110px]">{{ p.name }}</span>
                                            <span v-if="p.name === activeExpeditionName" class="text-[8px] font-black uppercase text-primary ml-0.5">• Aktif</span>
                                        </span>
                                    </div>
                                </div>
                                <p v-else class="text-xs text-gray-500 truncate mt-0.5 flex items-center gap-1">
                                    <i class="ri-community-line text-gray-400"></i>
                                    <span>{{ activeExpeditionName || expeditionName }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Data Detail Kendaraan & Kontak -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-100 overflow-hidden text-xs">
                            <div class="p-3.5 flex items-center justify-between">
                                <div class="flex items-center gap-2.5 text-gray-500">
                                    <i class="ri-whatsapp-line text-emerald-600 text-base"></i>
                                    <span>No. WhatsApp</span>
                                </div>
                                <span class="font-bold text-gray-800 font-mono">{{ driverPhone }}</span>
                            </div>

                            <div class="p-3.5 flex items-center justify-between">
                                <div class="flex items-center gap-2.5 text-gray-500">
                                    <i class="ri-truck-line text-primary text-base"></i>
                                    <span>Armada Kendaraan</span>
                                </div>
                                <span class="font-bold text-gray-800">{{ vehicleName }}</span>
                            </div>

                            <div v-if="manifest?.id" class="p-3.5 flex items-center justify-between">
                                <div class="flex items-center gap-2.5 text-gray-500">
                                    <i class="ri-file-list-3-line text-blue-600 text-base"></i>
                                    <span>Tugas Aktif</span>
                                </div>
                                <span class="font-black font-mono text-gray-900">#{{ manifest.id }}</span>
                            </div>
                        </div>

                        <!-- Kunci Layar HP / Biometrik Section -->
                        <div v-if="isBiometricSupported" class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
                                        <i class="ri-fingerprint-line text-lg"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-extrabold text-xs text-gray-900">Kunci Layar HP (Biometrik)</h5>
                                        <p class="text-[10px] text-gray-500">Masuk instan dengan sidik jari / PIN HP</p>
                                    </div>
                                </div>

                                <span 
                                    class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full flex items-center gap-1"
                                    :class="biometricRegisteredUser ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                >
                                    <i :class="biometricRegisteredUser ? 'ri-shield-check-fill' : 'ri-time-line'"></i>
                                    {{ biometricRegisteredUser ? 'Aktif di HP Ini' : 'Belum Aktif' }}
                                </span>
                            </div>

                            <!-- Alert message -->
                            <div v-if="biometricMsg" class="p-2.5 rounded-xl text-xs font-semibold flex items-center gap-2" :class="biometricMsg.type === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'">
                                <i :class="biometricMsg.type === 'success' ? 'ri-checkbox-circle-fill' : 'ri-error-warning-fill'"></i>
                                <span>{{ biometricMsg.text }}</span>
                            </div>

                            <button
                                v-if="!biometricRegisteredUser"
                                type="button"
                                @click="handleActivateBiometric"
                                :disabled="isBiometricRegistering"
                                class="w-full py-2.5 px-3 rounded-xl bg-primary hover:bg-primaryDark text-white font-bold text-xs flex items-center justify-center gap-2 transition disabled:opacity-50 shadow-md shadow-primary/20 active:scale-[0.98]"
                            >
                                <i v-if="isBiometricRegistering" class="ri-loader-4-line animate-spin"></i>
                                <i v-else class="ri-shield-keyhole-line text-sm"></i>
                                <span>{{ isBiometricRegistering ? 'Memproses...' : 'Hubungkan Kunci Layar HP Ini' }}</span>
                            </button>

                            <button
                                v-else
                                type="button"
                                @click="handleRemoveBiometric"
                                class="w-full py-2 px-3 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 font-semibold text-xs flex items-center justify-center gap-1.5 transition border border-red-200"
                            >
                                <i class="ri-delete-bin-line"></i>
                                <span>Lepas Kunci Layar</span>
                            </button>
                        </div>

                        <!-- SEKSI KEMITRAAN EKSPEDISI (HANDSHAKE) -->
                        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                        <i class="ri-shake-hands-line text-base"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-bold text-xs text-gray-900">Kemitraan Ekspedisi Resmi</h5>
                                        <p class="text-[10px] text-gray-500">Ekspedisi tempat armada Anda bertugas</p>
                                    </div>
                                </div>
                                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
                                    {{ partnerships.length }} Mitra
                                </span>
                            </div>

                            <!-- Pesan status handshake -->
                            <div 
                                v-if="handshakeMsg" 
                                class="p-2.5 rounded-xl text-xs font-semibold flex items-center gap-2" 
                                :class="{
                                    'bg-emerald-50 text-emerald-800 border border-emerald-200': handshakeMsg.type === 'success',
                                    'bg-amber-50 text-amber-800 border border-amber-300': handshakeMsg.type === 'pending',
                                    'bg-red-50 text-red-800 border border-red-200': handshakeMsg.type === 'error'
                                }"
                            >
                                <i 
                                    :class="{
                                        'ri-checkbox-circle-fill text-emerald-600': handshakeMsg.type === 'success',
                                        'ri-time-line text-amber-600': handshakeMsg.type === 'pending',
                                        'ri-error-warning-fill text-red-600': handshakeMsg.type === 'error'
                                    }"
                                ></i>
                                <span>{{ handshakeMsg.text }}</span>
                            </div>

                            <!-- Daftar ekspedisi terhubung -->
                            <div v-if="partnerships.length > 0" class="space-y-1.5 max-h-40 overflow-y-auto">
                                <div v-for="p in partnerships" :key="p.id" class="p-2.5 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white font-bold text-xs shadow-sm" :style="{ backgroundColor: p.theme_color || '#8B5A2B' }">
                                            {{ p.name.substring(0, 1) }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-800 leading-tight">{{ p.name }}</p>
                                            <p class="text-[10px] text-gray-400">{{ p.phone || 'Ekspedisi Rekanan' }}</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Status Badges -->
                                    <span v-if="p.status === 'ACTIVE'" class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 flex items-center gap-1">
                                        <i class="ri-checkbox-circle-fill"></i> Mitra Aktif
                                    </span>
                                    <span v-else-if="p.status === 'PENDING'" class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1">
                                        <i class="ri-time-line"></i> Menunggu Gudang
                                    </span>
                                    <span v-else-if="p.status === 'REJECTED'" class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700 flex items-center gap-1">
                                        <i class="ri-close-circle-fill"></i> Ditolak
                                    </span>
                                    <span v-else class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 flex items-center gap-1">
                                        <i class="ri-pause-circle-line"></i> Nonaktif
                                    </span>
                                </div>
                            </div>
                            <div v-else class="text-center py-4 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                <i class="ri-truck-line text-gray-400 text-xl block mb-1"></i>
                                <p class="text-xs text-gray-600 font-semibold">Belum terhubung ke ekspedisi mitra.</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">Scan QR di kantor atau gudang ekspedisi untuk menerima muatan.</p>
                            </div>

                            <!-- Tombol Handshake Scan QR Baru -->
                            <div class="pt-1 flex gap-2">
                                <button
                                    type="button"
                                    @click="openHandshakeScanner"
                                    class="flex-1 py-2.5 px-3 rounded-xl bg-slate-900 hover:bg-black text-white font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-[0.98] shadow-sm"
                                >
                                    <i class="ri-qr-scan-2-line text-sm text-primary"></i>
                                    <span>Scan QR Kemitraan</span>
                                </button>
                                <button
                                    type="button"
                                    @click="showManualInput = !showManualInput"
                                    class="py-2.5 px-3 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs transition"
                                    title="Ketik Kode Manual"
                                >
                                    <i class="ri-keyboard-line"></i>
                                </button>
                            </div>

                            <!-- Input Manual Token jika kamera bermasalah -->
                            <div v-if="showManualInput" class="pt-2 flex gap-1.5 animate-fadeIn">
                                <input
                                    type="text"
                                    v-model="manualTokenInput"
                                    placeholder="Contoh: EXP-A1B2C3D4"
                                    class="flex-1 px-3 py-2 text-xs font-mono border border-gray-300 rounded-lg text-gray-800 uppercase focus:border-primary focus:ring-1 focus:ring-primary"
                                />
                                <button
                                    type="button"
                                    @click="handleConnectHandshake()"
                                    :disabled="!manualTokenInput.trim() || isSubmittingHandshake"
                                    class="px-3 py-2 bg-primary hover:bg-primaryDark text-white text-xs font-bold rounded-lg transition disabled:opacity-50"
                                >
                                    <i v-if="isSubmittingHandshake" class="ri-loader-4-line animate-spin"></i>
                                    <span v-else>Sambungkan</span>
                                </button>
                            </div>
                        </div>

                        <!-- Konfirmasi Logout -->
                        <div v-if="showLogoutConfirm" class="bg-red-50 border border-red-200 p-4 rounded-2xl space-y-2.5 animate-fadeIn">
                            <div class="flex items-start gap-2.5">
                                <i class="ri-error-warning-fill text-red-500 text-lg shrink-0 mt-0.5"></i>
                                <div>
                                    <h6 class="font-bold text-xs text-red-800">Yakin ingin keluar aplikasi?</h6>
                                    <p class="text-[11px] text-red-600 mt-0.5">
                                        Anda harus masuk kembali menggunakan kata sandi atau kunci layar HP untuk melanjutkan tugas.
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-2 pt-1">
                                <button
                                    type="button"
                                    @click="showLogoutConfirm = false"
                                    class="flex-1 py-2 bg-white hover:bg-gray-100 border border-gray-300 text-gray-700 rounded-xl text-xs font-bold transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="button"
                                    @click="handleLogout"
                                    :disabled="isLoggingOut"
                                    class="flex-1 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm"
                                >
                                    <i v-if="isLoggingOut" class="ri-loader-4-line animate-spin"></i>
                                    <span>{{ isLoggingOut ? 'Keluar...' : 'Ya, Keluar' }}</span>
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Footer Action -->
                    <div class="p-4 bg-white border-t border-gray-100 shrink-0 space-y-2">
                        <button
                            v-if="!showLogoutConfirm"
                            type="button"
                            @click="showLogoutConfirm = true"
                            class="w-full py-3 px-4 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 font-bold text-xs flex items-center justify-center gap-2 transition border border-red-200/80 active:scale-[0.99]"
                        >
                            <i class="ri-logout-box-r-line text-base"></i>
                            <span>Keluar dari Aplikasi (Logout)</span>
                        </button>

                        <button
                            type="button"
                            @click="emit('close')"
                            class="w-full py-2.5 px-4 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold text-xs transition"
                        >
                            Tutup
                        </button>
                    </div>

                </div>
            </div>
        </Transition>

        <!-- SCANNER MODAL OVERLAY UNTUK HANDSHAKE -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="isHandshakeScannerOpen" class="fixed inset-0 z-[250] bg-black flex flex-col">
                <div class="p-4 bg-gray-900 text-white flex items-center justify-between z-10">
                    <div class="flex items-center gap-2">
                        <i class="ri-qr-scan-2-line text-primary text-xl"></i>
                        <div>
                            <h4 class="font-bold text-sm">Scan QR Kemitraan Ekspedisi</h4>
                            <p class="text-[11px] text-gray-400">Arahkan kamera ke QR di gudang / kantor ekspedisi</p>
                        </div>
                    </div>
                    <button 
                        @click="isHandshakeScannerOpen = false" 
                        class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center"
                    >
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>
                <div class="flex-1 relative bg-black">
                    <BarcodeScanner
                        :show-close="false"
                        initial-mode="resi"
                        :allow-mode-switch="false"
                        :clean-view="true"
                        @scan="onHandshakeQrScanned"
                        @close="isHandshakeScannerOpen = false"
                    />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.animate-slide-up {
    animation: slideUp 0.28s cubic-bezier(0.16, 1, 0.3, 1);
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
.animate-fadeIn {
    animation: fadeIn 0.2s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}
</style>
