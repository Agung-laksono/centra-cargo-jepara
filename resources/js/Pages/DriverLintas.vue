<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Sortable from 'sortablejs';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import BarcodeScanner from '@/Components/BarcodeScanner.vue';
import localforage from 'localforage';
import axios from 'axios';
import { useLocationTracking } from '@/Composables/useLocationTracking';
import { Fullscreen } from '@boengli/capacitor-fullscreen';
import { StatusBar } from '@capacitor/status-bar';
import DriverProfileModal from '@/Components/DriverProfileModal.vue';

const props = defineProps({
    manifest: Object,
    deliveries: Array,
});

const isProfileModalOpen = ref(false);

// Gunakan props jika ada, atau fallback ke simulasi jika sedang dipreview
const getInitialPengantaran = () => {
    if (props.deliveries && props.deliveries.length > 0) {
        return props.deliveries.map(d => ({
            id: d.id,
            titik: d.tujuan,
            resi: d.resi,
            penerima: d.penerima,
            alamat: d.tujuan, 
            alamatLengkap: d.alamat_lengkap || d.tujuan,
            koli: d.kolis ? d.kolis.length : 1, 
            kolis: d.kolis ? d.kolis.map(k => ({ ...k, scanned: false })) : [],
            deskripsi: d.deskripsi || (d.kolis ? d.kolis[0]?.deskripsi : ''),
            isCod: d.isCod !== undefined ? d.isCod : false, 
            tagihanCod: d.tagihanCod || 0,
            selesai: d.status === 'Selesai',
        }));
    }
    // Data simulasi daftar pengantaran
    return [
        {
            id: 1,
            titik: 'Titik 1',
            resi: 'JPR-2610-001',
            penerima: 'Bpk. Budi Santoso',
            alamat: 'Jl. Mawar No. 10, RT 01/RW 02, Kebayoran Baru, Jakarta Selatan.',
            alamatLengkap: 'Jl. Mawar No. 10, RT 01/RW 02, Kebayoran Baru, Jakarta Selatan.',
            koli: 3,
            kolis: [
                { id: 101, barcode: 'JPR-2610-001-1', deskripsi: 'Meja Makan Jati', scanned: false },
                { id: 102, barcode: 'JPR-2610-001-2', deskripsi: 'Kursi Makan (Set 1)', scanned: false },
                { id: 103, barcode: 'JPR-2610-001-3', deskripsi: 'Kaca Meja', scanned: false }
            ],
            deskripsi: 'Meja Makan Jati (Set)',
            isCod: true,
            tagihanCod: 3500000,
            selesai: false
        },
        {
            id: 2,
            titik: 'Titik 2',
            resi: 'JPR-2610-002',
            penerima: 'Ibu Ratna',
            alamat: 'Apartemen Sudirman Tower, Lantai 15 Unit C, Jakarta Pusat.',
            alamatLengkap: 'Apartemen Sudirman Tower, Lantai 15 Unit C, Jakarta Pusat.',
            koli: 1,
            kolis: [
                { id: 104, barcode: 'JPR-2610-002-1', deskripsi: 'Kursi Teras', scanned: false }
            ],
            deskripsi: 'Kursi Teras',
            isCod: false,
            tagihanCod: 0,
            selesai: false
        }
    ];
};

const pengantaranData = ref(getInitialPengantaran());
const isReordering = ref(false);
const sortableContainer = ref(null);
let sortableInstance = null;

const moveUp = (index) => {
    if (index > 0) {
        const temp = pengantaranData.value[index];
        pengantaranData.value[index] = pengantaranData.value[index - 1];
        pengantaranData.value[index - 1] = temp;
    }
};

const moveDown = (index) => {
    if (index < pengantaranData.value.length - 1) {
        const temp = pengantaranData.value[index];
        pengantaranData.value[index] = pengantaranData.value[index + 1];
        pengantaranData.value[index + 1] = temp;
    }
};

const activeManifest = computed(() => props.manifest || {
    id: 'SIMULASI-001',
    driver_name: 'Supir Simulasi',
});

// Mulai lacak lokasi supir otomatis
const { isTracking, hasSynced, lastSyncTime, trackingError } = useLocationTracking(activeManifest.value.token);

const isSticky = ref(false);

const handleScroll = () => {
    isSticky.value = window.scrollY > 100;
};

// Hybrid Database & Sync Logic
const DB_STORE_KEY = 'driver_deliveries';
const DB_QUEUE_KEY = 'sync_queue';

const initializeOfflineDB = async () => {
    const queue = await localforage.getItem(DB_QUEUE_KEY) || [];
    const isLocalClean = queue.length === 0;

    if (navigator.onLine && isLocalClean && props.deliveries && props.deliveries.length > 0) {
        // Jika online dan antrean sinkronisasi kosong, prioritaskan data baru dari server
        pengantaranData.value = getInitialPengantaran();
        await localforage.setItem(DB_STORE_KEY, pengantaranData.value);
    } else {
        // Jika offline atau ada antrean, prioritaskan data lokal di HP
        const localData = await localforage.getItem(DB_STORE_KEY);
        if (localData) {
            pengantaranData.value = localData;
        } else {
            await localforage.setItem(DB_STORE_KEY, pengantaranData.value);
        }
    }
    
    // Coba sinkronisasi jika ada antrean
    syncToServer();
};

let saveDebounceTimer = null;
const saveToLocalDB = async () => {
    await localforage.setItem(DB_STORE_KEY, pengantaranData.value);
};

// Pantau perubahan dengan debounce 1 detik agar tidak spam write ke IndexedDB
watch(pengantaranData, () => {
    clearTimeout(saveDebounceTimer);
    saveDebounceTimer = setTimeout(() => saveToLocalDB(), 1000);
}, { deep: true });

const queueSyncJob = async (action, data) => {
    let queue = await localforage.getItem(DB_QUEUE_KEY) || [];
    queue.push({ action, data, timestamp: new Date().getTime() });
    await localforage.setItem(DB_QUEUE_KEY, queue);
    syncToServer(); // Langsung coba sinkronkan
};

const syncToServer = async () => {
    if (!navigator.onLine) return; // Batal jika sedang offline
    
    let queue = await localforage.getItem(DB_QUEUE_KEY) || [];
    if (queue.length === 0) return;
    
    console.log(`Menyinkronkan ${queue.length} tugas ke server...`);
    let failedQueue = [];
    
    for (let job of queue) {
        try {
            // Ini akan ditembak ke Laravel API Endpoint Anda nanti
            await axios.post('/api/driver/sync', job);
            console.log('Sinkronisasi sukses:', job.action);
        } catch (error) {
            console.error('Sinkronisasi gagal, akan diulang:', error);
            failedQueue.push(job); // Simpan kembali yang gagal
        }
    }
    
    // Simpan sisa antrean yang gagal kembali ke HP
    await localforage.setItem(DB_QUEUE_KEY, failedQueue);
};

onMounted(() => {
    window.addEventListener('online', syncToServer);
    initializeOfflineDB();
    
    // Auto-redirect jika dibuka di browser HP (bukan di dalam aplikasi)
    if (typeof window !== 'undefined' && window.Capacitor?.isNativePlatform?.()) {
        // Memunculkan kembali top & bottom bar sistem HP untuk halaman Supir
        Fullscreen.deactivateImmersiveMode().catch(() => {});
        StatusBar.setOverlaysWebView({ overlay: false }).catch(() => {});
        StatusBar.show().catch(() => {});
    } else if (typeof window !== 'undefined' && !window.Capacitor?.isNativePlatform?.()) {
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        if (token && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
            // Coba buka aplikasi
            window.location.href = `jeparacargo://${window.location.pathname.substring(1)}?token=${token}`;
        }
    }
    
    window.addEventListener('scroll', handleScroll);
    
    const el = sortableContainer.value?.$el || sortableContainer.value;
    
    if (el) {
        sortableInstance = new Sortable(el, {
            animation: 250,
            handle: '.drag-handle',
            disabled: !isReordering.value,
            forceFallback: true,
            fallbackClass: 'shadow-2xl ring-2 ring-primary bg-white opacity-90',
            ghostClass: 'opacity-30',
            onEnd: (evt) => {
                const item = pengantaranData.value.splice(evt.oldIndex, 1)[0];
                pengantaranData.value.splice(evt.newIndex, 0, item);
            }
        });
    }
});

watch(isReordering, (val) => {
    if (sortableInstance) {
        sortableInstance.option('disabled', !val);
    }
});

const toggleReordering = async () => {
    isReordering.value = !isReordering.value;
    
    // Jika supir baru saja selesai mengatur rute
    if (!isReordering.value) {
        await saveToLocalDB(); // Simpan urutan baru ke IndexedDB
        
        // Ambil daftar ID berurutan untuk dikirim ke server
        const newOrderIds = pengantaranData.value.map(item => item.id);
        await queueSyncJob('update_route_order', { order: newOrderIds });
    }
};

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('online', syncToServer);
    
    if (typeof window !== 'undefined' && window.Capacitor?.isNativePlatform?.()) {
        // Kembalikan ke mode Fullscreen saat keluar dari halaman Supir
        Fullscreen.activateImmersiveMode().catch(() => {});
        StatusBar.setOverlaysWebView({ overlay: true }).catch(() => {});
    }
});

// Modal State
const isModalOpen = ref(false);
const selectedAntaran = ref(null);
const uploadedImage = ref(null);
const namaPenerima = ref('');
const isScannerOpen = ref(false);

const bukaModal = (item) => {
    selectedAntaran.value = item;
    isModalOpen.value = true;
    uploadedImage.value = item.fotoBase64 || null; // Ambil foto dari IndexedDB jika sudah pernah foto
    namaPenerima.value = item.penerima || '';
};

const bukaScanner = () => {
    isScannerOpen.value = true;
};

const manualBarcode = ref('');
const koliMessage = ref({ type: '', text: '' }); // type: 'success' | 'error'
let koliMessageTimer = null;

const showKoliMessage = (type, text) => {
    koliMessage.value = { type, text };
    clearTimeout(koliMessageTimer);
    koliMessageTimer = setTimeout(() => {
        koliMessage.value = { type: '', text: '' };
    }, 3000);
};

const submitManualBarcode = () => {
    let input = manualBarcode.value.trim().toUpperCase();
    if (!input) return;

    if (selectedAntaran.value && selectedAntaran.value.kolis) {
        // Cek kecocokan koli: barcode lengkap, akhiran suffix 3 huruf, atau resi + suffix
        const koli = selectedAntaran.value.kolis.find(k => {
            const b = (k.barcode || '').toUpperCase();
            return b === input || 
                   b.endsWith('-' + input) || 
                   b === (selectedAntaran.value.resi + '-' + input).toUpperCase();
        });

        if (koli) {
            koli.scanned = true;
            manualBarcode.value = ''; // clear on success
            showKoliMessage('success', 'Koli berhasil divalidasi!');
        } else {
            showKoliMessage('error', 'Kode salah atau tidak ditemukan di resi ini.');
        }
    }
};

const onBarcodeScanned = (barcode) => {
    isScannerOpen.value = false;
    if (selectedAntaran.value && selectedAntaran.value.kolis) {
        const cleanCode = (barcode || '').trim().toUpperCase();
        
        // 1. Cek kecocokan koli spesifik (barcode lengkap atau suffix)
        const koli = selectedAntaran.value.kolis.find(k => {
            const b = (k.barcode || '').toUpperCase();
            return b === cleanCode || b.endsWith('-' + cleanCode);
        });
        if (koli) {
            koli.scanned = true;
            showKoliMessage('success', 'Barcode cocok! Koli divalidasi.');
            return;
        }

        // 2. Jika yang discan adalah nomor resi (Bukan Koli)
        if (cleanCode === selectedAntaran.value.resi.toUpperCase() || cleanCode.includes(selectedAntaran.value.resi.toUpperCase())) {
            showKoliMessage('error', '⚠️ Ini Nomor Resi! Wajib scan Barcode Garis Koli fisik.');
            return;
        }

        showKoliMessage('error', 'Barcode tidak cocok dengan resi atau koli ini!');
    }
};

const semuaKoliTervalidasi = computed(() => {
    if (!selectedAntaran.value) return false;
    if (!selectedAntaran.value.kolis || selectedAntaran.value.kolis.length === 0) return true;
    return selectedAntaran.value.kolis.every(k => k.scanned);
});

const totalTagihanCod = computed(() => {
    return pengantaranData.value
        .filter(d => d.isCod)
        .reduce((sum, d) => sum + Number(d.tagihanCod || 0), 0);
});

const tagihanTerkumpul = computed(() => {
    return pengantaranData.value
        .filter(d => d.isCod && d.selesai)
        .reduce((sum, d) => sum + Number(d.tagihanCod || 0), 0);
});

const handleImageUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
        // Ubah gambar menjadi Base64 string agar bisa disimpan di IndexedDB secara Offline
        const reader = new FileReader();
        reader.onload = (event) => {
            uploadedImage.value = event.target.result;
            if(selectedAntaran.value) {
                selectedAntaran.value.fotoBase64 = event.target.result;
            }
        };
        reader.readAsDataURL(file);
    }
};

const isKendalaModalOpen = ref(false);
const kendalaAlasan = ref('');

const bukaModalKendala = () => {
    kendalaAlasan.value = '';
    isKendalaModalOpen.value = true;
};

const kirimKendala = async () => {
    if (kendalaAlasan.value.trim()) {
        if(selectedAntaran.value) {
            selectedAntaran.value.selesai = true; // Mark as done partially in UI
            selectedAntaran.value.kendala = kendalaAlasan.value.trim();
            
            await saveToLocalDB();
            await queueSyncJob('lapor_kendala', { id: selectedAntaran.value.id, alasan: selectedAntaran.value.kendala });
        }
        // Gunakan custom alert/toast nanti, sementara biarkan alert native atau hilangkan saja
        isKendalaModalOpen.value = false;
        isModalOpen.value = false;
    }
};

const selesaikanAntaran = async () => {
    if(selectedAntaran.value) {
        selectedAntaran.value.selesai = true;
        
        await saveToLocalDB();
        await queueSyncJob('selesaikan_antaran', { 
            id: selectedAntaran.value.id,
            fotoBase64: selectedAntaran.value.fotoBase64 || null
        });
    }
    alert("Berhasil! Data POD tersimpan aman (Otomatis tersinkron jika ada sinyal).");
    isModalOpen.value = false;
};

const formatRupiah = (angka) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
};
</script>

<template>
    <Head title="Surat Jalan" />

    <!-- Latar Belakang Status Bar Android -->
    <div class="fixed top-0 left-0 right-0 h-[35px] transition-colors duration-300 z-[100]" :class="isSticky ? 'bg-primary' : 'bg-transparent'"></div>

    <div class="bg-[#f8f9fc] min-h-[100dvh] font-sans antialiased text-gray-800 pb-[95px] max-w-md mx-auto shadow-2xl relative selection:bg-primary/20">
        
        <!-- Header with Glassmorphism -->
        <header class="bg-gradient-to-br from-primary to-primaryDark text-white px-4 pb-5 rounded-b-[1.5rem] shadow-md relative z-10 overflow-hidden" style="padding-top: calc(35px + 1.25rem);">
            <!-- Decorative circle -->
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
            
            <div class="flex justify-between items-center mb-3 relative z-10">
                <div class="flex items-center gap-2">
                    <Link v-if="$page.props.auth && $page.props.auth.user" href="/manifest" class="bg-white/20 hover:bg-white/30 p-1.5 rounded-full backdrop-blur-md border border-white/20 transition-colors flex items-center justify-center">
                        <i class="ri-arrow-left-line text-white text-base"></i>
                    </Link>
                    <div class="w-8 h-8 rounded-full flex items-center justify-center backdrop-blur-sm transition-colors duration-1000"
                         :class="hasSynced && !trackingError ? 'bg-green-400/20 border border-green-300/40' : 'bg-white/20 border border-white/30'">
                        <i class="ri-truck-fill text-lg transition-all duration-1000" :class="hasSynced && !trackingError ? 'text-green-100 animate-pulse' : 'text-white'"></i>
                    </div>
                    <h1 class="font-bold text-base tracking-wide">{{ $page.props.company?.name || 'Aplikasi Cargo' }}</h1>
                </div>
                <div class="flex items-center gap-2">
                    <div class="text-[9px] bg-white/20 px-2.5 py-1 rounded-full font-bold tracking-widest uppercase border border-white/20 backdrop-blur-md">
                        Tugas Lintas
                    </div>
                    <button
                        type="button"
                        @click="isProfileModalOpen = true"
                        class="w-7 h-7 rounded-full bg-white/20 hover:bg-white/30 border border-white/25 flex items-center justify-center text-white active:scale-95 transition-all shadow-sm"
                        title="Profil & Akun Supir"
                    >
                        <i class="ri-user-3-line text-xs"></i>
                    </button>
                </div>
            </div>
            
            <div class="relative z-10 flex flex-col items-start mt-2">
                <p class="text-[10px] text-white/80 font-medium uppercase tracking-wider mb-0.5">Manifest Perjalanan</p>
                <h2 class="text-2xl font-black tracking-tight drop-shadow-sm">MNF-{{ activeManifest.id }}</h2>
                <div class="flex flex-wrap gap-1.5 mt-2">
                    <button
                        type="button"
                        @click="isProfileModalOpen = true"
                        class="flex items-center gap-1.5 text-xs font-medium bg-black/20 hover:bg-black/35 active:scale-95 px-2 py-1 rounded border border-white/20 backdrop-blur-sm text-white transition-all cursor-pointer shadow-sm group"
                        title="Buka Info Profil & Keluar Aplikasi"
                    >
                        <i class="ri-user-star-fill text-white/90 group-hover:scale-110 transition-transform"></i>
                        <span>{{ activeManifest.driver_name }}</span>
                        <i class="ri-arrow-down-s-line text-white/60 group-hover:text-white text-xs"></i>
                    </button>
                    <div v-if="totalTagihanCod > 0" class="flex items-center gap-1.5 text-xs font-bold bg-green-500/20 text-green-100 px-2 py-1 rounded border border-green-400/30 backdrop-blur-sm shadow-inner">
                        <i class="ri-money-dollar-circle-fill text-green-300 text-sm"></i>
                        <span>COD: {{ formatRupiah(tagihanTerkumpul) }} / {{ formatRupiah(totalTagihanCod) }}</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Progress Summary -->
        <div class="relative z-40 transition-all duration-300" :class="[!isReordering ? 'sticky top-[35px]' : '', (isSticky && !isReordering) ? 'px-0 pt-0' : 'px-4 -mt-4']">
            <div class="bg-white/95 shadow-lg flex justify-between items-center border border-gray-100/50 backdrop-blur-xl transition-all duration-300" :class="(isSticky && !isReordering) ? 'rounded-none py-2 px-3 shadow-sm' : 'rounded-xl py-3 px-3'">
                <div class="flex-1 border-r border-gray-100 transition-all duration-300" :class="isSticky ? 'flex justify-center items-center gap-1.5' : 'text-center'">
                    <p class="font-bold text-gray-400 uppercase tracking-widest transition-all duration-300 text-[9px] mb-0.5">Titik</p>
                    <p class="font-black text-gray-800 transition-all duration-300" :class="isSticky ? 'text-sm' : 'text-lg'">{{ pengantaranData.length }}</p>
                </div>
                <div class="flex-1 border-r border-gray-100 transition-all duration-300" :class="isSticky ? 'flex justify-center items-center gap-1.5' : 'text-center'">
                    <p class="font-bold text-gray-400 uppercase tracking-widest transition-all duration-300 text-[9px] mb-0.5">Koli</p>
                    <p class="font-black text-gray-800 transition-all duration-300" :class="isSticky ? 'text-sm' : 'text-lg'">{{ pengantaranData.reduce((acc, curr) => acc + curr.koli, 0) }}</p>
                </div>
                <div class="flex-1 transition-all duration-300" :class="isSticky ? 'flex justify-center items-center gap-1.5' : 'text-center'">
                    <p class="font-bold text-gray-400 uppercase tracking-widest transition-all duration-300 text-[9px] mb-0.5">Selesai</p>
                    <p class="font-black text-green-500 transition-all duration-300" :class="isSticky ? 'text-sm' : 'text-lg'">{{ pengantaranData.filter(i => i.selesai).length }} <span class="text-gray-300 transition-all duration-300 text-xs">/ {{ pengantaranData.length }}</span></p>
                </div>
            </div>
        </div>

        <!-- Destination List -->
        <div class="px-3 pt-3 pb-4 mt-1 space-y-3">
            <div 
                class="flex items-center justify-between transition-all duration-300 z-30"
                :class="isReordering ? 'sticky top-[35px] bg-[#f8f9fc]/95 backdrop-blur-xl py-3 px-4 -mx-3 shadow-sm border-b border-gray-200/60 rounded-b-lg mb-3' : 'mb-2 px-1'"
            >
                <div class="flex items-center gap-2">
                    <h3 class="font-black text-gray-800 text-xs uppercase tracking-widest">Daftar Pengantaran</h3>
                </div>
                <!-- Tombol Atur Rute -->
                <button @click="toggleReordering" class="flex items-center gap-1 px-2.5 py-1 rounded text-[10px] font-bold transition-all" :class="isReordering ? 'bg-primary text-white shadow' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'">
                    <i :class="isReordering ? 'ri-check-line' : 'ri-list-settings-line'"></i>
                    {{ isReordering ? 'Selesai' : 'Atur Rute' }}
                </button>
            </div>

            <!-- Kartu Titik Antar (Raw SortableJS & TransitionGroup) -->
            <transition-group name="list" tag="div" ref="sortableContainer" class="flex flex-col gap-2.5">
                <div 
                    v-for="(element, index) in pengantaranData"
                    :key="element.id"
                    class="bg-white rounded-xl shadow-sm hover:shadow-md border border-gray-100/80 overflow-hidden transition-all duration-300 hover:-translate-y-1.5 active:scale-[0.98] cursor-pointer relative group"
                    :class="[
                        element.selesai ? 'opacity-75 grayscale-[20%]' : '',
                        isReordering ? 'py-1.5' : ''
                    ]"
                >
                        <!-- Border Kiri -->
                        <div class="absolute left-0 top-0 w-1 h-full" :class="element.selesai ? 'bg-green-400' : 'bg-primary'"></div>
                        
                        <div v-show="!isReordering" class="border-b border-gray-50 p-2.5 flex justify-between items-center" :class="element.isCod ? 'bg-gradient-to-r from-primary/5 to-white' : ''">
                            <div class="flex items-center gap-1.5">
                                <span class="w-4 h-4 rounded flex items-center justify-center text-[8px] font-black shadow-sm" :class="element.selesai ? 'bg-green-100 text-green-600' : 'bg-primary/10 text-primary'">{{ index + 1 }}</span>
                                <span class="text-[10px] font-bold text-gray-500 tracking-wider">RESI: <span class="text-gray-800">{{ element.resi }}</span></span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span v-if="element.isCod" class="text-[8px] font-black text-red-600 bg-red-100 px-1 py-0.5 rounded border border-red-200">COD</span>
                                <span v-if="element.selesai" class="text-[8px] font-black text-green-600 bg-green-100 px-1 py-0.5 rounded border border-green-200"><i class="ri-check-double-line"></i> SELESAI</span>
                            </div>
                        </div>
                        
                        <div class="p-2.5" :class="isReordering ? 'flex justify-between items-center' : 'pt-2'">
                            <!-- Compact Mode Header -->
                            <div v-if="isReordering" class="flex items-center gap-2.5">
                                <div class="w-5 h-5 rounded flex items-center justify-center text-[9px] font-black bg-gray-100 text-gray-500">
                                    {{ index + 1 }}
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-gray-800 text-xs leading-tight">{{ element.penerima }}</h4>
                                    <p class="text-[10px] text-gray-500 mt-0.5 font-medium truncate max-w-[160px]">{{ element.alamat }}</p>
                                </div>
                            </div>
                            
                            <!-- Up/Down arrows and Drag Handle in compact mode -->
                            <div v-if="isReordering" class="flex items-center gap-0.5 shrink-0">
                                <div class="drag-handle touch-none p-1.5 text-gray-400 hover:text-primary cursor-grab active:cursor-grabbing bg-gray-50 rounded">
                                    <i class="ri-draggable text-lg"></i>
                                </div>
                                <button @click="moveUp(index)" :disabled="index === 0" class="w-7 h-7 flex items-center justify-center bg-gray-50 text-gray-400 rounded disabled:opacity-30 active:scale-95 transition-all">
                                    <i class="ri-arrow-up-s-line text-lg"></i>
                                </button>
                                <button @click="moveDown(index)" :disabled="index === pengantaranData.length - 1" class="w-7 h-7 flex items-center justify-center bg-gray-50 text-gray-400 rounded disabled:opacity-30 active:scale-95 transition-all">
                                    <i class="ri-arrow-down-s-line text-lg"></i>
                                </button>
                            </div>

                            <!-- Normal Mode Content -->
                            <div v-if="!isReordering">
                                <div class="flex items-start gap-2.5">
                                    <div class="w-7 h-7 rounded bg-gray-50 flex items-center justify-center text-primary border border-gray-100 shrink-0 mt-0.5">
                                        <i class="ri-user-location-fill text-sm"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-800 text-xs leading-tight">{{ element.penerima }}</h4>
                                        <p class="text-[10px] text-gray-500 mt-0.5 leading-snug font-medium line-clamp-2">
                                            {{ element.alamat }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-2.5 p-1.5 bg-gray-50/80 rounded border border-gray-100 flex justify-between items-center">
                                    <div class="flex items-center gap-1.5 text-[10px] font-bold text-gray-700">
                                        <i class="ri-box-3-fill text-primary"></i> 
                                        <span>{{ element.koli }} Koli <span class="text-gray-400 font-medium ml-0.5 inline-block text-[9px] truncate max-w-[100px] align-bottom">{{ element.deskripsi }}</span></span>
                                    </div>
                                    
                                    <div v-if="element.isCod" class="text-right">
                                        <p class="font-black text-red-600 text-xs drop-shadow-sm">{{ formatRupiah(element.tagihanCod) }}</p>
                                    </div>
                                    <div v-else class="text-right flex items-center gap-0.5 text-green-600 bg-green-50 px-1.5 py-0.5 rounded border border-green-100">
                                        <i class="ri-check-line text-xs"></i>
                                        <span class="text-[8px] font-black tracking-wider uppercase">Lunas</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-show="!isReordering" class="p-2 bg-gray-50/50 border-t border-gray-50">
                            <button 
                                @click="bukaModal(element)"
                                :disabled="element.selesai"
                                class="w-full py-1.5 flex items-center justify-center gap-1.5 rounded shadow-sm font-bold active:scale-95 transition-all text-xs"
                                :class="element.selesai ? 'bg-gray-200 text-gray-400 cursor-not-allowed shadow-none' : 'bg-primary text-white hover:bg-primaryLight hover:shadow-md'"
                            >
                                <i :class="element.selesai ? 'ri-check-double-line' : 'ri-camera-lens-fill'" class="text-sm"></i>
                                <span>{{ element.selesai ? 'Selesai Diantar' : 'Scan Bukti Antar' }}</span>
                            </button>
                        </div>
                    </div>
            </transition-group>
        </div>

        <!-- Modal POD -->
        <Transition name="fade">
            <div v-if="isModalOpen" class="fixed inset-0 z-50 bg-[#f8f9fc] flex flex-col max-w-md mx-auto shadow-2xl pt-[35px] pb-[40px]">
                <!-- Header -->
                <div class="px-3 py-2.5 bg-white border-b border-gray-100 flex items-center gap-2.5 sticky top-0 z-20">
                    <button @click="isModalOpen = false" class="w-7 h-7 flex items-center justify-center bg-gray-50 rounded text-gray-600 hover:bg-gray-100 active:scale-95 transition-all">
                        <i class="ri-arrow-left-line text-lg"></i>
                    </button>
                    <div>
                        <h3 class="font-black text-gray-800 text-sm leading-none">Detail Pengantaran</h3>
                        <p class="text-[9px] font-bold text-primary tracking-widest uppercase mt-0.5">{{ selectedAntaran?.resi }}</p>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto pb-20">
                    <!-- Informasi Penerima & Aksi -->
                    <div class="bg-white px-3 py-2.5 mb-1.5 border-b border-gray-100">
                        <div class="flex items-start gap-2.5">
                            <div class="w-8 h-8 rounded bg-primary/10 flex items-center justify-center text-primary shrink-0">
                                <i class="ri-user-location-fill text-lg"></i>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-extrabold text-gray-800 text-xs leading-tight truncate">{{ selectedAntaran?.penerima }}</h4>
                                <p class="text-[10px] text-gray-500 mt-0.5 font-medium leading-snug line-clamp-2">{{ selectedAntaran?.alamatLengkap || selectedAntaran?.alamat }}</p>
                            </div>
                        </div>

                        <div class="mt-2.5 flex gap-2">
                            <button class="flex-1 flex items-center justify-center gap-1 bg-green-50 text-green-600 font-bold py-1.5 rounded border border-green-100 active:scale-95 transition-all text-[10px]">
                                <i class="ri-whatsapp-line text-xs"></i> Hubungi WA
                            </button>
                            <button class="flex-1 flex items-center justify-center gap-1 bg-blue-50 text-blue-600 font-bold py-1.5 rounded border border-blue-100 active:scale-95 transition-all text-[10px]">
                                <i class="ri-direction-line text-xs"></i> Petunjuk Arah
                            </button>
                        </div>
                    </div>
                    
                    <div class="p-3 space-y-3">
                        <!-- Kotak Validasi Koli (Hybrid Checklist + Barcode) -->
                        <div class="bg-white p-2.5 rounded-lg border border-gray-100 shadow-sm">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-[10px] font-bold text-gray-700 uppercase tracking-wider">Validasi Koli ({{ selectedAntaran?.kolis?.filter(k => k.scanned).length || 0 }}/{{ selectedAntaran?.koli }})</label>
                                <div class="flex items-center gap-1.5">
                                    <input type="text" v-model="manualBarcode" @keyup.enter="submitManualBarcode" placeholder="3 digit..." class="w-16 text-[9px] font-bold border border-gray-200 rounded px-1.5 py-1 focus:ring-primary focus:border-primary bg-gray-50 outline-none uppercase transition-all">
                                    <button @click="bukaScanner" class="text-[9px] font-bold bg-primary text-white px-2 py-1 rounded active:scale-95 transition-all flex items-center gap-0.5 shrink-0">
                                        <i class="ri-barcode-box-line text-[10px]"></i> Scan
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Scanner View (Inline) -->
                            <div v-if="isScannerOpen" class="mb-3 h-[40vh] rounded-lg overflow-hidden relative shadow-sm border border-gray-200">
                                <BarcodeScanner 
                                    :showClose="false"
                                    initialMode="koli"
                                    :allowModeSwitch="false"
                                    @scan="onBarcodeScanned" 
                                    @close="isScannerOpen = false" 
                                />
                                <button @click="isScannerOpen = false" class="absolute top-2 right-2 z-50 bg-black/50 text-white w-8 h-8 flex items-center justify-center rounded-full backdrop-blur">
                                    <i class="ri-close-line text-lg"></i>
                                </button>
                            </div>
                            
                            <!-- Inline Notification -->
                            <Transition name="fade">
                                <div v-if="koliMessage.text" class="mb-2 px-2 py-1.5 rounded flex items-center gap-1.5 text-[9px] font-bold transition-all" :class="koliMessage.type === 'error' ? 'bg-red-50 text-red-600 border border-red-100' : 'bg-green-50 text-green-600 border border-green-100'">
                                    <i :class="koliMessage.type === 'error' ? 'ri-error-warning-fill' : 'ri-checkbox-circle-fill'"></i>
                                    <span>{{ koliMessage.text }}</span>
                                </div>
                            </Transition>
                            
                            <div class="space-y-1.5">
                                <div v-for="k in selectedAntaran?.kolis" :key="k.id" @click="k.scanned = !k.scanned" class="flex items-center justify-between bg-gray-50/50 p-1.5 rounded border border-gray-100 transition-colors cursor-pointer hover:bg-gray-100 active:scale-[0.99]" :class="k.scanned ? 'border-primary/30 bg-primary/5' : ''" :title="k.scanned ? 'Klik untuk membatalkan verifikasi' : 'Klik untuk verifikasi manual koli ini'">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded flex items-center justify-center shrink-0 transition-colors" :class="k.scanned ? 'bg-primary text-white' : 'bg-gray-200 text-gray-400'">
                                            <i :class="k.scanned ? 'ri-check-line text-xs' : 'ri-box-3-line text-xs'"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-bold text-gray-800 truncate">{{ k.deskripsi || 'Paket' }}</p>
                                            <p class="text-[8px] font-mono text-gray-500 mt-0.5 truncate">{{ k.barcode || 'Koli #' + k.id }}</p>
                                        </div>
                                    </div>
                                    <div v-if="k.scanned" class="text-primary text-[8px] font-bold px-1 py-0.5 rounded border border-primary/20 bg-white">TER-SCAN</div>
                                    <div v-else class="text-gray-400 text-[8px] font-semibold px-1 py-0.5 rounded border border-gray-200 bg-white">TAP CEK</div>
                                </div>
                            </div>
                        </div>

                        <!-- Camera Upload Area -->
                        <div class="bg-white p-2.5 rounded-lg border border-gray-100 shadow-sm">
                            <label class="block text-[10px] font-bold text-gray-700 uppercase tracking-wider mb-1.5">Foto Bukti Terima (POD) <span class="text-red-500">*</span></label>
                            <label class="relative w-full h-20 border border-dashed border-primary/30 rounded-lg flex flex-col items-center justify-center bg-primary/5 text-primary cursor-pointer hover:bg-primary/10 transition-colors overflow-hidden group">
                                <input type="file" accept="image/*" capture="environment" class="hidden" @change="handleImageUpload">
                                
                                <template v-if="!uploadedImage">
                                    <i class="ri-camera-fill text-xl mb-0.5"></i>
                                    <span class="text-[9px] font-bold">Jepret Kamera</span>
                                </template>
                                
                                <img v-else :src="uploadedImage" class="absolute inset-0 w-full h-full object-cover" />
                                
                                <div v-if="uploadedImage" class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                    <span class="text-white font-bold text-[9px] bg-black/50 px-2 py-0.5 rounded backdrop-blur-sm"><i class="ri-camera-switch-line mr-1"></i> Ganti</span>
                                </div>
                            </label>
                        </div>
                        
                        <!-- Input Nama Penerima -->
                        <div class="bg-white p-2.5 rounded-lg border border-gray-100 shadow-sm">
                            <label class="block text-[10px] font-bold text-gray-700 uppercase tracking-wider mb-1.5">Nama Penerima <span class="text-red-500">*</span></label>
                            <div class="relative flex items-center">
                                <i class="ri-user-smile-line text-gray-400 absolute left-2.5 text-sm"></i>
                                <input type="text" v-model="namaPenerima" placeholder="Contoh: Budi (Keluarga)" class="w-full border border-gray-200 rounded pl-7 pr-2 py-1.5 bg-gray-50 focus:bg-white focus:border-primary outline-none transition-all font-medium text-xs text-gray-800">
                            </div>
                        </div>

                        <!-- Info COD -->
                        <div v-if="selectedAntaran?.isCod" class="bg-red-50 p-2.5 rounded-lg border border-red-200 relative overflow-hidden flex items-center justify-between">
                            <div class="relative z-10 flex-1">
                                <p class="text-[8px] font-black text-red-600 tracking-widest uppercase mb-0.5">Tagihan COD</p>
                                <p class="font-black text-red-700 text-lg leading-none mb-1.5">{{ formatRupiah(selectedAntaran?.tagihanCod) }}</p>
                                
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="checkbox" class="w-3 h-3 text-primary border-gray-300 rounded focus:ring-primary accent-primary shrink-0">
                                    <span class="text-[9px] font-bold text-gray-700 leading-tight">
                                        Konfirmasi terima tunai
                                    </span>
                                </label>
                            </div>
                            <i class="ri-money-dollar-circle-fill text-red-200 text-4xl absolute -right-2 -bottom-2 opacity-50 z-0"></i>
                        </div>
                    </div>
                </div>

                <div class="p-2.5 bg-white border-t border-gray-100 fixed left-0 right-0 z-20 max-w-md mx-auto flex gap-2 shadow-[0_-2px_10px_rgba(0,0,0,0.02)]" style="bottom: 40px;">
                    <button v-if="!semuaKoliTervalidasi" @click="bukaModalKendala" class="w-10 flex-none bg-red-50 text-red-600 border border-red-100 rounded font-bold active:scale-95 transition-all flex items-center justify-center">
                        <i class="ri-error-warning-fill text-sm"></i>
                    </button>
                    <button v-if="semuaKoliTervalidasi" @click="selesaikanAntaran" class="flex-1 bg-primary text-white py-2 rounded font-bold shadow-sm active:scale-95 transition-all text-xs flex items-center justify-center gap-1">
                        <i class="ri-check-double-line text-sm"></i> Selesaikan
                    </button>
                    <div v-else class="flex-1 bg-gray-100 text-gray-400 py-2 rounded font-bold text-[10px] flex items-center justify-center gap-1 cursor-not-allowed">
                        <i class="ri-scan-2-line text-xs"></i> Scan Koli Dulu
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Modal Kendala (Lapor Masalah) -->
        <Transition name="fade">
            <div v-if="isKendalaModalOpen" class="fixed inset-0 z-[60] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 pt-[35px] pb-[40px]">
                <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden flex flex-col transform transition-all">
                    <!-- Modal Header -->
                    <div class="bg-red-50 border-b border-red-100 p-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0">
                                <i class="ri-error-warning-fill text-lg"></i>
                            </div>
                            <h3 class="font-black text-red-700 text-sm">Lapor Kendala Koli</h3>
                        </div>
                        <button @click="isKendalaModalOpen = false" class="text-red-400 hover:text-red-600 transition-colors w-6 h-6 flex items-center justify-center rounded-full hover:bg-red-100">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-4 bg-gray-50/50">
                        <p class="text-[10px] text-gray-600 font-medium mb-3 leading-relaxed">Ada koli yang belum terscan. Harap masukkan alasan jika koli kurang/hilang atau ada kendala lain di lapangan.</p>
                        
                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-gray-700 uppercase tracking-wider">Alasan Kendala <span class="text-red-500">*</span></label>
                            <textarea 
                                v-model="kendalaAlasan" 
                                rows="3" 
                                placeholder="Contoh: Barang tertinggal di gudang, atau rusak..." 
                                class="w-full border border-gray-200 rounded-lg p-3 bg-white focus:bg-white focus:border-red-400 focus:ring-2 focus:ring-red-100 outline-none transition-all text-xs text-gray-800 resize-none shadow-inner"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="p-3 border-t border-gray-100 bg-white flex gap-2">
                        <button @click="isKendalaModalOpen = false" class="flex-1 py-2 rounded-lg font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 active:scale-95 transition-all text-xs">Batal</button>
                        <button 
                            @click="kirimKendala" 
                            :disabled="!kendalaAlasan.trim()"
                            class="flex-1 py-2 rounded-lg font-bold text-white shadow-sm active:scale-95 transition-all text-xs flex items-center justify-center gap-1.5"
                            :class="kendalaAlasan.trim() ? 'bg-red-600 hover:bg-red-700 shadow-red-600/30' : 'bg-red-300 cursor-not-allowed shadow-none'"
                        >
                            <i class="ri-send-plane-fill"></i> Kirim Laporan
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
        driver-role="DRIVER_LINTAS"
        @close="isProfileModalOpen = false"
    />

    <!-- Latar Belakang Navigation Bar Android -->
    <div class="fixed bottom-0 left-0 right-0 h-[40px] bg-primary z-[100]"></div>
</template>
<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.list-move {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
