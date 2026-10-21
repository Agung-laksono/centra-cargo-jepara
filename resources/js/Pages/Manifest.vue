<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import BarcodeScanner from '@/Components/BarcodeScanner.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { useTabCache } from '@/Composables/useTabCache';
import { stopAllActiveCameraStreams } from '@/Composables/useCameraManager';
import axios from 'axios';
import localforage from 'localforage';
import ManifestQrModal from '@/Components/ManifestQrModal.vue';
import { useEcho } from '@/Composables/useEcho';

// ---------------------------------------------------------
// PROPS (Data dari Backend)
// ---------------------------------------------------------
const props = defineProps({
    drivers: { type: Array, default: () => [] },
    shipments: { type: Array, default: () => [] },
    manifests: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
});

// ---------------------------------------------------------
// STATE
// ---------------------------------------------------------
const viewMode = ref('list'); // 'list' | 'grid' | 'table'
const manifestsData = ref([...props.manifests]);
const warehousesList = ref([...props.warehouses]);
const loadingMore = ref(false);
const hasMoreManifests = ref(props.manifests.length >= 10);
const selectedQrManifest = ref(null);
const isQrModalOpen = ref(false);

const { getEcho } = useEcho();
let manifestsEchoChannel = null;

const handleHandshakeSuccess = (event) => {
    const found = manifestsData.value.find(m => m.id == event.manifestId);
    if (found) {
        found.driver_id = event.driverId;
        found.driver = { name: event.driverName, vehicle_name: event.vehicleName };
        found.status = event.status || 'loaded';
    }
    router.reload({ preserveScroll: true, preserveState: true });
};

onMounted(() => {
    const echo = getEcho();
    if (echo) {
        manifestsEchoChannel = echo.channel('manifests');
        manifestsEchoChannel.listen('.manifest.handshaked', (event) => {
            handleHandshakeSuccess(event);
        });
    }
});

onUnmounted(() => {
    if (manifestsEchoChannel) {
        try {
            getEcho()?.leave('manifests');
        } catch (e) {}
    }
});

const openQrModal = (m) => {
    selectedQrManifest.value = m;
    isQrModalOpen.value = true;
};

const loadMoreManifests = async () => {
    if (loadingMore.value || !hasMoreManifests.value) return;
    loadingMore.value = true;
    try {
        const res = await axios.get(`/api/manifests?offset=${manifestsData.value.length}&limit=10`);
        const newItems = res.data.manifests || [];
        manifestsData.value.push(...newItems);
        if (newItems.length < 10) hasMoreManifests.value = false;
    } catch(e) {
        console.error('Error loading manifests', e);
    } finally {
        loadingMore.value = false;
    }
};

const manifestTabs = [
    { id: 'buat', label: 'Buat Surat', icon: 'ri-add-box-line' },
    { id: 'list', label: 'Daftar Surat', icon: 'ri-list-check' }
];
const { activeTab } = useTabCache('manifest-tab', 'buat');
const barcodeInput = ref('');
const isCameraOpen = ref(false);
const cameraInputRef = ref(null);
const closedInputRef = ref(null);
const scanMessage = ref(null);
let scanMessageTimer = null;
let highlightTimer = null;
const highlightedKoliId = ref(null);
const highlightedShipmentId = ref(null);
const isDriverScreenOpen = ref(false);
const isSticky = ref(false);
let lastScrollY = 0;
let scrollDelta = 0;
let isScrollTicking = false;
const isDriverBarVisible = ref(true);

const handleScroll = () => {
    if (isScrollTicking) return;
    isScrollTicking = true;

    requestAnimationFrame(() => {
        const currentScrollY = window.scrollY;
        isSticky.value = currentScrollY > 30;

        // Di dekat bagian atas halaman (< 50px), selalu tampilkan
        if (currentScrollY <= 50) {
            isDriverBarVisible.value = true;
            scrollDelta = 0;
            lastScrollY = currentScrollY;
            isScrollTicking = false;
            return;
        }

        const diff = currentScrollY - lastScrollY;

        // Reset akumulasi jika arah scroll berbalik
        if ((diff > 0 && scrollDelta < 0) || (diff < 0 && scrollDelta > 0)) {
            scrollDelta = 0;
        }
        scrollDelta += diff;

        // Scroll ke BAWAH: butuh gerakan mantap minimal 35px agar tidak jitter saat jari bergetar/gerak sedikit
        if (scrollDelta > 35 && isDriverBarVisible.value) {
            isDriverBarVisible.value = false;
            scrollDelta = 0;
        } 
        // Scroll ke ATAS: gerakan balik minimal 15px langsung memunculkan sticky bar secara responsif & halus
        else if (scrollDelta < -15 && !isDriverBarVisible.value) {
            isDriverBarVisible.value = true;
            scrollDelta = 0;
        }

        lastScrollY = currentScrollY;
        isScrollTicking = false;
    });
};
const statusFilter = ref('all'); // 'pending' | 'complete' | 'all'

const form = useForm({
    rute: '',
    ekspedisi: 'Internal',
    driver_id: '',
    vendor_name: '',
    vendor_resi: '',
    koli_ids: [],
    destination_warehouse_id: null,
});

// Daftar koli yang sudah di-scan (berhasil dimuat)
const scannedKolis = ref([]);
const DB_QUEUE_KEY = 'manifest_sync_queue';

const queueManifestSync = async (job) => {
    let queue = await localforage.getItem(DB_QUEUE_KEY) || [];
    queue.push({ ...job, timestamp: new Date().getTime() });
    await localforage.setItem(DB_QUEUE_KEY, queue);
    syncManifestToServer();
};

const syncManifestToServer = async () => {
    if (!navigator.onLine) return;
    
    let queue = await localforage.getItem(DB_QUEUE_KEY) || [];
    if (queue.length === 0) return;
    
    let failedQueue = [];
    let hasSuccess = false;
    for (let job of queue) {
        try {
            const res = await axios.post('/api/manifest/sync', job);
            if (res.data.wa_link) {
                console.log("Berhasil sinkron Manifest! Link WA: ", res.data.wa_link);
            }
            hasSuccess = true;
        } catch (error) {
            failedQueue.push(job);
        }
    }
    await localforage.setItem(DB_QUEUE_KEY, failedQueue);
    
    if (hasSuccess) {
        router.reload({ only: ['manifests', 'shipments'] });
    }
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
    window.addEventListener('online', syncManifestToServer);
    syncManifestToServer();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('online', syncManifestToServer);
    clearTimeout(scanMessageTimer);
    clearTimeout(highlightTimer);
    stopCamera();
});

// ---------------------------------------------------------
// AUDIO & TOAST FEEDBACK
// ---------------------------------------------------------
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

const focusActiveInput = () => {
    nextTick(() => {
        if (isCameraOpen.value && cameraInputRef.value) {
            cameraInputRef.value.focus();
        } else if (!isCameraOpen.value && closedInputRef.value) {
            closedInputRef.value.focus();
        }
    });
};

const openCamera = () => {
    isCameraOpen.value = true;
    // Jangan auto-focus agar keyboard ponsel tidak otomatis muncul menutupi area bidik scanner
};

const stopCamera = () => {
    isCameraOpen.value = false;
    stopAllActiveCameraStreams();
};

// ---------------------------------------------------------
// COMPUTED & FILTER
// ---------------------------------------------------------
const searchQuery = ref('');
const filterProvinsi = ref('');
const filterKota = ref('');
const filterKecamatan = ref('');
const isSearchActive = ref(false);
const searchQueryList = ref('');
const showFilterList = ref(false);
const isSearchExpanded = ref(false);
const isLocationFilterOpen = ref(false);

const hasActiveLocationFilter = computed(() => Boolean(filterProvinsi.value || filterKota.value || filterKecamatan.value));

const resetLocationFilter = () => {
    filterProvinsi.value = '';
    filterKota.value = '';
    filterKecamatan.value = '';
    isLocationFilterOpen.value = false;
};

const availableProvinsi = computed(() => {
    const provs = props.shipments.map(s => s.provinsi).filter(Boolean);
    return [...new Set(provs)].sort();
});

const availableKota = computed(() => {
    let result = props.shipments;
    if (filterProvinsi.value) {
        result = result.filter(s => s.provinsi === filterProvinsi.value);
    }
    const cities = result.map(s => s.kota).filter(Boolean);
    return [...new Set(cities)].sort();
});

const availableKecamatan = computed(() => {
    let result = props.shipments;
    if (filterKota.value) {
        result = result.filter(s => s.kota === filterKota.value);
    }
    const kecs = result.map(s => s.kecamatan).filter(Boolean);
    return [...new Set(kecs)].sort();
});

watch(filterProvinsi, () => { filterKota.value = ''; filterKecamatan.value = ''; });
watch(filterKota, () => { filterKecamatan.value = ''; });

// Auto-fill Rute Armada based on selected warehouse
watch(() => form.destination_warehouse_id, (newId) => {
    if (newId) {
        const wh = props.warehouses.find(w => w.id === newId);
        if (wh) {
            form.rute = `Jepara → ${wh.city}`;
        }
    } else {
        form.rute = `Jepara → Pengantaran Langsung`;
    }
});

// Form Supir Baru
const showAddDriver = ref(false);
const driverFormAdd = useForm({
    name: '',
    vehicle_name: '',
    phone_number: '',
    avatar: null
});

const saveDriver = () => {
    driverFormAdd.post('/drivers', {
        preserveScroll: true,
        onSuccess: () => {
            showAddDriver.value = false;
            driverFormAdd.reset();
            if (props.drivers && props.drivers.length > 0) {
                form.driver_id = props.drivers[props.drivers.length - 1].id;
            }
        }
    });
};

// Form Gudang Cabang Baru (Quick Add Terminal)
const showAddWarehouse = ref(false);
const warehouseFormAdd = useForm({
    name: '',
    city: '',
    address: '',
    phone: '',
    maps_url: '',
});

const isSavingWarehouse = ref(false);
const saveWarehouse = async () => {
    if (!warehouseFormAdd.name.trim() || !warehouseFormAdd.city.trim()) {
        alert('Nama gudang dan kota operasional wajib diisi!');
        return;
    }

    isSavingWarehouse.value = true;
    try {
        const response = await axios.post('/settings/warehouses', {
            name: warehouseFormAdd.name,
            city: warehouseFormAdd.city,
            address: warehouseFormAdd.address,
            phone: warehouseFormAdd.phone,
            maps_url: warehouseFormAdd.maps_url,
        }, {
            headers: { 'Accept': 'application/json' }
        });

        if (response.data && response.data.warehouse) {
            const newW = response.data.warehouse;
            warehousesList.value.push(newW);
            form.destination_warehouse_id = newW.id;
        }

        showAddWarehouse.value = false;
        warehouseFormAdd.reset();
    } catch (error) {
        console.error('Gagal menyimpan gudang baru:', error);
        alert(error.response?.data?.message || 'Gagal menyimpan gudang baru.');
    } finally {
        isSavingWarehouse.value = false;
    }
};

const driverAssignmentMode = ref('auto'); // 'auto' (Bebas / Handshake) | 'manual' (Pilih Supir)

const selectedDriver = computed(() =>
    props.drivers.find(d => d.id == form.driver_id)
);

const isFleetConfigured = computed(() => {
    if (!form.rute || !form.rute.trim()) return false;
    if (form.ekspedisi === 'Internal') {
        if (driverAssignmentMode.value === 'auto') return true;
        return Boolean(form.driver_id);
    }
    if (form.ekspedisi === 'Vendor') return Boolean(form.vendor_name && form.vendor_name.trim());
    return false;
});

const fleetSummaryText = computed(() => {
    if (!isFleetConfigured.value) {
        return '+ Pilih Supir / Armada';
    }
    if (form.ekspedisi === 'Internal') {
        if (driverAssignmentMode.value === 'auto') {
            return `${form.rute} · Bebas (Handshake)`;
        }
        const d = selectedDriver.value;
        const driverName = d ? d.name : 'Supir';
        return `${form.rute} · ${driverName}`;
    } else {
        return `${form.rute} · Vendor ${form.vendor_name || ''}`;
    }
});

// Bangun flat map barcode -> info koli dari semua shipment
const allKoliMap = computed(() => {
    const map = {};
    (props.shipments || []).forEach(shipment => {
        (shipment.kolis || []).forEach(koli => {
            const barcodeVal = (koli.barcode_id || koli.barcode || '').toUpperCase();
            if (barcodeVal) {
                map[barcodeVal] = {
                    koli_id: koli.id,
                    barcode: barcodeVal,
                    description: koli.description,
                    tracking_number: shipment.tracking_number,
                    sender: shipment.sender,
                    recipient: shipment.recipient_name,
                    destination: shipment.destination,
                    shipment_id: shipment.id,
                    koli_number: koli.koli_number,
                    total_koli: shipment.total_koli,
                };
            }
        });
    });
    return map;
});

// Helper Koli & Shipment Muat
const isKoliLoaded = (koli) => {
    if (!koli) return false;
    return form.koli_ids.includes(koli.id);
};

const getScannedKolisCount = (shipment) => {
    if (!shipment.kolis) return 0;
    return shipment.kolis.filter(k => isKoliLoaded(k)).length;
};

const getShipmentProgress = (shipment) => {
    if (!shipment.kolis || shipment.kolis.length === 0) return 0;
    return Math.round((getScannedKolisCount(shipment) / shipment.kolis.length) * 100);
};

const isShipmentComplete = (shipment) => {
    if (!shipment.kolis || shipment.kolis.length === 0) return false;
    return shipment.kolis.every(k => isKoliLoaded(k));
};

const pendingShipmentsCount = computed(() => {
    return (props.shipments || []).filter(s => !isShipmentComplete(s)).length;
});

const completedShipmentsCount = computed(() => {
    return (props.shipments || []).filter(s => isShipmentComplete(s) && (s.kolis || []).length > 0).length;
});

const totalExpectedKolis = computed(() => {
    let total = 0;
    (props.shipments || []).forEach(s => {
        total += (s.kolis || []).length;
    });
    return total;
});

const totalScanned = computed(() => form.koli_ids.length);

// Jumlah resi yang sudah ada kolinya termuat di truk
const loadedShipmentsCount = computed(() => {
    const ids = new Set();
    (props.shipments || []).forEach(s => {
        if ((s.kolis || []).some(k => isKoliLoaded(k))) {
            ids.add(s.id);
        }
    });
    return ids.size;
});

const totalShipmentsCount = computed(() => {
    return (props.shipments || []).length;
});

const filteredShipments = computed(() => {
    let result = props.shipments || [];

    // Filter status tab
    if (statusFilter.value === 'pending') {
        result = result.filter(s => !isShipmentComplete(s));
    } else if (statusFilter.value === 'complete') {
        result = result.filter(s => isShipmentComplete(s));
    }

    if (filterProvinsi.value) {
        result = result.filter(s => s.provinsi === filterProvinsi.value);
    }
    if (filterKota.value) {
        result = result.filter(s => s.kota === filterKota.value);
    }
    if (filterKecamatan.value) {
        result = result.filter(s => s.kecamatan === filterKecamatan.value);
    }
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase().trim();
        result = result.filter(s => 
            (s.tracking_number && s.tracking_number.toLowerCase().includes(q)) ||
            (s.sender && s.sender.toLowerCase().includes(q)) ||
            (s.recipient_name && s.recipient_name.toLowerCase().includes(q)) ||
            (s.destination && s.destination.toLowerCase().includes(q)) ||
            (s.kolis && s.kolis.some(k => 
                (k.barcode_id && k.barcode_id.toLowerCase().includes(q)) ||
                (k.barcode && k.barcode.toLowerCase().includes(q)) ||
                (k.description && k.description.toLowerCase().includes(q))
            ))
        );
    }
    return result;
});

const filteredManifests = computed(() => {
    let list = manifestsData.value;
    if (searchQueryList.value && searchQueryList.value.trim()) {
        const q = searchQueryList.value.toLowerCase().trim();
        list = list.filter(m => 
            (m.id && m.id.toString().includes(q)) ||
            (m.driver && m.driver.toLowerCase().includes(q)) ||
            (m.vehicle && m.vehicle.toLowerCase().includes(q)) ||
            (m.resi_vendor && m.resi_vendor.toLowerCase().includes(q)) ||
            (m.rute && m.rute.toLowerCase().includes(q)) ||
            (m.status && m.status.toLowerCase().includes(q))
        );
    }
    return list;
});

// Peringatan koli kurang per resi
const warnings = computed(() => {
    const warns = [];
    props.shipments.forEach(shipment => {
        const totalKoli = shipment.total_koli;
        const scannedCount = getScannedKolisCount(shipment);
        if (scannedCount > 0 && scannedCount < totalKoli) {
            warns.push({
                resi: shipment.tracking_number,
                scanned: scannedCount,
                total: totalKoli,
                kurang: totalKoli - scannedCount,
            });
        }
    });
    return warns;
});

// ---------------------------------------------------------
// AUTO SCROLL & HIGHLIGHT
// ---------------------------------------------------------
const scrollToKoli = (koliInfo) => {
    if (!koliInfo) return;
    const bId = (koliInfo.barcode || '').toUpperCase();
    highlightedKoliId.value = bId;
    highlightedShipmentId.value = koliInfo.tracking_number;

    const shipment = (props.shipments || []).find(s => s.tracking_number === koliInfo.tracking_number);
    if (shipment) {
        if (statusFilter.value === 'complete' && !isShipmentComplete(shipment)) {
            statusFilter.value = 'all';
        } else if (statusFilter.value === 'pending' && isShipmentComplete(shipment)) {
            statusFilter.value = 'all';
        }
    }

    nextTick(() => {
        const el = document.getElementById(`koli-item-${bId}`) || document.getElementById(`shipment-card-${koliInfo.tracking_number}`);
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
    highlightedShipmentId.value = shipment.tracking_number;

    if (statusFilter.value === 'complete' && !isShipmentComplete(shipment)) {
        statusFilter.value = 'all';
    } else if (statusFilter.value === 'pending' && isShipmentComplete(shipment)) {
        statusFilter.value = 'all';
    }

    nextTick(() => {
        const el = document.getElementById(`shipment-card-${shipment.tracking_number}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    clearTimeout(highlightTimer);
    highlightTimer = setTimeout(() => {
        if (highlightedShipmentId.value === shipment.tracking_number) {
            highlightedShipmentId.value = null;
        }
    }, 3500);
};

// ---------------------------------------------------------
// METHODS
// ---------------------------------------------------------
const prosesBarcode = (kode) => {
    const raw = (kode || barcodeInput.value).trim().toUpperCase();
    if (!raw) return;
    barcodeInput.value = '';

    if (!kode && !isCameraOpen.value) {
        focusActiveInput();
    }

    // 1. Cek apakah kode yang di-scan adalah NOMOR RESI (Bukan Koli) -> Tolak & Scroll ke resi
    const matchingShipment = (props.shipments || []).find(s => 
        s.tracking_number && s.tracking_number.toUpperCase() === raw
    );

    if (matchingShipment) {
        playBeep('error');
        showMessage('error', `⚠️ Kode "${matchingShipment.tracking_number}" adalah Nomor Resi! Wajib scan Barcode Koli fisik.`);
        if (navigator.vibrate) navigator.vibrate([150, 80, 150]);
        scrollToShipment(matchingShipment);
        return;
    }

    // 2. Cek apakah cocok langsung dengan Barcode Koli
    let koliInfo = allKoliMap.value[raw];

    // 3. Cek jika operator hanya mengetik 3 karakter suffix koli (misal 'KW9')
    if (!koliInfo && raw.length === 3) {
        const matches = Object.values(allKoliMap.value).filter(k => 
            k.barcode.endsWith('-' + raw) || k.barcode.endsWith(raw)
        );
        if (matches.length === 1) {
            koliInfo = matches[0];
        } else if (matches.length > 1) {
            playBeep('error');
            showMessage('error', `Suffix "${raw}" cocok dengan beberapa koli. Harap ketik barcode lengkap.`);
            return;
        }
    }

    if (!koliInfo) {
        playBeep('error');
        showMessage('error', `Barcode "${raw}" tidak ditemukan di sistem / belum siap kirim!`);
        return;
    }

    // 4. Cegah duplikasi scan (sudah dimuat)
    if (form.koli_ids.includes(koliInfo.koli_id)) {
        playBeep('duplicate');
        showMessage('duplicate', `⚠️ Koli "${koliInfo.barcode}" sudah dimuat ke truk.`);
        scrollToKoli(koliInfo);
        return;
    }

    // 5. Berhasil muat koli
    scannedKolis.value.unshift({ ...koliInfo, scanned_at: new Date() });
    form.koli_ids.push(koliInfo.koli_id);
    playBeep('success');
    if (navigator.vibrate) navigator.vibrate(80);
    const koliNumInfo = koliInfo.koli_number && koliInfo.total_koli 
        ? `(Koli ${koliInfo.koli_number}/${koliInfo.total_koli})` 
        : `(${koliInfo.tracking_number})`;
    showMessage('ok', `✅ Dimuat: ${koliInfo.barcode} ${koliNumInfo}`);
    scrollToKoli(koliInfo);
};

const hapusKoli = (koliId) => {
    const targetKoli = scannedKolis.value.find(k => k.koli_id === koliId);
    scannedKolis.value = scannedKolis.value.filter(k => k.koli_id !== koliId);
    form.koli_ids = form.koli_ids.filter(id => id !== koliId);
    if (targetKoli) {
        showMessage('duplicate', `Dibatalkan: Koli "${targetKoli.barcode}" dikeluarkan dari muatan.`);
        playBeep('duplicate');
    }
};

// ---------------------------------------------------------
// SLIDE TO CONFIRM SAVE MODAL
// ---------------------------------------------------------
const isConfirmSaveModalOpen = ref(false);
const sliderTrackRef = ref(null);
const sliderOffset = ref(0);
const sliderProgress = ref(0);
const isDraggingSlider = ref(false);
const isSaveSuccess = ref(false);
let sliderStartX = 0;

const openConfirmSaveModal = () => {
    if (form.koli_ids.length === 0) {
        playBeep('error');
        showMessage('error', '⚠️ Belum ada koli yang discan untuk dimuat!');
        return;
    }

    if (!isFleetConfigured.value) {
        playBeep('duplicate');
        showMessage('duplicate', '⚠️ Harap tentukan Supir / Rute armada terlebih dahulu.');
        isDriverScreenOpen.value = true;
        return;
    }

    sliderOffset.value = 0;
    sliderProgress.value = 0;
    isSaveSuccess.value = false;
    isConfirmSaveModalOpen.value = true;
};

const closeConfirmSaveModal = () => {
    if (form.processing || isSaveSuccess.value) return;
    isConfirmSaveModalOpen.value = false;
    sliderOffset.value = 0;
    sliderProgress.value = 0;
};

const startSliderDrag = (clientX) => {
    if (form.processing || isSaveSuccess.value) return;
    isDraggingSlider.value = true;
    sliderStartX = clientX - sliderOffset.value;

    const onMove = (e) => {
        if (!isDraggingSlider.value || !sliderTrackRef.value) return;
        const currentX = e.touches ? e.touches[0].clientX : e.clientX;
        const trackWidth = sliderTrackRef.value.clientWidth;
        const thumbWidth = 46;
        const maxTravel = Math.max(0, trackWidth - thumbWidth - 8);

        const diff = currentX - sliderStartX;
        const clamped = Math.max(0, Math.min(diff, maxTravel));
        sliderOffset.value = clamped;
        sliderProgress.value = maxTravel > 0 ? (clamped / maxTravel) * 100 : 0;
    };

    const onEnd = () => {
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseup', onEnd);
        window.removeEventListener('touchmove', onMove);
        window.removeEventListener('touchend', onEnd);

        if (!isDraggingSlider.value) return;
        isDraggingSlider.value = false;

        if (sliderProgress.value >= 85) {
            if (sliderTrackRef.value) {
                const trackWidth = sliderTrackRef.value.clientWidth;
                const thumbWidth = 46;
                sliderOffset.value = Math.max(0, trackWidth - thumbWidth - 8);
                sliderProgress.value = 100;
            }
            isSaveSuccess.value = true;
            playBeep('success');
            if (navigator.vibrate) navigator.vibrate([60, 40, 100]);

            setTimeout(() => {
                isConfirmSaveModalOpen.value = false;
                generateLink();
            }, 300);
        } else {
            sliderOffset.value = 0;
            sliderProgress.value = 0;
        }
    };

    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onEnd);
    window.addEventListener('touchmove', onMove, { passive: true });
    window.addEventListener('touchend', onEnd);
};

const onThumbTouchStart = (e) => {
    if (e.touches && e.touches.length > 0) {
        startSliderDrag(e.touches[0].clientX);
    }
};

const onThumbMouseDown = (e) => {
    startSliderDrag(e.clientX);
};

const generateLink = () => {
    if (form.koli_ids.length === 0) {
        playBeep('error');
        showMessage('error', '⚠️ Belum ada koli yang discan untuk dimuat!');
        return;
    }

    if (!isFleetConfigured.value) {
        playBeep('duplicate');
        showMessage('duplicate', '⚠️ Harap tentukan Supir / Rute armada terlebih dahulu.');
        isDriverScreenOpen.value = true;
        return;
    }

    if (!navigator.onLine) {
        queueManifestSync({
            action: 'create_manifest',
            rute: form.rute,
            ekspedisi: form.ekspedisi,
            driver_id: form.driver_id,
            vendor_name: form.vendor_name,
            vendor_resi: form.vendor_resi,
            destination_warehouse_id: form.destination_warehouse_id,
            koli_ids: [...form.koli_ids]
        });
        
        alert("OFFLINE MODE: Surat Jalan disimpan secara lokal! Pastikan terhubung internet untuk mendapatkan Link WA Supir.");
        scannedKolis.value = [];
        form.reset();
        activeTab.value = 'list';
        return;
    }

    form.post(route('manifest.store'), {
        onSuccess: (page) => {
            const flash = page.props.flash ?? usePage().props.flash;
            if (flash?.wa_link) {
                window.open(flash.wa_link, '_blank');
            }
            scannedKolis.value = [];
            form.reset();
            activeTab.value = 'list';
        },
    });
};

const handleScan = (decodedText) => {
    if (decodedText) {
        prosesBarcode(decodedText);
    }
};
</script>

<template>
    <Head title="Surat Jalan & Manifest" />

    <div class="bg-[#f8f9fc] min-h-[100dvh] font-sans antialiased text-gray-800 pb-20 max-w-md mx-auto shadow-2xl relative selection:bg-primary/20">
        
        <!-- Header Ramping Selaras Menu Lain (White & Flat - Sticky Menyeluruh) -->
        <header 
            ref="headerRef"
            class="bg-white border-b border-gray-200 sticky top-0 z-40 transition-all shadow-xs"
        >
            <!-- BARIS 1: PALING ATAS / SEJAJAR NOTCH (Back + Tab mepet ke kiri, Search & Filter di kanan) -->
            <div v-show="!isCameraOpen" class="h-11 px-3 flex items-center justify-between gap-2 border-b border-gray-100 bg-white relative">
                <!-- Kiri: Tombol Back + 2 Tombol Tab (mepet berdampingan di sebelah tombol back) -->
                <div class="flex items-center gap-2 min-w-0">
                    <!-- Tombol Back -->
                    <Link href="/dashboard" class="w-7 h-7 rounded-full bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-600 hover:text-primary flex items-center justify-center shrink-0 shadow-2xs transition-colors" title="Kembali ke Dashboard">
                        <i class="ri-arrow-left-line text-sm"></i>
                    </Link>

                    <!-- Segmented Tabs (Muat & Riwayat) - mepet ke kiri sebelah tombol back (tidak tertutup notch) -->
                    <nav class="flex bg-gray-100/90 p-0.5 rounded-xl border border-gray-200/60 items-center shrink-0">
                        <button 
                            type="button"
                            @click="activeTab = 'buat'"
                            class="px-2 py-1 rounded-lg font-bold text-xs transition-all flex items-center gap-1 cursor-pointer"
                            :class="activeTab === 'buat' ? 'bg-primary text-white shadow-xs font-black' : 'text-gray-500 hover:text-gray-700'"
                        >
                            <i class="ri-add-box-line text-xs"></i>
                            <span>Muat</span>
                        </button>
                        <button 
                            type="button"
                            @click="activeTab = 'list'"
                            class="px-2 py-1 rounded-lg font-bold text-xs transition-all flex items-center gap-1 cursor-pointer"
                            :class="activeTab === 'list' ? 'bg-primary text-white shadow-xs font-black' : 'text-gray-500 hover:text-gray-700'"
                        >
                            <i class="ri-file-list-3-line text-xs"></i>
                            <span>Riwayat</span>
                        </button>
                    </nav>
                </div>

                <!-- Kanan: Search & Filter Actions (mepet ke kanan) -->
                <div class="flex items-center gap-1 shrink-0">
                    <button 
                        type="button"
                        @click="isSearchExpanded = true" 
                        class="w-7 h-7 bg-white border border-gray-200 rounded-full text-gray-600 hover:bg-gray-50 hover:text-primary transition-all shadow-2xs flex items-center justify-center cursor-pointer"
                        title="Cari"
                    >
                        <i class="ri-search-line text-xs"></i>
                    </button>
                    <button 
                        type="button"
                        @click="activeTab === 'buat' ? (isLocationFilterOpen = true) : (showFilterList = !showFilterList)" 
                        class="relative w-7 h-7 bg-white border border-gray-200 rounded-full text-gray-600 hover:bg-gray-50 hover:text-primary transition-all shadow-2xs flex items-center justify-center cursor-pointer"
                        title="Filter"
                    >
                        <i class="ri-filter-3-line text-xs"></i>
                        <span v-if="(activeTab === 'buat' && hasActiveLocationFilter)" class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white shadow-xs"></span>
                    </button>
                </div>

                <!-- Expanded Search Overlay (Mobile) -->
                <div v-if="isSearchExpanded" class="absolute inset-0 bg-white z-20 flex items-center px-3 gap-2 shadow-sm animate-fade-in">
                    <i class="ri-search-line text-gray-400 text-sm"></i>
                    <input 
                        v-if="activeTab === 'buat'"
                        type="text" 
                        v-model="searchQuery" 
                        placeholder="Cari resi, penerima, tujuan..." 
                        class="flex-1 border-none bg-transparent outline-none text-xs font-medium px-1 focus:ring-0" 
                        autofocus
                    />
                    <input 
                        v-else
                        type="text" 
                        v-model="searchQueryList" 
                        placeholder="Cari nomor manifest, supir, resi vendor..." 
                        class="flex-1 border-none bg-transparent outline-none text-xs font-medium px-1 focus:ring-0" 
                        autofocus
                    />
                    <button @click="isSearchExpanded = false" class="p-1 text-gray-400 hover:text-gray-600 cursor-pointer">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- BARIS 2: SUPIR & TOMBOL WA (Smart Reverse-Scroll: Ter-scroll saat ke bawah, segera sticky saat scroll balik) -->
            <div 
                v-show="!isCameraOpen" 
                v-if="activeTab === 'buat'" 
                class="overflow-hidden transition-[height,opacity] duration-200 ease-out bg-gray-50/75"
                :class="isDriverBarVisible ? 'h-10 opacity-100 border-b border-gray-100' : 'h-0 opacity-0 border-b-0 pointer-events-none'"
            >
                <div class="h-10 px-3 flex items-center justify-between gap-2">
                    <!-- Info Supir & Rute (Klik untuk Atur) -->
                    <button 
                        type="button"
                        @click="isDriverScreenOpen = true"
                        class="flex-1 min-w-0 px-2.5 py-1 rounded-xl border transition-all active:scale-98 flex items-center justify-between gap-1.5 cursor-pointer shadow-2xs"
                        :class="isFleetConfigured 
                            ? 'bg-primary/5 border-primary/25 text-primary hover:bg-primary/10' 
                            : 'bg-amber-50/90 border-amber-200 text-amber-900 hover:bg-amber-100'"
                        title="Klik untuk Pengaturan Armada & Supir"
                    >
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span 
                                class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] shrink-0"
                                :class="isFleetConfigured ? 'bg-primary text-white' : 'bg-amber-400 text-gray-900 font-bold'"
                            >
                                <i class="ri-truck-fill"></i>
                            </span>
                            <div class="min-w-0 text-left">
                                <p class="text-[8px] font-bold uppercase tracking-wider leading-none text-gray-500">
                                    {{ isFleetConfigured ? (form.rute || 'Rute Armada') : 'Armada & Supir' }}
                                </p>
                                <p class="text-[11px] font-black truncate leading-tight">
                                    {{ isFleetConfigured ? (form.ekspedisi === 'Internal' ? (driverAssignmentMode === 'auto' ? 'Bebas (Handshake)' : (selectedDriver?.name || 'Supir')) : ('Vendor ' + (form.vendor_name || ''))) : 'Belum Diatur ▾' }}
                                </p>
                            </div>
                        </div>
                        <span class="text-[10px] font-bold text-gray-400 flex items-center shrink-0">
                            <i class="ri-settings-4-line mr-0.5 text-xs"></i>
                            <i class="ri-arrow-right-s-line"></i>
                        </span>
                    </button>

                    <!-- Tombol Simpan (Membuka Modal Geser untuk Konfirmasi) -->
                    <button 
                        @click="openConfirmSaveModal" 
                        type="button"
                        :disabled="form.processing || form.koli_ids.length === 0"
                        class="h-[30px] px-3.5 rounded-xl text-xs font-bold bg-primary hover:bg-primaryDark text-white flex items-center gap-1.5 transition-all shadow-xs active:scale-95 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shrink-0"
                        title="Simpan Surat Jalan & Manifest"
                    >
                        <i class="ri-save-3-line text-sm"></i>
                        <span>Simpan</span>
                    </button>
                </div>
            </div>

            <!-- BARIS 3: COCKPIT SCANNER & CONTROL (Sticky menyatu 100% dalam Header) -->
            <div v-if="activeTab === 'buat'" class="bg-white">
                <!-- 1. Live Camera Preview (Saat Scanner Aktif) -->
                <div v-if="isCameraOpen" class="w-full h-[210px] bg-black relative">
                    <BarcodeScanner 
                        :cleanView="true"
                        :showClose="true"
                        initialMode="koli"
                        :allowModeSwitch="false"
                        @close="stopCamera"
                        @scan="handleScan" 
                        @error="(err) => console.log('Scanner error:', err)"
                    >
                        <!-- Area Input Manual di Bawah Kotak Bidik -->
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
                                        class="px-3 bg-primary hover:bg-primaryDark text-white text-xs font-bold rounded-xl transition-all active:scale-95 shrink-0 h-[30px] flex items-center justify-center shadow-lg cursor-pointer"
                                    >
                                        Input
                                    </button>
                                </Transition>
                            </form>
                        </template>
                    </BarcodeScanner>
                </div>

                <!-- 2. Integrated Control & Input Bar (1 Baris Ramping: [📦 0/23 Koli] [Input Barcode...] [📷]) -->
                <div :class="isCameraOpen ? 'px-3 py-1.5 bg-white border-b border-gray-100 flex items-center justify-between' : 'p-2 bg-white'">
                    <!-- 2A. Saat Kamera Aktif -->
                    <template v-if="isCameraOpen">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold text-gray-700">Muatan Truk:</span>
                            <span class="bg-primary/10 text-primary text-xs font-black px-2.5 py-0.5 rounded-full font-mono">
                                {{ loadedShipmentsCount }}/{{ totalShipmentsCount }} Resi · {{ form.koli_ids.length }}/{{ totalExpectedKolis }} Koli
                            </span>
                        </div>
                        <span class="text-[10px] text-green-700 font-bold bg-green-50 border border-green-200 px-2 py-0.5 rounded-full flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                            Scan Aktif
                        </span>
                    </template>

                    <!-- 2B. Saat Kamera Tutup: 1 Baris Tunggal [📦 Resi & Koli] [Input Barcode...] [📷] -->
                    <form v-else @submit.prevent="prosesBarcode()" class="flex items-center gap-1.5">
                        <!-- Badge Resi & Koli (2 Baris Ramping & Jelas) -->
                        <div 
                            class="h-[34px] px-2.5 bg-primary/10 border border-primary/20 text-primary rounded-xl flex flex-col justify-center items-start font-mono leading-none shrink-0 shadow-2xs"
                            title="Jumlah Resi dan Koli Masuk Truk"
                        >
                            <div class="flex items-center gap-1 text-[9px] font-bold text-gray-600">
                                <i class="ri-file-text-line text-[9px] text-primary"></i>
                                <span>{{ loadedShipmentsCount }}/{{ totalShipmentsCount }} Resi</span>
                            </div>
                            <div class="flex items-center gap-1 text-[11px] font-black text-primary mt-0.5">
                                <i class="ri-box-3-fill text-[10px] text-primary/70"></i>
                                <span>{{ form.koli_ids.length }}/{{ totalExpectedKolis }} Koli</span>
                            </div>
                        </div>

                        <!-- Input Barcode -->
                        <div class="relative flex-1">
                            <i class="ri-barcode-line absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                            <input 
                                ref="closedInputRef"
                                type="text" 
                                v-model="barcodeInput"
                                placeholder="Scan gun / ketik barcode..."
                                class="w-full pl-7 pr-2 py-1 bg-gray-50 hover:bg-white focus:bg-white border border-gray-200 rounded-xl text-xs font-mono font-bold text-gray-900 placeholder:font-sans placeholder-gray-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all uppercase h-[34px]"
                            />
                        </div>

                        <!-- Bergantian: Tombol Input (saat ada teks) vs Tombol Scan Kamera (saat kosong) -->
                        <Transition name="fade" mode="out-in">
                            <button 
                                v-if="barcodeInput && barcodeInput.trim().length > 0"
                                key="btn-input"
                                type="submit" 
                                class="px-3 bg-primary hover:bg-primaryDark text-white text-xs font-bold rounded-xl transition-all active:scale-95 shrink-0 h-[34px] flex items-center justify-center cursor-pointer shadow-xs"
                                title="Kirim / Input Barcode"
                            >
                                Input
                            </button>

                            <!-- Tombol Buka Kamera Scanner -->
                            <button 
                                v-else
                                key="btn-scan"
                                @click="openCamera" 
                                type="button"
                                class="w-[34px] h-[34px] rounded-xl flex items-center justify-center transition-all shadow-xs active:scale-95 shrink-0 cursor-pointer bg-primary hover:bg-primaryDark text-white shadow-primary/20"
                                title="Buka Kamera Scanner"
                            >
                                <i class="ri-barcode-box-line text-base"></i>
                            </button>
                        </Transition>
                    </form>
                </div>

                <!-- Progress Bar Ramping (2.5px) -->
                <div class="w-full bg-gray-100 h-[2.5px] overflow-hidden relative">
                    <div 
                        class="h-full bg-gradient-to-r from-primary to-green-500 transition-all duration-500"
                        :style="{ width: totalExpectedKolis > 0 ? ((form.koli_ids.length / totalExpectedKolis) * 100) + '%' : '0%' }"
                    ></div>
                </div>

                <!-- Feedback Toast -->
                <Transition name="fade">
                    <div 
                        v-if="scanMessage" 
                        class="mx-2 my-1.5 px-3 py-1.5 rounded-xl text-xs font-bold flex items-center justify-between gap-2 shadow-xs border transition-all"
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
                        <button @click="scanMessage = null" class="text-gray-400 hover:text-gray-600 text-sm cursor-pointer">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                </Transition>
            </div>
        </header>

        <!-- ==========================================
             TAB 1: MUAT SURAT JALAN BARU (SCAN & CHECKLIST)
             ========================================== -->
        <main v-show="activeTab === 'buat'" class="space-y-3 pt-2.5 px-3">

            <!-- DAFTAR RESI & KOLI (REALTIME CHECKLIST) -->
            <div class="space-y-3 pb-8">
                <!-- Indikator Filter/Pencarian Aktif (Jika ada query atau lokasi terpilih) -->
                <div v-if="searchQuery || hasActiveLocationFilter" class="flex items-center justify-between px-3 py-1.5 bg-primary/5 border border-primary/20 rounded-xl text-xs text-primary mb-1 shadow-2xs">
                    <div class="flex items-center gap-1.5 truncate">
                        <i class="ri-filter-fill text-primary text-xs"></i>
                        <span class="truncate text-[11px] font-medium">
                            <span v-if="searchQuery">Cari: "<strong>{{ searchQuery }}</strong>"</span>
                            <span v-if="searchQuery && hasActiveLocationFilter"> · </span>
                            <span v-if="filterProvinsi">{{ filterProvinsi }}</span>
                            <span v-if="filterKota">, {{ filterKota }}</span>
                            <span v-if="filterKecamatan">, {{ filterKecamatan }}</span>
                        </span>
                    </div>
                    <button @click="searchQuery = ''; resetLocationFilter()" class="text-primary hover:text-primaryDark font-bold ml-2 shrink-0 flex items-center gap-0.5 cursor-pointer text-[11px]">
                        <span>Reset</span>
                        <i class="ri-close-line text-sm"></i>
                    </button>
                </div>

                <!-- Peringatan Koli Kurang jika ada muatan parsial -->
                <div
                    v-for="warn in warnings"
                    :key="warn.resi"
                    class="flex items-start gap-2.5 p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs shadow-sm"
                >
                    <i class="ri-error-warning-fill text-base text-amber-600 shrink-0 mt-0.5"></i>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold">Resi {{ warn.resi }} belum lengkap di truk!</p>
                        <p class="mt-0.5 text-amber-700">
                            Baru dimuat {{ warn.scanned }} dari {{ warn.total }} koli (masih kurang <strong class="bg-amber-200/80 px-1 rounded">{{ warn.kurang }} koli</strong> lagi).
                        </p>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="filteredShipments.length === 0" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                    <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="ri-inbox-line text-3xl font-bold"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-sm">
                        {{ statusFilter === 'pending' ? 'Semua Koli Sudah Dimuat ke Truk!' : 'Tidak Ada Resi yang Cocok' }}
                    </h4>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ statusFilter === 'pending' ? 'Bagus! Semua muatan siap untuk diberangkatkan.' : 'Coba ubah tab filter atau kata kunci pencarian.' }}
                    </p>
                </div>

                <!-- Card Shipments & Kolis -->
                <div v-else class="space-y-3">
                    <div 
                        v-for="shipment in filteredShipments" 
                        :key="shipment.id" 
                        :id="'shipment-card-' + shipment.tracking_number"
                        class="bg-white rounded-2xl shadow-sm border p-3.5 transition-all duration-300"
                        :class="[
                            highlightedShipmentId === shipment.tracking_number 
                                ? 'ring-2 ring-primary border-primary shadow-lg ring-offset-2' 
                                : isShipmentComplete(shipment) 
                                    ? 'border-green-300 ring-1 ring-green-100 bg-green-50/15' 
                                    : 'border-gray-200/80'
                        ]"
                    >
                        <!-- Header Resi -->
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-black text-sm text-gray-900 tracking-wide">{{ shipment.tracking_number }}</span>
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 font-mono">
                                        {{ shipment.total_koli }} Koli
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5 flex items-center gap-1 truncate max-w-[200px]">
                                    <i class="ri-user-3-line text-gray-400"></i> {{ shipment.sender }} → {{ shipment.recipient_name }}
                                </p>
                                <p class="text-[11px] text-gray-400 truncate mt-0.5">
                                    <i class="ri-map-pin-line text-gray-400"></i> {{ shipment.destination }}
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
                                Belum Dimuat
                            </span>
                        </div>

                        <!-- Mini Progress Bar -->
                        <div class="mb-2.5 bg-gray-50/80 p-2 rounded-xl border border-gray-100">
                            <div class="flex justify-between items-center text-[10px] text-gray-500 mb-1 font-semibold">
                                <span>Status Muatan:</span>
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

                        <!-- Daftar Koli Fisik -->
                        <div class="space-y-1.5 border-t border-gray-100 pt-2.5">
                            <div 
                                v-for="koli in shipment.kolis" 
                                :key="koli.id" 
                                :id="'koli-item-' + (koli.barcode_id || koli.barcode || koli.id).toUpperCase()"
                                class="flex items-center justify-between text-xs p-2 rounded-xl transition-all duration-300 border select-none"
                                :class="[
                                    highlightedKoliId === (koli.barcode_id || koli.barcode || '').toUpperCase() 
                                        ? 'ring-2 ring-primary ring-offset-1 bg-amber-50/90 border-primary font-bold shadow-md scale-[1.01]' 
                                        : isKoliLoaded(koli) 
                                            ? 'bg-green-50/90 border-green-300 text-green-900 shadow-sm' 
                                            : 'bg-gray-50/70 border-gray-100 text-gray-700'
                                ]"
                            >
                                <div class="min-w-0 pr-2">
                                    <div class="truncate font-mono font-bold flex items-center gap-1.5" :class="isKoliLoaded(koli) ? 'text-green-800' : 'text-gray-800'">
                                        <i class="ri-barcode-line text-xs" :class="isKoliLoaded(koli) ? 'text-green-600' : 'text-gray-400'"></i>
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
                                    <span v-if="isKoliLoaded(koli)" class="text-[9px] font-bold text-green-700 bg-green-200/60 px-1.5 py-0.5 rounded">
                                        Dimuat
                                    </span>
                                    <span v-else class="text-[9px] font-medium text-gray-400 bg-gray-200/50 px-1.5 py-0.5 rounded">
                                        Di Gudang
                                    </span>
                                    <!-- Tombol Batalkan / Keluarkan Koli jika sudah dimuat -->
                                    <button 
                                        v-if="isKoliLoaded(koli)"
                                        type="button"
                                        @click="hapusKoli(koli.id)"
                                        class="text-red-400 hover:text-red-600 p-0.5 rounded hover:bg-red-50 transition-colors cursor-pointer"
                                        title="Keluarkan koli dari truk"
                                    >
                                        <i class="ri-close-circle-line text-base"></i>
                                    </button>
                                    <i v-else class="ri-checkbox-blank-circle-line text-gray-300 text-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- ==========================================
             TAB 2: DAFTAR RIWAYAT SURAT JALAN
             ========================================== -->
        <main v-show="activeTab === 'list'" class="pt-3 px-3 space-y-3 pb-8 animate-fade-in">
            <!-- Indikator Pencarian Riwayat Aktif (Jika dicari dari Header 🔍) -->
            <div v-if="searchQueryList" class="flex items-center justify-between px-3 py-1.5 bg-primary/5 border border-primary/20 rounded-xl text-xs text-primary shadow-2xs">
                <div class="flex items-center gap-1.5 truncate">
                    <i class="ri-search-line text-primary text-xs"></i>
                    <span class="truncate text-[11px] font-medium">
                        Hasil cari: "<strong>{{ searchQueryList }}</strong>" ({{ filteredManifests.length }} surat jalan)
                    </span>
                </div>
                <button @click="searchQueryList = ''" class="text-primary hover:text-primaryDark font-bold ml-2 shrink-0 flex items-center gap-0.5 cursor-pointer text-[11px]">
                    <span>Reset</span>
                    <i class="ri-close-line text-sm"></i>
                </button>
            </div>

            <!-- Empty State Riwayat -->
            <div v-if="filteredManifests.length === 0" class="text-center py-10 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <i class="ri-file-list-3-line text-4xl text-gray-300 mb-2 block"></i>
                <p class="text-gray-500 font-medium text-xs">
                    {{ searchQueryList ? 'Tidak ada surat jalan yang cocok dengan kata kunci "' + searchQueryList + '".' : 'Belum ada riwayat Surat Jalan.' }}
                </p>
                <button v-if="searchQueryList" @click="searchQueryList = ''" class="mt-2 text-xs font-bold text-primary hover:underline cursor-pointer">
                    Hapus Kata Kunci
                </button>
            </div>

            <!-- List Cards Riwayat (Mobile-First) -->
            <div v-else class="space-y-3">
                <div 
                    v-for="manifest in filteredManifests" 
                    :key="manifest.id" 
                    class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm relative overflow-hidden transition-all space-y-2.5"
                >
                    <div class="absolute top-0 left-0 w-1.5 h-full" :class="manifest.status === 'SELESAI' ? 'bg-emerald-500' : (manifest.type === 'DELIVERY' ? 'bg-blue-500' : 'bg-purple-500')"></div>

                    <!-- Header Manifest -->
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400">MANIFEST-{{ manifest.id.toString().padStart(4, '0') }}</span>
                            <h3 class="font-extrabold text-sm text-gray-800 leading-tight">
                                {{ manifest.type === 'DELIVERY' ? 'Pengiriman Lintas' : 'Penjemputan Barang' }}
                            </h3>
                        </div>
                        <span v-if="manifest.status === 'SELESAI'" class="text-[10px] bg-emerald-50 text-emerald-600 border border-emerald-100 px-2 py-0.5 rounded-md font-bold uppercase tracking-wider shadow-sm flex items-center gap-1 shrink-0">
                            <i class="ri-check-double-line"></i> Selesai
                        </span>
                        <span v-else class="text-[10px] bg-blue-50 text-blue-600 border border-blue-100 px-2 py-0.5 rounded-md font-bold uppercase tracking-wider shadow-sm flex items-center gap-1 shrink-0">
                            <span class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-pulse"></span> {{ manifest.type === 'DELIVERY' ? 'Perjalanan' : 'Penjemputan' }}
                        </span>
                    </div>

                    <!-- Info Details -->
                    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600 font-medium">
                        <span class="flex items-center gap-1 bg-gray-50 px-2 py-0.5 rounded-md text-[11px]">
                            <i :class="manifest.driver === 'Vendor' ? 'ri-building-4-fill' : 'ri-user-fill'" class="text-gray-400"></i> {{ manifest.driver }}
                        </span>
                        <span class="flex items-center gap-1 bg-gray-50 px-2 py-0.5 rounded-md text-[11px]">
                            <i :class="manifest.driver === 'Vendor' ? 'ri-barcode-box-fill' : 'ri-truck-fill'" class="text-gray-400"></i> {{ manifest.driver === 'Vendor' ? ('Resi: ' + (manifest.resi_vendor || '-')) : manifest.vehicle }}
                        </span>
                        <span class="flex items-center gap-1 bg-gray-50 px-2 py-0.5 rounded-md text-[10px] text-gray-400">
                            <i class="ri-calendar-line"></i> {{ manifest.created_at }}
                        </span>
                    </div>

                    <!-- Action Bar & Muatan -->
                    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                        <div>
                            <p class="text-[9px] text-gray-400 font-bold uppercase">Muatan</p>
                            <p class="text-base font-black text-primary leading-none">{{ manifest.total_koli }} <span class="text-[10px] font-bold text-gray-400">Koli</span></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <!-- Tombol Tampilkan QR Handshake -->
                            <button 
                                type="button"
                                @click="openQrModal(manifest)"
                                class="px-2.5 py-1.5 bg-slate-900 hover:bg-black text-white font-bold text-xs rounded-xl transition-all flex items-center gap-1 shadow-sm active:scale-95 cursor-pointer"
                                title="Tampilkan QR Code Handshake untuk di-scan supir"
                            >
                                <i class="ri-qr-code-line text-primary"></i>
                                <span>QR</span>
                            </button>

                            <!-- Tombol Cetak Dokumen Surat Jalan -->
                            <a 
                                :href="'/manifest/' + manifest.id + '/print?autoprint=1'" 
                                target="_blank"
                                class="px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs rounded-xl transition-all flex items-center gap-1 border border-gray-200 shadow-sm active:scale-95"
                                title="Cetak Surat Jalan / Surat Tugas (Print)"
                            >
                                <i class="ri-printer-line text-gray-600"></i>
                                <span>Cetak</span>
                            </a>

                            <a :href="manifest.type === 'DELIVERY' ? '/supir/lintas?token=' + manifest.token : '/supir/pickup?token=' + manifest.token" target="_blank" class="px-2.5 py-1.5 bg-primary/10 hover:bg-primary/20 text-primary font-bold text-xs rounded-xl transition-colors flex items-center gap-1 border border-primary/20 shadow-sm active:scale-95">
                                <i class="ri-external-link-line"></i> Link
                            </a>
                            <a v-if="manifest.wa_link" :href="manifest.wa_link" target="_blank" class="px-2.5 py-1.5 bg-[#B89B7D] hover:bg-[#A6886A] text-white font-bold text-xs rounded-xl transition-colors flex items-center gap-1 shadow-sm active:scale-95">
                                <i class="ri-whatsapp-line"></i> WA
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Load More Button -->
                <div v-if="hasMoreManifests" class="flex justify-center pt-2 pb-6">
                    <button @click="loadMoreManifests" :disabled="loadingMore" class="px-5 py-2 bg-white border border-gray-200 text-gray-600 font-bold text-xs rounded-full shadow-sm hover:bg-gray-50 transition-all flex items-center gap-1.5 active:scale-95 disabled:opacity-50">
                        <span v-if="loadingMore" class="w-3.5 h-3.5 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                        <i v-else class="ri-refresh-line"></i>
                        Muat Lebih Banyak
                    </button>
                </div>
            </div>
        </main>

        <!-- ==========================================
             LAYAR PENUH FORM SUPIR & ARMADA (SLIDE DARI KANAN)
             (Persis seperti Modal Konfirmasi Sampai di Halaman Supir)
             ========================================== -->
        <Transition name="slide-screen">
            <div 
                v-if="isDriverScreenOpen" 
                class="fixed inset-0 z-50 bg-[#f8f9fc] flex flex-col max-w-md mx-auto shadow-2xl pt-[35px] overflow-hidden"
            >
                <!-- Top App Bar dengan Tombol Back [←] -->
                <div class="px-3.5 py-3 bg-white border-b border-gray-100 flex items-center justify-between sticky top-0 z-20 shadow-sm">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <button 
                            type="button"
                            @click="isDriverScreenOpen = false" 
                            class="w-8 h-8 flex items-center justify-center bg-gray-50 hover:bg-gray-100 rounded-xl text-gray-700 active:scale-95 transition-all border border-gray-200 cursor-pointer"
                            title="Kembali ke Scanner & Muatan"
                        >
                            <i class="ri-arrow-left-line text-lg"></i>
                        </button>
                        <div class="min-w-0">
                            <h3 class="font-extrabold text-gray-900 text-sm leading-tight truncate">Pengaturan Armada & Supir</h3>
                            <p class="text-[10px] text-gray-500 truncate">Atur supir atau vendor ekspedisi</p>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary shrink-0">
                        Langkah 1/2
                    </span>
                </div>

                <!-- Form Fields Body (Scrollable) -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3.5 pb-24">
                    <!-- Rute Armada -->
                    <div class="bg-white p-3.5 rounded-2xl border border-gray-100 shadow-sm space-y-1.5">
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide">
                            Rute Armada <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            v-model="form.rute" 
                            placeholder="Misal: Jepara → Jakarta" 
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 bg-gray-50 focus:bg-white text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-semibold"
                        />
                        <p class="text-[11px] text-gray-400">Rute perjalanan atau area tujuan supir.</p>
                    </div>

                    <!-- Tujuan Gudang Drop -->
                    <div class="bg-white p-3.5 rounded-2xl border border-gray-100 shadow-sm space-y-2">
                        <div class="flex justify-between items-center">
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide">Tujuan Gudang Drop</label>
                            <button 
                                type="button" 
                                @click="showAddWarehouse = true" 
                                class="text-xs font-bold text-primary hover:text-primaryLight flex items-center gap-1 px-2 py-0.5 rounded-lg hover:bg-primary/5 transition cursor-pointer"
                            >
                                <i class="ri-store-2-line"></i> + Gudang
                            </button>
                        </div>
                        <select 
                            v-model="form.destination_warehouse_id" 
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 bg-gray-50 focus:bg-white text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all cursor-pointer font-medium"
                        >
                            <option :value="null">Langsung ke Penerima (Last Mile)</option>
                            <option v-for="w in warehousesList" :key="w.id" :value="w.id">Gudang Drop: {{ w.name }} ({{ w.city }})</option>
                        </select>
                    </div>

                    <!-- Jenis Ekspedisi Switcher -->
                    <div class="bg-white p-3.5 rounded-2xl border border-gray-100 shadow-sm space-y-2.5">
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wide">Jenis Ekspedisi</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button 
                                type="button"
                                @click="form.ekspedisi = 'Internal'"
                                class="py-2.5 px-3 rounded-xl border text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                :class="form.ekspedisi === 'Internal' ? 'bg-primary text-white border-primary shadow-sm' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'"
                            >
                                <i class="ri-truck-fill"></i>
                                <span>Armada Internal</span>
                            </button>
                            <button 
                                type="button"
                                @click="form.ekspedisi = 'Vendor'"
                                class="py-2.5 px-3 rounded-xl border text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                :class="form.ekspedisi === 'Vendor' ? 'bg-primary text-white border-primary shadow-sm' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'"
                            >
                                <i class="ri-building-4-fill"></i>
                                <span>Titip Vendor</span>
                            </button>
                        </div>

                        <!-- Supir Internal -->
                        <div v-if="form.ekspedisi === 'Internal'" class="pt-2 border-t border-gray-100 space-y-3">
                            <!-- Switch/Toggle Mode Penugasan Supir -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1.5">Penugasan Supir</label>
                                <div class="grid grid-cols-2 gap-1.5 p-1 bg-gray-100 rounded-xl">
                                    <button 
                                        type="button"
                                        @click="driverAssignmentMode = 'auto'; form.driver_id = ''"
                                        class="py-2 px-2.5 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                        :class="driverAssignmentMode === 'auto' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'"
                                    >
                                        <i class="ri-qr-scan-2-line text-primary"></i>
                                        <span>Bebas (Handshake)</span>
                                    </button>
                                    <button 
                                        type="button"
                                        @click="driverAssignmentMode = 'manual'"
                                        class="py-2 px-2.5 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                        :class="driverAssignmentMode === 'manual' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'"
                                    >
                                        <i class="ri-user-settings-line text-blue-600"></i>
                                        <span>Pilih Manual</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Mode Bebas / Handshake Pool -->
                            <div v-if="driverAssignmentMode === 'auto'" class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200/80 text-amber-900 flex items-start gap-2.5 text-xs animate-fadeIn">
                                <i class="ri-information-fill text-amber-600 text-lg shrink-0 mt-0.5"></i>
                                <div class="leading-relaxed">
                                    <p class="font-extrabold text-xs text-amber-950">Pool Armada Terbuka (Handshake)</p>
                                    <p class="text-[11px] text-amber-800 mt-1 leading-normal">
                                        Surat jalan bisa langsung dicetak tanpa supir. Siapapun supir resmi yang memindai QR pada surat jalan fisik di gudang akan otomatis ditugaskan.
                                    </p>
                                </div>
                            </div>

                            <!-- Mode Pilih Manual -->
                            <div v-else class="space-y-2 animate-fadeIn">
                                <div class="flex justify-between items-center">
                                    <label class="block text-xs font-bold text-gray-700">Pilih Supir <span class="text-red-500">*</span></label>
                                    <button 
                                        v-if="!selectedDriver" 
                                        type="button" 
                                        @click="showAddDriver = true" 
                                        class="text-xs font-bold text-primary hover:text-primaryLight flex items-center gap-1 cursor-pointer"
                                    >
                                        <i class="ri-add-circle-fill"></i> Supir Baru
                                    </button>
                                </div>
                                <select 
                                    v-if="!selectedDriver" 
                                    v-model="form.driver_id" 
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 bg-gray-50 focus:bg-white text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all cursor-pointer font-medium"
                                >
                                    <option value="">Pilih Supir...</option>
                                    <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                        {{ driver.name }} — {{ driver.vehicle_name }}
                                    </option>
                                </select>
                                <div v-else class="flex items-center gap-3 bg-primary/5 border border-primary/20 rounded-xl p-3">
                                    <img v-if="selectedDriver.avatar_path" :src="'/storage/' + selectedDriver.avatar_path" class="w-10 h-10 rounded-full object-cover border-2 border-primary/30 shrink-0">
                                    <div v-else class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center shrink-0">
                                        <i class="ri-user-line text-primary text-lg"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-dark text-sm truncate">{{ selectedDriver.name }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ selectedDriver.vehicle_name }}</p>
                                    </div>
                                    <button 
                                        type="button" 
                                        @click="form.driver_id = ''" 
                                        class="text-gray-400 hover:text-red-500 p-1.5 rounded-lg transition-colors cursor-pointer"
                                        title="Ganti Supir"
                                    >
                                        <i class="ri-close-circle-line text-xl"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Vendor Fields -->
                        <div v-if="form.ekspedisi === 'Vendor'" class="pt-2 border-t border-gray-100 space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Nama Vendor Ekspedisi <span class="text-red-500">*</span></label>
                                <input 
                                    type="text" 
                                    v-model="form.vendor_name" 
                                    placeholder="Misal: JNE, TIKI, Sicepat..." 
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 bg-gray-50 focus:bg-white text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-medium"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">No. Resi Vendor</label>
                                <input 
                                    type="text" 
                                    v-model="form.vendor_resi" 
                                    placeholder="Nomor resi vendor..." 
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 bg-gray-50 focus:bg-white text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-medium"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Button: Simpan & Lanjutkan Muat -->
                <div class="p-3 bg-white border-t border-gray-100 shadow-lg mt-auto">
                    <button 
                        type="button"
                        @click="isDriverScreenOpen = false" 
                        class="w-full py-3 bg-primary hover:bg-primaryDark text-white font-bold rounded-xl transition-all shadow-md active:scale-95 flex items-center justify-center gap-2 text-sm cursor-pointer"
                    >
                        <i class="ri-check-line text-lg"></i>
                        <span>Simpan & Lanjutkan Muat</span>
                    </button>
                </div>
            </div>
        </Transition>

        <!-- MODAL TAMBAH SUPIR BARU -->
        <div v-if="showAddDriver" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl w-full max-w-sm overflow-hidden shadow-xl">
                <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-bold text-base text-dark">Tambah Supir Baru</h3>
                    <button @click="showAddDriver = false" class="text-gray-400 hover:text-gray-600 transition">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
                <div class="p-5 space-y-3.5">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Nama Supir</label>
                        <input v-model="driverFormAdd.name" type="text" placeholder="Misal: Agus" class="w-full border border-gray-300 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">No. WhatsApp</label>
                        <input v-model="driverFormAdd.phone_number" type="tel" placeholder="Misal: 08123456789" class="w-full border border-gray-300 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Kendaraan / Plat</label>
                        <input v-model="driverFormAdd.vehicle_name" type="text" placeholder="Misal: L300 - K 1234 AB" class="w-full border border-gray-300 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition-all">
                    </div>
                </div>
                <div class="px-5 py-3.5 bg-gray-50 border-t border-gray-100 flex justify-end gap-2.5">
                    <button @click="showAddDriver = false" class="px-3.5 py-2 text-gray-600 font-medium hover:bg-gray-200 rounded-xl transition text-xs">Batal</button>
                    <button @click="saveDriver" :disabled="driverFormAdd.processing" class="px-4 py-2 bg-primary text-white font-bold rounded-xl hover:bg-primaryLight transition text-xs flex items-center gap-1.5 shadow-sm">
                        <span v-if="driverFormAdd.processing" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        Simpan & Pilih
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL TAMBAH GUDANG CABANG BARU (QUICK ADD) -->
        <div v-if="showAddWarehouse" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm animate-fadeIn">
            <div class="bg-white rounded-2xl w-full max-w-sm overflow-hidden shadow-2xl border border-gray-100">
                <div class="px-5 py-3.5 border-b border-gray-100 flex justify-between items-center bg-gray-50/70">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center text-sm">
                            <i class="ri-store-2-fill"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-gray-900">Tambah Gudang Cabang</h3>
                            <p class="text-[10px] text-gray-500">Transit / Gudang drop</p>
                        </div>
                    </div>
                    <button @click="showAddWarehouse = false" class="text-gray-400 hover:text-gray-600 transition p-1 rounded-lg">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>
                <div class="p-4 space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Nama Gudang <span class="text-red-500">*</span></label>
                        <input v-model="warehouseFormAdd.name" type="text" placeholder="Misal: Gudang Transit Jakarta" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Kota / Wilayah <span class="text-red-500">*</span></label>
                        <input v-model="warehouseFormAdd.city" type="text" placeholder="Misal: Jakarta Barat" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Alamat</label>
                        <textarea v-model="warehouseFormAdd.address" rows="2" placeholder="Jalan, Patokan..." class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"></textarea>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex justify-end gap-2">
                    <button @click="showAddWarehouse = false" type="button" class="px-3.5 py-2 text-gray-600 font-semibold hover:bg-gray-200 rounded-xl transition text-xs">Batal</button>
                    <button @click="saveWarehouse" :disabled="isSavingWarehouse" type="button" class="px-4 py-2 bg-primary text-white font-bold rounded-xl hover:bg-primaryLight transition text-xs flex items-center gap-1.5 shadow-sm shadow-primary/30">
                        <span v-if="isSavingWarehouse" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        Simpan & Pilih
                    </button>
                </div>
            </div>
        </div>
        <!-- MODAL FILTER LOKASI (TAB MUAT BARU) -->
        <Transition name="fade">
            <div v-if="isLocationFilterOpen" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-black/50 backdrop-blur-xs">
                <div class="bg-white w-full max-w-sm rounded-t-2xl sm:rounded-2xl overflow-hidden shadow-2xl border border-gray-100 flex flex-col max-h-[85vh]">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-gray-50/80">
                        <div class="flex items-center gap-2">
                            <i class="ri-map-pin-range-fill text-primary text-base"></i>
                            <h3 class="font-bold text-xs text-gray-800">Filter Wilayah Pengiriman</h3>
                        </div>
                        <button @click="isLocationFilterOpen = false" class="text-gray-400 hover:text-gray-600 p-1 cursor-pointer">
                            <i class="ri-close-line text-lg"></i>
                        </button>
                    </div>

                    <div class="p-4 space-y-3.5 overflow-y-auto">
                        <!-- Provinsi -->
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-gray-700 uppercase">Provinsi</label>
                            <select 
                                v-model="filterProvinsi" 
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs bg-gray-50 focus:bg-white outline-none focus:ring-1 focus:ring-primary cursor-pointer font-medium"
                            >
                                <option value="">Semua Provinsi</option>
                                <option v-for="prov in availableProvinsi" :key="prov" :value="prov">{{ prov }}</option>
                            </select>
                        </div>

                        <!-- Kota / Kabupaten -->
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-gray-700 uppercase">Kota / Kabupaten</label>
                            <select 
                                v-model="filterKota" 
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs bg-gray-50 focus:bg-white outline-none focus:ring-1 focus:ring-primary cursor-pointer font-medium"
                            >
                                <option value="">Semua Kota</option>
                                <option v-for="kota in availableKota" :key="kota" :value="kota">{{ kota }}</option>
                            </select>
                        </div>

                        <!-- Kecamatan -->
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-gray-700 uppercase">Kecamatan</label>
                            <select 
                                v-model="filterKecamatan" 
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs bg-gray-50 focus:bg-white outline-none focus:ring-1 focus:ring-primary cursor-pointer font-medium"
                            >
                                <option value="">Semua Kecamatan</option>
                                <option v-for="kec in availableKecamatan" :key="kec" :value="kec">{{ kec }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-2">
                        <button 
                            type="button"
                            @click="resetLocationFilter"
                            class="px-3 py-1.5 text-xs font-bold text-gray-500 hover:text-red-500 cursor-pointer"
                        >
                            Reset
                        </button>
                        <button 
                            type="button"
                            @click="isLocationFilterOpen = false"
                            class="px-4 py-1.5 bg-primary hover:bg-primaryDark text-white text-xs font-bold rounded-xl shadow-xs transition active:scale-95 cursor-pointer"
                        >
                            Terapkan
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ==========================================
             MODAL GESER UNTUK SIMPAN (SLIDE TO CONFIRM)
             Mencegah tombol simpan kepencet tidak sengaja
             ========================================== -->
        <Transition name="fade">
            <div 
                v-if="isConfirmSaveModalOpen" 
                class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4 animate-fade-in"
                @click.self="closeConfirmSaveModal"
            >
                <div class="bg-white w-full max-w-md rounded-t-3xl sm:rounded-3xl p-5 shadow-2xl border border-gray-100 space-y-4 max-h-[90vh] overflow-y-auto animate-slide-up">
                    <!-- Header Modal -->
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-bold text-lg">
                                <i class="ri-checkbox-circle-line"></i>
                            </div>
                            <div>
                                <h3 class="font-black text-gray-900 text-sm">Selesai Muat Truk?</h3>
                                <p class="text-[11px] text-gray-500">Periksa muatan sebelum menyimpan</p>
                            </div>
                        </div>
                        <button 
                            @click="closeConfirmSaveModal" 
                            type="button" 
                            class="w-8 h-8 rounded-full bg-gray-100 text-gray-400 hover:text-gray-700 hover:bg-gray-200 flex items-center justify-center cursor-pointer transition-colors"
                        >
                            <i class="ri-close-line text-lg"></i>
                        </button>
                    </div>

                    <!-- Ringkasan Armada & Muatan -->
                    <div class="bg-gray-50 rounded-2xl p-3.5 space-y-2.5 border border-gray-100 text-xs">
                        <div class="flex justify-between items-center pb-2 border-b border-gray-200/60">
                            <span class="text-gray-500 font-bold">Rute & Armada</span>
                            <span class="font-black text-gray-800 text-right">
                                {{ form.rute || '-' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center pb-2 border-b border-gray-200/60">
                            <span class="text-gray-500 font-bold">Supir / Kendaraan</span>
                            <span class="font-extrabold text-gray-800 text-right">
                                {{ form.ekspedisi === 'Internal' ? (selectedDriver?.name || 'Supir') : ('Vendor ' + (form.vendor_name || '')) }}
                                <span v-if="selectedDriver?.vehicle_name" class="text-gray-400 font-normal">({{ selectedDriver.vehicle_name }})</span>
                            </span>
                        </div>
                        <div class="flex justify-between items-center pb-2 border-b border-gray-200/60">
                            <span class="text-gray-500 font-bold">Jumlah Resi Termuat</span>
                            <span class="font-black text-gray-900 text-sm font-mono">
                                {{ loadedShipmentsCount }} Resi
                                <span v-if="completedShipmentsCount > 0" class="text-[11px] text-green-600 font-bold ml-1">({{ completedShipmentsCount }} Lengkap)</span>
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 font-bold">Total Koli Masuk Truk</span>
                            <span class="font-black text-primary text-sm font-mono">
                                {{ form.koli_ids.length }} Koli
                            </span>
                        </div>
                    </div>

                    <!-- Peringatan Jika Ada Koli Belum Lengkap -->
                    <div v-if="warnings.length > 0" class="p-3 bg-amber-50 border border-amber-200 rounded-2xl text-xs space-y-1.5">
                        <div class="flex items-center gap-1.5 text-amber-900 font-black">
                            <i class="ri-alert-fill text-amber-500 text-sm"></i>
                            <span>Perhatian: Ada {{ warnings.length }} Resi Belum Lengkap!</span>
                        </div>
                        <p class="text-[11px] text-amber-700 leading-tight">
                            Beberapa koli fisik belum di-scan dan mungkin tertinggal di gudang:
                        </p>
                        <div class="max-h-24 overflow-y-auto space-y-1 pt-1">
                            <div 
                                v-for="w in warnings" 
                                :key="w.resi" 
                                class="flex justify-between text-[11px] bg-white/70 px-2 py-1 rounded-lg border border-amber-200/50"
                            >
                                <span class="font-mono font-bold text-gray-700">{{ w.resi }}</span>
                                <span class="text-amber-800 font-bold">Muat {{ w.scanned }}/{{ w.total }} (Kurang {{ w.kurang }})</span>
                            </div>
                        </div>
                    </div>

                    <!-- THE SLIDE TO CONFIRM TRACK -->
                    <div class="pt-2">
                        <div 
                            ref="sliderTrackRef"
                            class="relative h-13 bg-gray-100 rounded-2xl p-1 flex items-center select-none overflow-hidden border border-gray-200/80 shadow-inner"
                        >
                            <!-- Progress fill background -->
                            <div 
                                class="absolute left-0 top-0 bottom-0 bg-gradient-to-r from-primary to-green-600 transition-all rounded-2xl opacity-90"
                                :style="{ width: `calc(${sliderOffset}px + 46px)` }"
                            ></div>

                            <!-- Shimmering Instruction Text -->
                            <div 
                                class="absolute inset-0 flex items-center justify-center pointer-events-none transition-opacity"
                                :class="sliderProgress > 30 ? 'opacity-0' : 'opacity-100'"
                            >
                                <span class="text-xs font-black uppercase tracking-wider text-gray-500 flex items-center gap-1.5 animate-pulse pl-10">
                                    <span>Geser untuk Simpan</span>
                                    <i class="ri-arrow-right-double-line text-sm"></i>
                                </span>
                            </div>

                            <!-- Success Text (when completed) -->
                            <div 
                                v-if="isSaveSuccess" 
                                class="absolute inset-0 flex items-center justify-center pointer-events-none z-10 text-white font-black text-xs uppercase tracking-wider"
                            >
                                <i class="ri-check-line text-lg mr-1"></i> Menyimpan Manifest...
                            </div>

                            <!-- Draggable Thumb Button -->
                            <div 
                                @touchstart.passive="onThumbTouchStart"
                                @mousedown="onThumbMouseDown"
                                class="relative z-20 w-[46px] h-[46px] rounded-xl flex items-center justify-center text-white cursor-grab active:cursor-grabbing shadow-md transition-transform"
                                :class="isSaveSuccess ? 'bg-green-600 shadow-green-500/30' : 'bg-primary hover:bg-primaryDark active:scale-95 shadow-primary/30'"
                                :style="{ 
                                    transform: `translateX(${sliderOffset}px)`,
                                    transition: isDraggingSlider ? 'none' : 'transform 0.25s ease-out'
                                }"
                            >
                                <i v-if="isSaveSuccess" class="ri-check-line text-xl font-bold"></i>
                                <i v-else class="ri-truck-line text-xl font-bold animate-bounce-horizontal"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Batal / Lanjut Muat -->
                    <button 
                        @click="closeConfirmSaveModal" 
                        type="button" 
                        class="w-full py-2.5 text-xs font-bold text-gray-500 hover:text-gray-800 transition active:scale-98 cursor-pointer"
                    >
                        Batal, Masih Mau Muat Lagi
                    </button>
                </div>
            </div>
        </Transition>

        <!-- MODAL QR CODE HANDSHAKE & PRINT MANIFEST -->
        <ManifestQrModal
            :show="isQrModalOpen"
            :manifest="selectedQrManifest"
            @close="isQrModalOpen = false"
            @handshaked="handleHandshakeSuccess"
        />

    </div>
</template>

<style scoped>
@keyframes bounce-horizontal {
  0%, 100% { transform: translateX(0); }
  50% { transform: translateX(4px); }
}
.animate-bounce-horizontal {
  animation: bounce-horizontal 1.2s infinite ease-in-out;
}

.slide-screen-enter-active,
.slide-screen-leave-active {
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
}
.slide-screen-enter-from,
.slide-screen-leave-to {
  transform: translateX(100%);
  opacity: 0.9;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.list-enter-active,
.list-leave-active {
  transition: all 0.3s ease;
}
.list-enter-from,
.list-leave-to {
  opacity: 0;
  transform: translateX(-15px);
}
</style>
