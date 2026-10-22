<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import localforage from 'localforage';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import SwipeableTabs from '@/Components/SwipeableTabs.vue';
import { useTabCache } from '@/Composables/useTabCache';
import { useFilterCache } from '@/Composables/useFilterCache';
import AppDrawer from '@/Components/AppDrawer.vue';
import LoadMoreButton from '@/Components/LoadMoreButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Browser } from '@capacitor/browser';
import { Capacitor } from '@capacitor/core';

const isSearchExpanded = ref(false);

const filteredItems = (query) => {
    if (!query) return props.suggestedItems;
    const q = query.toLowerCase();
    return props.suggestedItems.filter(item => item.toLowerCase().includes(q));
};

// ---------------------------------------------------------
// PROPS (Data dari Controller)
// ---------------------------------------------------------
const props = defineProps({
    senders: {
        type: Array,
        default: () => []
    },
    recipients: {
        type: Array,
        default: () => []
    },
    suggestedItems: {
        type: Array,
        default: () => []
    },
    units: {
        type: Array,
        default: () => []
    },
    recentShipments: {
        type: Array,
        default: () => []
    }
});

// ---------------------------------------------------------
// DATA API WILAYAH (OFFLINE READY)
// ---------------------------------------------------------
const DB_WILAYAH = 'jepara_wilayah_master';
const isWilayahDownloading = ref(false);

const precacheWilayah = async () => {
    try {
        let cached = await localforage.getItem(DB_WILAYAH);
        if (!cached || !Array.isArray(cached) || cached.length === 0) {
            isWilayahDownloading.value = true;
            console.log("Mengunduh Master Data Wilayah (5 MB) untuk Offline Mode...");
            const res = await fetch('/wilayah.json');
            if (res.ok) {
                const data = await res.json();
                if (Array.isArray(data)) {
                    await localforage.setItem(DB_WILAYAH, data);
                }
            }
            isWilayahDownloading.value = false;
            console.log("Selesai mengunduh!");
        }
    } catch(e) {
        console.error("Gagal precache wilayah", e);
        isWilayahDownloading.value = false;
    }
};

const getWilayahOffline = async (type, parentId = null) => {
    const data = await localforage.getItem(DB_WILAYAH) || [];
    let filtered = [];
    if (type === 'provinces') {
        filtered = data.filter(d => d.type === 'province');
    } else {
        filtered = data.filter(d => d.type === type && d.parent_id == parentId);
    }
    return filtered.sort((a,b) => a.name.localeCompare(b.name));
};

// Helper fetch wilayah aman (online API dengan fallback otomatis ke localforage jika offline atau server 500)
const fetchWilayahData = async (url, offlineType, parentId = null) => {
    if (navigator.onLine) {
        try {
            const res = await fetch(url);
            if (res.ok) {
                const data = await res.json();
                if (Array.isArray(data)) return data;
            }
        } catch (e) {
            console.warn(`Fallback offline untuk ${url}:`, e);
        }
    }
    return await getWilayahOffline(offlineType, parentId);
};

const provinces = ref([]);
const cities = ref([]);
const districts = ref([]);
const villages = ref([]);

const selectedProvinceId = ref('');
const selectedCityId = ref('');
const selectedDistrictId = ref('');

// Fetch list Provinsi saat komponen dimuat
onMounted(async () => {
    precacheWilayah();
    provinces.value = await fetchWilayahData('/api/wilayah/provinces', 'provinces');
});

// Watcher untuk Provinsi -> Fetch Kota
watch(selectedProvinceId, async (newId) => {
    cities.value = []; districts.value = []; villages.value = [];
    selectedCityId.value = ''; selectedDistrictId.value = '';
    form.destination_city = ''; form.destination_district = ''; form.destination_village = '';
    
    if (newId) {
        const prov = provinces.value.find(p => p.id === newId);
        if(prov) form.destination_province = { id: prov.id, name: prov.name };
        cities.value = await fetchWilayahData(`/api/wilayah/regencies/${newId}`, 'regency', newId);
    }
});

// Watcher untuk Kota -> Fetch Kecamatan
watch(selectedCityId, async (newId) => {
    districts.value = []; villages.value = [];
    selectedDistrictId.value = '';
    form.destination_district = ''; form.destination_village = '';
    
    if (newId) {
        const city = cities.value.find(c => c.id === newId);
        if(city) form.destination_city = { id: city.id, name: city.name };
        districts.value = await fetchWilayahData(`/api/wilayah/districts/${newId}`, 'district', newId);
    }
});

// Watcher untuk Kecamatan -> Fetch Kelurahan
watch(selectedDistrictId, async (newId) => {
    villages.value = [];
    form.destination_village = '';
    
    if (newId) {
        const dist = districts.value.find(d => d.id === newId);
        if(dist) form.destination_district = { id: dist.id, name: dist.name };
        villages.value = await fetchWilayahData(`/api/wilayah/villages/${newId}`, 'village', newId);
    }
});

// ---------------------------------------------------------
// STATE & DATA
// ---------------------------------------------------------
const DB_RESI_QUEUE = 'resi_sync_queue';

const queueResiSync = async (data) => {
    let queue = await localforage.getItem(DB_RESI_QUEUE) || [];
    queue.push({ ...data, temp_id: 'TEMP-' + new Date().getTime() });
    await localforage.setItem(DB_RESI_QUEUE, queue);
    syncResiToServer();
};

const syncResiToServer = async () => {
    if (!navigator.onLine) return;
    
    let queue = await localforage.getItem(DB_RESI_QUEUE) || [];
    if (queue.length === 0) return;
    
    let failedQueue = [];
    for (let data of queue) {
        try {
            await axios.post('/shipments', data);
        } catch (error) {
            failedQueue.push(data);
        }
    }
    await localforage.setItem(DB_RESI_QUEUE, failedQueue);
    // Reload halaman jika berhasil supaya data tampil di tabel
    if(failedQueue.length < queue.length) {
        window.location.reload();
    }
};

import { onUnmounted } from 'vue';
onMounted(async () => {
    window.addEventListener('online', syncResiToServer);
    syncResiToServer();
});

onUnmounted(() => {
    window.removeEventListener('online', syncResiToServer);
});

// Menggunakan useForm Inertia agar lebih mudah mengirim data ke backend nanti
const form = useForm({
    sender_id: '',
    metode_pickup: 'PICKUP', // Default ke Minta Dijemput Armada
    recipient_name: '',
    recipient_phone: '',
    destination_province: '',
    destination_city: '',
    destination_district: '',
    destination_village: '',
    recipient_address: '',
    payment_type: 'COD',
    goods_value: '',
    freight_fee: '',
    
    // Items Barang
    items: [{ name: '', unit: '', qty: '', koli_per_item: '', showSuggest: false }]
});

// Total Koli Keseluruhan Resi
const totalKoliKeseluruhan = computed(() => {
    return form.items.reduce((sum, item) => sum + (Number(item.koli_per_item) || 0), 0);
});

// Pantau perubahan tipe pembayaran: Jika Lunas (PREPAID), tagihan otomatis 0
watch(() => form.payment_type, (newType) => {
    if (newType === 'PREPAID') {
        form.goods_value = '0';
    } else {
        if (form.goods_value === '0') {
            form.goods_value = '';
        }
    }
});

const searchSenderQuery = ref('');
const showSenderDropdown = ref(false);

const handleSenderBlur = () => {
    setTimeout(() => { showSenderDropdown.value = false; }, 300);
};

const filteredSenders = computed(() => {
    if (!searchSenderQuery.value) return props.senders;
    const query = searchSenderQuery.value.toLowerCase();
    return props.senders.filter(s => 
        s.business_name.toLowerCase().includes(query) ||
        s.phone.includes(query)
    );
});

const resetSender = () => {
    form.sender_id = '';
    searchSenderQuery.value = '';
};

const selectSender = (sender) => {
    form.sender_id = sender.id;
    searchSenderQuery.value = `${sender.business_name} (${sender.phone})`;
    showSenderDropdown.value = false;
};

const selectFirstSender = () => {
    if (filteredSenders.value.length > 0) {
        selectSender(filteredSenders.value[0]);
    }
};

const searchRecipientQuery = ref('');
const showRecipientDropdown = ref(false);

const handleRecipientBlur = () => {
    setTimeout(() => { showRecipientDropdown.value = false; }, 300);
};

const filteredRecipients = computed(() => {
    if (!searchRecipientQuery.value) return props.recipients;
    const query = searchRecipientQuery.value.toLowerCase();
    return props.recipients.filter(r => 
        r.recipient_name.toLowerCase().includes(query) || 
        r.recipient_phone.includes(query)
    );
});

const isRecipientSelected = ref(false);

const resetRecipient = () => {
    isRecipientSelected.value = false;
    searchRecipientQuery.value = '';
    form.recipient_name = '';
    form.recipient_phone = '';
    form.recipient_address = '';
    selectedProvinceId.value = ''; // ini akan memicu watcher membersihkan data kota dst
};

const selectRecipient = async (recipient) => {
    form.recipient_name = recipient.recipient_name;
    form.recipient_phone = recipient.recipient_phone;
    form.recipient_address = recipient.recipient_address || '';
    searchRecipientQuery.value = recipient.recipient_name;
    showRecipientDropdown.value = false;
    isRecipientSelected.value = true;

    const provVal = recipient.destination_province;
    const cityVal = recipient.destination_city;
    const distVal = recipient.destination_district;
    const vilVal = recipient.destination_village;

    const provName = (typeof provVal === 'object' ? provVal?.name : provVal) || '';
    const cityName = (typeof cityVal === 'object' ? cityVal?.name : cityVal) || '';
    const distName = (typeof distVal === 'object' ? distVal?.name : distVal) || '';
    const vilName = (typeof vilVal === 'object' ? vilVal?.name : vilVal) || '';

    if (provName) {
        let targetProv = provinces.value.find(p => p.id == provVal?.id || (p.name && provName && p.name.toLowerCase() === provName.toLowerCase()));
        if (!targetProv && provinces.value.length === 0) {
            provinces.value = await fetchWilayahData('/api/wilayah/provinces', 'provinces');
            targetProv = provinces.value.find(p => p.id == provVal?.id || (p.name && provName && p.name.toLowerCase() === provName.toLowerCase()));
        }

        if (targetProv) {
            selectedProvinceId.value = targetProv.id;
            const regList = await fetchWilayahData(`/api/wilayah/regencies/${targetProv.id}`, 'regency', targetProv.id);
            cities.value = regList;
            
            const targetCity = regList.find(c => c.id == cityVal?.id || (c.name && cityName && c.name.toLowerCase() === cityName.toLowerCase()));
            if (targetCity) {
                selectedCityId.value = targetCity.id;
                const distList = await fetchWilayahData(`/api/wilayah/districts/${targetCity.id}`, 'district', targetCity.id);
                districts.value = distList;

                const targetDist = distList.find(d => d.id == distVal?.id || (d.name && distName && d.name.toLowerCase() === distName.toLowerCase()));
                if (targetDist) {
                    selectedDistrictId.value = targetDist.id;
                    const vilList = await fetchWilayahData(`/api/wilayah/villages/${targetDist.id}`, 'village', targetDist.id);
                    villages.value = vilList;

                    if (vilName) {
                        form.destination_village = vilName;
                    }
                }
            }
        }
    }
};

const selectFirstRecipient = () => {
    if (filteredRecipients.value.length > 0) {
        selectRecipient(filteredRecipients.value[0]);
    }
};

// Global Wilayah Search
const searchWilayahQuery = ref('');
const wilayahResults = ref([]);
const showWilayahDropdown = ref(false);

const handleWilayahBlur = () => {
    setTimeout(() => { showWilayahDropdown.value = false; }, 300);
};

const searchWilayah = async (event) => {
    const query = event.target.value;
    searchWilayahQuery.value = query;
    if (query.length < 3) {
        wilayahResults.value = [];
        showWilayahDropdown.value = false;
        return;
    }
    
    let loaded = false;
    if (navigator.onLine) {
        try {
            const res = await axios.get(route('api.wilayah.search'), { params: { q: query } });
            if (Array.isArray(res.data)) {
                wilayahResults.value = res.data;
                loaded = true;
            }
        } catch(e) {
            console.warn("Online search gagal, beralih ke offline search", e);
        }
    }
    
    if (!loaded) {
        try {
            // Pencarian Offline manual (Mencari di dalam 72.000 baris)
            const allData = await localforage.getItem(DB_WILAYAH) || [];
            const terms = query.toLowerCase().split(' ');
            
            // Cari kecocokan di village atau district
            let matches = allData.filter(d => 
                (d.type === 'village' || d.type === 'district') && 
                terms.every(t => d.name.toLowerCase().includes(t))
            ).slice(0, 15);
            
            // Format ulang agar strukturnya mirip API online
            wilayahResults.value = matches.map(m => {
                let district = m.type === 'district' ? m : allData.find(d => d.id == m.parent_id);
                let city = district ? allData.find(c => c.id == district.parent_id) : null;
                let prov = city ? allData.find(p => p.id == city.parent_id) : null;
                
                return {
                    village_id: m.type === 'village' ? m.id : null,
                    village_name: m.type === 'village' ? m.name : '',
                    district_id: district ? district.id : null,
                    district_name: district ? district.name : '',
                    city_id: city ? city.id : null,
                    city_name: city ? city.name : '',
                    province_id: prov ? prov.id : null,
                    province_name: prov ? prov.name : ''
                };
            });
        } catch(e) {
            console.error("Error offline wilayah search:", e);
        }
    }
    showWilayahDropdown.value = true;
};

const selectWilayah = async (wil) => {
    searchWilayahQuery.value = `${wil.village_name}, ${wil.district_name}, ${wil.city_name}, ${wil.province_name}`;
    showWilayahDropdown.value = false;

    if (wil.province_id) {
        selectedProvinceId.value = wil.province_id;
        const regList = await fetchWilayahData(`/api/wilayah/regencies/${wil.province_id}`, 'regency', wil.province_id);
        cities.value = regList;

        if (wil.city_id) {
            selectedCityId.value = wil.city_id;
            const distList = await fetchWilayahData(`/api/wilayah/districts/${wil.city_id}`, 'district', wil.city_id);
            districts.value = distList;

            if (wil.district_id) {
                selectedDistrictId.value = wil.district_id;
                const vilList = await fetchWilayahData(`/api/wilayah/villages/${wil.district_id}`, 'village', wil.district_id);
                villages.value = vilList;

                if (wil.village_name) {
                    form.destination_village = wil.village_name;
                }
            }
        }
    }
};

const selectFirstWilayah = () => {
    if (wilayahResults.value.length > 0) {
        selectWilayah(wilayahResults.value[0]);
    }
};

// Modal state untuk Pengirim Baru
const isModalOpen = ref(false);
const newSenderForm = useForm({
    business_name: '',
    owner_name: '',
    phone: '',
    address: '',
    province: '',
    city: '',
    district: '',
    village: '',
    maps_url: ''
});

// ID Jawa Tengah = '33', ID Jepara = '3320'
const JATENG_ID = '33';
const JEPARA_ID = '3320';

const senderProvinceId = ref('');
const senderCityId = ref('');
const senderDistrictId = ref('');

const senderCities = ref([]);
const senderDistricts = ref([]);
const senderVillages = ref([]);

const isLuarJepara = ref(false);

const toggleLuarJepara = async () => {
    senderProvinceId.value = '';
    senderCityId.value = '';
    senderDistrictId.value = '';
    newSenderForm.province = '';
    newSenderForm.city = '';
    newSenderForm.district = '';
    newSenderForm.village = '';
    senderCities.value = [];
    senderDistricts.value = [];
    senderVillages.value = [];
    
    if (!isLuarJepara.value) {
        // Mode Jepara-First
        newSenderForm.province = 'JAWA TENGAH';
        newSenderForm.city = 'KABUPATEN JEPARA';
        senderDistricts.value = await fetchWilayahData(`/api/wilayah/districts/${JEPARA_ID}`, 'district', JEPARA_ID);
    } else {
        // Mode Luar Jepara (Tunggu provinces termuat)
        if (provinces.value.length === 0) {
            provinces.value = await fetchWilayahData('/api/wilayah/provinces', 'provinces');
        }
    }
};

// Buka modal dan auto-set Mode Jepara
const openSenderModal = async () => {
    // Reset state wilayah setiap kali modal dibuka agar tidak nyangkut data sebelumnya
    isLuarJepara.value = false;
    senderProvinceId.value = '';
    senderCityId.value = '';
    senderDistrictId.value = '';
    senderCities.value = [];
    senderDistricts.value = [];
    senderVillages.value = [];
    newSenderForm.reset();

    isModalOpen.value = true;
    
    // Default Mode: Jepara-First
    newSenderForm.province = 'JAWA TENGAH';
    newSenderForm.city = 'KABUPATEN JEPARA';
    senderDistricts.value = await fetchWilayahData(`/api/wilayah/districts/${JEPARA_ID}`, 'district', JEPARA_ID);
};

watch(senderProvinceId, async (newId) => {
    if (!isLuarJepara.value) return; // Abaikan jika bukan mode luar jepara
    
    senderCities.value = []; senderDistricts.value = []; senderVillages.value = [];
    senderCityId.value = ''; senderDistrictId.value = '';
    newSenderForm.city = ''; newSenderForm.district = ''; newSenderForm.village = '';
    
    if (newId) {
        const prov = provinces.value.find(p => p.id === newId);
        if(prov) newSenderForm.province = prov.name;
        senderCities.value = await fetchWilayahData(`/api/wilayah/regencies/${newId}`, 'regency', newId);
    }
});

watch(senderCityId, async (newId) => {
    if (!isLuarJepara.value) return; // Abaikan jika bukan mode luar jepara
    
    senderDistricts.value = []; senderVillages.value = [];
    senderDistrictId.value = '';
    newSenderForm.district = ''; newSenderForm.village = '';
    
    if (newId) {
        const city = senderCities.value.find(c => c.id === newId);
        if(city) newSenderForm.city = city.name;
        senderDistricts.value = await fetchWilayahData(`/api/wilayah/districts/${newId}`, 'district', newId);
    }
});

watch(senderDistrictId, async (newId) => {
    senderVillages.value = [];
    newSenderForm.village = '';
    
    if (newId) {
        const dist = senderDistricts.value.find(d => d.id === newId);
        if(dist) newSenderForm.district = dist.name;
        senderVillages.value = await fetchWilayahData(`/api/wilayah/villages/${newId}`, 'village', newId);
    }
});

// Custom Alert State
const showSuccessAlert = ref(false);
const successMessage = ref('');
const successPrintUrl = ref('');
const successWhatsappUrl = ref('');
const successTrackingNumber = ref('');
const copiedResi = ref(false);

const copyResi = async () => {
    if (!successTrackingNumber.value) return;
    try {
        await navigator.clipboard.writeText(successTrackingNumber.value);
        copiedResi.value = true;
        setTimeout(() => {
            copiedResi.value = false;
        }, 2000);
    } catch (e) {
        console.error('Gagal menyalin nomor resi:', e);
    }
};

const closeSuccessAlert = () => {
    showSuccessAlert.value = false;
    successPrintUrl.value = '';
    successWhatsappUrl.value = '';
    successTrackingNumber.value = '';
    copiedResi.value = false;
};

const lihatDaftarResi = () => {
    closeSuccessAlert();
    activeFilter.value = 'Semua';
    searchQuery.value = '';
    activeTab.value = 'list';
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// ---------------------------------------------------------
// METHODS
// ---------------------------------------------------------
const tambahItem = () => {
    form.items.push({ name: '', unit: '', qty: '', koli_per_item: '', showSuggest: false });
};

const activeUnitItemIndex = ref(null);
const isUnitModalOpen = ref(false);
const newUnitName = ref('');

const handleUnitChange = (item, index) => {
    if (item.unit === 'ADD_NEW') {
        activeUnitItemIndex.value = index;
        isUnitModalOpen.value = true;
    }
};

const closeUnitModal = () => {
    isUnitModalOpen.value = false;
    newUnitName.value = '';
    if (activeUnitItemIndex.value !== null) {
        if (form.items[activeUnitItemIndex.value].unit === 'ADD_NEW') {
            form.items[activeUnitItemIndex.value].unit = 'Pcs';
        }
        activeUnitItemIndex.value = null;
    }
};

const submitNewUnit = async () => {
    if (!newUnitName.value || newUnitName.value.trim() === '') return;
    try {
        const response = await axios.post('/api/units', { name: newUnitName.value.trim() });
        const addedUnit = response.data.unit;
        if (!props.units.includes(addedUnit)) {
            props.units.push(addedUnit);
        }
        if (activeUnitItemIndex.value !== null) {
            form.items[activeUnitItemIndex.value].unit = addedUnit;
        }
        closeUnitModal();
    } catch (error) {
        alert('Gagal: ' + (error.response?.data?.message || error.message));
    }
};

const pilihDariModal = (u) => {
    if (activeUnitItemIndex.value !== null) {
        form.items[activeUnitItemIndex.value].unit = u;
    }
    closeUnitModal();
};

const hapusItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const simpanResi = () => {
    const dataToSave = {
        ...form.data(),
        freight_fee: String(form.freight_fee).replace(/[^0-9]/g, ''),
        goods_value: String(form.goods_value).replace(/[^0-9]/g, ''),
    };

    if (!navigator.onLine) {
        queueResiSync(dataToSave);
        alert("OFFLINE MODE: Resi sementara disimpan di HP Anda. Resi akan dikirim ke server dan diterbitkan secara resmi ketika internet kembali.");
        form.reset();
        selectedProvinceId.value = '';
        selectedCityId.value = '';
        selectedDistrictId.value = '';
        searchSenderQuery.value = '';
        searchRecipientQuery.value = '';
        currentStep.value = 1;
        maxStepReached.value = 1;
        return;
    }

    // Bersihkan format Rupiah sebelum dikirim dan kirim request
    form.transform((data) => ({
        ...data,
        freight_fee: String(data.freight_fee).replace(/[^0-9]/g, ''),
        goods_value: data.payment_type === 'PREPAID' ? 0 : String(data.goods_value || 0).replace(/[^0-9]/g, ''),
        items: data.items.map(item => ({
            name: item.name,
            unit: item.unit,
            qty: Number(item.qty) || 1,
            koli_per_item: Number(item.koli_per_item) || 1
        }))
    })).post(route('shipments.store'), {
        onSuccess: (page) => {
            const flash = page.props?.flash || {};
            
            // Dapatkan tracking number dari flash atau fallback regex print_url
            let trNumber = flash.tracking_number;
            if (!trNumber && flash.print_url) {
                const match = flash.print_url.match(/shipments\/([^/]+)/);
                if (match) trNumber = match[1];
            }
            if (trNumber) {
                successTrackingNumber.value = trNumber;
            }

            if (flash.print_url) {
                successPrintUrl.value = flash.print_url;
            }

            if (successTrackingNumber.value) {
                // Generate WhatsApp URL
                const senderId = form.sender_id;
                const sender = props.senders.find(s => s.id === senderId);
                if (sender && sender.phone) {
                    let phone = sender.phone;
                    if (phone.startsWith('0')) phone = '62' + phone.substring(1);
                    
                    const waMsg = `Halo,\n\nResi pengiriman Anda telah berhasil dibuat!\nNomor Resi: *${successTrackingNumber.value}*\n\nSilakan klik link berikut untuk mengunduh / mencetak resi Anda:\n${flash.print_url}\n\nTerima kasih telah menggunakan layanan Jepara Cargo.`;
                    successWhatsappUrl.value = `https://wa.me/${phone}?text=${encodeURIComponent(waMsg)}`;
                }
            }
            
            if (flash.success) {
                successMessage.value = flash.success;
            } else if (successTrackingNumber.value) {
                successMessage.value = `Resi ${successTrackingNumber.value} telah berhasil dibuat dan disimpan!`;
            } else {
                successMessage.value = 'Resi berhasil dibuat dan disimpan ke dalam sistem.';
            }

            // Segera sinkronkan daftar resi terbaru
            if (page.props?.recentShipments && Array.isArray(page.props.recentShipments)) {
                localRecentShipments.value = [...page.props.recentShipments];
            } else {
                refreshRecentShipments();
            }
            
            // Reset form langsung setelah resi dibuat
            form.reset();
            selectedProvinceId.value = '';
            selectedCityId.value = '';
            selectedDistrictId.value = '';
            searchSenderQuery.value = '';
            searchRecipientQuery.value = '';
            currentStep.value = 1;
            maxStepReached.value = 1;

            showSuccessAlert.value = true;
        },
        onError: (errors) => {
            alert("Mohon periksa kembali form Anda:\n" + Object.values(errors).join("\n"));
        }
    });
};

const cetakResi = async (url) => {
    // Normalisasi URL: Jika URL mengarah ke http://localhost:8000 padahal diakses via ngrok/LAN,
    // ganti domainnya ke domain yang sedang aktif (window.location.origin) agar tidak 404!
    let targetUrl = url;
    try {
        const parsed = new URL(url, window.location.origin);
        targetUrl = window.location.origin + parsed.pathname + parsed.search;
    } catch (e) {}

    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

    if (isMobile) {
        // Jika Capacitor ada, gunakan plugin Browser agar tidak me-restart aplikasi (membuka Custom Tab)
        if (Capacitor.isNativePlatform()) {
            await Browser.open({ url: targetUrl });
        } else {
            // Fallback PWA / Web Mobile
            window.open(targetUrl, '_blank');
        }
    } else {
        // PC / Laptop: Cetak di tab yang sama menggunakan Hidden Iframe
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = targetUrl;
        document.body.appendChild(iframe);
        
        iframe.onload = () => {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
            
            // Bersihkan iframe setelah beberapa detik secara aman
            setTimeout(() => {
                try {
                    if (iframe && iframe.parentNode) {
                        iframe.parentNode.removeChild(iframe);
                    }
                } catch (e) {}
            }, 5000);
        };
    }
};

const openUrl = async (url) => {
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    if (isMobile && Capacitor.isNativePlatform()) {
        await Browser.open({ url: url });
    } else {
        window.open(url, '_blank');
    }
};

const simpanPengirim = () => {
    newSenderForm.post(route('senders.store'), {
        onSuccess: () => {
            successMessage.value = 'Pengirim berhasil ditambahkan ke database!';
            showSuccessAlert.value = true;
            isModalOpen.value = false;
            newSenderForm.reset();
        },
        onError: (errors) => {
            alert("Gagal menambahkan pengirim. Pastikan kolom yang bertanda bintang terisi:\n" + Object.values(errors).join("\n"));
        },
        preserveScroll: true
    });
};

const formatRupiahInput = (field) => {
    let rawValue = String(form[field]).replace(/[^0-9]/g, '');
    if (!rawValue) {
        form[field] = '';
        return;
    }
    form[field] = new Intl.NumberFormat('id-ID').format(rawValue);
};

// Stepper Logic
// Tabs state
const { activeTab, scrollPositions: tabScrollPositions } = useTabCache('jepara_buatresi_active_tab', 'form');

const currentStep = ref(1);
const maxStepReached = ref(1);

const steps = [
    { num: 1, title: 'Pengirim', icon: 'ri-store-2-fill' },
    { num: 2, title: 'Penerima', icon: 'ri-user-location-fill' },
    { num: 3, title: 'Barang', icon: 'ri-box-3-fill' },
    { num: 4, title: 'Keuangan', icon: 'ri-wallet-3-fill' },
];

const searchQuery = ref('');
const activeFilter = useFilterCache('jepara_buatresi_active_filter', 'Semua');
const showFiltersCache = useFilterCache('jepara_buatresi_show_filters', false);
const showFilters = computed({
    get: () => activeTab.value === 'list' && showFiltersCache.value,
    set: (val) => { showFiltersCache.value = val; }
});
const hasActiveFilters = computed(() => activeFilter.value !== 'Semua');

const localRecentShipments = ref([...(props.recentShipments || [])]);
const loadingMore = ref(false);
const hasMoreShipments = ref(props.recentShipments?.length === 20);
const isRefreshingShipments = ref(false);

watch(() => props.recentShipments, (newVal) => {
    if (newVal && Array.isArray(newVal)) {
        localRecentShipments.value = [...newVal];
    }
}, { deep: true });

const refreshRecentShipments = async () => {
    isRefreshingShipments.value = true;
    try {
        const res = await axios.get(route('api.recent-shipments'), {
            params: {
                offset: 0,
                limit: 20
            }
        });
        const shipments = res.data?.shipments || [];
        localRecentShipments.value = [...shipments];
        hasMoreShipments.value = shipments.length === 20;
    } catch (error) {
        console.error("Gagal refresh resi terbaru:", error);
    } finally {
        isRefreshingShipments.value = false;
    }
};

const loadMore = async () => {
    if (loadingMore.value || !hasMoreShipments.value) return;
    loadingMore.value = true;
    try {
        const res = await axios.get(route('api.recent-shipments'), {
            params: {
                offset: localRecentShipments.value.length,
                limit: 20
            }
        });
        const newShipments = res.data.shipments || [];
        if (newShipments.length < 20) {
            hasMoreShipments.value = false;
        }
        localRecentShipments.value.push(...newShipments);
    } catch (error) {
        console.error("Gagal memuat resi tambahan:", error);
    } finally {
        loadingMore.value = false;
    }
};

const filteredRecentShipments = computed(() => {
    let data = localRecentShipments.value;
    
    // Filter
    if (activeFilter.value !== 'Semua') {
        data = data.filter(s => s.current_status === activeFilter.value);
    }
    
    // Search
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        data = data.filter(s => 
            s.tracking_number.toLowerCase().includes(q) ||
            s.sender_name.toLowerCase().includes(q) ||
            s.recipient_name.toLowerCase().includes(q) ||
            s.destination.toLowerCase().includes(q)
        );
    }
    
    return data;
});

const isStepComplete = (stepNum) => {
    if (stepNum === 1) return !!form.sender_id;
    if (stepNum === 2) return !!form.recipient_name && !!form.recipient_phone && !!selectedProvinceId.value && !!selectedCityId.value && !!selectedDistrictId.value;
    if (stepNum === 3) return !!form.items[0]?.name && Number(form.items[0]?.qty) > 0 && Number(form.items[0]?.koli_per_item) > 0;
    if (stepNum === 4) return (form.payment_type === 'PREPAID' || !!form.goods_value) && !!form.freight_fee;
    return false;
};

const nextStep = () => {
    if (currentStep.value < 4) {
        currentStep.value++;
        if (currentStep.value > maxStepReached.value) {
            maxStepReached.value = currentStep.value;
        }
    }
};

const goToStep = (step) => {
    if (step <= maxStepReached.value) {
        currentStep.value = step;
    }
};

// Summary singkat untuk collapsed card
const senderSummary = computed(() => {
    if (!form.sender_id) return null;
    const sender = props.senders.find(s => s.id === form.sender_id);
    if (!sender) return null;
    return {
        name: sender.business_name,
        phone: sender.phone,
        metode: form.metode_pickup === 'PICKUP' ? 'Dijemput Armada' : 'Diantar Pengirim',
        icon: form.metode_pickup === 'PICKUP' ? 'ri-car-line' : 'ri-walk-line',
    };
});

const recipientSummary = computed(() => {
    if (!form.recipient_name || !selectedProvinceId.value || !selectedCityId.value) return null;
    const cityName = cities.value.find(c => c.id === selectedCityId.value)?.name || '';
    const distName = districts.value.find(d => d.id === selectedDistrictId.value)?.name || '';
    return {
        name: form.recipient_name,
        phone: form.recipient_phone,
        city: cityName,
        district: distName,
        village: form.destination_village,
        address: form.recipient_address,
    };
});

const itemsSummary = computed(() => {
    return form.items.filter(i => i.name).map(i => `${i.name} (${i.koli_per_item || 0} Koli)`).join(', ');
});

const financeSummary = computed(() => {
    if (!form.freight_fee) return null;
    return {
        type: form.payment_type === 'PREPAID' ? 'Lunas (Prepaid)' : `COD Rp ${form.goods_value || '0'}`,
        ongkir: `Ongkir Rp ${form.freight_fee}`
    };
});
</script>

<template>
    <AdminLayout title="Buat Resi">
        <!-- TABS NAV IN HEADER (DESKTOP) -->

        <!-- SEARCH & FILTER IN HEADER -->
        <template #header_actions>
            <div class="flex items-center gap-1 md:gap-2 shrink-0" v-if="activeTab === 'list'">
                <!-- Search Button (Mobile) -->
                <button @click="isSearchExpanded = true" class="md:hidden relative flex items-center justify-center w-7 h-7 bg-white border border-gray-200 rounded-full text-gray-600 hover:bg-gray-50 hover:text-primary transition-all shadow-sm">
                    <i class="ri-search-line text-xs"></i>
                </button>
                
                <!-- Expanded Search Overlay (Mobile) -->
                <div v-if="isSearchExpanded" class="md:hidden absolute inset-y-0 left-0 right-0 bg-white z-50 flex items-center px-3 gap-2 shadow-sm animate-fade-in">
                    <i class="ri-search-line text-gray-400"></i>
                    <input type="text" autocomplete="off" v-model="searchQuery" placeholder="Cari resi/pengirim..." class="flex-1 border-none bg-transparent outline-none text-sm px-2 focus:ring-0" autofocus>
                    <button @click="isSearchExpanded = false" class="p-1 text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>

                <!-- Input Search Dinamis (Desktop) -->
                <div class="hidden md:block relative animate-fade-in transition-all">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" autocomplete="off" v-model="searchQuery" placeholder="Cari resi / pengirim..." 
                           class="pl-9 pr-4 py-1.5 text-sm border border-gray-200 rounded-full focus:ring-primary focus:border-primary w-48 transition-all bg-gray-50 hover:bg-white outline-none shadow-sm" />
                </div>
                
                <button @click="showFilters = true" class="relative flex items-center justify-center w-7 h-7 md:w-8 md:h-8 bg-white border border-gray-200 rounded-full text-gray-600 hover:bg-gray-50 hover:text-primary transition-all shadow-sm cursor-pointer" title="Filter">
                    <i class="ri-filter-3-line text-xs md:text-base"></i>
                    <span v-if="hasActiveFilters" class="absolute -top-0.5 -right-0.5 w-2 h-2 md:w-2.5 md:h-2.5 bg-red-500 rounded-full border-2 border-white shadow-sm"></span>
                </button>
            </div>
        </template>

        <template #header_tabs>
            <nav class="flex bg-gray-100/80 p-0.5 md:p-1 rounded-lg md:rounded-xl border border-gray-200/50 items-center shrink-0" aria-label="Tabs">
                <button 
                    @click="activeTab = 'form'" 
                    class="px-2 md:px-4 py-1 md:py-1.5 rounded-md md:rounded-lg font-bold text-[10px] md:text-xs transition-all flex items-center gap-1"
                    :class="activeTab === 'form' ? 'bg-primary text-white shadow-md shadow-primary/30' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50'"
                >
                    <i class="ri-add-box-line text-xs md:text-sm"></i> <span class="hidden sm:inline">Form Input</span><span class="sm:hidden">Form</span>
                </button>
                <button 
                    @click="activeTab = 'list'" 
                    class="px-2 md:px-4 py-1 md:py-1.5 rounded-md md:rounded-lg font-bold text-[10px] md:text-xs transition-all flex items-center gap-1"
                    :class="activeTab === 'list' ? 'bg-primary text-white shadow-md shadow-primary/30' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50'"
                >
                    <i class="ri-list-check text-xs md:text-sm"></i> <span class="hidden sm:inline">Daftar Resi</span><span class="sm:hidden">Daftar</span>
                </button>
            </nav>
        </template>



        <div class="flex flex-col lg:flex-row items-start gap-6 lg:gap-8 relative pb-24 pt-4 md:pt-8 w-full transition-all duration-300">
        <SwipeableTabs v-model="activeTab" :tabs="[{ id: 'form' }, { id: 'list' }]">
            <template #form>
        <!-- ==========================================
             FORMULIR PENGISIAN
             ========================================== -->
        <div class="max-w-4xl mx-auto md:mt-4 h-full">

            <form autocomplete='off' @submit.prevent="simpanResi" class="space-y-4 md:space-y-6">
                
                <!-- ==========================================
                     STEPPER PROGRESS BAR DENGAN INFO RINGKASAN
                     ========================================== -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 md:gap-3 mb-6">
                    <button 
                        v-for="step in steps" 
                        :key="step.num"
                        type="button"
                        @click="step.num <= maxStepReached && goToStep(step.num)"
                        :disabled="step.num > maxStepReached"
                        class="p-3 md:p-3.5 rounded-2xl border text-left transition-all duration-200 relative group flex flex-col justify-between"
                        :class="[
                            currentStep === step.num 
                                ? 'bg-white border-[#8B5E34] shadow-md ring-2 ring-[#8B5E34]/20' 
                                : (step.num <= maxStepReached && isStepComplete(step.num))
                                    ? 'bg-white border-green-200 hover:border-green-400 hover:shadow-sm cursor-pointer'
                                    : 'bg-gray-50/70 border-gray-200/60 opacity-60 cursor-not-allowed'
                        ]"
                    >
                        <!-- Baris Atas: Badge Nomor/Centang + Nama Langkah + Status Ubah -->
                        <div class="flex items-center justify-between gap-1.5 mb-1.5">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-bold shrink-0 transition-colors"
                                    :class="[
                                        currentStep === step.num 
                                            ? 'bg-[#8B5E34] text-white shadow-sm' 
                                            : (step.num <= maxStepReached && isStepComplete(step.num))
                                                ? 'bg-green-600 text-white' 
                                                : 'bg-gray-200 text-gray-500'
                                    ]"
                                >
                                    <i v-if="(step.num <= maxStepReached && isStepComplete(step.num)) && currentStep !== step.num" class="ri-check-line text-sm font-bold"></i>
                                    <span v-else>{{ step.num }}</span>
                                </span>
                                <span class="font-bold text-xs md:text-sm truncate"
                                    :class="currentStep === step.num ? 'text-[#8B5E34]' : ((step.num <= maxStepReached && isStepComplete(step.num)) ? 'text-gray-800' : 'text-gray-400')"
                                >
                                    {{ step.title }}
                                </span>
                            </div>
                            
                            <!-- Indikator Ubah / Titik Aktif -->
                            <span v-if="(step.num <= maxStepReached && isStepComplete(step.num)) && currentStep !== step.num" 
                                  class="text-[10px] font-bold text-primary group-hover:underline flex items-center gap-0.5 shrink-0 bg-primary/5 px-1.5 py-0.5 rounded-md">
                                <i class="ri-edit-line text-[10px]"></i> Ubah
                            </span>
                            <span v-else-if="currentStep === step.num" class="w-2 h-2 rounded-full bg-[#8B5E34] animate-pulse shrink-0"></span>
                        </div>

                        <!-- Baris Bawah: Informasi yang Sudah Diinput -->
                        <div class="text-[11px] leading-tight truncate pl-8">
                            <!-- Langkah 1: Pengirim -->
                            <template v-if="step.num === 1">
                                <span v-if="senderSummary" class="text-gray-600 font-medium truncate block" :title="senderSummary.name + ' · ' + senderSummary.metode">
                                    <i class="ri-store-2-line text-primary/70"></i> {{ senderSummary.name }}
                                </span>
                                <span v-else-if="currentStep === 1" class="text-primary font-medium">Sedang diisi...</span>
                                <span v-else class="text-gray-400">Belum diisi</span>
                            </template>

                            <!-- Langkah 2: Penerima -->
                            <template v-else-if="step.num === 2">
                                <span v-if="recipientSummary" class="text-gray-600 font-medium truncate block" :title="recipientSummary.name + ' · ' + [recipientSummary.district, recipientSummary.city].filter(Boolean).join(', ')">
                                    <i class="ri-user-location-line text-blue-500"></i> {{ recipientSummary.name }} <span class="text-gray-400">· {{ recipientSummary.district || recipientSummary.city }}</span>
                                </span>
                                <span v-else-if="currentStep === 2" class="text-primary font-medium">Sedang diisi...</span>
                                <span v-else class="text-gray-400">Belum diisi</span>
                            </template>

                            <!-- Langkah 3: Barang -->
                            <template v-else-if="step.num === 3">
                                <span v-if="itemsSummary && totalKoliKeseluruhan > 0" class="text-gray-600 font-medium truncate block" :title="itemsSummary">
                                    <i class="ri-box-3-line text-orange-500"></i> {{ totalKoliKeseluruhan }} Koli <span class="text-gray-400">({{ form.items[0]?.name || 'Barang' }})</span>
                                </span>
                                <span v-else-if="currentStep === 3" class="text-primary font-medium">Sedang diisi...</span>
                                <span v-else class="text-gray-400">Belum diisi</span>
                            </template>

                            <!-- Langkah 4: Keuangan -->
                            <template v-else-if="step.num === 4">
                                <span v-if="financeSummary" class="text-gray-600 font-medium truncate block">
                                    <i class="ri-wallet-3-line text-emerald-500"></i> {{ financeSummary.type }}
                                </span>
                                <span v-else-if="currentStep === 4" class="text-primary font-medium">Sedang diisi...</span>
                                <span v-else class="text-gray-400">Belum diisi</span>
                            </template>
                        </div>
                    </button>
                </div>

                <!-- ==========================================
                     FORMULIR PENGISIAN (HANYA LANGKAH AKTIF)
                     ========================================== -->
                <div class="space-y-4 md:space-y-6">
                    
                    <!-- Row 1: Pengirim -->
                    <div v-show="currentStep === 1" class="space-y-4 relative z-20">
                        <div class="bg-white rounded-2xl border border-gray-200/80 shadow-md hover:shadow-lg transition-shadow duration-300 relative">
                            <!-- Aksen Pita Kuning/Orange seperti Titik 1 di screenshot -->
                            <div class="bg-primary/10 px-4 py-3 border-b border-primary/10 flex justify-between items-center rounded-t-2xl">
                                <div class="flex items-center gap-2">
                                    <span class="bg-yellow-200 text-yellow-800 text-[10px] font-bold px-2 py-0.5 rounded shadow-sm">LANGKAH 1</span>
                                    <h3 class="font-bold text-dark text-sm md:text-base flex items-center gap-1.5"><i class="ri-store-2-fill text-[#8B5E34]"></i> Data Pengirim</h3>
                                </div>
                            </div>
                            
                            <div class="p-5 md:p-6 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cari Nama Toko / Pengirim</label>
                                <div class="flex gap-2 relative">
                                    <div class="relative flex-1">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                            <i class="ri-search-line text-gray-400"></i>
                                        </div>
                                        <input autocomplete='off' 
                                            type="text" 
                                            v-model="searchSenderQuery" 
                                            :disabled="!!form.sender_id"
                                            @focus="!form.sender_id && (showSenderDropdown = true)"
                                            @blur="handleSenderBlur"
                                            @keydown.enter.prevent="selectFirstSender"
                                            placeholder="Ketik nama atau nomor HP..." 
                                            class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl pl-10 pr-10 py-3 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all disabled:opacity-80 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                        >
                                        <!-- Tombol X jika terpilih -->
                                        <button v-if="form.sender_id" @click="resetSender" type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-red-500 hover:text-red-700">
                                            <i class="ri-close-circle-fill text-xl"></i>
                                        </button>
                                    </div>
                                    <button 
                                        v-if="!form.sender_id"
                                        type="button" 
                                        @click="openSenderModal()" 
                                        class="bg-primary/10 text-[#8B5E34] border border-primary/20 w-12 rounded-xl flex items-center justify-center hover:bg-primary/10 hover:border-primary/30 transition-all shadow-sm group"
                                        title="Tambah Pengirim Baru"
                                    >
                                        <i class="ri-add-line text-xl font-bold group-hover:scale-110 transition-transform"></i>
                                    </button>
                                </div>
                                
                                <!-- Dropdown Suggestion -->
                                <div v-if="showSenderDropdown && filteredSenders.length > 0" 
                                     @mousedown.prevent
                                     class="absolute z-50 w-full mt-1 bg-white border border-gray-100 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                                    <ul class="py-1">
                                        <li 
                                            v-for="sender in filteredSenders" 
                                            :key="sender.id"
                                            @mousedown.prevent="selectSender(sender)"
                                            @click="selectSender(sender)"
                                            class="px-4 py-2 hover:bg-primary/5 cursor-pointer flex justify-between items-center border-b border-gray-50 last:border-0"
                                        >
                                            <div>
                                                <div class="font-bold text-sm text-dark">{{ sender.business_name }}</div>
                                                <div class="text-xs text-gray-500">{{ sender.phone }}</div>
                                            </div>
                                            <i v-if="form.sender_id === sender.id" class="ri-check-line text-primary"></i>
                                        </li>
                                    </ul>
                                </div>
                                <div v-else-if="showSenderDropdown && searchSenderQuery !== ''" 
                                     @mousedown.prevent
                                     class="absolute z-50 w-full mt-1 bg-white border border-gray-100 rounded-xl shadow-lg p-4 text-center">
                                    <p class="text-sm text-gray-500 mb-2">Pengirim tidak ditemukan.</p>
                                    <button type="button" @mousedown.prevent="isModalOpen = true" @click="isModalOpen = true" class="text-sm text-primary font-bold hover:underline">+ Tambah Pengirim Baru</button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Penyerahan Barang ke Gudang</label>
                                <div class="flex flex-col sm:flex-row gap-3 sm:gap-6 mt-1">
                                    <label class="flex items-center gap-2 cursor-pointer bg-gray-50 px-4 py-2 rounded-lg border border-gray-200 shadow-sm hover:border-primary/50 transition-all">
                                        <input autocomplete='off' type="radio" v-model="form.metode_pickup" value="DROP_OFF" class="w-4 h-4 text-primary focus:ring-primary border-gray-300">
                                        <span class="text-sm text-gray-700 font-medium">Diantar Pengirim</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer bg-primary/5 px-4 py-2 rounded-lg border border-primary border-dashed shadow-sm hover:border-primary transition-all">
                                        <input autocomplete='off' type="radio" v-model="form.metode_pickup" value="PICKUP" class="w-4 h-4 text-primary focus:ring-primary border-gray-300">
                                        <span class="text-sm text-gray-700 font-bold text-primary">Minta Dijemput Armada</span>
                                    </label>
                                </div>
                                <p class="text-[10px] text-gray-500 mt-2">*Catatan: Resi penjemputan akan otomatis masuk ke tugas Supir Pick-up.</p>
                            </div>
                        </div>
                        </div>
                        </div>

                        <!-- Tombol Lanjut Pengirim -->
                        <div class="flex justify-end pt-2">
                            <button @click="nextStep" type="button" :disabled="!form.sender_id" class="w-full sm:w-auto px-8 py-3.5 bg-[#8B5E34] text-white rounded-xl font-bold hover:bg-[#724C2A] transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform hover:-translate-y-1">Lanjut ke Penerima <i class="ri-arrow-right-line"></i></button>
                        </div>
                    </div>
                    
                    <!-- Row 2: Penerima -->
                    <div v-show="currentStep === 2" class="space-y-4 transition-all duration-500">
                        <div class="bg-white rounded-2xl border border-gray-200/80 shadow-md hover:shadow-lg transition-shadow duration-300 relative">
                            <!-- Aksen Pita Biru/Abu seperti di screenshot -->
                            <div class="bg-gray-50/80 px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <span class="bg-gray-200 text-gray-700 text-[10px] font-bold px-2 py-0.5 rounded shadow-sm">LANGKAH 2</span>
                                    <h3 class="font-bold text-dark text-sm md:text-base flex items-center gap-1.5"><i class="ri-user-location-fill text-gray-600"></i> Data Penerima</h3>
                                </div>
                            </div>
                        
                            <div class="p-5 md:p-6 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                            <!-- Autocomplete Nama Lengkap Penerima -->
                            <div class="relative z-[50]">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        autocomplete="off"
                                        v-model="searchRecipientQuery" 
                                        :disabled="isRecipientSelected"
                                        @focus="!isRecipientSelected && (showRecipientDropdown = true)"
                                        @blur="handleRecipientBlur"
                                        @keydown.enter.prevent="selectFirstRecipient"
                                        @input="showRecipientDropdown = true; form.recipient_name = $event.target.value"
                                        placeholder="Ketik Bpk. Andi atau pilih dari riwayat..." 
                                        class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 pl-11 pr-10 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all disabled:opacity-80 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                    <i class="ri-user-search-line absolute left-4 top-3.5 text-gray-400 text-lg"></i>
                                    <!-- Tombol X jika terpilih -->
                                    <button v-if="isRecipientSelected" @click="resetRecipient" type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-red-500 hover:text-red-700">
                                        <i class="ri-close-circle-fill text-xl"></i>
                                    </button>
                                </div>
                                <!-- Dropdown Suggestion -->
                                <div v-if="showRecipientDropdown && filteredRecipients.length > 0" 
                                     @mousedown.prevent
                                     class="absolute z-20 w-full mt-2 bg-white border border-gray-100 rounded-xl shadow-xl overflow-hidden max-h-60 overflow-y-auto">
                                    <ul>
                                        <li v-for="recipient in filteredRecipients" :key="recipient.recipient_phone"
                                            @mousedown.prevent="selectRecipient(recipient)"
                                            @click="selectRecipient(recipient)"
                                            class="px-4 py-3 hover:bg-primary/5 cursor-pointer border-b border-gray-50 last:border-0 transition-colors flex items-center justify-between group">
                                            <div>
                                                <div class="font-bold text-gray-800 group-hover:text-primary transition-colors">{{ recipient.recipient_name }}</div>
                                                <div class="text-xs text-gray-500"><i class="ri-map-pin-line mr-1"></i>{{ (typeof recipient.destination_city === 'object' ? recipient.destination_city?.name : recipient.destination_city) || 'Riwayat' }}</div>
                                            </div>
                                            <div class="text-xs font-medium text-gray-400 bg-gray-100 px-2 py-1 rounded-md">{{ recipient.recipient_phone }}</div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">No. WhatsApp Penerima <span class="text-red-500">*</span></label>
                                <input autocomplete='off' type="tel" v-model="form.recipient_phone" :disabled="isRecipientSelected" placeholder="0812xxxxxx" class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all disabled:opacity-80 disabled:bg-gray-100 disabled:cursor-not-allowed">
                            </div>
                            
                            <!-- Area Wilayah Dinamis (EMSIFA API) -->
                            <div class="col-span-1 md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cari Wilayah (Kecamatan / Kelurahan)</label>
                                <div class="relative mb-4 z-[45]">
                                    <input 
                                        type="text" 
                                        autocomplete="off"
                                        v-model="searchWilayahQuery" 
                                        :disabled="isRecipientSelected"
                                        @input="searchWilayah"
                                        @focus="!isRecipientSelected && searchWilayahQuery.length >= 3 && (showWilayahDropdown = true)"
                                        @blur="handleWilayahBlur"
                                        @keydown.enter.prevent="selectFirstWilayah"
                                        placeholder="Ketik nama kecamatan atau desa..." 
                                        class="w-full bg-white border border-gray-300 text-gray-800 rounded-xl px-4 py-3 pl-11 focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none shadow-sm transition-all disabled:opacity-80 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                    <i class="ri-search-line absolute left-4 top-3.5 text-gray-400 text-lg"></i>
                                    <!-- Dropdown Wilayah -->
                                    <div v-if="showWilayahDropdown && wilayahResults.length > 0" 
                                         @mousedown.prevent
                                         class="absolute w-full mt-2 bg-white border border-gray-100 rounded-xl shadow-xl overflow-hidden max-h-60 overflow-y-auto">
                                        <ul>
                                            <li v-for="wil in wilayahResults" :key="wil.village_id"
                                                @mousedown.prevent="selectWilayah(wil)"
                                                @click="selectWilayah(wil)"
                                                class="px-4 py-3 hover:bg-primary/5 cursor-pointer border-b border-gray-50 last:border-0 transition-colors">
                                                <div class="font-bold text-gray-800">{{ wil.village_name }}, {{ wil.district_name }}</div>
                                                <div class="text-xs text-gray-500">{{ wil.city_name }}, {{ wil.province_name }}</div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Provinsi Tujuan <span class="text-red-500">*</span></label>
                                        <select v-model="selectedProvinceId" :disabled="isRecipientSelected" class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all disabled:opacity-80 disabled:bg-gray-100 disabled:cursor-not-allowed">
                                            <option value="">Pilih Provinsi...</option>
                                            <option v-for="prov in provinces" :key="prov.id" :value="prov.id">{{ prov.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Kota/Kab <span class="text-red-500">*</span></label>
                                        <select v-model="selectedCityId" :disabled="!selectedProvinceId || cities.length === 0 || isRecipientSelected" class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                            <option value="">Pilih Kota...</option>
                                            <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Kecamatan <span class="text-red-500">*</span></label>
                                        <select v-model="selectedDistrictId" :disabled="!selectedCityId || districts.length === 0 || isRecipientSelected" class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                            <option value="">Pilih Kecamatan...</option>
                                            <option v-for="dist in districts" :key="dist.id" :value="dist.id">{{ dist.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Kelurahan / Desa</label>
                                        <select v-model="form.destination_village" :disabled="!selectedDistrictId || villages.length === 0 || isRecipientSelected" class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                            <option value="">Pilih Kelurahan...</option>
                                            <option v-for="vil in villages" :key="vil.id" :value="vil.name">{{ vil.name }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-span-1 md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Lengkap</label>
                                <textarea v-model="form.recipient_address" :disabled="isRecipientSelected" rows="2" placeholder="Jalan, RT/RW, Patokan Rumah..." class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all disabled:opacity-80 disabled:bg-gray-100 disabled:cursor-not-allowed"></textarea>
                            </div>
                        </div>
                        </div>
                        </div>

                        <!-- Tombol Navigasi Penerima -->
                        <div class="flex justify-between items-center gap-3 pt-2">
                            <button @click="goToStep(1)" type="button" class="px-5 py-3 border border-gray-200 text-gray-700 rounded-xl font-bold hover:bg-gray-100 flex items-center gap-1.5 transition-all text-sm">
                                <i class="ri-arrow-left-line"></i> Kembali
                            </button>
                            <button @click="nextStep" type="button" :disabled="!form.recipient_name || !form.recipient_phone || !selectedProvinceId || !selectedCityId || !selectedDistrictId" class="px-8 py-3.5 bg-[#8B5E34] text-white rounded-xl font-bold hover:bg-[#724C2A] transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform hover:-translate-y-1 text-sm md:text-base">
                                Lanjut ke Barang <i class="ri-arrow-right-line"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Row 3: Detail Barang -->
                    <div v-show="currentStep === 3" class="space-y-4 transition-all duration-500">
                        <div class="bg-white rounded-2xl border border-gray-200/80 shadow-md hover:shadow-lg transition-shadow duration-300 relative">
                            <!-- Aksen Pita Biru/Abu seperti di screenshot -->
                            <div class="bg-gray-50/80 px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <span class="bg-gray-200 text-gray-700 text-[10px] font-bold px-2 py-0.5 rounded shadow-sm">LANGKAH 3</span>
                                    <h3 class="font-bold text-dark text-sm md:text-base flex items-center gap-1.5"><i class="ri-box-3-fill text-gray-600"></i> Detail Barang (Items)</h3>
                                </div>
                            </div>
                    
                            <div class="p-5 md:p-6 space-y-3">
                                <!-- Looping Vue (v-for) untuk Items -->
                                <div v-for="(item, index) in form.items" :key="index" class="bg-gray-50 p-3 md:p-4 rounded-lg border border-gray-200 relative group transition-all">
                                    <div class="flex flex-wrap sm:flex-nowrap gap-2 md:gap-4 items-end">
                                        <div class="w-full sm:w-16 hidden sm:block">
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Item</label>
                                            <input autocomplete='off' type="text" :value="index + 1" disabled class="w-full bg-gray-100 border border-gray-300 rounded-lg px-3 py-2 text-center font-bold text-gray-600">
                                        </div>
                                        
                                        <div class="relative w-full sm:flex-1 mt-2 sm:mt-0">
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Nama Barang <span class="text-red-500">*</span></label>
                                            <input 
                                                autocomplete='off' 
                                                type="text" 
                                                v-model="item.name" 
                                                @focus="item.showSuggest = true"
                                                @input="item.showSuggest = true"
                                                placeholder="Contoh: Lemari 4 Pintu" 
                                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all"
                                            >
                                            <!-- Dropdown Suggestion Kustom -->
                                            <div v-if="item.showSuggest && filteredItems(item.name).length > 0" 
                                                 @mousedown.prevent
                                                 class="absolute z-[60] w-full mt-1 bg-white border border-gray-100 rounded-xl shadow-xl overflow-hidden max-h-48 overflow-y-auto left-0">
                                                <ul>
                                                    <li v-for="suggest in filteredItems(item.name)" :key="suggest"
                                                        @mousedown.prevent="item.name = suggest; item.showSuggest = false"
                                                        @click="item.name = suggest; item.showSuggest = false"
                                                        class="px-4 py-2.5 hover:bg-primary/5 cursor-pointer border-b border-gray-50 last:border-0 transition-colors">
                                                        <div class="font-medium text-sm text-gray-800">{{ suggest }}</div>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        <div class="w-full sm:w-24 mt-2 sm:mt-0 flex flex-col">
                                            <div class="flex justify-between items-center mb-1">
                                                <label class="block text-xs font-medium text-gray-700" title="Satuan barang">Satuan <span class="text-red-500">*</span></label>
                                            </div>
                                            <select v-model="item.unit" @change="handleUnitChange(item, index)" class="w-full h-[42px] border border-gray-300 rounded-lg px-2 focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all text-center bg-white cursor-pointer">
                                                <option value="" disabled selected>Pilih...</option>
                                                <option v-for="u in units" :key="u" :value="u">{{ u }}</option>
                                                <option value="ADD_NEW" class="text-primary font-bold bg-primary/5">+ Baru...</option>
                                            </select>
                                        </div>

                                        <div class="w-full sm:w-20 mt-2 sm:mt-0">
                                            <label class="block text-xs font-medium text-gray-700 mb-1" title="Berapa banyak barang ini?">Jml <span class="text-red-500">*</span></label>
                                            <input autocomplete='off' type="number" min="0" v-model="item.qty" placeholder="0" class="w-full border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all text-center">
                                        </div>

                                        <div class="w-full sm:w-28 mt-2 sm:mt-0">
                                            <label class="block text-xs font-medium text-gray-700 mb-1" title="Total fisik koli (kardus/bungkus) untuk barang ini">Total Koli <span class="text-red-500">*</span></label>
                                            <div class="flex gap-2">
                                                <input autocomplete='off' type="number" min="0" v-model="item.koli_per_item" placeholder="0" class="w-full border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all text-center">
                                                
                                                <!-- Tombol Hapus Item (Muncul jika Items > 1) -->
                                                <button 
                                                    v-if="form.items.length > 1" 
                                                    @click="hapusItem(index)" 
                                                    type="button" 
                                                    class="px-3 py-2 bg-red-50 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition-colors border border-red-100 flex items-center justify-center"
                                                    title="Hapus Barang"
                                                >
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Label Total Koli Barang Ini -->
                                    <div class="mt-3 flex justify-end">
                                        <span class="text-xs font-medium text-gray-600 bg-white px-3 py-1 rounded-full border border-gray-200 shadow-sm flex items-center gap-1.5">
                                            <i class="ri-box-3-line text-primary"></i> Koli Barang Ini: <strong class="text-primary font-bold">{{ Number(item.koli_per_item) || 0 }}</strong> Koli
                                        </span>
                                    </div>
                                </div>

                                <!-- Ringkasan Total Koli Keseluruhan Resi -->
                                <div class="mt-4 p-3.5 bg-primary/5 border border-primary/20 rounded-xl flex items-center justify-between shadow-sm">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                                            <i class="ri-stack-line text-lg"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-gray-800">Total Koli Fisik Resi Ini</p>
                                            <p class="text-[11px] text-gray-500">Jumlah seluruh koli/paket yang akan dicetak labelnya</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-lg font-black text-primary font-mono">{{ totalKoliKeseluruhan }}</span>
                                        <span class="text-xs text-gray-600 font-semibold ml-1">Koli</span>
                                    </div>
                                </div>

                                <div class="flex justify-center mt-4 mb-2">
                                    <button type="button" @click="tambahItem" class="text-sm text-primary font-bold hover:bg-primary/10 bg-primary/5 border border-primary/20 px-6 py-2 rounded-xl transition-all shadow-sm flex items-center gap-2">
                                        <i class="ri-add-line"></i> Tambah Barang Lagi
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Navigasi Barang -->
                        <div class="flex justify-between items-center gap-3 pt-2">
                            <button @click="goToStep(2)" type="button" class="px-5 py-3 border border-gray-200 text-gray-700 rounded-xl font-bold hover:bg-gray-100 flex items-center gap-1.5 transition-all text-sm">
                                <i class="ri-arrow-left-line"></i> Kembali
                            </button>
                            <button @click="nextStep" type="button" :disabled="!form.items[0].name || !form.items[0].qty || form.items[0].qty < 1 || !form.items[0].koli_per_item || form.items[0].koli_per_item < 1" class="px-8 py-3.5 bg-[#8B5E34] text-white rounded-xl font-bold hover:bg-[#724C2A] transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform hover:-translate-y-1 text-sm md:text-base">
                                Lanjut ke Keuangan <i class="ri-arrow-right-line"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Row 4: Keuangan -->
                    <div v-show="currentStep === 4" class="space-y-4 transition-all duration-500">
                        <div class="bg-white rounded-2xl border border-gray-200/80 shadow-md hover:shadow-lg transition-shadow duration-300 relative">
                            <div class="bg-gray-50/80 px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <span class="bg-gray-200 text-gray-700 text-[10px] font-bold px-2 py-0.5 rounded shadow-sm">LANGKAH 4</span>
                                    <h3 class="font-bold text-dark text-sm md:text-base flex items-center gap-1.5"><i class="ri-wallet-3-fill text-gray-600"></i> Keuangan (Borongan)</h3>
                                </div>
                            </div>
                            <div class="p-5 md:p-6 grid gap-4 md:gap-6" :class="form.payment_type === 'PREPAID' ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-1 sm:grid-cols-3'">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Pembayaran</label>
                                    <select v-model="form.payment_type" class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all">
                                        <option value="COD">COD (Ditagih Supir)</option>
                                        <option value="PREPAID">LUNAS (Prepaid)</option>
                                    </select>
                                </div>
                                <div v-if="form.payment_type !== 'PREPAID'">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tagihan (Rp) <span class="text-red-500">*</span></label>
                                    <input autocomplete='off' type="text" v-model="form.goods_value" @input="formatRupiahInput('goods_value')" placeholder="2.000.000" class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ongkos Kirim (Rp) <span class="text-red-500">*</span></label>
                                    <input autocomplete='off' type="text" v-model="form.freight_fee" @input="formatRupiahInput('freight_fee')" placeholder="500.000" class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 font-bold text-dark focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all">
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Navigasi Keuangan & Submit -->
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-3 pt-4">
                            <button @click="goToStep(3)" type="button" class="w-full sm:w-auto px-6 py-3.5 border border-gray-200 text-gray-700 rounded-xl font-bold hover:bg-gray-100 flex items-center justify-center gap-2 transition-all text-sm">
                                <i class="ri-arrow-left-line"></i> Kembali ke Barang
                            </button>
                            <button v-show="(form.payment_type === 'PREPAID' || form.goods_value) && form.freight_fee" type="submit" class="w-full sm:w-auto px-8 py-4 bg-[#1F2937] text-white rounded-xl font-bold hover:bg-gray-800 flex items-center justify-center gap-2 shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-1 text-base md:text-lg">
                                <i class="ri-check-double-line"></i> Simpan Resi & Cetak Label
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </template>
            <template #list>
                <!-- ==========================================
                     DAFTAR RESI TERBARU
                     ========================================== -->
                <div class="max-w-5xl mx-auto md:mt-4 h-full">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                            <h3 class="font-bold text-dark flex items-center gap-2">
                                <i class="ri-history-line text-primary"></i> Resi Terbaru
                            </h3>
                            <button 
                                type="button" 
                                @click="refreshRecentShipments" 
                                class="text-xs text-gray-500 hover:text-primary flex items-center gap-1 font-semibold px-2.5 py-1 rounded-lg hover:bg-white border border-transparent hover:border-gray-200 transition-all active:scale-95"
                                :class="{ 'opacity-50 pointer-events-none': isRefreshingShipments }"
                                title="Segarkan Data"
                            >
                                <i class="ri-refresh-line" :class="{ 'animate-spin': isRefreshingShipments }"></i>
                                <span>Segarkan</span>
                            </button>
                        </div>
                        
                        <div v-if="filteredRecentShipments.length === 0" class="p-8 text-center text-gray-500">
                            <i class="ri-file-list-3-line text-4xl text-gray-300 mb-3 block"></i>
                            <p>Belum ada resi yang dibuat.</p>
                        </div>

                        <div v-else class="divide-y divide-gray-100">
                            <div v-for="shipment in filteredRecentShipments" :key="shipment.id" class="p-4 sm:p-5 hover:bg-primary/5 transition-colors">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex items-start gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex flex-col items-center justify-center shrink-0">
                                            <i class="ri-barcode-box-line text-lg"></i>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <h4 class="font-bold text-dark tracking-wide font-mono text-sm sm:text-base">{{ shipment.tracking_number }}</h4>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                                    :class="shipment.current_status === 'Diterima' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                                                    {{ shipment.current_status }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500 mb-1"><i class="ri-store-2-fill text-gray-400"></i> {{ shipment.sender_name }} &bull; <i class="ri-user-location-fill text-gray-400"></i> {{ shipment.recipient_name }} ({{ shipment.destination }})</p>
                                            <p class="text-xs text-gray-400">{{ shipment.total_koli }} Koli &bull; Dibuat: {{ shipment.created_at_label }}</p>
                                        </div>
                                    </div>
                                    <div class="shrink-0">
                                        <button @click.prevent="cetakResi(route('shipments.print', shipment.tracking_number))" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg hover:border-primary hover:text-primary transition-all text-sm font-medium shadow-sm">
                                            <i class="ri-printer-line"></i> Cetak
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <LoadMoreButton 
                            :has-more="filteredRecentShipments.length > 0 && hasMoreShipments"
                            :loading="loadingMore"
                            @load-more="loadMore"
                        />
                    </div>
                </div>
            </template>
        </SwipeableTabs>

        <!-- ==========================================
             MODAL TAMBAH PENGIRIM
             ========================================== -->
        <Teleport to="body">
            <div v-if="isModalOpen" class="fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center p-4 transition-opacity">
                <div class="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden transform scale-100 transition-transform">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                        <h3 class="font-bold text-dark text-lg">Tambah Pengirim Baru</h3>
                        <button type="button" @click="isModalOpen = false" class="text-gray-400 hover:text-red-500 transition-colors">
                            <i class="ri-close-line text-2xl"></i>
                        </button>
                    </div>
                    <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Toko / Bisnis <span class="text-red-500">*</span></label>
                            <input autocomplete='off' type="text" v-model="newSenderForm.business_name" placeholder="Misal: Jati Berkah" class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Pemilik</label>
                                <input autocomplete='off' type="text" v-model="newSenderForm.owner_name" placeholder="Opsional" class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">No. WhatsApp <span class="text-red-500">*</span></label>
                                <input autocomplete='off' type="tel" v-model="newSenderForm.phone" placeholder="0812xxxxxx" class="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-xl px-4 py-3 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all">
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-bold text-gray-800 text-sm">Alamat Lengkap</h4>
                                <label class="flex items-center space-x-2 text-xs text-gray-600 cursor-pointer hover:text-primary transition-colors">
                                    <input type="checkbox" v-model="isLuarJepara" @change="toggleLuarJepara" class="rounded text-primary focus:ring-primary/30">
                                    <span>Pengirim Luar Jepara?</span>
                                </label>
                            </div>
                            
                            <div v-if="isLuarJepara" class="grid grid-cols-2 gap-4 mb-3 p-3 bg-orange-50/50 rounded-lg border border-orange-100">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Provinsi <span class="text-red-500">*</span></label>
                                    <select v-model="senderProvinceId" class="w-full border border-gray-200 bg-white rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-primary/30 text-sm">
                                        <option value="">Pilih Provinsi...</option>
                                        <option v-for="prov in provinces" :key="prov.id" :value="prov.id">{{ prov.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Kab/Kota <span class="text-red-500">*</span></label>
                                    <select v-model="senderCityId" :disabled="!senderProvinceId" class="w-full border border-gray-200 bg-white rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-primary/30 text-sm disabled:opacity-50">
                                        <option value="">Pilih Kota...</option>
                                        <option v-for="city in senderCities" :key="city.id" :value="city.id">{{ city.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Kecamatan <span class="text-red-500">*</span></label>
                                    <select v-model="senderDistrictId" :disabled="isLuarJepara && !senderCityId" class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 outline-none focus:bg-white focus:ring-2 focus:ring-primary/30 text-sm disabled:opacity-50">
                                        <option value="">Pilih Kec...</option>
                                        <option v-for="dist in senderDistricts" :key="dist.id" :value="dist.id">{{ dist.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">Kalurahan / Desa</label>
                                    <select v-model="newSenderForm.village" :disabled="!senderDistrictId" class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 outline-none focus:bg-white focus:ring-2 focus:ring-primary/30 text-sm disabled:opacity-50">
                                        <option value="">Pilih Kalurahan...</option>
                                        <option v-for="vil in senderVillages" :key="vil.id" :value="vil.name">{{ vil.name }}</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Detail Alamat (Jalan, RT/RW) <span class="text-red-500">*</span></label>
                                <textarea v-model="newSenderForm.address" placeholder="Detail alamat..." class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 outline-none focus:bg-white focus:ring-2 focus:ring-primary/30 text-sm h-16 resize-none"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Link Google Maps (Opsional)</label>
                                <input type="url" v-model="newSenderForm.maps_url" placeholder="https://maps.app.goo.gl/..." class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 outline-none focus:bg-white focus:ring-2 focus:ring-primary/30 text-sm">
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
                        <button type="button" @click="isModalOpen = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-white transition-colors">Batal</button>
                        <button type="button" @click="simpanPengirim" class="px-4 py-2 bg-primary text-white rounded-lg font-bold hover:bg-primaryLight transition-colors" :class="{ 'opacity-50': newSenderForm.processing }" :disabled="newSenderForm.processing">Simpan Data</button>
                    </div>
                </div>
            </div>
        </Teleport>


        <!-- CUSTOM SUCCESS ALERT MODAL -->
        <div v-if="showSuccessAlert" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-[2rem] shadow-2xl p-6 sm:p-8 max-w-sm w-full text-center transform transition-all scale-100 border border-gray-100">
                <div class="w-16 h-16 sm:w-20 sm:h-20 bg-emerald-100/80 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner">
                    <i class="ri-checkbox-circle-fill text-4xl sm:text-5xl text-emerald-600"></i>
                </div>
                <h3 class="text-xl sm:text-2xl font-black text-gray-800 mb-1">
                    {{ successTrackingNumber ? `Resi ${successTrackingNumber} Telah Dibuat!` : 'Berhasil!' }}
                </h3>
                <p class="text-xs text-gray-500 mb-4 font-medium">{{ successMessage }}</p>

                <!-- KOTAK NOMOR RESI BARU (DITAMPILKAN JELAS & BISA DISALIN) -->
                <div v-if="successTrackingNumber" class="mb-5 bg-amber-50/90 border-2 border-dashed border-amber-300 rounded-2xl p-3.5 relative">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">Nomor Resi Baru</span>
                        <span class="text-[10px] bg-amber-200/80 text-amber-900 font-bold px-2 py-0.5 rounded-full">Tersimpan</span>
                    </div>
                    <div class="flex items-center justify-between gap-2 bg-white px-3 py-2 rounded-xl border border-amber-200 shadow-inner">
                        <span class="text-base sm:text-lg font-black font-mono text-dark tracking-wider select-all">{{ successTrackingNumber }}</span>
                        <button 
                            type="button" 
                            @click="copyResi" 
                            class="p-1.5 rounded-lg text-amber-800 hover:bg-amber-100 active:scale-90 transition-all flex items-center gap-1 font-bold text-xs"
                            :title="copiedResi ? 'Sudah disalin' : 'Salin resi'"
                        >
                            <i :class="copiedResi ? 'ri-check-line text-emerald-600' : 'ri-file-copy-line'"></i>
                            <span>{{ copiedResi ? 'Tersalin' : 'Salin' }}</span>
                        </button>
                    </div>
                </div>

                <div class="space-y-2.5 mb-4">
                    <button v-if="successPrintUrl" @click.prevent="cetakResi(successPrintUrl)" type="button" class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-primary-hover text-white font-bold py-3 px-4 rounded-xl transition-all shadow-md shadow-primary/20 active:scale-98">
                        <i class="ri-printer-line text-lg"></i> Cetak / Unduh Resi
                    </button>
                    
                    <button v-if="successWhatsappUrl" @click.prevent="openUrl(successWhatsappUrl)" type="button" class="w-full flex items-center justify-center gap-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 font-bold py-2.5 px-4 rounded-xl transition-all shadow-sm active:scale-98">
                        <i class="ri-whatsapp-line text-lg text-emerald-600"></i> Kirim Resi via WA
                    </button>
                    
                    <button v-if="successTrackingNumber" type="button" @click="lihatDaftarResi" class="w-full flex items-center justify-center gap-2 bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 font-bold py-2.5 px-4 rounded-xl transition-all shadow-sm active:scale-98">
                        <i class="ri-file-list-3-line text-lg text-primary"></i> Lihat di Daftar Resi
                    </button>
                </div>

                <button @click="closeSuccessAlert" class="w-full text-gray-400 hover:text-gray-600 font-medium py-2 px-4 rounded-xl transition-all text-xs">
                    {{ successTrackingNumber ? 'Buat Resi Lainnya' : 'Tutup' }}
                </button>
            </div>
        </div>
        
        <!-- ==========================================
             MODAL KELOLA SATUAN (UNIT)
             ========================================== -->
        <div v-if="isUnitModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden transform transition-all">
                <div class="bg-primary px-5 py-4 flex justify-between items-center text-white">
                    <h3 class="font-bold text-lg flex items-center gap-2"><i class="ri-ruler-line"></i> Kelola Satuan</h3>
                    <button @click="closeUnitModal" class="hover:bg-white/20 p-1 rounded-lg transition-colors"><i class="ri-close-line text-xl"></i></button>
                </div>
                <div class="p-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tambah Satuan Baru</label>
                    <div class="flex gap-2 mb-5">
                        <input autocomplete="off" type="text" v-model="newUnitName" @keyup.enter="submitNewUnit" placeholder="Misal: Karung..." class="flex-1 border border-gray-300 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all text-sm">
                        <button @click="submitNewUnit" type="button" class="bg-primary/10 text-primary px-4 py-2 rounded-xl font-bold hover:bg-primary/20 transition-colors shadow-sm text-sm"><i class="ri-add-line"></i> Simpan</button>
                    </div>
                    
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih dari Daftar</label>
                    <div class="bg-gray-50 border border-gray-200 rounded-xl max-h-48 overflow-y-auto">
                        <ul>
                            <li v-for="u in units" :key="u" class="px-4 py-2.5 border-b border-gray-100 last:border-0 text-sm text-gray-700 flex justify-between items-center hover:bg-white transition-colors">
                                <span class="font-medium">{{ u }}</span>
                                <button type="button" @click="pilihDariModal(u)" class="text-xs font-bold bg-white border border-gray-200 hover:border-primary hover:text-primary px-3 py-1 rounded-lg shadow-sm transition-all">Pilih</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>


        <!-- ==========================================
             DRAWER FILTER DAFTAR RESI
             ========================================== -->
        <AppDrawer
            v-model="showFilters"
            title="Filter Daftar Resi"
        >
            <div class="space-y-6">
                <!-- Filter Status -->
                <div>
                    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                        <i class="ri-price-tag-3-line text-primary"></i> Status Pengiriman
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        <button 
                            v-for="st in ['Semua', 'Diterima', 'Dikirim', 'Selesai']" :key="st"
                            @click="activeFilter = st"
                            class="px-4 py-2 rounded-xl text-sm font-medium transition-all"
                            :class="activeFilter === st ? 'bg-primary text-white shadow-md shadow-primary/20' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        >
                            {{ st }}
                        </button>
                    </div>
                </div>

                <!-- Tombol Terapkan (Mobile) -->
                <div class="pt-4 mt-6 border-t border-gray-100 md:hidden">
                    <button @click="showFilters = false" class="w-full bg-primary text-white font-bold py-3.5 rounded-xl shadow-lg shadow-primary/20 hover:bg-primaryLight transition-all active:scale-95">
                        Terapkan Filter
                    </button>
                </div>
            </div>
        </AppDrawer>
        </div>

    </AdminLayout>
</template>

<style scoped>
.step-card-enter-active,
.step-card-leave-active {
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
.step-card-enter-from,
.step-card-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
}

.step-form-enter-active,
.step-form-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.step-form-enter-from,
.step-form-leave-to {
    opacity: 0;
    transform: translateY(12px);
}
</style>
