<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppDrawer from '@/Components/AppDrawer.vue';
import SwipeableTabs from '@/Components/SwipeableTabs.vue';
import { useTabCache } from '@/Composables/useTabCache';
import { useFilterCache } from '@/Composables/useFilterCache';
import ManifestQrModal from '@/Components/ManifestQrModal.vue';

// Logika scroll untuk menyembunyikan filter & mendeteksi posisi sticky
const showHeaderScroll = ref(true);
const isFilterSticky = ref(false);
const isSearchExpanded = ref(false);
const riwayatContainerRef = ref(null);
// Active Tab State & Scroll Position handled by Composable
const { activeTab, scrollPositions: tabScrollPositions } = useTabCache('jepara_pickup_active_tab', 'antrean');

// Show Filters State (Drawer visibility) handled by Composable
const filterStates = useFilterCache('jepara_show_filters_states', { antrean: false, riwayat: false });

const showFilters = computed({
    get: () => filterStates.value[activeTab.value],
    set: (val) => { filterStates.value[activeTab.value] = val; }
});



// Swipe logic has been moved to SwipeableTabs component

let lastScrollY = typeof window !== 'undefined' ? window.scrollY : 0;

const handleScroll = () => {
    const currentScrollY = window.scrollY;
    
    // Auto-hide logic
    if (currentScrollY <= 50) {
        showHeaderScroll.value = true;
    } else if (currentScrollY > lastScrollY) {
        showHeaderScroll.value = false;
    } else if (currentScrollY < lastScrollY) {
        showHeaderScroll.value = true;
    }
    lastScrollY = currentScrollY;

    // Sticky shadow logic (gunakan container parent agar tidak terpengaruh animasi translate filter)
    if (riwayatContainerRef.value) {
        // Hilangkan shadow jika posisi scroll masih di atas (untuk desktop di mana top selalu <= 64)
        isFilterSticky.value = (currentScrollY > 10) && (riwayatContainerRef.value.getBoundingClientRect().top <= 64.5);
    }
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    // Trigger sekali saat mount untuk set nilai awal
    handleScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

// ---------------------------------------------------------
// STATE & DATA
// ---------------------------------------------------------
const props = defineProps({
    pickupList: {
        type: Array,
        default: () => []
    },
    drivers: {
        type: Array,
        default: () => []
    },
    allManifests: {
        type: Array,
        default: () => []
    }
});

// Drawer Riwayat Rute (activeTab already defined above)
const filterStatus = ref('semua'); // 'semua' | 'aktif' | 'nonaktif'
const filterDateFrom = ref('');
const filterDateTo = ref('');
const searchRiwayat = ref('');
const selectedQrManifest = ref(null);
const isQrModalOpen = ref(false);

const openQrModal = (m) => {
    selectedQrManifest.value = m;
    isQrModalOpen.value = true;
};

const filteredManifests = computed(() => {
    return props.allManifests.filter(m => {
        // Filter status
        if (filterStatus.value === 'aktif' && !m.is_active) return false;
        if (filterStatus.value === 'nonaktif' && m.is_active) return false;
        // Filter tanggal dari
        if (filterDateFrom.value && m.created_at < filterDateFrom.value) return false;
        // Filter tanggal sampai
        if (filterDateTo.value && m.created_at > filterDateTo.value) return false;
        // Filter pencarian
        if (searchRiwayat.value) {
            const query = searchRiwayat.value.toLowerCase();
            const driverMatch = m.driver?.name?.toLowerCase().includes(query) || false;
            const codeMatch = m.manifest_code?.toLowerCase().includes(query) || false;
            const statusMatch = m.status?.toLowerCase().includes(query) || false;
            if (!driverMatch && !codeMatch && !statusMatch) return false;
        }
        return true;
    });
});

const activeCount = computed(() => props.allManifests.filter(m => m.is_active).length);

const statusBadge = (manifest) => {
    if (manifest.status === 'SELESAI') return { label: 'Selesai', cls: 'bg-green-100 text-green-700' };
    if (manifest.status === 'CANCELLED') return { label: 'Dibatalkan', cls: 'bg-red-100 text-red-700' };
    if (manifest.status === 'PREPARING') return { label: 'Menjemput', cls: 'bg-yellow-100 text-yellow-700' };
    return { label: manifest.status, cls: 'bg-gray-100 text-gray-600' };
};

const armada = ref('');
const area = ref('');
const isMobileModalOpen = ref(false);

// Data daftar resi — disimpan lokal agar checkbox bisa bereaksi,
// tapi di-sync ulang dari props setiap kali Inertia me-refresh
const pickupList = ref(props.pickupList.map(item => ({
    ...item,
    shipments: item.shipments?.map(s => ({ ...s, selected: false })) ?? []
})));

// Sync ulang ketika props berubah (setelah assign berhasil)
watch(() => props.pickupList, (newList) => {
    pickupList.value = newList.map(item => ({
        ...item,
        shipments: item.shipments?.map(s => ({ ...s, selected: false })) ?? []
    }));
}, { deep: false });

const searchQuery = ref('');
const isSearchActive = ref(false);
const isDateActive = ref(false);

// Form Supir Baru
import { useForm, router, usePage } from '@inertiajs/vue3';
const showAddDriver = ref(false);
const driverForm = useForm({
    name: '',
    vehicle_name: '',
    phone_number: '',
    avatar: null
});

const handleAvatarChange = (e) => {
    driverForm.avatar = e.target.files[0];
};

const saveDriver = () => {
    driverForm.post('/drivers', {
        preserveScroll: true,
        onSuccess: () => {
            showAddDriver.value = false;
            driverForm.reset();
            // Otomatis pilih supir yang baru ditambahkan
            if (props.drivers && props.drivers.length > 0) {
                armada.value = props.drivers[props.drivers.length - 1].id;
            }
        }
    });
};

// ---------------------------------------------------------
// COMPUTED & METHODS
// ---------------------------------------------------------
// Menghitung jumlah yang dicentang secara otomatis (Reaktif)
const selectedCount = computed(() => {
    let count = 0;
    pickupList.value.forEach(item => {
        if (item.shipments) {
            count += item.shipments.filter(sub => sub.selected).length;
        }
    });
    return count;
});

const clearSelection = () => {
    pickupList.value.forEach(item => {
        if (item.shipments) {
            item.shipments.forEach(sub => {
                sub.selected = false;
            });
        }
    });
};

const filterKecamatan = ref('');
const filterKoli = ref('');
const sortBy = ref('lama');

const availableKecamatans = computed(() => {
    const districts = props.pickupList.map(item => item.kecamatan).filter(Boolean);
    return [...new Set(districts)].sort();
});

const totalAntrean = computed(() => {
    let count = 0;
    pickupList.value.forEach(item => {
        count += (item.shipments?.length || 0);
    });
    return count;
});

const areaSummary = computed(() => {
    const summary = {};
    pickupList.value.forEach(item => {
        const area = item.kecamatan || 'Lainnya';
        if (!summary[area]) summary[area] = 0;
        summary[area] += (item.shipments?.length || 0);
    });
    
    return Object.entries(summary)
        .map(([name, count]) => ({ name, count }))
        .sort((a, b) => b.count - a.count);
});

const filteredPickupList = computed(() => {
    let result = pickupList.value;
    
    // Filter by Kecamatan
    if (filterKecamatan.value) {
        result = result.filter(item => item.kecamatan === filterKecamatan.value);
    }
    
    // Filter by Koli
    if (filterKoli.value === 'kecil') {
        result = result.filter(item => item.koli >= 1 && item.koli <= 10);
    } else if (filterKoli.value === 'besar') {
        result = result.filter(item => item.koli > 10);
    }
    
    // Filter by Search (nama / alamat)
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(item => {
            const matchNama = item.nama && item.nama.toLowerCase().includes(query);
            const matchAlamat = item.alamat && item.alamat.toLowerCase().includes(query);
            return matchNama || matchAlamat;
        });
    }
    
    // Sorting
    result = [...result].sort((a, b) => {
        if (sortBy.value === 'lama') {
            return new Date(a.created_at) - new Date(b.created_at);
        } else if (sortBy.value === 'baru') {
            return new Date(b.created_at) - new Date(a.created_at);
        } else if (sortBy.value === 'koli_banyak') {
            return b.koli - a.koli;
        }
        return 0;
    });
    
    return result;
});

const selectedDriver = computed(() => {
    return props.drivers.find(d => d.id === armada.value);
});

const isFormValid = computed(() => {
    return selectedDriver.value && area.value && area.value.trim() !== '' && selectedCount.value > 0;
});

const kirimRute = () => {
    if (!selectedDriver.value) {
        alert("Silakan pilih supir pick-up terlebih dahulu!");
        return;
    }

    if (selectedCount.value === 0) {
        alert("Pilih minimal 1 resi untuk dijemput!");
        return;
    }

    // Kumpulkan data resi yang dipilih
    let shipmentIds = [];
    let koliIds = [];
    
    pickupList.value.forEach(item => {
        if (item.shipments) {
            item.shipments.forEach(sub => {
                if (sub.selected) {
                    shipmentIds.push(sub.id);
                    if (sub.koli_ids && sub.koli_ids.length > 0) {
                        koliIds.push(...sub.koli_ids);
                    }
                }
            });
        }
    });
    
    router.post('/pickup/assign', {
        driver_id: selectedDriver.value.id,
        shipment_ids: shipmentIds,
        koli_ids: koliIds
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            const flash = page.props.flash ?? usePage().props.flash;
            if (flash?.magic_url) {
                window.open(flash.magic_url, '_blank');
            }
            isMobileModalOpen.value = false;
            armada.value = ''; // Reset form
        }
    });
};

const hasActiveFilters = computed(() => {
    if (activeTab.value === 'antrean') {
        return filterKecamatan.value !== '' || filterKoli.value !== '' || sortBy.value !== 'lama';
    } else {
        return filterStatus.value !== 'semua' || filterDateFrom.value !== '' || filterDateTo.value !== '';
    }
});
</script>
<template>
    <!-- Floating Action Bar (All Devices) -->
    <Transition name="slide-bottom">
        <div v-if="selectedCount > 0 && activeTab === 'antrean'" class="fixed bottom-24 md:bottom-8 left-1/2 -translate-x-1/2 w-auto min-w-[280px] z-40">
            <div class="relative w-full">
                <!-- Tombol Batal (Offside Kiri Atas) -->
                <button @click="clearSelection" class="absolute -top-3 -left-3 w-8 h-8 bg-red-500 text-white rounded-full shadow-md border-2 border-white flex items-center justify-center hover:bg-red-600 hover:scale-110 transition-transform z-50" title="Batal (Hapus Pilihan)">
                    <i class="ri-close-line text-lg font-bold"></i>
                </button>

                <!-- Tombol Utama -->
                <button @click="isMobileModalOpen = true" class="w-full bg-primary text-white font-bold py-3 px-5 rounded-full shadow-xl flex justify-between items-center gap-6 border border-primaryLight hover:-translate-y-1 transition-transform">
                    <span class="flex items-center gap-2 text-sm"><i class="ri-checkbox-multiple-fill"></i> {{ selectedCount }} Dipilih</span>
                    <span class="bg-white/20 px-3 py-1.5 rounded-xl text-xs hover:bg-white/30 transition whitespace-nowrap">Pilih Supir <i class="ri-arrow-right-line"></i></span>
                </button>
            </div>
        </div>
    </Transition>

    <!-- MODAL TUGASKAN ARMADA (ALL DEVICES) -->
    <Transition name="fade">
        <div v-if="isMobileModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm" @click.self="isMobileModalOpen = false">
            <div class="bg-white w-full max-w-md p-6 rounded-2xl animate-slide-up-fast overflow-y-auto max-h-[90vh] shadow-2xl">
                <div class="flex justify-between items-center border-b pb-3 mb-4">
                    <h3 class="font-bold text-dark text-lg">Tugaskan Armada Kecil</h3>
                    <button @click="isMobileModalOpen = false" class="w-8 h-8 flex items-center justify-center bg-gray-100 text-gray-500 rounded-full hover:bg-gray-200 transition">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">Supir Pick-up</label>
                            <button v-if="!selectedDriver" @click="showAddDriver = true" class="text-xs font-bold text-primary hover:text-primaryLight flex items-center gap-1 transition">
                                <i class="ri-add-circle-fill"></i> Supir Baru
                            </button>
                        </div>
                        
                        <select v-if="!selectedDriver" v-model="armada" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all shadow-sm">
                            <option value="">Pilih Supir...</option>
                            <option v-for="driver in props.drivers" :key="driver.id" :value="driver.id">
                                {{ driver.name }} ({{ driver.vehicle_name }})
                            </option>
                        </select>

                        <div v-else @click="armada = ''" class="p-3 bg-white border border-gray-200 rounded-xl flex items-center justify-between gap-3 animate-fade-in shadow-sm hover:border-primary/50 transition cursor-pointer group" title="Klik untuk mengganti supir">
                            <div class="flex items-center gap-3">
                                <img v-if="selectedDriver.avatar_path" :src="`/storage/${selectedDriver.avatar_path}`" class="w-10 h-10 rounded-full shadow-sm object-cover bg-white" alt="Avatar">
                                <img v-else :src="`https://ui-avatars.com/api/?name=${selectedDriver.name}&background=random&color=fff&rounded=true&bold=true`" class="w-10 h-10 rounded-full shadow-sm bg-white" alt="Avatar">
                                <div>
                                    <p class="font-bold text-dark text-sm">{{ selectedDriver.name }}</p>
                                    <p class="text-xs text-gray-500 font-medium"><i class="ri-truck-fill text-gray-400"></i> {{ selectedDriver.vehicle_name }}</p>
                                </div>
                            </div>
                            <div class="text-xs font-bold text-gray-400 group-hover:text-red-500 transition px-2 py-1 bg-gray-50 rounded-md">
                                Ubah <i class="ri-arrow-down-s-line"></i>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Area Penjemputan Utama</label>
                        <input type="text" v-model="area" placeholder="Misal: Area Tahunan" class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all shadow-sm">
                    </div>
                </div>

                <button 
                    @click="kirimRute" 
                    :disabled="!isFormValid"
                    class="w-full mt-6 text-white py-3 rounded-xl font-bold flex items-center justify-center gap-2 transition-all"
                    :class="isFormValid ? 'bg-primary hover:bg-primaryLight hover:-translate-y-0.5 shadow-md cursor-pointer' : 'bg-gray-300 cursor-not-allowed opacity-80'"
                >
                    <i class="ri-whatsapp-line text-lg"></i> Kirim Rute ke WA Supir
                </button>
                <p class="text-[11px] text-gray-400 text-center mt-3 leading-relaxed">Supir akan menerima pesan WA otomatis berisi daftar alamat pengirim di bawah ini.</p>
            </div>
        </div>
    </Transition>


    <AdminLayout title="Penjemputan" :noPaddingTop="true">
        <!-- TABS NAV IN HEADER -->
        <template #header_tabs>
            <nav class="flex bg-gray-100/80 p-0.5 md:p-1 rounded-lg md:rounded-xl border border-gray-200/50 items-center shrink-0" aria-label="Tabs">
                <button 
                    @click="activeTab = 'antrean'" 
                    :class="activeTab === 'antrean' ? 'bg-primary text-white shadow-md shadow-primary/30' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50'"
                    class="px-2 md:px-4 py-1 md:py-1.5 rounded-md md:rounded-lg font-bold text-[10px] md:text-xs transition-all flex items-center gap-1"
                >
                    <i class="ri-list-check-2 text-xs md:text-sm"></i> <span class="hidden sm:inline">Antrean</span><span class="sm:hidden">Antre</span>
                </button>
                
                <button 
                    @click="activeTab = 'riwayat'"
                    :class="activeTab === 'riwayat' ? 'bg-primary text-white shadow-md shadow-primary/30' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50'"
                    class="px-2 md:px-4 py-1 md:py-1.5 rounded-md md:rounded-lg font-bold text-[10px] md:text-xs transition-all flex items-center gap-1"
                >
                    <i class="ri-history-line text-xs md:text-sm"></i> Riwayat
                </button>
            </nav>
        </template>

        <!-- SEARCH & FILTER IN HEADER -->
        <template #header_actions>
            <div class="flex items-center gap-1 md:gap-2 shrink-0">
                <!-- Search Button (Mobile) -->
                <button @click="isSearchExpanded = true" class="md:hidden relative flex items-center justify-center w-7 h-7 bg-white border border-gray-200 rounded-full text-gray-600 hover:bg-gray-50 hover:text-primary transition-all shadow-sm">
                    <i class="ri-search-line text-xs"></i>
                </button>
                
                <!-- Expanded Search Overlay (Mobile) -->
                <div v-if="isSearchExpanded" class="md:hidden absolute inset-y-0 left-0 right-0 bg-white z-50 flex items-center px-3 gap-2 shadow-sm animate-fade-in">
                    <i class="ri-search-line text-gray-400"></i>
                    <input v-if="activeTab === 'antrean'" type="text" v-model="searchQuery" placeholder="Cari area/toko..." class="flex-1 border-none bg-transparent outline-none text-sm px-2 focus:ring-0" autofocus>
                    <input v-else type="text" v-model="searchRiwayat" placeholder="Cari supir/resi..." class="flex-1 border-none bg-transparent outline-none text-sm px-2 focus:ring-0" autofocus>
                    <button @click="isSearchExpanded = false" class="p-1 text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>

                <!-- Input Search Dinamis (Desktop) -->
                <div class="hidden md:block relative animate-fade-in transition-all">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input v-if="activeTab === 'antrean'" type="text" v-model="searchQuery" placeholder="Cari area atau toko..." 
                           class="pl-9 pr-4 py-1.5 text-sm border border-gray-200 rounded-full focus:ring-primary focus:border-primary w-48 transition-all bg-gray-50 hover:bg-white outline-none shadow-sm" />
                    <input v-else type="text" v-model="searchRiwayat" placeholder="Cari supir atau resi..." 
                           class="pl-9 pr-4 py-1.5 text-sm border border-gray-200 rounded-full focus:ring-primary focus:border-primary w-48 transition-all bg-gray-50 hover:bg-white outline-none shadow-sm" />
                </div>
                
                <!-- Tombol Filter Toggle -->
                <button @click="showFilters = !showFilters" class="relative flex items-center justify-center w-7 h-7 md:w-8 md:h-8 bg-white border border-gray-200 rounded-full text-gray-600 hover:bg-gray-50 hover:text-primary transition-all shadow-sm cursor-pointer" title="Filter">
                    <i class="ri-filter-3-line text-xs md:text-base"></i>
                    <span v-if="hasActiveFilters" class="absolute -top-0.5 -right-0.5 w-2 h-2 md:w-2.5 md:h-2.5 bg-red-500 rounded-full border-2 border-white shadow-sm"></span>
                </button>
            </div>
        </template>



        
        
        <div class="flex flex-col lg:flex-row items-start gap-6 lg:gap-8 relative pb-24 pt-4 md:pt-8 w-full">
            <!-- MAIN CONTENT (Left) -->
            <SwipeableTabs 
                v-model="activeTab" 
                :tabs="[{ id: 'antrean' }, { id: 'riwayat' }]"
            >
                <template #antrean>
                    <!-- TAB: ANTREAN JEMPUT -->
                    <div class="space-y-4 h-full">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center pb-3 mb-4 border-b border-gray-200 gap-3">
                        <h3 class="font-bold text-dark text-base md:text-lg shrink-0">Daftar Antrean Jemput</h3>
                        <div class="flex items-center justify-between gap-2 w-full sm:w-auto">
                            <span class="text-xs font-bold px-3 py-2 rounded-lg shrink-0 transition-colors" :class="selectedCount > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600'">
                                {{ selectedCount }} Resi Dipilih
                            </span>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div v-if="filteredPickupList.length === 0" class="text-center py-10 bg-white rounded-2xl border border-gray-100 border-dashed">
                            <i class="ri-search-eye-line text-4xl text-gray-300 mb-2 block"></i>
                            <p class="text-gray-500 font-medium">Tidak ada antrean di area tersebut.</p>
                        </div>

                        <div 
                            v-for="item in filteredPickupList" 
                            :key="item.id"
                            class="block p-4 sm:p-5 border border-gray-100 rounded-2xl shadow-sm relative overflow-hidden bg-white"
                        >
                            <div class="flex items-start gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start gap-2">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i class="ri-store-3-fill text-primary text-lg shrink-0"></i>
                                            <h4 class="font-bold text-dark text-sm sm:text-base truncate">{{ item.nama }}</h4>
                                        </div>
                                        <span class="text-xs font-bold text-gray-500 shrink-0 bg-gray-100 px-2 py-1 rounded-lg">{{ item.koli }} Koli</span>
                                    </div>
                                    <p class="text-xs text-gray-500 flex items-start gap-1 mt-1">
                                        <i class="ri-map-pin-2-fill text-red-400 mt-0.5 shrink-0"></i>
                                        <span class="line-clamp-2">{{ item.alamat }}</span>
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <div v-for="sub in item.shipments" :key="sub.id" class="rounded-xl border border-gray-100 bg-gray-50/80">
                                    <label class="flex items-start gap-3 p-3 cursor-pointer hover:bg-primary/5 rounded-xl transition-colors">
                                        <input type="checkbox" v-model="sub.selected" class="mt-0.5 w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary shrink-0">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap justify-between items-center gap-1">
                                                <span class="font-mono font-bold text-xs sm:text-sm text-dark">{{ sub.resi }}</span>
                                                <span class="text-xs font-semibold text-gray-600 bg-white border border-gray-200 px-2 py-0.5 rounded-lg">{{ sub.koli }} Koli</span>
                                            </div>
                                            <div v-if="sub.item_details && sub.item_details.length" class="mt-2 mb-2 space-y-1">
                                                <div v-for="det in sub.item_details" :key="det.name" class="flex justify-between items-center text-[11px] sm:text-xs bg-white border border-gray-100 px-2 py-1.5 rounded-md">
                                                    <span class="text-gray-600 truncate max-w-[150px] sm:max-w-[200px]" :title="det.name">
                                                        <i class="ri-box-3-fill text-gray-400"></i> {{ det.name }}
                                                    </span>
                                                    <div class="flex items-center gap-2 shrink-0 text-right">
                                                        <span class="font-bold text-dark">{{ det.qty }} <span class="text-[10px] font-normal text-gray-400">set</span></span>
                                                        <span class="text-gray-300">|</span>
                                                        <span class="text-gray-500 font-medium">{{ det.koli }} koli</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <p v-else class="text-gray-500 text-[11px] sm:text-xs truncate mt-0.5 mb-1" :title="sub.items">
                                                <i class="ri-box-3-fill text-gray-400"></i> {{ sub.items || '-' }}
                                            </p>
                                            <div class="shrink-0 mt-1">
                                                <div class="inline-flex items-center gap-1 bg-white border border-gray-200 px-2 py-1 rounded-lg">
                                                    <i class="ri-map-pin-user-fill text-primary text-xs"></i>
                                                    <span class="font-medium text-gray-700 text-[10px] sm:text-xs truncate" :title="sub.destination">{{ sub.destination }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                </div>

                        </div>
                    </div>
                </div>
            </div>

            
                </template>
                <template #riwayat>
                    <!-- TAB: RIWAYAT RUTE -->
                    <div class="h-full" ref="riwayatContainerRef">
                    <div v-if="filteredManifests.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-400 bg-white rounded-2xl border border-gray-100">
                        <i class="ri-route-line text-5xl mb-3"></i>
                        <p class="font-medium">Tidak ada data ditemukan.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-4 items-start">
                        <div
                            v-for="manifest in filteredManifests"
                            :key="manifest.id"
                            class="bg-white border rounded-2xl shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md hover:-translate-y-1" :class="manifest.is_active ? 'border-primary/40' : 'border-gray-100'"
                        >
                            <!-- Card Header -->
                            <div class="p-3.5 sm:p-4 flex flex-col gap-3" :class="manifest.is_active ? 'bg-primary/5' : 'bg-gray-50'">
                                <div class="flex items-start justify-between gap-2">
                                    <span :class="['inline-block px-2 py-1 rounded-md text-[10px] font-bold shrink-0', statusBadge(manifest).cls]">
                                        {{ statusBadge(manifest).label }}
                                    </span>
                                    <p class="text-[10px] text-gray-400 font-medium text-right shrink-0">{{ manifest.created_at_label }}</p>
                                </div>
                                <div class="flex items-center gap-3 min-w-0">
                                    <img
                                        v-if="manifest.driver.avatar_path"
                                        :src="`/storage/${manifest.driver.avatar_path}`"
                                        class="w-9 h-9 rounded-full object-cover shadow-sm shrink-0"
                                        alt="Avatar"
                                    >
                                    <img
                                        v-else
                                        :src="`https://ui-avatars.com/api/?name=${manifest.driver.name}&background=random&color=fff&rounded=true&bold=true`"
                                        class="w-9 h-9 rounded-full shadow-sm shrink-0"
                                        alt="Avatar"
                                    >
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-dark text-sm leading-tight truncate" :title="manifest.driver.name">{{ manifest.driver.name }}</p>
                                        <p class="text-[11px] text-gray-500 truncate mt-0.5" :title="manifest.driver.vehicle_name"><i class="ri-truck-fill text-gray-400"></i> {{ manifest.driver.vehicle_name }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Ringkasan -->
                            <div class="px-3.5 sm:px-4 pt-3 pb-4">
                                <div class="flex gap-4 text-[11px] text-gray-500 mb-2 font-medium">
                                    <span><i class="ri-file-list-3-line text-primary"></i> <strong class="text-dark">{{ manifest.total_resi }}</strong> Resi</span>
                                    <span><i class="ri-box-3-line text-primary"></i> <strong class="text-dark">{{ manifest.total_koli }}</strong> Koli</span>
                                </div>
                                <ul class="space-y-1">
                                    <li
                                        v-for="ship in manifest.shipments.slice(0, 5)"
                                        :key="ship.resi"
                                        class="flex justify-between items-center text-xs py-1 border-b border-dashed border-gray-100 last:border-0"
                                    >
                                        <span class="font-mono text-gray-700 font-medium truncate shrink" :title="ship.resi">{{ ship.resi }}</span>
                                        <span class="text-gray-500 truncate max-w-[110px] text-right shrink-0" :title="ship.sender + ' • ' + ship.koli + ' koli'">{{ ship.sender }} &bull; {{ ship.koli }} koli</span>
                                    </li>
                                </ul>
                                <div v-if="manifest.shipments.length > 5" class="mt-2 text-center">
                                    <span class="inline-block px-3 py-1 bg-gray-50 text-gray-500 text-[10px] font-bold rounded-lg border border-gray-100">
                                        +{{ manifest.shipments.length - 5 }} Resi Lainnya
                                    </span>
                                </div>
                            </div>

                            <!-- Tindakan -->
                            <div class="px-3.5 sm:px-4 py-3 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-400">TINDAKAN</span>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <!-- Tombol QR Code Handshake -->
                                    <button
                                        type="button"
                                        @click="openQrModal(manifest)"
                                        title="Tampilkan QR Code Handshake"
                                        class="h-8 px-2.5 flex items-center gap-1 bg-slate-900 hover:bg-black text-white text-xs font-bold rounded-lg transition-all active:scale-95 cursor-pointer shadow-sm"
                                    >
                                        <i class="ri-qr-code-line text-primary text-sm"></i>
                                        <span>QR</span>
                                    </button>

                                    <!-- Tombol Cetak Dokumen Surat Tugas -->
                                    <a
                                        :href="'/manifest/' + manifest.id + '/print?autoprint=1'"
                                        target="_blank"
                                        title="Cetak Surat Tugas Penjemputan (Print)"
                                        class="h-8 px-2.5 flex items-center gap-1 bg-gray-100 hover:bg-gray-200 text-gray-800 border border-gray-200 text-xs font-bold rounded-lg transition-all active:scale-95 shadow-sm"
                                    >
                                        <i class="ri-printer-line text-gray-600 text-sm"></i>
                                        <span>Cetak</span>
                                    </a>

                                    <a
                                        v-if="manifest.wa_link"
                                        :href="manifest.wa_link"
                                        target="_blank"
                                        title="Kirim Ulang WA ke Supir"
                                        class="w-8 h-8 flex items-center justify-center bg-green-50 text-green-600 hover:bg-green-500 hover:text-white border border-green-200 text-sm rounded-lg transition-all active:scale-95"
                                    >
                                        <i class="ri-whatsapp-fill"></i>
                                    </a>
                                    <a
                                        :href="manifest.magic_url"
                                        target="_blank"
                                        title="Buka Link Layar Supir"
                                        class="w-8 h-8 flex items-center justify-center bg-white border border-gray-200 text-gray-600 hover:border-primary hover:text-primary text-sm rounded-lg transition-all active:scale-95"
                                    >
                                        <i class="ri-external-link-line"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                                    </div>
                </template>
            </SwipeableTabs>

            <!-- FILTER & WIDGET SIDEBAR (Right) -->
            <AppDrawer 
                v-model="showFilters" 
                :title="'Filter ' + (activeTab === 'antrean' ? 'Antrean' : 'Riwayat')"
            >
                <template #icon>
                    <i class="ri-filter-3-line text-primary"></i>
                </template>
                
                <!-- Ringkasan Area (For Antrean) -->
                <div v-if="activeTab === 'antrean'" class="bg-gray-50 p-4 rounded-xl border border-gray-100 mb-6">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-bold text-dark text-sm flex items-center gap-2">
                            <i class="ri-pie-chart-2-fill text-primary"></i> Ringkasan Area
                        </h3>
                        <div class="bg-primary/10 text-primary px-2 py-0.5 rounded-lg text-[10px] font-bold shrink-0">
                            {{ totalAntrean }} Resi
                        </div>
                    </div>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-2">
                        <div v-for="area in areaSummary" :key="area.name" class="flex justify-between items-center text-xs border-b border-gray-100 pb-1.5 last:border-0 last:pb-0">
                            <span class="text-gray-600 truncate font-medium"><i class="ri-map-pin-2-fill text-gray-300 mr-1"></i> {{ area.name }}</span>
                            <span class="font-bold text-dark bg-white border border-gray-100 px-1.5 py-0.5 rounded">{{ area.count }}</span>
                        </div>
                    </div>
                </div>

                <!-- Filter Antrean -->
                <div v-if="activeTab === 'antrean'" class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-wide">Area Jemput</label>
                        <select v-model="filterKecamatan" class="w-full bg-white border border-gray-200 text-sm rounded-xl px-3 py-2.5 outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all shadow-sm cursor-pointer">
                            <option value="">Semua Area</option>
                            <option v-for="kec in availableKecamatans" :key="kec" :value="kec">{{ kec }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-wide">Kapasitas Muatan</label>
                        <select v-model="filterKoli" class="w-full bg-white border border-gray-200 text-sm rounded-xl px-3 py-2.5 outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all shadow-sm cursor-pointer">
                            <option value="">Semua Muatan</option>
                            <option value="kecil">Muatan Kecil (1-10 Koli)</option>
                            <option value="besar">Muatan Besar (>10 Koli)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-wide">Urutkan</label>
                        <select v-model="sortBy" class="w-full bg-white border border-gray-200 text-sm rounded-xl px-3 py-2.5 outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all shadow-sm cursor-pointer">
                            <option value="lama">Paling Lama (Prioritas)</option>
                            <option value="baru">Terbaru Masuk</option>
                            <option value="koli_banyak">Koli Terbanyak</option>
                        </select>
                    </div>
                </div>

                <!-- Filter Riwayat -->
                <div v-else-if="activeTab === 'riwayat'" class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-wide">Status Pengiriman</label>
                        <select v-model="filterStatus" class="w-full bg-white border border-gray-200 text-sm rounded-xl px-3 py-2.5 outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all shadow-sm cursor-pointer">
                            <option value="semua">Semua Status ({{ props.allManifests.length }})</option>
                            <option value="aktif">Sedang Aktif ({{ activeCount }})</option>
                            <option value="nonaktif">Selesai/Dibatalkan ({{ props.allManifests.length - activeCount }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-wide">Rentang Tanggal</label>
                        <div class="flex flex-col gap-2">
                            <div>
                                <label class="text-[10px] text-gray-400 font-bold ml-1 mb-0.5 block">Dari</label>
                                <input type="date" v-model="filterDateFrom" class="w-full bg-white border border-gray-200 text-sm rounded-xl px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all shadow-sm text-gray-600">
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-400 font-bold ml-1 mb-0.5 block">Sampai</label>
                                <input type="date" v-model="filterDateTo" class="w-full bg-white border border-gray-200 text-sm rounded-xl px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all shadow-sm text-gray-600">
                            </div>
                        </div>
                    </div>
                </div>
                
                <template #footer>
                    <button @click="resetFilters" class="w-full bg-red-50 hover:bg-red-100 text-red-500 font-bold py-3 rounded-xl transition-colors shadow-sm">
                        Reset Semua Filter
                    </button>
                    <!-- Tambahkan tombol terapkan khusus di mobile -->
                    <button @click="showFilters = false" class="w-full lg:hidden bg-primary hover:bg-primaryLight text-white font-bold py-3 rounded-xl transition-colors shadow-md">
                        Terapkan Filter
                    </button>
                </template>
            </AppDrawer>
            
        </div>
            <!-- MODAL TAMBAH SUPIR -->
        <div v-if="showAddDriver" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-xl">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-bold text-lg text-dark">Tambah Supir Baru</h3>
                    <button @click="showAddDriver = false" class="text-gray-400 hover:text-gray-600 transition">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Supir</label>
                        <input v-model="driverForm.name" type="text" placeholder="Misal: Agus" class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                        <p v-if="driverForm.errors.name" class="text-xs text-red-500 mt-1">{{ driverForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon / WhatsApp</label>
                        <input v-model="driverForm.phone_number" type="tel" placeholder="Misal: 08123456789" class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                        <p v-if="driverForm.errors.phone_number" class="text-xs text-red-500 mt-1">{{ driverForm.errors.phone_number }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kendaraan / Plat</label>
                        <input v-model="driverForm.vehicle_name" type="text" placeholder="Misal: Grandmax K 1234 A" class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                        <p v-if="driverForm.errors.vehicle_name" class="text-xs text-red-500 mt-1">{{ driverForm.errors.vehicle_name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Foto Profil (Opsional)</label>
                        <input type="file" @change="handleAvatarChange" accept="image/*" class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p v-if="driverForm.errors.avatar" class="text-xs text-red-500 mt-1">{{ driverForm.errors.avatar }}</p>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3 border-t border-gray-100">
                    <button @click="showAddDriver = false" class="px-4 py-2 font-bold text-gray-600 hover:text-gray-800 transition">Batal</button>
                    <button @click="saveDriver" :disabled="driverForm.processing" class="px-5 py-2 font-bold text-white bg-primary rounded-lg hover:bg-primaryLight transition disabled:opacity-50">
                        {{ driverForm.processing ? 'Menyimpan...' : 'Simpan Supir' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL QR CODE HANDSHAKE & PRINT MANIFEST -->
        <ManifestQrModal
            :show="isQrModalOpen"
            :manifest="selectedQrManifest"
            @close="isQrModalOpen = false"
        />

    </AdminLayout>

</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* SLIDE BOTTOM ANIMATION */
.slide-bottom-enter-active, .slide-bottom-leave-active {
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-bottom-enter-from, .slide-bottom-leave-to {
    opacity: 0;
    transform: translate(-50%, 100%) !important;
}

/* DRAWER ANIMATION NATIVE-LIKE */
.drawer-enter-active, .drawer-leave-active {
    transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}
@media (min-width: 1024px) {
    /* Desktop: Animate width to push content */
    .drawer-enter-from, .drawer-leave-to {
        width: 0 !important;
        opacity: 0;
        transform: scale(0.95);
    }
    .drawer-enter-from .drawer-content, .drawer-leave-to .drawer-content {
        transform: translateX(100px);
        opacity: 0;
    }
    .drawer-enter-active .drawer-content, .drawer-leave-active .drawer-content {
        transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    }
}
@media (max-width: 1023px) {
    /* Mobile: Bottom Sheet Overlay */
    .drawer-enter-from, .drawer-leave-to {
        background-color: transparent !important;
        backdrop-filter: blur(0px) !important;
    }
    .drawer-enter-from .drawer-content, .drawer-leave-to .drawer-content {
        transform: translateY(100%);
        box-shadow: none;
    }
    .drawer-enter-active .drawer-content, .drawer-leave-active .drawer-content {
        transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.6s;
    }
}
</style>
