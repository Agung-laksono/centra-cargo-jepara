<script setup>
import { ref, onMounted, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SwipeableTabs from '@/Components/SwipeableTabs.vue';
import { useTabCache } from '@/Composables/useTabCache';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js';
import { Bar } from 'vue-chartjs';
import { router, Link } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Fix Leaflet default icon issue
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
L.Icon.Default.mergeOptions({
  iconRetinaUrl,
  iconUrl,
  shadowUrl,
});

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
);

// ---------------------------------------------------------
// STATE & DATA
// ---------------------------------------------------------
const props = defineProps({
    is_super_admin: {
        type: Boolean,
        default: false,
    },
    super_stats: {
        type: Object,
        default: () => ({
            total_expeditions: 0,
            active_expeditions: 0,
            total_users: 0,
            total_shipments_month: 0,
            total_koli_month: 0,
            active_drivers: 0,
        })
    },
    expeditions_summary: {
        type: Array,
        default: () => []
    },
    platform_chart: {
        type: Object,
        default: () => ({
            labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
            data: [0, 0, 0, 0, 0, 0, 0]
        })
    },
    recent_platform_activities: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({
            menunggu_pickup: 0,
            di_gudang: 0,
            dalam_perjalanan: 0,
            terkirim_bulan_ini: 0,
            uang_cod_di_supir: 0,
            paket_bermasalah: 2,
            omset_bulan_ini: 15450000
        })
    },
    drivers_location: {
        type: Array,
        default: () => []
    }
});

// Setup Tabs
const dashboardTabs = [
    { id: 'operasional', label: 'Operasional', icon: 'ri-dashboard-line', shortLabel: 'Operasional' },
    { id: 'aktivitas', label: 'Aktivitas Terkini', icon: 'ri-history-line', shortLabel: 'Aktivitas' },
    { id: 'keuangan', label: 'Keuangan', icon: 'ri-wallet-3-line', shortLabel: 'Keuangan' },
    { id: 'analisa', label: 'Analisa', icon: 'ri-bar-chart-box-line', shortLabel: 'Analisa' }
];
const { activeTab } = useTabCache('dashboard-tab', 'operasional');

// Data untuk Chart Pengiriman Mingguan
const chartData = computed(() => {
  if (props.is_super_admin) {
    return {
      labels: props.platform_chart?.labels || ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
      datasets: [
        {
          label: 'Total Pengiriman Agregator',
          backgroundColor: 'rgb(var(--color-primary))',
          borderRadius: 8,
          data: props.platform_chart?.data || [0, 0, 0, 0, 0, 0, 0]
        }
      ]
    };
  }
  return {
    labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
    datasets: [
      {
        label: 'Total Pengiriman',
        backgroundColor: '#3b82f6',
        borderRadius: 6,
        data: [40, 55, 30, 80, 65, 90, 45]
      }
    ]
  };
});

const chartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      backgroundColor: '#1f2937',
      padding: 12,
      cornerRadius: 8,
      titleFont: { size: 14, weight: 'bold' },
      bodyFont: { size: 13 }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: { display: true, color: '#f3f4f6' },
      border: { display: false }
    },
    x: {
      grid: { display: false },
      border: { display: false }
    }
  }
});

// Mock Data Aktivitas Terkini
const recentActivities = ref([
    { id: 1, type: 'delivery', title: 'Paket JPR-1029 Terkirim', time: '10 menit yang lalu', icon: 'ri-checkbox-circle-fill', color: 'text-green-500', bg: 'bg-green-50' },
    { id: 2, type: 'pickup', title: 'Budi (Supir) pickup 5 koli', time: '45 menit yang lalu', icon: 'ri-car-fill', color: 'text-primary', bg: 'bg-primary/10' },
    { id: 3, type: 'warehouse', title: 'Paket JPR-1030 tiba di Gudang', time: '2 jam yang lalu', icon: 'ri-box-3-fill', color: 'text-blue-500', bg: 'bg-blue-50' },
    { id: 4, type: 'problem', title: 'Kendala alamat: JPR-0992', time: '3 jam yang lalu', icon: 'ri-error-warning-fill', color: 'text-red-500', bg: 'bg-red-50' },
    { id: 5, type: 'admin', title: 'Admin membuat 12 resi baru', time: '5 jam yang lalu', icon: 'ri-file-list-3-fill', color: 'text-purple-500', bg: 'bg-purple-50' },
]);

const isLoadingMore = ref(false);
const loadMore = () => {
    isLoadingMore.value = true;
    setTimeout(() => {
        const nextId = recentActivities.value.length + 1;
        recentActivities.value.push(
            { id: nextId, type: 'delivery', title: `Paket JPR-${1000 + nextId} Terkirim`, time: 'Kemarin, 14:00', icon: 'ri-checkbox-circle-fill', color: 'text-green-500', bg: 'bg-green-50' },
            { id: nextId + 1, type: 'warehouse', title: `Paket JPR-${1000 + nextId + 1} tiba di Gudang`, time: 'Kemarin, 11:30', icon: 'ri-box-3-fill', color: 'text-blue-500', bg: 'bg-blue-50' },
            { id: nextId + 2, type: 'admin', title: 'Admin membuat 5 resi baru', time: 'Kemarin, 09:15', icon: 'ri-file-list-3-fill', color: 'text-purple-500', bg: 'bg-purple-50' }
        );
        isLoadingMore.value = false;
    }, 800);
};

// Fungsi helper untuk format mata uang Rupiah
const formatRupiah = (angka) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka || 0);
};


</script>

<template>
    <AdminLayout title="Dashboard" :noPaddingTop="!props.is_super_admin">
        <!-- HEADER TABS (Hanya untuk Admin Ekspedisi) -->
        <template v-if="!props.is_super_admin" #header_tabs>
            <nav class="flex bg-gray-100/80 p-0.5 md:p-1 rounded-lg md:rounded-xl border border-gray-200/50 items-center shrink-0" aria-label="Tabs">
                <button 
                    v-for="tab in dashboardTabs"
                    :key="tab.id"
                    @click="activeTab = tab.id" 
                    class="px-2 md:px-4 py-1 md:py-1.5 rounded-md md:rounded-lg font-bold text-[10px] md:text-sm transition-all flex items-center gap-1"
                    :class="activeTab === tab.id ? 'bg-primary text-white shadow-md shadow-primary/30' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50'"
                >
                    <i :class="tab.icon" class="text-xs md:text-base"></i> 
                    <span class="hidden sm:inline">{{ tab.label }}</span>
                    <span class="sm:hidden">{{ tab.shortLabel }}</span>
                </button>
            </nav>
        </template>
        
        <!-- ============================================================== -->
        <!-- VIEW 1: SUPER ADMIN DASHBOARD (PLATFORM AGREGATOR)              -->
        <!-- ============================================================== -->
        <div v-if="props.is_super_admin" class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6">
            
            <!-- Header Ringkasan Platform -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 text-xs font-bold bg-primary/10 text-primary rounded-full">
                            Owner / Platform Pusat
                        </span>
                        <span class="text-xs text-gray-400">• Pantauan Real-Time</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mt-1">
                        Ikhtisar Agregator Kargo
                    </h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Kelola seluruh ekosistem ekspedisi, volume kiriman, dan staf dari satu pusat kontrol.
                    </p>
                </div>
                
                <div class="flex items-center gap-2">
                    <Link 
                        :href="route('expeditions.index')"
                        class="px-4 py-2 bg-primary text-white text-sm font-bold rounded-xl shadow-md shadow-primary/20 hover:bg-primary/90 transition flex items-center gap-1.5"
                    >
                        <i class="ri-add-line text-base"></i> Tambah Ekspedisi
                    </Link>
                    <Link 
                        :href="route('settings.index')"
                        class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-200 transition flex items-center gap-1.5"
                    >
                        <i class="ri-settings-3-line text-base"></i> Pengaturan
                    </Link>
                </div>
            </div>

            <!-- Kartu Metrik Utama (Platform KPIs) -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 1. Ekspedisi Mitra -->
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl shrink-0">
                        <i class="ri-community-line"></i>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 font-medium block">Mitra Ekspedisi</span>
                        <h3 class="text-2xl font-black text-gray-800 leading-tight">
                            {{ props.super_stats?.total_expeditions || 0 }}
                        </h3>
                        <span class="text-[11px] text-emerald-600 font-semibold flex items-center gap-0.5 mt-0.5">
                            <i class="ri-checkbox-circle-fill"></i> {{ props.super_stats?.active_expeditions || 0 }} Aktif Beroperasi
                        </span>
                    </div>
                </div>

                <!-- 2. Total Pengiriman Bulan Ini -->
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center text-2xl shrink-0">
                        <i class="ri-file-list-3-line"></i>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 font-medium block">Resi Bulan Ini</span>
                        <h3 class="text-2xl font-black text-gray-800 leading-tight">
                            {{ props.super_stats?.total_shipments_month || 0 }}
                        </h3>
                        <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">
                            {{ props.super_stats?.total_koli_month || 0 }} Koli terdaftar
                        </span>
                    </div>
                </div>

                <!-- 3. Total Pengguna Sistem -->
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-2xl shrink-0">
                        <i class="ri-team-line"></i>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 font-medium block">Pengguna Sistem</span>
                        <h3 class="text-2xl font-black text-gray-800 leading-tight">
                            {{ props.super_stats?.total_users || 0 }}
                        </h3>
                        <span class="text-[11px] text-purple-600 font-semibold mt-0.5 block">
                            Pusat & Seluruh Cabang
                        </span>
                    </div>
                </div>

                <!-- 4. Supir Aktif Beroperasi -->
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl shrink-0">
                        <i class="ri-steering-2-line"></i>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 font-medium block">Supir Terdaftar</span>
                        <h3 class="text-2xl font-black text-gray-800 leading-tight">
                            {{ props.super_stats?.active_drivers || 0 }}
                        </h3>
                        <span class="text-[11px] text-amber-700 font-semibold mt-0.5 block">
                            Armada Pickup & Lintas
                        </span>
                    </div>
                </div>
            </div>

            <!-- Grid 2 Kolom: Kinerja Ekspedisi & Grafik Pengiriman -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Kiri (2 Kolom): Tabel Ringkasan Ekspedisi Mitra -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-gray-800">Performa Ekspedisi Mitra</h2>
                            <p class="text-xs text-gray-500">Volume resi, jumlah personel, dan gudang per ekspedisi.</p>
                        </div>
                        <Link :href="route('expeditions.index')" class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                            Kelola Semua <i class="ri-arrow-right-s-line"></i>
                        </Link>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-xs font-bold text-gray-500 uppercase">
                                    <th class="p-3">Ekspedisi</th>
                                    <th class="p-3">Gudang</th>
                                    <th class="p-3">Staf</th>
                                    <th class="p-3">Resi Bulan Ini</th>
                                    <th class="p-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="exp in props.expeditions_summary" :key="exp.id" class="hover:bg-gray-50/50 transition">
                                    <td class="p-3 font-semibold text-gray-800">
                                        <div class="flex items-center gap-2.5">
                                            <div 
                                                class="w-7 h-7 rounded-lg text-white flex items-center justify-center font-bold text-xs"
                                                :style="{ backgroundColor: exp.theme_color }"
                                            >
                                                {{ exp.receipt_code }}
                                            </div>
                                            <div>
                                                <span class="block leading-tight">{{ exp.name }}</span>
                                                <span class="text-[11px] font-normal text-gray-400 font-mono">{{ exp.domain_slug }}.com</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3 text-gray-600 font-medium">{{ exp.warehouses_count }} Gudang</td>
                                    <td class="p-3 text-gray-600 font-medium">{{ exp.users_count }} Akun</td>
                                    <td class="p-3 font-bold text-gray-800">{{ exp.shipments_month }} Resi</td>
                                    <td class="p-3">
                                        <span 
                                            class="px-2 py-0.5 rounded-full text-[11px] font-bold"
                                            :class="exp.is_active ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-red-50 text-red-600 border border-red-200'"
                                        >
                                            {{ exp.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!props.expeditions_summary || props.expeditions_summary.length === 0">
                                    <td colspan="5" class="p-8 text-center text-gray-400">Belum ada ekspedisi terdaftar.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Kanan (1 Kolom): Feed Log Aktivitas & Quick Actions -->
                <div class="space-y-6">
                    <!-- Grafik Tren -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-3">
                        <h3 class="font-bold text-gray-800 text-base">Tren Pengiriman 7 Hari</h3>
                        <p class="text-xs text-gray-500">Total akumulasi pengiriman di seluruh platform.</p>
                        <div class="h-44 w-full pt-2">
                            <Bar :data="chartData" :options="chartOptions" />
                        </div>
                    </div>

                    <!-- Aktivitas Ekosistem Terbaru -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-3">
                        <h3 class="font-bold text-gray-800 text-base">Aktivitas Ekosistem</h3>
                        <div class="space-y-3 pt-1">
                            <div 
                                v-for="act in props.recent_platform_activities" 
                                :key="act.id" 
                                class="flex items-start gap-3 p-2 rounded-xl hover:bg-gray-50 transition"
                            >
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5" :class="[act.bg, act.color]">
                                    <i :class="act.icon"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-gray-800 truncate">{{ act.title }}</p>
                                    <div class="flex items-center gap-1.5 text-[11px] text-gray-400 mt-0.5">
                                        <span>{{ act.subtitle }}</span>
                                        <span>•</span>
                                        <span>{{ act.time }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!props.recent_platform_activities || props.recent_platform_activities.length === 0" class="text-center text-xs text-gray-400 py-4">
                                Belum ada aktivitas tercatat.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ============================================================== -->
        <!-- VIEW 2: TENANT / EKSPEDISI OPERATIONAL DASHBOARD                -->
        <!-- ============================================================== -->
        <div v-else class="py-6 flex flex-col">
            
            <SwipeableTabs v-model="activeTab" :tabs="dashboardTabs">
                
                <!-- TAB 1: OPERASIONAL -->
                <template #operasional>
                    <div class="space-y-6">
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 md:gap-5">
                            <!-- Kartu: Menunggu Pickup -->
                            <div class="bg-white p-4 md:p-5 rounded-2xl md:rounded-[24px] border border-gray-100 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] flex flex-col justify-between aspect-square hover:shadow-[0_8px_30px_-4px_rgba(0,0,0,0.1)] transition-all duration-300">
                                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xl md:text-2xl mb-4">
                                    <i class="ri-car-fill"></i>
                                </div>
                                <div>
                                    <p class="text-3xl md:text-4xl font-semibold text-gray-900 tracking-tight mb-1">{{ props.stats.menunggu_pickup }}</p>
                                    <p class="text-xs md:text-sm text-gray-500 font-medium">Menunggu Pickup</p>
                                </div>
                            </div>

                            <!-- Kartu: Paket di Gudang -->
                            <div class="bg-white p-4 md:p-5 rounded-2xl md:rounded-[24px] border border-gray-100 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] flex flex-col justify-between aspect-square hover:shadow-[0_8px_30px_-4px_rgba(0,0,0,0.1)] transition-all duration-300">
                                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center text-xl md:text-2xl mb-4">
                                    <i class="ri-box-3-fill"></i>
                                </div>
                                <div>
                                    <p class="text-3xl md:text-4xl font-semibold text-gray-900 tracking-tight mb-1">{{ props.stats.di_gudang }}</p>
                                    <p class="text-xs md:text-sm text-gray-500 font-medium">Di Gudang</p>
                                </div>
                            </div>
                            
                            <!-- Kartu: Dalam Perjalanan -->
                            <div class="bg-white p-4 md:p-5 rounded-2xl md:rounded-[24px] border border-gray-100 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] flex flex-col justify-between aspect-square hover:shadow-[0_8px_30px_-4px_rgba(0,0,0,0.1)] transition-all duration-300">
                                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-primary/20 text-primary flex items-center justify-center text-xl md:text-2xl mb-4">
                                    <i class="ri-truck-fill"></i>
                                </div>
                                <div>
                                    <p class="text-3xl md:text-4xl font-semibold text-gray-900 tracking-tight mb-1">{{ props.stats.dalam_perjalanan }}</p>
                                    <p class="text-xs md:text-sm text-gray-500 font-medium">Dalam Perjalanan</p>
                                </div>
                            </div>
                            
                            <!-- Kartu: Terkirim -->
                            <div class="bg-white p-4 md:p-5 rounded-2xl md:rounded-[24px] border border-gray-100 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] flex flex-col justify-between aspect-square hover:shadow-[0_8px_30px_-4px_rgba(0,0,0,0.1)] transition-all duration-300">
                                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-green-50 text-green-500 flex items-center justify-center text-xl md:text-2xl mb-4">
                                    <i class="ri-checkbox-circle-fill"></i>
                                </div>
                                <div>
                                    <p class="text-3xl md:text-4xl font-semibold text-gray-900 tracking-tight mb-1">{{ props.stats.terkirim_bulan_ini }}</p>
                                    <p class="text-xs md:text-sm text-gray-500 font-medium">Terkirim (Bulan Ini)</p>
                                </div>
                            </div>
                            
                            <!-- Kartu: Paket Bermasalah (Retur/Nyasar) -->
                            <div class="col-span-2 md:col-span-1 bg-white p-4 md:p-5 rounded-2xl md:rounded-[24px] border border-red-100 shadow-[0_4px_20px_-4px_rgba(239,68,68,0.1)] flex flex-col justify-between aspect-auto md:aspect-square hover:shadow-[0_8px_30px_-4px_rgba(239,68,68,0.2)] transition-all duration-300">
                                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-xl md:text-2xl mb-4">
                                    <i class="ri-error-warning-fill"></i>
                                </div>
                                <div>
                                    <p class="text-3xl md:text-4xl font-semibold text-red-600 tracking-tight mb-1">{{ props.stats.paket_bermasalah }}</p>
                                    <p class="text-xs md:text-sm text-red-500 font-medium">Kendala / Retur</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </template>



                <!-- TAB 2: KEUANGAN -->
                <template #keuangan>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-5">
                            <!-- Kartu Omset -->
                            <div class="bg-white p-5 md:p-6 rounded-2xl md:rounded-[24px] border border-gray-100 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] hover:shadow-[0_8px_30px_-4px_rgba(0,0,0,0.1)] transition-all duration-300 flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-500 font-medium mb-1">Estimasi Omset (Bulan Ini)</p>
                                    <p class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">{{ formatRupiah(props.stats.omset_bulan_ini) }}</p>
                                </div>
                                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-2xl shrink-0">
                                    <i class="ri-wallet-3-fill"></i>
                                </div>
                            </div>

                            <!-- Kartu Setoran COD -->
                            <div class="bg-gradient-to-br from-gray-900 to-gray-800 p-5 md:p-6 rounded-2xl md:rounded-[24px] shadow-[0_8px_30px_-4px_rgba(0,0,0,0.2)] flex items-center justify-between hover:shadow-[0_12px_40px_-4px_rgba(0,0,0,0.3)] transition-all duration-300 relative overflow-hidden text-white">
                                <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                                <div class="z-10">
                                    <p class="text-sm text-gray-300 font-medium mb-1">Uang COD di Supir</p>
                                    <p class="text-2xl md:text-3xl font-bold tracking-tight">{{ formatRupiah(props.stats.uang_cod_di_supir) }}</p>
                                </div>
                                <div class="w-12 h-12 rounded-full bg-white/20 backdrop-blur-md text-white flex items-center justify-center text-2xl shrink-0 z-10 border border-white/20">
                                    <i class="ri-money-dollar-circle-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- TAB 3: ANALISA -->
                <template #analisa>
                    <div class="space-y-6">
                        <!-- Grafik Tren Mingguan -->
                        <div class="bg-white p-5 md:p-6 rounded-2xl md:rounded-[24px] border border-gray-100 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] flex flex-col">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="font-bold text-gray-900 text-lg">Tren Pengiriman (7 Hari Terakhir)</h3>
                                <button class="text-gray-400 hover:text-gray-600 transition-colors">
                                    <i class="ri-more-2-fill text-xl"></i>
                                </button>
                            </div>
                            <!-- Area Chart -->
                            <div class="flex-grow w-full h-64 md:h-80">
                                <Bar :data="chartData" :options="chartOptions" />
                            </div>
                        </div>
                    </div>
                </template>

                <!-- TAB 4: AKTIVITAS TERKINI -->
                <template #aktivitas>
                    <div class="space-y-6">
                        <!-- Aktivitas Terkini (Timeline) -->
                        <div class="bg-white p-5 md:p-6 rounded-2xl md:rounded-[24px] border border-gray-100 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] flex flex-col min-h-[350px]">
                            <div class="flex justify-between items-center mb-6 shrink-0">
                                <h3 class="font-bold text-gray-900 text-lg">Aktivitas Terkini</h3>
                            </div>
                            
                            <div class="flex-grow pr-2">
                                <div class="space-y-6">
                                    <div v-for="(activity, index) in recentActivities" :key="activity.id" class="flex gap-4 relative">
                                        <!-- Garis Vertikal Timeline -->
                                        <div v-if="index !== recentActivities.length - 1" class="absolute left-5 top-10 bottom-[-24px] w-0.5 bg-gray-100 -ml-[1px]"></div>
                                        
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 z-10" :class="[activity.bg, activity.color]">
                                            <i :class="activity.icon" class="text-lg"></i>
                                        </div>
                                        <div class="pt-1">
                                            <p class="text-sm font-semibold text-gray-900">{{ activity.title }}</p>
                                            <p class="text-xs text-gray-500 mt-1">{{ activity.time }}</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-8 text-center pb-2">
                                    <button 
                                        @click="loadMore"
                                        :disabled="isLoadingMore"
                                        class="px-6 py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-700 text-sm font-semibold rounded-xl border border-gray-200 transition-all active:scale-95 disabled:opacity-70 flex items-center justify-center mx-auto gap-2"
                                    >
                                        <span v-if="isLoadingMore" class="w-4 h-4 border-2 border-gray-400 border-t-transparent rounded-full animate-spin"></span>
                                        <span>{{ isLoadingMore ? 'Memuat data...' : 'Muat Lebih Banyak' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

            </SwipeableTabs>

        </div>

    </AdminLayout>
</template>

<style scoped>
/* Custom Scrollbar untuk Activity Feed */
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #e5e7eb;
  border-radius: 10px;
}
.custom-scrollbar:hover::-webkit-scrollbar-thumb {
  background-color: #d1d5db;
}
</style>
