<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BarcodeScanner from '@/Components/BarcodeScanner.vue';
import localforage from 'localforage';
import axios from 'axios';
import { useEcho } from '@/Composables/useEcho';
import { Capacitor } from '@capacitor/core';
import { App as CapacitorApp } from '@capacitor/app';
import { BarcodeScanner as NativeBarcodeScanner, BarcodeFormat, LensFacing } from '@capacitor-mlkit/barcode-scanning';

const props = defineProps({
    shipments: Array,
});

const DB_SCANNED_KEY = 'inbound_scanned_barcodes';
const DB_QUEUE_KEY = 'inbound_sync_queue';
const DB_CACHE_KEY = 'inbound_cache_shipments';

const localShipments = ref([]);
const isScannerOpen = ref(false);
const barcodeInput = ref('');
const cameraInputRef = ref(null);
const closedInputRef = ref(null);
const scanMessage = ref(null);
const scannedBarcodes = ref([]);
const isSubmitting = ref(false);
const isSyncingLive = ref(false);
const searchQuery = ref('');
const isSearchExpanded = ref(false);
let scanMessageTimer = null;
let highlightTimer = null;
const highlightedKoliId = ref(null);
const highlightedShipmentId = ref(null);

const isNative = computed(() => {
    return typeof window !== 'undefined' && Capacitor.isNativePlatform();
});

// Deteksi koneksi aman (HTTPS / localhost untuk izin kamera)
const isSecureContext = computed(() => {
    if (typeof window === 'undefined') return true;
    return Boolean(window.isSecureContext || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
});

const focusActiveInput = () => {
    nextTick(() => {
        if (isScannerOpen.value && cameraInputRef.value) {
            cameraInputRef.value.focus();
        } else if (!isScannerOpen.value && closedInputRef.value) {
            closedInputRef.value.focus();
        }
    });
};

const closeScanner = () => {
    isScannerOpen.value = false;
    try {
        const videoEls = document.querySelectorAll('video');
        videoEls.forEach(v => {
            if (v.srcObject && typeof v.srcObject.getTracks === 'function') {
                v.srcObject.getTracks().forEach(t => t.stop());
                v.srcObject = null;
            }
        });
    } catch (e) {
        console.warn('Error stopping camera tracks:', e);
    }
    focusActiveInput();
};

const openScanner = () => {
    isScannerOpen.value = true;
    focusActiveInput();
};

onUnmounted(() => {
    clearTimeout(scanMessageTimer);
    closeScanner();
});

// Audio Web API Beep Generator
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
            osc.frequency.exponentialRampToValueAtTime(330, audioCtx.currentTime + 0.1);
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
        // Fallback jika audio dibatasi browser
    }
};

const showMessage = (type, text) => {
    scanMessage.value = { type, text };
    clearTimeout(scanMessageTimer);
    scanMessageTimer = setTimeout(() => {
        scanMessage.value = null;
    }, 3500);
};

const syncToServer = async () => {
    if (!navigator.onLine) return;
    
    let queue = (await localforage.getItem(DB_QUEUE_KEY)) || [];
    if (queue.length === 0) return;
    
    let failedQueue = [];
    let isSuccess = false;
    
    for (let job of queue) {
        try {
            await axios.post('/api/inbound/sync', job);
            isSuccess = true;
        } catch (error) {
            failedQueue.push(job);
        }
    }
    
    await localforage.setItem(DB_QUEUE_KEY, failedQueue);
    
    if (isSuccess && failedQueue.length === 0) {
        router.reload({ only: ['shipments'] });
    }
};

const initializeOfflineDB = async () => {
    if (navigator.onLine && props.shipments && props.shipments.length > 0) {
        localShipments.value = props.shipments;
        await localforage.setItem(DB_CACHE_KEY, props.shipments);
    } else {
        const cached = await localforage.getItem(DB_CACHE_KEY);
        if (cached) {
            localShipments.value = cached;
        } else {
            localShipments.value = props.shipments || [];
        }
    }
};

const { getEcho } = useEcho();

const reloadInboundData = () => {
    router.reload({
        only: ['shipments'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            initializeOfflineDB();
        }
    });
};

onMounted(async () => {
    await initializeOfflineDB();
    
    const savedScans = await localforage.getItem(DB_SCANNED_KEY);
    if (savedScans) {
        scannedBarcodes.value = savedScans;
    }
    
    window.addEventListener('online', () => {
        syncToServer();
        initializeOfflineDB();
    });
    syncToServer();

    // Echo Realtime Listeners
    const echo = getEcho();
    if (echo) {
        echo.channel('warehouse')
            .listen('.koli.inbound.scanned', () => {
                reloadInboundData();
            });

        echo.channel('shipments')
            .listen('.shipment.created', () => {
                reloadInboundData();
            })
            .listen('.shipment.status_updated', () => {
                reloadInboundData();
            });

        echo.channel('manifests')
            .listen('.pickup.completed', () => {
                reloadInboundData();
            });
    }
});

onUnmounted(() => {
    window.removeEventListener('online', syncToServer);
    clearTimeout(scanMessageTimer);
    clearTimeout(highlightTimer);
    try {
        const echo = getEcho();
        if (echo) {
            echo.leave('warehouse');
            echo.leave('shipments');
            echo.leave('manifests');
        }
    } catch (e) {}
});

// Watcher simpan scans ke localforage
watch(scannedBarcodes, (newVal) => {
    localforage.setItem(DB_SCANNED_KEY, newVal);
}, { deep: true });

watch(() => props.shipments, (newShipments) => {
    if (newShipments) {
        localShipments.value = newShipments;
    }
});

// Filter Tab Status
const statusFilter = ref('pending'); // 'pending' (Perlu Di-scan) | 'complete' (Sudah Lengkap) | 'all' (Semua)

const isKoliScanned = (koli) => {
    if (!koli) return false;
    const bId = (koli.barcode_id || koli.barcode || '').toUpperCase();
    if (bId && scannedBarcodes.value.some(s => s.toUpperCase() === bId)) return true;
    if (koli.physical_status === 'DI_GUDANG' || koli.physical_status === 'Di Gudang') return true;
    return false;
};

const getScannedKolisCount = (shipment) => {
    if (!shipment.kolis) return 0;
    return shipment.kolis.filter(k => isKoliScanned(k)).length;
};

const getShipmentProgress = (shipment) => {
    if (!shipment.kolis || shipment.kolis.length === 0) return 0;
    const scanned = getScannedKolisCount(shipment);
    return Math.round((scanned / shipment.kolis.length) * 100);
};

const isShipmentComplete = (shipment) => {
    if (!shipment.kolis || shipment.kolis.length === 0) return false;
    return shipment.kolis.every(k => isKoliScanned(k));
};

const pendingCount = computed(() => {
    return (localShipments.value || []).filter(s => !isShipmentComplete(s)).length;
});

const completedCount = computed(() => {
    return (localShipments.value || []).filter(s => isShipmentComplete(s)).length;
});

const filteredShipments = computed(() => {
    let list = localShipments.value || [];

    // Filter status tab
    if (statusFilter.value === 'pending') {
        list = list.filter(s => !isShipmentComplete(s));
    } else if (statusFilter.value === 'complete') {
        list = list.filter(s => isShipmentComplete(s));
    }

    if (!searchQuery.value) return list;
    
    const query = searchQuery.value.toLowerCase().trim();
    return list.filter(shipment => {
        if (shipment.tracking_number && shipment.tracking_number.toLowerCase().includes(query)) return true;
        if (shipment.sender && shipment.sender.toLowerCase().includes(query)) return true;
        if (shipment.kolis && shipment.kolis.some(koli => 
            (koli.barcode_id && koli.barcode_id.toLowerCase().includes(query)) ||
            (koli.description && koli.description.toLowerCase().includes(query))
        )) return true;
        return false;
    });
});

// Map seluruh koli
const expectedKolis = computed(() => {
    let kolis = [];
    localShipments.value.forEach(shipment => {
        (shipment.kolis || []).forEach(koli => {
            kolis.push({
                ...koli,
                shipment_id: shipment.id,
                tracking_number: shipment.tracking_number,
                sender: shipment.sender,
                current_status: shipment.current_status
            });
        });
    });
    return kolis;
});

// Pengiriman sinkronisasi instan per koli ke backend
const submitSingleKoli = async (koliId) => {
    isSyncingLive.value = true;

    // Update physical_status koli di memory localShipments secara instan
    for (const s of localShipments.value) {
        if (s.kolis) {
            const foundK = s.kolis.find(item => item.id === koliId);
            if (foundK) {
                foundK.physical_status = 'DI_GUDANG';
                if (s.kolis.every(k => isKoliScanned(k))) {
                    s.current_status = 'Di Gudang';
                } else {
                    s.current_status = 'Sebagian di Gudang';
                }
                break;
            }
        }
    }
    await localforage.setItem(DB_CACHE_KEY, localShipments.value);

    const job = {
        action: 'inbound_scan',
        koli_ids: [koliId],
        timestamp: new Date().getTime()
    };
    
    if (navigator.onLine) {
        try {
            await axios.post('/api/inbound/sync', job);
            isSyncingLive.value = false;
            return;
        } catch (err) {
            console.warn("Direct sync error, queueing offline:", err);
        }
    }

    let queue = (await localforage.getItem(DB_QUEUE_KEY)) || [];
    queue.push(job);
    await localforage.setItem(DB_QUEUE_KEY, queue);
    isSyncingLive.value = false;
};

const scrollToKoli = (koli) => {
    if (!koli) return;
    const bId = (koli.barcode_id || koli.barcode || '').toUpperCase();
    highlightedKoliId.value = bId;
    highlightedShipmentId.value = koli.shipment_id;

    // Jika filter tab menyembunyikan koli ini, sesuaikan filter agar kartu tampil
    const shipment = localShipments.value.find(s => s.id === koli.shipment_id);
    if (shipment) {
        if (statusFilter.value === 'complete' && !isShipmentComplete(shipment)) {
            statusFilter.value = 'all';
        } else if (statusFilter.value === 'pending' && isShipmentComplete(shipment)) {
            statusFilter.value = 'all';
        }
    }

    nextTick(() => {
        // Cari element koli atau kartu resi terkait
        const el = document.getElementById(`koli-item-${bId}`) || document.getElementById(`shipment-card-${koli.shipment_id}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    clearTimeout(highlightTimer);
    highlightTimer = setTimeout(() => {
        if (highlightedKoliId.value === bId) {
            highlightedKoliId.value = null;
            highlightedShipmentId.value = null;
        }
    }, 3500);
};

const scrollToShipment = (shipment) => {
    if (!shipment) return;
    highlightedShipmentId.value = shipment.id;

    nextTick(() => {
        const el = document.getElementById(`shipment-card-${shipment.id}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    clearTimeout(highlightTimer);
    highlightTimer = setTimeout(() => {
        if (highlightedShipmentId.value === shipment.id) {
            highlightedShipmentId.value = null;
        }
    }, 3500);
};

const toggleKoliScan = (koli) => {
    const code = (koli.barcode_id || koli.barcode || '').toUpperCase();
    if (!code) return;
    
    if (isKoliScanned(koli)) {
        scannedBarcodes.value = scannedBarcodes.value.filter(b => b.toUpperCase() !== code);
        koli.physical_status = 'MENUNGGU_PICKUP';
        showMessage('duplicate', `Dibatalkan: Koli "${code}"`);
    } else {
        if (!scannedBarcodes.value.some(b => b.toUpperCase() === code)) {
            scannedBarcodes.value.unshift(code);
        }
        koli.physical_status = 'DI_GUDANG';
        playBeep('success');
        if (navigator.vibrate) navigator.vibrate(60);
        showMessage('ok', `✅ Koli "${code}" tercentang dan masuk gudang!`);
        submitSingleKoli(koli.id);
    }
    scrollToKoli(koli);
};

const koliIdsToSubmit = computed(() => {
    return expectedKolis.value
        .filter(k => {
            const bId = (k.barcode_id || k.barcode || '').toUpperCase();
            return scannedBarcodes.value.some(s => s.toUpperCase() === bId);
        })
        .map(k => k.id);
});

const prosesBarcode = (kode) => {
    const val = (kode || barcodeInput.value).trim().toUpperCase();
    if (!val) return;
    barcodeInput.value = '';

    if (!kode) {
        focusActiveInput();
    }

    // 1. Cek apakah cocok langsung dengan Koli Barcode ID (1D Code 128)
    const foundKoli = expectedKolis.value.find(k => 
        (k.barcode_id && k.barcode_id.toUpperCase() === val) ||
        (k.barcode && k.barcode.toUpperCase() === val)
    );

    if (foundKoli) {
        const bId = (foundKoli.barcode_id || foundKoli.barcode).toUpperCase();
        
        // Cek jika koli ini sudah pernah di-scan atau sudah di gudang
        if (isKoliScanned(foundKoli)) {
            playBeep('duplicate');
            showMessage('duplicate', `⚠️ Koli "${bId}" sudah tercatat di gudang.`);
            scrollToKoli(foundKoli);
            return;
        }

        // Tandai fisik koli langsung di state
        foundKoli.physical_status = 'DI_GUDANG';
        if (!scannedBarcodes.value.some(b => b.toUpperCase() === bId)) {
            scannedBarcodes.value.unshift(bId);
        }

        playBeep('success');
        if (navigator.vibrate) navigator.vibrate(80);
        const koliNumInfo = foundKoli.koli_number && foundKoli.total_koli 
            ? `(Koli ${foundKoli.koli_number}/${foundKoli.total_koli})` 
            : `(${foundKoli.tracking_number})`;
        showMessage('ok', `✅ Berhasil scan: ${bId} ${koliNumInfo}`);
        submitSingleKoli(foundKoli.id);
        scrollToKoli(foundKoli);
        return;
    }

    // 2. Jika kode yang di-input adalah NOMOR RESI (Bukan Koli) -> Tolak & Berikan Edukasi + Scroll ke resi
    const matchingShipment = localShipments.value.find(s => 
        s.tracking_number && s.tracking_number.toUpperCase() === val
    );

    if (matchingShipment) {
        playBeep('error');
        showMessage('error', `⚠️ Kode "${matchingShipment.tracking_number}" adalah Nomor Resi! Wajib scan Barcode Garis Koli fisik.`);
        if (navigator.vibrate) navigator.vibrate([150, 80, 150]);
        scrollToShipment(matchingShipment);
        return;
    }

    // 3. Cerdas: Cek suffix jika operator hanya mengetik 3 karakter kode unik koli (misal 'KW9')
    if (val.length === 3) {
        const suffixMatches = expectedKolis.value.filter(k => {
            const bId = (k.barcode_id || k.barcode || '').toUpperCase();
            return bId.endsWith('-' + val) || bId.endsWith(val);
        });

        if (suffixMatches.length === 1) {
            const found = suffixMatches[0];
            const bId = (found.barcode_id || found.barcode).toUpperCase();
            if (isKoliScanned(found)) {
                playBeep('duplicate');
                showMessage('duplicate', `⚠️ Koli "${bId}" sudah pernah dicatat di gudang.`);
                scrollToKoli(found);
                return;
            }
            found.physical_status = 'DI_GUDANG';
            if (!scannedBarcodes.value.some(b => b.toUpperCase() === bId)) {
                scannedBarcodes.value.unshift(bId);
            }
            playBeep('success');
            if (navigator.vibrate) navigator.vibrate(80);
            showMessage('ok', `✅ Suffix cocok: ${bId} (${found.tracking_number})`);
            submitSingleKoli(found.id);
            scrollToKoli(found);
            return;
        } else if (suffixMatches.length > 1) {
            playBeep('error');
            showMessage('error', `⚠️ Suffix "${val}" cocok dengan beberapa koli berbeda. Harap ketik barcode lengkap.`);
            return;
        }
    }

    playBeep('error');
    showMessage('error', `❌ Barcode "${val}" tidak valid atau barang sudah di gudang.`);
    if (navigator.vibrate) navigator.vibrate([150, 80, 150]);
};

const handleScan = (decodedText) => {
    prosesBarcode(decodedText);
};

// Selesaikan & Commit Keseluruhan ke Server
const submitAllScan = async () => {
    if (koliIdsToSubmit.value.length === 0 || isSubmitting.value) return;

    isSubmitting.value = true;
    const ids = [...koliIdsToSubmit.value];
    
    const job = {
        action: 'inbound_scan',
        koli_ids: ids,
        timestamp: new Date().getTime()
    };
    
    if (navigator.onLine) {
        try {
            await axios.post('/api/inbound/sync', job);
            scannedBarcodes.value = [];
            scanMessage.value = null;
            isSubmitting.value = false;
            router.reload({ only: ['shipments'] });
            return;
        } catch (err) {
            console.warn("Direct commit sync failed, falling back to queue:", err);
        }
    }

    let queue = (await localforage.getItem(DB_QUEUE_KEY)) || [];
    queue.push(job);
    await localforage.setItem(DB_QUEUE_KEY, queue);
    
    alert('Data tersimpan secara lokal dan akan disinkronkan saat online.');
    scannedBarcodes.value = [];
    scanMessage.value = null;
    isSubmitting.value = false;
    syncToServer();
};
</script>

<template>
    <AdminLayout title="Terima Gudang" :noPaddingTop="true" :hideHeader="isScannerOpen">
        <!-- SEARCH DI HEADER ACTION -->
        <template #header_actions>
            <div class="flex items-center gap-1 md:gap-2 shrink-0">
                <!-- Mobile Search Toggle -->
                <button @click="isSearchExpanded = true" class="md:hidden flex items-center justify-center w-8 h-8 bg-white border border-gray-200 rounded-full text-gray-600 hover:bg-gray-50 hover:text-primary transition-all shadow-sm">
                    <i class="ri-search-line text-sm"></i>
                </button>
                
                <!-- Expanded Search Overlay (Mobile) -->
                <div v-if="isSearchExpanded" class="md:hidden absolute inset-y-0 left-0 right-0 bg-white z-50 flex items-center px-3 gap-2 shadow-sm animate-fade-in">
                    <i class="ri-search-line text-gray-400"></i>
                    <input type="text" v-model="searchQuery" placeholder="Cari resi, nama pengirim, nama barang..." class="flex-1 border-none bg-transparent outline-none text-sm px-2 focus:ring-0" autofocus>
                    <button @click="isSearchExpanded = false" class="p-1 text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>

                <!-- Input Search Desktop -->
                <div class="hidden md:block relative animate-fade-in transition-all">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input type="text" v-model="searchQuery" placeholder="Cari resi, nama, atau barang..." 
                           class="pl-9 pr-4 py-1.5 text-xs border border-gray-200 rounded-full focus:ring-primary focus:border-primary w-64 transition-all bg-gray-50 hover:bg-white outline-none shadow-sm" />
                </div>
            </div>
        </template>

        <!-- ==========================================
             UNIFIED STICKY SCANNER & CONTROL COCKPIT
             (Edge-to-edge di HP, rounded di Desktop, Gigit header jadi top-0 saat kamera aktif)
             ========================================== -->
        <div 
            :class="[
                isScannerOpen ? 'top-0' : 'top-12 md:top-16',
                'sticky z-30 -mx-4 md:mx-0 mb-3 bg-white border-b border-gray-200/90 shadow-md md:rounded-2xl md:border transition-all overflow-hidden'
            ]"
        >
            <!-- 1. Live Camera Preview (Fokus Tajam & Fit Pas: Mengambil alih area header) -->
            <div v-if="isScannerOpen" class="w-full h-[210px] sm:h-[225px] bg-black relative">
                <BarcodeScanner 
                    :cleanView="true"
                    :showClose="true"
                    initialMode="koli"
                    :allowModeSwitch="false"
                    @close="closeScanner"
                    @scan="handleScan" 
                    @error="(err) => console.log('Scanner error:', err)"
                >
                    <!-- Area Input Manual di Bawah Kotak Bidik (DI DALAM Camera Preview) -->
                    <template #below-frame>
                        <form @submit.prevent="prosesBarcode()" class="flex items-center gap-1.5 w-full">
                            <div class="relative flex-1">
                                <i class="ri-barcode-line absolute left-2.5 top-1/2 -translate-y-1/2 text-white/70 text-xs"></i>
                                <input 
                                    ref="cameraInputRef"
                                    type="text" 
                                    v-model="barcodeInput"
                                    placeholder="Ketik barcode (misal: KW9)..."
                                    class="w-full pl-7 pr-2 py-1 bg-black/75 hover:bg-black/90 focus:bg-black backdrop-blur-md border border-white/30 rounded-xl text-xs font-mono font-bold text-white placeholder-white/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all uppercase h-[30px] shadow-lg"
                                />
                            </div>
                            <Transition name="fade">
                                <button 
                                    v-if="barcodeInput && barcodeInput.trim().length > 0"
                                    type="submit" 
                                    class="px-3 bg-primary hover:bg-primaryDark text-white text-xs font-bold rounded-xl transition-all active:scale-95 shrink-0 h-[30px] flex items-center justify-center shadow-lg cursor-pointer animate-fade-in"
                                >
                                    Input
                                </button>
                            </Transition>
                        </form>
                    </template>
                </BarcodeScanner>
            </div>

            <!-- 2. Integrated Control & Input Bar (Ramping & Terpadu) -->
            <div class="p-2.5 sm:p-3 bg-white">
                <!-- Baris Info Status & Commit -->
                <div class="flex items-center justify-between gap-2 px-0.5">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="font-black text-xs sm:text-sm text-gray-900 truncate">Inbound Gudang</span>
                        <span class="bg-primary/10 text-primary text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0 font-mono">
                            {{ scannedBarcodes.length }}/{{ expectedKolis.length }} Koli
                        </span>
                        <span v-if="isSyncingLive" class="text-[10px] text-primary font-bold animate-pulse hidden sm:inline">
                            <i class="ri-refresh-line animate-spin"></i>
                        </span>
                    </div>

                    <!-- Tombol Commit Massal jika ada koli pending -->
                    <div v-if="koliIdsToSubmit.length > 0" class="shrink-0">
                        <button 
                            @click="submitAllScan" 
                            type="button"
                            :disabled="isSubmitting"
                            class="px-2.5 sm:px-3 py-1 rounded-xl text-xs font-bold bg-green-600 hover:bg-green-700 text-white flex items-center gap-1 transition-all shadow-sm active:scale-95 cursor-pointer"
                        >
                            <i class="ri-check-double-line text-sm"></i>
                            <span>Commit ({{ koliIdsToSubmit.length }})</span>
                        </button>
                    </div>
                </div>

                <!-- Progress Line Ramping (2px) -->
                <div class="w-full bg-gray-100 h-1 rounded-full overflow-hidden mt-1.5 relative">
                    <div 
                        class="h-full bg-gradient-to-r from-primary to-green-500 transition-all duration-500 rounded-full"
                        :style="{ width: expectedKolis.length > 0 ? ((scannedBarcodes.length / expectedKolis.length) * 100) + '%' : '0%' }"
                    ></div>
                </div>

                <!-- Form Input Barcode saat Camera Preview DITUTUP (Tersedia scan gun / keyboard input + tombol icon kamera) -->
                <form v-if="!isScannerOpen" @submit.prevent="prosesBarcode()" class="mt-2 flex items-center gap-1.5">
                    <div class="relative flex-1">
                        <i class="ri-barcode-line absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input 
                            ref="closedInputRef"
                            type="text" 
                            v-model="barcodeInput"
                            placeholder="Scan gun / ketik barcode (misal: KW9)..."
                            class="w-full pl-8 pr-2 py-1.5 bg-gray-50 hover:bg-white focus:bg-white border border-gray-200 rounded-xl text-xs font-mono font-bold text-gray-900 placeholder:font-sans placeholder-gray-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all uppercase h-[34px]"
                        />
                    </div>
                    
                    <!-- Tombol Input Manual (Hanya Muncul saat ada isinya) -->
                    <Transition name="fade">
                        <button 
                            v-if="barcodeInput && barcodeInput.trim().length > 0"
                            type="submit" 
                            class="px-3.5 bg-gray-900 hover:bg-black text-white text-xs font-bold rounded-xl transition-all active:scale-95 shrink-0 h-[34px] flex items-center justify-center cursor-pointer animate-fade-in"
                        >
                            Input
                        </button>
                    </Transition>

                    <!-- Tombol Buka Scanner (Hanya Icon Barcode di Samping Tombol Input) -->
                    <button 
                        @click="openScanner" 
                        type="button"
                        class="w-[34px] h-[34px] rounded-xl flex items-center justify-center transition-all shadow-sm active:scale-95 shrink-0 cursor-pointer bg-primary hover:bg-primaryDark text-white shadow-primary/20"
                        title="Buka Camera Preview"
                    >
                        <i class="ri-barcode-box-line text-lg"></i>
                    </button>
                </form>

                <!-- Floating Feedback Toast / Pesan Scan -->
                <Transition name="fade">
                    <div 
                        v-if="scanMessage" 
                        class="mt-1.5 px-3 py-1.5 rounded-xl text-xs font-bold flex items-center justify-between gap-2 shadow-sm border transition-all"
                        :class="[
                            scanMessage.type === 'ok' ? 'bg-green-50 border-green-200 text-green-800' :
                            scanMessage.type === 'duplicate' ? 'bg-amber-50 border-amber-200 text-amber-800' :
                            'bg-red-50 border-red-200 text-red-800'
                        ]"
                    >
                        <div class="flex items-center gap-1.5 truncate">
                            <i :class="[
                                scanMessage.type === 'ok' ? 'ri-checkbox-circle-fill text-green-600' :
                                scanMessage.type === 'duplicate' ? 'ri-information-fill text-amber-600' :
                                'ri-error-warning-fill text-red-600'
                            ]"></i>
                            <span class="truncate">{{ scanMessage.text }}</span>
                        </div>
                        <button @click="scanMessage = null" class="text-gray-400 hover:text-gray-600 text-sm">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                </Transition>
            </div>
        </div>

        <div class="space-y-3 max-w-5xl mx-auto">

            <!-- ==========================================
                 LIST RESI & KOLI (REALTIME CHECKLIST)
                 ========================================== -->
            <div>
                <!-- Filter Tabs Ramping: Perlu Di-scan | Sudah Lengkap | Semua -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1.5 mb-2.5 px-1 scrollbar-none">
                    <button 
                        type="button" 
                        @click="statusFilter = 'pending'"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shrink-0"
                        :class="statusFilter === 'pending' ? 'bg-primary text-white shadow' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                    >
                        <i class="ri-time-line"></i>
                        <span>Perlu Di-scan</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="statusFilter === 'pending' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'">
                            {{ pendingCount }}
                        </span>
                    </button>
                    <button 
                        type="button" 
                        @click="statusFilter = 'complete'"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shrink-0"
                        :class="statusFilter === 'complete' ? 'bg-green-600 text-white shadow' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                    >
                        <i class="ri-checkbox-circle-line"></i>
                        <span>Sudah Lengkap</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="statusFilter === 'complete' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'">
                            {{ completedCount }}
                        </span>
                    </button>
                    <button 
                        type="button" 
                        @click="statusFilter = 'all'"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shrink-0"
                        :class="statusFilter === 'all' ? 'bg-dark text-white shadow' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                    >
                        <i class="ri-list-check"></i>
                        <span>Semua Resi</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="statusFilter === 'all' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'">
                            {{ (localShipments || []).length }}
                        </span>
                    </button>
                </div>

                <!-- Empty State -->
                <div v-if="filteredShipments.length === 0" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                    <div class="w-16 h-16 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="ri-checkbox-circle-line text-3xl font-bold"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm">
                        {{ statusFilter === 'pending' ? 'Semua Koli Sudah Diterima di Gudang!' : 'Tidak Ada Resi yang Cocok' }}
                    </h4>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ statusFilter === 'pending' ? 'Tidak ada paket dari penjemputan yang masih menggantung.' : 'Coba ubah tab filter atau kata kunci pencarian.' }}
                    </p>
                </div>

                <!-- Card Shipments & Kolis -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div 
                        v-for="shipment in filteredShipments" 
                        :key="shipment.id" 
                        :id="'shipment-card-' + shipment.id"
                        class="bg-white rounded-2xl shadow-sm border p-4 transition-all duration-300"
                        :class="[
                            highlightedShipmentId === shipment.id 
                                ? 'ring-2 ring-primary border-primary shadow-lg ring-offset-2' 
                                : isShipmentComplete(shipment) 
                                    ? 'border-green-300 ring-1 ring-green-100 bg-green-50/15' 
                                    : 'border-gray-200/80'
                        ]"
                    >
                        <!-- Header Resi -->
                        <div class="flex justify-between items-start mb-2.5">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-black text-sm text-gray-900 tracking-wide">{{ shipment.tracking_number }}</span>
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 font-mono">
                                        {{ shipment.total_koli }} Koli
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5 flex items-center gap-1 truncate max-w-[200px]">
                                    <i class="ri-user-3-line text-gray-400"></i> {{ shipment.sender }}
                                </p>
                            </div>
                            
                            <!-- Status Badge -->
                            <span 
                                v-if="isShipmentComplete(shipment)" 
                                class="text-[11px] px-2.5 py-1 bg-green-100 text-green-700 font-bold rounded-full border border-green-200 flex items-center gap-1 shrink-0"
                            >
                                <i class="ri-check-line font-black"></i> Lengkap
                            </span>
                            <span 
                                v-else-if="getScannedKolisCount(shipment) > 0" 
                                class="text-[11px] px-2.5 py-1 bg-amber-50 text-amber-700 font-bold rounded-full border border-amber-200 shrink-0 flex items-center gap-1"
                            >
                                <i class="ri-time-line"></i> {{ getScannedKolisCount(shipment) }}/{{ shipment.total_koli }} Koli
                            </span>
                            <span 
                                v-else 
                                class="text-[10px] px-2 py-0.5 bg-gray-100 text-gray-600 font-bold rounded-full shrink-0"
                            >
                                Belum Scan
                            </span>
                        </div>

                        <!-- Mini Progress Bar -->
                        <div class="mb-3 bg-gray-50/80 p-2 rounded-xl border border-gray-100">
                            <div class="flex justify-between items-center text-[10px] text-gray-500 mb-1 font-semibold">
                                <span>Status Fisik:</span>
                                <span :class="isShipmentComplete(shipment) ? 'text-green-600 font-bold' : 'text-amber-600 font-bold'">
                                    {{ getScannedKolisCount(shipment) }} dari {{ shipment.total_koli }} Koli ({{ getShipmentProgress(shipment) }}%)
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                                <div 
                                    class="h-full transition-all duration-300 rounded-full"
                                    :class="isShipmentComplete(shipment) ? 'bg-green-500' : 'bg-primary'"
                                    :style="{ width: `${getShipmentProgress(shipment)}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Daftar Koli Fisik (Semua Koli Tetap Tampil Lengkap) -->
                        <div class="space-y-1.5 border-t border-gray-100 pt-3">
                            <div 
                                v-for="koli in shipment.kolis" 
                                :key="koli.id" 
                                :id="'koli-item-' + (koli.barcode_id || koli.barcode || koli.id).toUpperCase()"
                                class="flex items-center justify-between text-xs p-2 rounded-xl transition-all duration-300 border select-none"
                                :class="[
                                    highlightedKoliId === (koli.barcode_id || koli.barcode || '').toUpperCase() 
                                        ? 'ring-2 ring-primary ring-offset-1 bg-amber-50/90 border-primary font-bold shadow-md scale-[1.01]' 
                                        : isKoliScanned(koli) 
                                            ? 'bg-green-50/90 border-green-300 text-green-900 shadow-sm' 
                                            : 'bg-gray-50/70 border-gray-100 text-gray-700'
                                ]"
                            >
                                <div class="min-w-0 pr-2">
                                    <div class="truncate font-mono font-bold flex items-center gap-1.5" :class="isKoliScanned(koli) ? 'text-green-800' : 'text-gray-800'">
                                        <i class="ri-barcode-line text-xs" :class="isKoliScanned(koli) ? 'text-green-600' : 'text-gray-400'"></i>
                                        <span>{{ koli.barcode_id || koli.barcode }}</span>
                                        <span v-if="koli.koli_number" class="text-[10px] font-normal px-1 rounded bg-white/70 border border-black/5">
                                            #{{ koli.koli_number }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-gray-500 truncate mt-0.5">
                                        {{ koli.description || 'Paket / Koli' }}
                                    </div>
                                </div>
                                <div class="shrink-0 flex items-center gap-1.5">
                                    <span v-if="isKoliScanned(koli)" class="text-[9px] font-bold text-green-700 bg-green-200/60 px-1.5 py-0.5 rounded">
                                        Di Gudang
                                    </span>
                                    <span v-else class="text-[9px] font-medium text-gray-400 bg-gray-200/50 px-1.5 py-0.5 rounded">
                                        Belum Scan
                                    </span>
                                    <i :class="isKoliScanned(koli) ? 'ri-checkbox-circle-fill text-green-600 text-lg' : 'ri-checkbox-blank-circle-line text-gray-300 text-lg'"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
