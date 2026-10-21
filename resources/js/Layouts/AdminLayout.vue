<script>
import { reactive } from 'vue';
const globalPageCache = reactive({});
let globalLastRouteIndex = -1; // Menyimpan indeks tab terakhir sebelum navigasi
let globalWasSwiped = false; // Flag untuk deteksi navigasi via swipe
</script>
<script setup>
import { ref, onMounted, onUnmounted, onBeforeUnmount, onUpdated, provide, computed } from 'vue';
import { Link, Head, router, usePage } from '@inertiajs/vue3';
import { useBiometricAuth } from '@/Composables/useBiometricAuth';

// Mendefinisikan properti (props) yang bisa diterima oleh Layout ini
// Misalnya: judul halaman yang akan tampil di Header
const props = defineProps({
    title: {
        type: String,
        default: 'Aplikasi Cargo'
    },
    hideHeader: {
        type: Boolean,
        default: false
    },
    noPaddingTop: {
        type: Boolean,
        default: false
    }
});

const page = usePage();

// State untuk mengatur apakah sidebar mengecil (collapsed) atau melebar
// Cek memori browser (localStorage) secara sinkron agar tidak ada jumping saat render pertama
const isCollapsed = ref(typeof localStorage !== 'undefined' ? localStorage.getItem('sidebarCollapsed') === 'true' : false);
const showMobileMenu = ref(false);

// Biometric & Kunci Layar HP
const {
    isSupported: isBiometricSupported,
    isRegistering: isBiometricRegistering,
    registeredUser: biometricRegisteredUser,
    checkSupport: checkBiometricSupport,
    loadRegisteredDevice: loadBiometricDevice,
    registerDevice: registerBiometricDevice,
    removeBiometric: removeBiometricDevice,
} = useBiometricAuth();

const showBiometricBanner = ref(false);
const biometricSuccessMsg = ref(null);
const isBiometricModalOpen = ref(false);

const handleActivateBiometric = async () => {
    try {
        await registerBiometricDevice(page.props.auth.user);
        showBiometricBanner.value = false;
        biometricSuccessMsg.value = 'Kunci layar HP berhasil diaktifkan! Anda bisa masuk instan berikutnya.';
        setTimeout(() => {
            biometricSuccessMsg.value = null;
        }, 4000);
    } catch (e) {
        if (e.message && !e.message.includes('dibatalkan')) {
            alert(e.message || 'Gagal mengaktifkan kunci layar.');
        }
    }
};

const handleRemoveBiometric = async () => {
    if (confirm('Lepas kunci layar dari perangkat ini?')) {
        await removeBiometricDevice();
        isBiometricModalOpen.value = false;
        biometricSuccessMsg.value = 'Kunci layar berhasil dinonaktifkan.';
        setTimeout(() => {
            biometricSuccessMsg.value = null;
        }, 3000);
    }
};

const dismissBiometricBanner = () => {
    showBiometricBanner.value = false;
    localStorage.setItem('biometric_banner_dismissed', 'true');
};

// Saat komponen pertama kali dimuat di browser
const pwaStatus = ref('Mendeteksi...');

onMounted(() => {
    // Deteksi apakah dijalankan dari instalan (PWA) atau browser biasa
    if (typeof window !== 'undefined') {
        const isInstalled = window.matchMedia('(display-mode: standalone)').matches || 
                            window.matchMedia('(display-mode: fullscreen)').matches || 
                            window.matchMedia('(display-mode: minimal-ui)').matches || 
                            window.navigator.standalone === true || 
                            document.referrer.includes('android-app://');
        
        pwaStatus.value = isInstalled ? 'Instalan' : 'Browser';

        // Cek dukungan kunci layar HP & tampilkan tawaran jika belum aktif
        checkBiometricSupport().then(async (supported) => {
            if (supported) {
                const registered = await loadBiometricDevice();
                const isDismissed = localStorage.getItem('biometric_banner_dismissed') === 'true';
                if (!registered && !isDismissed && page.props.auth?.user) {
                    setTimeout(() => {
                        showBiometricBanner.value = true;
                    }, 1800);
                }
            }
        });
    }
});

// Fungsi untuk melipat/melebarkan sidebar di desktop
const toggleCollapse = () => {
    isCollapsed.value = !isCollapsed.value;
    localStorage.setItem('sidebarCollapsed', isCollapsed.value);
};

// ==========================================
// SWIPE NAVIGATION LOGIC (Mobile Only)
// ==========================================
const touchStartX = ref(0);
const touchStartY = ref(0);
const swipeOffset = ref(0);
const isSwiping = ref(false);
const isDragging = ref(false);

const isSuperAdmin = computed(() => page.props.auth?.is_super_admin);
const userRole = computed(() => page.props.auth?.user?.role);
const isGudang = computed(() => userRole.value === 'GUDANG');
const isDriverPickup = computed(() => userRole.value === 'DRIVER_PICKUP');
const isDriverLintas = computed(() => userRole.value === 'DRIVER_LINTAS');
const isDriver = computed(() => isDriverPickup.value || isDriverLintas.value);

// Urutan halaman tab bawah dinamis sesuai role
const pages = computed(() => {
    if (isSuperAdmin.value) {
        return ['dashboard', 'expeditions.index', 'settings.index', 'dokumentasi'];
    }
    if (isGudang.value) {
        return ['inbound', 'manifest', 'dokumentasi'];
    }
    if (isDriverPickup.value) {
        return ['pickup', 'lokasi-supir'];
    }
    if (isDriverLintas.value) {
        return ['manifest', 'lokasi-supir'];
    }
    return ['dashboard', 'lokasi-supir', 'buat-resi', 'pickup', 'inbound', 'manifest', 'dokumentasi', 'settings.index'];
});

const handleTouchStart = (e) => {
    if (window.innerWidth >= 1024) return; // Hanya untuk HP & Tablet
    
    // Deteksi Edge Swipe (50px dari pinggir kiri atau kanan)
    const startX = e.touches[0].clientX;
    const edgeThreshold = 50; 
    
    // Jika BUKAN swipe dari pinggir layar, abaikan! Biarkan Tab yang merespon.
    if (startX > edgeThreshold && startX < window.innerWidth - edgeThreshold) {
        return;
    }

    touchStartX.value = startX;
    touchStartY.value = e.touches[0].clientY;
    isSwiping.value = true;
    isDragging.value = true;
};

const handleTouchMove = (e) => {
    if (!isSwiping.value) return;
    
    const currentX = e.touches[0].clientX;
    const currentY = e.touches[0].clientY;
    const diffX = currentX - touchStartX.value;
    const diffY = currentY - touchStartY.value;
    
    // Pastikan ini swipe horizontal (kiri/kanan), bukan scroll atas/bawah
    if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 10) {
        const currentRoute = route().current();
        const currentIndex = pages.value.indexOf(currentRoute);
        
        // Jangan biarkan swipe jika tidak ada halaman di sebelahnya (kasih efek karet / resistance)
        if (currentIndex === 0 && diffX > 0) {
            swipeOffset.value = diffX * 0.2; // Mentok kiri
        } else if (currentIndex === pages.value.length - 1 && diffX < 0) {
            swipeOffset.value = diffX * 0.2; // Mentok kanan
        } else {
            swipeOffset.value = diffX;
        }
    } else if (Math.abs(diffY) > Math.abs(diffX)) {
        // Jika ternyata user scroll ke bawah/atas, batalkan efek swipe
        isSwiping.value = false;
        swipeOffset.value = 0;
    }
};

const handleTouchEnd = () => {
    if (!isSwiping.value) return;
    isSwiping.value = false;
    isDragging.value = false; // Nyalakan kembali animasi CSS (transition-transform)
    
    const currentRoute = route().current();
    const currentIndex = pages.value.indexOf(currentRoute);
    const threshold = window.innerWidth * 0.25; // 25% dari lebar layar
    
    if (swipeOffset.value > threshold && currentIndex > 0) {
        // Pindah ke tab sebelumnya (kiri)
        swipeOffset.value = window.innerWidth;
        globalWasSwiped = true;
        setTimeout(() => {
            router.get(route(pages.value[currentIndex - 1]));
        }, 200);
    } else if (swipeOffset.value < -threshold && currentIndex < pages.value.length - 1) {
        // Pindah ke tab selanjutnya (kanan)
        swipeOffset.value = -window.innerWidth;
        globalWasSwiped = true;
        setTimeout(() => {
            router.get(route(pages.value[currentIndex + 1]));
        }, 200);
    } else {
        // Batal pindah, kembali ke tengah
        swipeOffset.value = 0;
    }
};

// State untuk lebar layar agar indikator sinkron
const screenWidth = ref(375); // Nilai default

// ==========================================
// INDIKATOR NAVIGASI (FAKE TRANSITION)
// ==========================================
const actualIndex = computed(() => pages.value.indexOf(route().current()));
// Jika baru saja di-swipe, langsung mulai dari posisi baru. Jika diklik, mulai dari posisi lama untuk animasi.
const displayIndex = ref((globalLastRouteIndex !== -1 && !globalWasSwiped) ? globalLastRouteIndex : (actualIndex.value !== -1 ? actualIndex.value : 0));
const enableIndicatorTransition = ref(false);

onMounted(() => {
    // ... logic bawaan onMounted ...
    if (typeof window !== 'undefined') {
        screenWidth.value = window.innerWidth;
        window.addEventListener('resize', () => {
            screenWidth.value = window.innerWidth;
        });
    }
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        isCollapsed.value = true;
    }

    // Listener untuk mendeteksi perubahan status fullscreen
    if (typeof document !== 'undefined') {
        document.addEventListener('fullscreenchange', () => {
            isFullscreen.value = !!document.fullscreenElement;
        });
    }

    // Jika posisi lama berbeda dengan sekarang, kita set posisi lama, 
    // lalu paksa geser ke posisi baru dengan animasi aktif (KECUALI JIKA DI-SWIPE!)
    if (globalWasSwiped) {
        displayIndex.value = actualIndex.value !== -1 ? actualIndex.value : 0;
        enableIndicatorTransition.value = true;
        globalWasSwiped = false; // Reset flag
    } else if (globalLastRouteIndex !== -1 && globalLastRouteIndex !== actualIndex.value) {
        setTimeout(() => {
            enableIndicatorTransition.value = true;
            displayIndex.value = actualIndex.value !== -1 ? actualIndex.value : 0;
        }, 50);
    } else {
        enableIndicatorTransition.value = true;
    }

    // PRECACHE BACKGROUND DOWNLOADER
    if (typeof window !== 'undefined' && navigator.onLine) {
        setTimeout(runPrecacheTasks, 2000); // Tunda 2 detik agar tidak ganggu loading awal
    }
});

// ==========================================
// PRECACHE INDICATOR LOGIC
// ==========================================
import axios from 'axios';
const precacheStatus = ref('idle'); // 'idle' | 'downloading' | 'done' | 'error'
const precacheProgress = ref(0);

const runPrecacheTasks = async () => {
    // Daftar semua menu utama yang mau disedot untuk offline
    const pagesToCache = [
        '/dashboard', 
        '/buat-resi', 
        '/pickup', 
        '/inbound', 
        '/manifest', 
        '/settings/company'
    ];
    
    // Cek apakah sedang berjalan di sesi ini (agar tidak ngulang kalau user pindah halaman)
    if (typeof window !== 'undefined' && window.isPrecaching) return;
    if (typeof window !== 'undefined') window.isPrecaching = true;

    // Cek apakah hari ini sudah precache agar tidak spam tiap hari
    const lastCacheDate = localStorage.getItem('last_precache_date');
    const today = new Date().toDateString();
    
    // Aktifkan kembali limit 1x sehari
    if (lastCacheDate === today) return; 

    precacheStatus.value = 'downloading';
    let downloaded = 0;
    
    try {
        for (const url of pagesToCache) {
            // 1. Sedot versi HTML (Berguna kalau user me-refresh halaman (F5) saat offline)
            await axios.get(url).catch(() => {});
            
            // 2. Sedot versi Inertia JSON (Berguna kalau user pindah halaman pakai klik menu)
            await axios.get(url, { 
                headers: { 'X-Inertia': 'true' } 
            }).catch(() => {});
            
            downloaded++;
            precacheProgress.value = Math.round((downloaded / pagesToCache.length) * 100);
        }
        precacheStatus.value = 'done';
        localStorage.setItem('last_precache_date', today);
        
        // Sembunyikan indikator setelah 3 detik selesai
        setTimeout(() => {
            precacheStatus.value = 'idle';
        }, 3000);
    } catch (e) {
        precacheStatus.value = 'error';
    }
};

// Hitung posisi indikator yang bereaksi terhadap swipe secara proporsional
const indicatorTransform = computed(() => {
    // Jika geser kiri (negatif), indikator bergerak ke kanan (positif)
    const dragPercentage = -(swipeOffset.value / screenWidth.value) * 100;
    return `translateX(calc(${displayIndex.value * 100}% + ${dragPercentage}%))`;
});

// ==========================================
// DOM CACHING LOGIC
// ==========================================
const savePageToCache = () => {
    // Pastikan tidak berjalan di server-side rendering
    if (typeof document !== 'undefined') {
        const content = document.getElementById('page-content');
        if (content) {
            globalPageCache[route().current()] = content.innerHTML;
        }
    }
};

// ==========================================
// FULLSCREEN LOGIC
// ==========================================
const isFullscreen = ref(false);

const ensureFullscreen = () => {
    // Hanya paksa fullscreen di perangkat mobile/tablet (lebar layar < 1024px)
    if (typeof window !== 'undefined' && window.innerWidth >= 1024) return;

    // Jika tidak sedang fullscreen, minta layar penuh
    if (!document.fullscreenElement && document.documentElement.requestFullscreen) {
        document.documentElement.requestFullscreen().catch((err) => {
            console.log("Gagal masuk fullscreen:", err.message);
        });
    }
};

const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
};

onUpdated(savePageToCache);
onBeforeUnmount(() => {
    globalLastRouteIndex = actualIndex.value; // Simpan indeks saat ini sebelum hancur
    savePageToCache();
});

// ==========================================
// DYNAMIC TRANSITION LOGIC
// ==========================================
const transitionProps = computed(() => {
    if (typeof window !== 'undefined' && window.innerWidth >= 1024) {
        // PC / Tablet Landscape: Fade memudar
        return {
            enterActiveClass: "transition-opacity duration-[600ms] ease-out",
            enterFromClass: "opacity-0",
            enterToClass: "opacity-100"
        };
    }
    
    // Mobile / Tablet Vertical: Tidak ada animasi (langsung muncul)
    return {
        enterActiveClass: "",
        enterFromClass: "",
        enterToClass: ""
    };
});

</script>

<template>
    <!-- Head untuk mengubah judul tab browser dinamis sesuai prop title -->
    <Head :title="title" />

    <div @click="ensureFullscreen" class="bg-gray-100/70 flex min-h-screen font-sans">
        
        <!-- Sidebar (Desktop Only) -->
        <aside 
            :class="[
                'hidden lg:flex fixed top-0 left-0 z-50 bg-white border-r border-gray-200 flex-col transition-all duration-300 h-screen',
                isCollapsed ? 'w-16' : 'w-48 xl:w-56'
            ]"
        >
            <!-- Header Sidebar (Logo & Tombol Collapse) -->
            <div class="h-16 flex items-center justify-between px-4 border-b border-gray-100 overflow-hidden">
                <div class="flex items-center min-w-0 flex-1 pr-2">
                    <i class="ri-truck-fill text-2xl text-primary shrink-0"></i>
                    <!-- Videotron Container -->
                    <div 
                        class="ml-2 overflow-hidden flex-1 transition-all duration-300"
                        :class="isCollapsed ? 'opacity-0 w-0' : 'opacity-100'"
                    >
                        <div class="premium-title">
                            {{ $page.props.auth?.is_super_admin ? ($page.props.app?.name || 'Agregator Cargo') : ($page.props.company?.name || 'Aplikasi Cargo') }}
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <!-- Tombol collapse (Desktop) -->
                    <button @click="toggleCollapse" class="flex items-center justify-center w-8 h-8 shrink-0 rounded-lg bg-primary/10 text-primary hover:bg-primary hover:text-white transition shadow-sm">
                        <i :class="isCollapsed ? 'ri-menu-unfold-line' : 'ri-menu-fold-line'" class="text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Menu Navigasi -->
            <nav class="flex-1 p-3 flex flex-col gap-1 overflow-y-auto overflow-x-hidden">
                
                <!-- Dashboard (Hanya jika BUKAN supir / BUKAN gudang) -->
                <Link 
                    v-if="!isDriver && !isGudang"
                    :href="route('dashboard')" 
                    :title="isCollapsed ? 'Dashboard' : ''"
                    class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                    :class="route().current('dashboard') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                >
                    <i class="ri-dashboard-fill text-lg shrink-0"></i>
                    <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Dashboard</span>
                </Link>

                <!-- KHUSUS SUPER ADMIN / PLATFORM OWNER -->
                <template v-if="$page.props.auth?.is_super_admin">
                    <Link 
                        :href="route('expeditions.index')" 
                        :title="isCollapsed ? 'Ekspedisi' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('expeditions.*') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-community-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Kelola Ekspedisi</span>
                    </Link>

                    <Link 
                        :href="route('settings.index')" 
                        :title="isCollapsed ? 'Pengaturan' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('settings.*') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-settings-3-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Pengaturan</span>
                    </Link>

                    <Link 
                        :href="route('dokumentasi')" 
                        :title="isCollapsed ? 'Dokumentasi' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('dokumentasi') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-book-3-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Dokumentasi</span>
                    </Link>
                </template>

                <!-- KHUSUS PETUGAS GUDANG (CHECKER) -->
                <template v-else-if="isGudang">
                    <Link 
                        :href="route('inbound')" 
                        :title="isCollapsed ? 'Inbound Gudang' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('inbound') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-qr-scan-2-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Inbound Gudang</span>
                    </Link>

                    <Link 
                        :href="route('manifest')" 
                        :title="isCollapsed ? 'Muat Manifest' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('manifest') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-truck-fill text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Muat Manifest Truk</span>
                    </Link>

                    <Link 
                        :href="route('dokumentasi')" 
                        :title="isCollapsed ? 'Dokumentasi' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('dokumentasi') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-book-3-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Panduan & SOP</span>
                    </Link>
                </template>

                <!-- KHUSUS SUPIR PICKUP -->
                <template v-else-if="isDriverPickup">
                    <a 
                        href="/supir/pickup" 
                        :title="isCollapsed ? 'Tugas Jemput' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition text-gray-600 hover:bg-primary/10 hover:text-primary"
                    >
                        <i class="ri-truck-line text-lg shrink-0 inline-block -scale-x-100"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Tugas Penjemputan</span>
                    </a>

                    <Link 
                        :href="route('lokasi-supir')" 
                        :title="isCollapsed ? 'Peta Lokasi' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('lokasi-supir') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-map-pin-user-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Peta GPS Armada</span>
                    </Link>
                </template>

                <!-- KHUSUS SUPIR LINTAS -->
                <template v-else-if="isDriverLintas">
                    <a 
                        href="/supir/lintas" 
                        :title="isCollapsed ? 'Surat Jalan' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition text-gray-600 hover:bg-primary/10 hover:text-primary"
                    >
                        <i class="ri-route-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Surat Jalan Lintas</span>
                    </a>

                    <Link 
                        :href="route('lokasi-supir')" 
                        :title="isCollapsed ? 'Peta Lokasi' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('lokasi-supir') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-map-pin-user-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Peta GPS Armada</span>
                    </Link>
                </template>

                <!-- KHUSUS OPERASIONAL EKSPEDISI (Admin Tenant / Staff) -->
                <template v-else>
                    <Link 
                        :href="route('lokasi-supir')" 
                        :title="isCollapsed ? 'Lokasi Supir' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('lokasi-supir') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-map-pin-user-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Lokasi Supir</span>
                    </Link>

                    <Link 
                        :href="route('buat-resi')" 
                        :title="isCollapsed ? 'Resi' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('buat-resi') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-add-box-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Resi</span>
                    </Link>

                    <Link 
                        :href="route('pickup')" 
                        :title="isCollapsed ? 'Pickup' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('pickup') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-truck-line text-lg shrink-0 inline-block -scale-x-100"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Pickup</span>
                    </Link>

                    <Link 
                        :href="route('inbound')" 
                        :title="isCollapsed ? 'Inbound' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('inbound') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-qr-scan-2-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Inbound</span>
                    </Link>

                    <Link 
                        :href="route('manifest')" 
                        :title="isCollapsed ? 'Pengiriman' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('manifest') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-truck-fill text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Pengiriman</span>
                    </Link>

                    <Link 
                        :href="route('settings.index')" 
                        :title="isCollapsed ? 'Pengaturan' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('settings.*') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-settings-3-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Pengaturan</span>
                    </Link>

                    <Link 
                        :href="route('dokumentasi')" 
                        :title="isCollapsed ? 'Dokumentasi' : ''"
                        class="flex items-center gap-3 px-3 py-3 rounded-lg font-medium transition"
                        :class="route().current('dokumentasi') ? 'bg-primary text-white shadow-md shadow-primary/20 pointer-events-none' : 'text-gray-600 hover:bg-primary/10 hover:text-primary'"
                    >
                        <i class="ri-book-3-line text-lg shrink-0"></i>
                        <span class="whitespace-nowrap transition-all duration-300" :class="isCollapsed ? 'opacity-0 w-0 hidden' : 'opacity-100 w-auto'">Dokumentasi</span>
                    </Link>
                </template>

            </nav>

            <!-- Profil Admin (Bawah Sidebar) -->
            <div class="p-3 border-t border-gray-100 bg-gray-100/70 mt-auto overflow-hidden flex justify-between items-center group">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <div class="w-9 h-9 rounded-full bg-white border border-gray-200 flex items-center justify-center text-primary font-bold shadow-sm shrink-0 text-sm">
                        {{ $page.props.auth.user.name.substring(0,2).toUpperCase() }}
                    </div>
                    <div class="transition-all duration-300 flex-1 min-w-0" :class="isCollapsed ? 'opacity-0 w-0' : 'opacity-100'">
                        <p class="text-sm font-bold text-dark truncate">{{ $page.props.auth.user.name }}</p>
                        <p class="text-xs text-gray-500 truncate">
                            {{ $page.props.auth.is_super_admin ? 'Super Admin (Agregator)' : ($page.props.auth.user.expedition?.name || 'Ekspedisi') }}
                        </p>
                    </div>
                </div>
                <!-- Tombol Kunci Layar / Biometrik -->
                <button
                    v-if="isBiometricSupported && !isCollapsed"
                    type="button"
                    @click="isBiometricModalOpen = true"
                    class="text-gray-400 hover:text-primary transition-colors p-2 rounded-lg hover:bg-primary/10 shrink-0 ml-auto"
                    :title="biometricRegisteredUser ? 'Kunci Layar HP Aktif' : 'Aktifkan Kunci Layar HP'"
                >
                    <i :class="biometricRegisteredUser ? 'ri-fingerprint-line text-emerald-600' : 'ri-fingerprint-line text-gray-400'" class="text-lg"></i>
                </button>

                <!-- Tombol Logout -->
                <Link 
                    v-if="!isCollapsed"
                    :href="route('logout')" 
                    method="post" 
                    as="button"
                    class="text-gray-400 hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-50 shrink-0 ml-1"
                    title="Log Out"
                >
                    <i class="ri-logout-box-r-line text-lg"></i>
                </Link>
            </div>
        </aside>

        <!-- Area Konten Utama -->
        <main 
            class="flex-1 flex flex-col min-h-screen relative w-full pb-16 lg:pb-0 transition-all duration-300 overflow-x-clip"
            :class="[
                !isDragging ? 'ease-out' : '',
                isCollapsed ? 'lg:pl-16' : 'lg:pl-48 xl:pl-56'
            ]"
            @touchstart="handleTouchStart"
            @touchmove="handleTouchMove"
            @touchend="handleTouchEnd"
            :style="{ transform: swipeOffset !== 0 ? `translateX(${swipeOffset}px)` : '' }"
        >
            
            <!-- Skeleton Halaman Sebelumnya (Kiri) -->
            <div v-if="pages.indexOf(route().current()) > 0" class="lg:hidden absolute top-0 bottom-0 w-full bg-gray-100/70 z-20 overflow-hidden transform" style="left: -100%;">
                <div v-if="globalPageCache[pages[pages.indexOf(route().current()) - 1]]" v-html="globalPageCache[pages[pages.indexOf(route().current()) - 1]]" class="flex-1 flex flex-col pointer-events-none"></div>
                <div v-else>
                    <div class="h-12 bg-white border-b border-gray-200 flex items-center px-4 shadow-sm">
                        <div class="w-6 h-6 bg-gray-200 rounded-full animate-pulse mr-3"></div>
                        <div class="w-32 h-4 bg-gray-200 rounded animate-pulse"></div>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="w-full h-24 bg-white rounded-xl border border-gray-100 animate-pulse shadow-sm"></div>
                        <div class="w-full h-64 bg-white rounded-xl border border-gray-100 animate-pulse shadow-sm"></div>
                    </div>
                </div>
            </div>

            <!-- Skeleton Halaman Selanjutnya (Kanan) -->
            <div v-if="pages.indexOf(route().current()) < pages.length - 1" class="lg:hidden absolute top-0 bottom-0 w-full bg-gray-100/70 z-20 overflow-hidden transform" style="right: -100%;">
                <div v-if="globalPageCache[pages[pages.indexOf(route().current()) + 1]]" v-html="globalPageCache[pages[pages.indexOf(route().current()) + 1]]" class="flex-1 flex flex-col pointer-events-none"></div>
                <div v-else>
                    <div class="h-12 bg-white border-b border-gray-200 flex items-center px-4 shadow-sm">
                        <div class="w-6 h-6 bg-gray-200 rounded-full animate-pulse mr-3"></div>
                        <div class="w-32 h-4 bg-gray-200 rounded animate-pulse"></div>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="w-full h-24 bg-white rounded-xl border border-gray-100 animate-pulse shadow-sm"></div>
                        <div class="w-full h-64 bg-white rounded-xl border border-gray-100 animate-pulse shadow-sm"></div>
                    </div>
                </div>
            </div>

            <!-- Page Content Wrapper (Diberi ID agar HTML-nya bisa di-cache) -->
            <Transition
                appear
                :enter-active-class="transitionProps.enterActiveClass"
                :enter-from-class="transitionProps.enterFromClass"
                :enter-to-class="transitionProps.enterToClass"
            >
                <div id="page-content" class="flex-1 flex flex-col w-full relative">
                    <!-- Topbar / Header -->
                    <header v-if="!hideHeader" class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm flex flex-col">
                        <div class="h-12 md:h-16 flex items-center justify-between px-3 md:px-8">
                    <div class="flex items-center min-w-0">
                        <!-- Judul Halaman Dinamis -->
                        <div class="flex items-center gap-1.5 md:gap-6 min-w-0">
                            <h2 class="hidden lg:block text-lg font-bold text-dark truncate leading-tight">{{ title }}</h2>
                            <slot name="header_tabs" />
                        </div>
                    </div>
                    <div class="flex items-center gap-2 md:gap-4 shrink-0">
                        <slot name="header_actions" />
                        
                        <!-- Tombol Fullscreen (Terutama untuk PC) -->
                        <button @click.stop="toggleFullscreen" class="hidden lg:block relative p-1 md:p-2 text-gray-500 hover:text-primary transition" title="Layar Penuh">
                            <i :class="isFullscreen ? 'ri-fullscreen-exit-line' : 'ri-fullscreen-line'" class="text-lg md:text-xl"></i>
                        </button>

                        <button class="relative p-1 md:p-2 text-gray-500 hover:text-primary transition">
                            <i class="ri-notification-3-line text-lg md:text-xl"></i>
                            <span class="absolute top-0.5 right-0.5 md:top-1 md:right-1 w-2 md:w-2.5 h-2 md:h-2.5 bg-red-500 rounded-full border-2 border-white"></span>
                        </button>
                    </div>
                    <!-- Menutup div.h-12 yang merupakan baris utama header -->
                    </div>
                </header>

                    <!-- Konten Khusus Halaman (Slot) akan disuntikkan ke sini -->
                    <div class="px-4 md:px-8 max-w-7xl mx-auto w-full mb-6 pb-4 md:pb-8" :class="noPaddingTop ? '' : 'pt-4 md:pt-8'">
                        <slot />
                    </div>
                </div>
            </Transition>
        </main>

        <!-- ==========================================
             BOTTOM NAVIGATION BAR (Khusus Mobile & Tablet)
             Ini akan membuat aplikasi terasa seperti NATIVE APP
             ========================================== -->
        <nav class="lg:hidden fixed bottom-0 left-0 w-full bg-white/95 backdrop-blur-md border-t border-gray-100 shadow-[0_-4px_16px_rgba(0,0,0,0.06)] z-50 pb-safe">
            <div class="flex justify-around items-center h-16 px-1 relative z-20">
                
                <!-- Beranda / Dashboard -->
                <Link 
                    :href="route('dashboard')" 
                    class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                    :class="route().current('dashboard') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                >
                    <!-- Indikator Pill tepat di atas menu ketika aktif -->
                    <span 
                        v-if="route().current('dashboard')"
                        class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                    ></span>
                    <i class="ri-dashboard-fill text-[22px] transition-transform duration-200" :class="route().current('dashboard') ? 'scale-110 drop-shadow-sm' : ''"></i>
                    <span v-if="route().current('dashboard')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Beranda</span>
                </Link>

                <!-- KHUSUS SUPER ADMIN DI MOBILE -->
                <template v-if="$page.props.auth?.is_super_admin">
                    <Link 
                        :href="route('expeditions.index')" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="route().current('expeditions.*') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span 
                            v-if="route().current('expeditions.*')"
                            class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                        ></span>
                        <i class="ri-community-line text-[22px] transition-transform duration-200" :class="route().current('expeditions.*') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span v-if="route().current('expeditions.*')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Ekspedisi</span>
                    </Link>

                    <Link 
                        :href="route('settings.index')" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="route().current('settings.*') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span 
                            v-if="route().current('settings.*')"
                            class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                        ></span>
                        <i class="ri-settings-3-line text-[22px] transition-transform duration-200" :class="route().current('settings.*') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span v-if="route().current('settings.*')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Pengaturan</span>
                    </Link>

                    <Link 
                        :href="route('dokumentasi')" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="route().current('dokumentasi') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span 
                            v-if="route().current('dokumentasi')"
                            class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                        ></span>
                        <i class="ri-book-3-line text-[22px] transition-transform duration-200" :class="route().current('dokumentasi') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span v-if="route().current('dokumentasi')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Panduan</span>
                    </Link>
                </template>

                <!-- KHUSUS PETUGAS GUDANG DI MOBILE -->
                <template v-else-if="isGudang">
                    <Link 
                        :href="route('inbound')" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="route().current('inbound') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span 
                            v-if="route().current('inbound')"
                            class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                        ></span>
                        <i class="ri-qr-scan-2-line text-[22px] transition-transform duration-200" :class="route().current('inbound') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span v-if="route().current('inbound')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Inbound</span>
                    </Link>

                    <Link 
                        :href="route('manifest')" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="route().current('manifest') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span 
                            v-if="route().current('manifest')"
                            class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                        ></span>
                        <i class="ri-truck-fill text-[22px] transition-transform duration-200" :class="route().current('manifest') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span v-if="route().current('manifest')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Manifest</span>
                    </Link>

                    <button 
                        @click="showMobileMenu = true"
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="showMobileMenu || route().current('dokumentasi') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span 
                            v-if="showMobileMenu || route().current('dokumentasi')"
                            class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                        ></span>
                        <i class="ri-menu-4-line text-[22px] transition-transform duration-200" :class="showMobileMenu || route().current('dokumentasi') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span v-if="showMobileMenu || route().current('dokumentasi')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Lainnya</span>
                    </button>
                </template>

                <!-- KHUSUS SUPIR PICKUP DI MOBILE -->
                <template v-else-if="isDriverPickup">
                    <a 
                        href="/supir/pickup" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1 text-gray-500 hover:text-primary"
                    >
                        <i class="ri-truck-line text-[22px] inline-block -scale-x-100 transition-transform duration-200"></i>
                        <span class="text-[10px] tracking-tight leading-none mt-1">Jemput</span>
                    </a>

                    <Link 
                        :href="route('lokasi-supir')" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="route().current('lokasi-supir') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <i class="ri-map-pin-user-line text-[22px] transition-transform duration-200" :class="route().current('lokasi-supir') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span class="text-[10px] tracking-tight leading-none mt-1">Peta GPS</span>
                    </Link>

                    <button 
                        @click="showMobileMenu = true"
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="showMobileMenu ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <i class="ri-menu-4-line text-[22px] transition-transform duration-200"></i>
                        <span class="text-[10px] tracking-tight leading-none mt-1">Akun</span>
                    </button>
                </template>

                <!-- KHUSUS SUPIR LINTAS DI MOBILE -->
                <template v-else-if="isDriverLintas">
                    <a 
                        href="/supir/lintas" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1 text-gray-500 hover:text-primary"
                    >
                        <i class="ri-route-line text-[22px] transition-transform duration-200"></i>
                        <span class="text-[10px] tracking-tight leading-none mt-1">Surat Jalan</span>
                    </a>

                    <Link 
                        :href="route('lokasi-supir')" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="route().current('lokasi-supir') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <i class="ri-map-pin-user-line text-[22px] transition-transform duration-200" :class="route().current('lokasi-supir') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span class="text-[10px] tracking-tight leading-none mt-1">Peta GPS</span>
                    </Link>

                    <button 
                        @click="showMobileMenu = true"
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="showMobileMenu ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <i class="ri-menu-4-line text-[22px] transition-transform duration-200"></i>
                        <span class="text-[10px] tracking-tight leading-none mt-1">Akun</span>
                    </button>
                </template>

                <!-- KHUSUS OPERASIONAL EKSPEDISI DI MOBILE (Admin Cabang / Kasir) -->
                <template v-else>
                    <Link 
                        :href="route('lokasi-supir')" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="route().current('lokasi-supir') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span 
                            v-if="route().current('lokasi-supir')"
                            class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                        ></span>
                        <i class="ri-map-pin-user-line text-[22px] transition-transform duration-200" :class="route().current('lokasi-supir') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span v-if="route().current('lokasi-supir')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Peta</span>
                    </Link>

                    <Link 
                        :href="route('buat-resi')" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="route().current('buat-resi') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span 
                            v-if="route().current('buat-resi')"
                            class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                        ></span>
                        <i class="ri-add-box-line text-[22px] transition-transform duration-200" :class="route().current('buat-resi') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span v-if="route().current('buat-resi')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Buat Resi</span>
                    </Link>

                    <Link 
                        :href="route('pickup')" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="route().current('pickup') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span 
                            v-if="route().current('pickup')"
                            class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                        ></span>
                        <i class="ri-truck-line text-[22px] inline-block -scale-x-100 transition-transform duration-200" :class="route().current('pickup') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span v-if="route().current('pickup')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Jemput</span>
                    </Link>

                    <Link 
                        :href="route('inbound')" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="route().current('inbound') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span 
                            v-if="route().current('inbound')"
                            class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                        ></span>
                        <i class="ri-qr-scan-2-line text-[22px] transition-transform duration-200" :class="route().current('inbound') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span v-if="route().current('inbound')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Inbound</span>
                    </Link>

                    <Link 
                        :href="route('manifest')" 
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="route().current('manifest') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span 
                            v-if="route().current('manifest')"
                            class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                        ></span>
                        <i class="ri-truck-fill text-[22px] transition-transform duration-200" :class="route().current('manifest') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span v-if="route().current('manifest')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Pengiriman</span>
                    </Link>

                    <button 
                        @click="showMobileMenu = true"
                        class="flex flex-col items-center justify-center w-full h-full transition-all duration-200 relative py-1"
                        :class="showMobileMenu || route().current('dokumentasi') ? 'text-primary font-bold' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span 
                            v-if="showMobileMenu || route().current('dokumentasi')"
                            class="absolute top-0 left-1/2 -translate-x-1/2 w-7 h-1 bg-primary rounded-b-full shadow-[0_2px_4px_rgba(37,99,235,0.4)]"
                        ></span>
                        <i class="ri-menu-4-line text-[22px] transition-transform duration-200" :class="showMobileMenu || route().current('dokumentasi') ? 'scale-110 drop-shadow-sm' : ''"></i>
                        <span v-if="showMobileMenu || route().current('dokumentasi')" class="text-[10px] tracking-tight leading-none mt-1 animate-fadeIn">Lainnya</span>
                    </button>
                </template>

            </div>
        </nav>

        <!-- Action Sheet Menu (Mobile & Tablet) -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showMobileMenu" class="lg:hidden fixed inset-0 z-[60] flex flex-col justify-end">
                <!-- Backdrop -->
                <div @click="showMobileMenu = false" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity"></div>
                
                <!-- Sheet -->
                <div class="bg-white rounded-t-3xl w-full relative z-10 p-5 pb-8 shadow-2xl transition-transform" 
                     :class="showMobileMenu ? 'translate-y-0' : 'translate-y-full'"
                     style="transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-lg text-dark">Menu Lainnya</h3>
                        <button @click="showMobileMenu = false" class="w-8 h-8 flex justify-center items-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-2">
                        <Link 
                            :href="route('dokumentasi')" 
                            class="w-full flex items-center gap-3 p-4 rounded-xl transition font-medium"
                            :class="route().current('dokumentasi') ? 'bg-primary/10 text-primary border border-primary/20' : 'bg-gray-50 text-gray-700 hover:bg-gray-100'"
                            @click="showMobileMenu = false"
                        >
                            <i class="ri-book-3-line text-xl"></i>
                            Dokumentasi
                        </Link>

                        <Link 
                            :href="route('settings.index')" 
                            class="w-full flex items-center gap-3 p-4 rounded-xl transition font-medium"
                            :class="route().current('settings.*') ? 'bg-primary/10 text-primary border border-primary/20' : 'bg-gray-50 text-gray-700 hover:bg-gray-100'"
                            @click="showMobileMenu = false"
                        >
                            <i class="ri-settings-3-line text-xl"></i>
                            Pengaturan
                        </Link>

                        <Link 
                            v-if="$page.props.auth?.is_super_admin"
                            :href="route('expeditions.index')" 
                            class="w-full flex items-center gap-3 p-4 rounded-xl transition font-medium"
                            :class="route().current('expeditions.*') ? 'bg-primary/10 text-primary border border-primary/20' : 'bg-gray-50 text-gray-700 hover:bg-gray-100'"
                            @click="showMobileMenu = false"
                        >
                            <i class="ri-community-line text-xl"></i>
                            Ekspedisi (Agregator)
                        </Link>
                        
                        <button
                            v-if="isBiometricSupported"
                            type="button"
                            @click="showMobileMenu = false; isBiometricModalOpen = true"
                            class="w-full flex items-center justify-between p-4 rounded-xl transition font-medium bg-gray-50 text-gray-700 hover:bg-gray-100"
                        >
                            <div class="flex items-center gap-3">
                                <i class="ri-fingerprint-line text-xl text-primary"></i>
                                <span>Kunci Layar HP (Biometrik)</span>
                            </div>
                            <span v-if="biometricRegisteredUser" class="text-xs bg-emerald-100 text-emerald-700 px-2.5 py-0.5 rounded-full font-bold">Aktif</span>
                            <span v-else class="text-xs bg-amber-100 text-amber-700 px-2.5 py-0.5 rounded-full font-bold">Belum Aktif</span>
                        </button>

                        <Link 
                            :href="route('logout')" 
                            method="post" 
                            as="button"
                            class="w-full flex items-center gap-3 p-4 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 transition font-medium border border-red-100 mt-4"
                            @click="showMobileMenu = false"
                        >
                            <i class="ri-logout-box-r-line text-xl"></i>
                            Keluar
                        </Link>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Floating Precache Indicator -->
        <Transition
            enter-active-class="transition-transform transition-opacity duration-500 ease-out"
            enter-from-class="translate-y-10 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-transform transition-opacity duration-300 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-10 opacity-0"
        >
            <div v-if="precacheStatus !== 'idle'" class="fixed bottom-32 md:bottom-20 right-4 md:right-6 z-[70] flex items-center gap-3 bg-gray-900/90 backdrop-blur-md text-white px-4 py-3 rounded-2xl shadow-xl shadow-black/20 border border-white/10 pointer-events-none">
                <div v-if="precacheStatus === 'downloading'" class="relative flex items-center justify-center w-6 h-6">
                    <svg class="w-full h-full text-white/20" viewBox="0 0 36 36">
                        <path class="stroke-current" fill="none" stroke-width="3" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <svg class="w-full h-full text-primary absolute top-0 left-0 -rotate-90 origin-center transition-all duration-300" viewBox="0 0 36 36">
                        <path class="stroke-current" fill="none" stroke-width="3" :stroke-dasharray="`${precacheProgress}, 100`" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                </div>
                <i v-else-if="precacheStatus === 'done'" class="ri-checkbox-circle-fill text-green-400 text-xl animate-bounce"></i>
                <i v-else-if="precacheStatus === 'error'" class="ri-error-warning-fill text-red-400 text-xl"></i>

                <div class="flex flex-col">
                    <span class="text-xs font-bold">{{ precacheStatus === 'downloading' ? 'Menyiapkan Offline' : (precacheStatus === 'done' ? 'Siap Offline!' : 'Gagal Download') }}</span>
                    <span v-if="precacheStatus === 'downloading'" class="text-[10px] text-gray-300">{{ precacheProgress }}% Selesai</span>
                </div>
            </div>
        </Transition>

        <!-- PWA Status Badge -->
        <div class="fixed bottom-24 right-4 lg:bottom-8 lg:right-8 z-[100] bg-black/80 backdrop-blur-sm text-white px-3 py-1.5 rounded-full text-[10px] md:text-xs font-bold shadow-lg flex items-center gap-1.5 border border-white/10 pointer-events-none transition-all">
            <i :class="pwaStatus === 'Instalan' ? 'ri-smartphone-line text-green-400' : 'ri-global-line text-blue-400'"></i>
            <span class="tracking-wide uppercase">{{ pwaStatus }}</span>
        </div>

        <!-- Toast Sukses Biometrik -->
        <Transition
            enter-active-class="transition-transform transition-opacity duration-300 ease-out"
            enter-from-class="-translate-y-6 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-transform transition-opacity duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-6 opacity-0"
        >
            <div v-if="biometricSuccessMsg" class="fixed top-4 left-1/2 -translate-x-1/2 z-[110] bg-emerald-600 text-white px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3 max-w-sm w-[90%] pointer-events-auto">
                <i class="ri-checkbox-circle-fill text-2xl shrink-0"></i>
                <p class="text-xs font-semibold leading-relaxed">{{ biometricSuccessMsg }}</p>
            </div>
        </Transition>

        <!-- Floating Prompt: Aktifkan Kunci Layar HP -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="translate-y-12 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-12 opacity-0 scale-95"
        >
            <div 
                v-if="showBiometricBanner && isBiometricSupported && !biometricRegisteredUser" 
                class="fixed bottom-20 left-4 right-4 md:left-auto md:right-6 md:w-96 z-[95] bg-gradient-to-br from-gray-900 to-gray-800 text-white p-4 rounded-2xl shadow-2xl border border-white/10"
            >
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary/20 border border-primary/40 flex items-center justify-center shrink-0 text-primary">
                        <i class="ri-fingerprint-line text-2xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-sm text-white">Login Kunci Layar HP</h4>
                            <button @click="dismissBiometricBanner" class="text-gray-400 hover:text-white -mr-1 -mt-1 p-1">
                                <i class="ri-close-line text-lg"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-300 mt-1 leading-relaxed">
                            Buka aplikasi lebih cepat tanpa ketik sandi dengan Sidik Jari, Wajah, atau PIN/Pola HP Anda.
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <button
                                @click="handleActivateBiometric"
                                :disabled="isBiometricRegistering"
                                class="flex-1 bg-primary hover:bg-primary/90 text-white text-xs font-bold py-2 px-3 rounded-lg flex items-center justify-center gap-1.5 transition disabled:opacity-50"
                            >
                                <i v-if="isBiometricRegistering" class="ri-loader-4-line animate-spin"></i>
                                <i v-else class="ri-shield-keyhole-line"></i>
                                <span>{{ isBiometricRegistering ? 'Memproses...' : 'Aktifkan Sekarang' }}</span>
                            </button>
                            <button
                                @click="dismissBiometricBanner"
                                class="text-xs text-gray-400 hover:text-white py-2 px-3 rounded-lg hover:bg-white/5 transition"
                            >
                                Nanti
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Modal Pengaturan Kunci Layar & Biometrik -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isBiometricModalOpen" class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-gray-100 relative">
                    <button 
                        @click="isBiometricModalOpen = false" 
                        class="absolute top-4 right-4 w-8 h-8 rounded-full bg-gray-100 text-gray-400 hover:text-gray-700 hover:bg-gray-200 flex items-center justify-center transition"
                    >
                        <i class="ri-close-line text-xl"></i>
                    </button>

                    <div class="text-center">
                        <div class="w-16 h-16 rounded-2xl bg-primary/10 border border-primary/20 text-primary mx-auto flex items-center justify-center mb-3">
                            <i class="ri-fingerprint-line text-4xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Kunci Layar HP & Biometrik</h3>
                        <p class="text-xs text-gray-500 mt-1">
                            Gunakan Sidik Jari, Face Unlock, atau Pola/PIN layar HP untuk masuk tanpa repot mengetik password.
                        </p>
                    </div>

                    <div class="mt-5 p-4 rounded-2xl bg-gray-50 border border-gray-100 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-500">Status di Perangkat ini:</span>
                            <span v-if="biometricRegisteredUser" class="font-bold text-emerald-600 flex items-center gap-1">
                                <i class="ri-checkbox-circle-fill"></i> Terhubung
                            </span>
                            <span v-else class="font-bold text-amber-600 flex items-center gap-1">
                                <i class="ri-close-circle-fill"></i> Belum Terhubung
                            </span>
                        </div>
                        <div v-if="biometricRegisteredUser" class="text-[11px] text-gray-500 pt-1 border-t border-gray-200/60">
                            Terdaftar untuk: <b class="text-gray-800">{{ biometricRegisteredUser.name }}</b> ({{ biometricRegisteredUser.username }})
                        </div>
                    </div>

                    <div class="mt-5 space-y-2">
                        <button
                            v-if="!biometricRegisteredUser"
                            @click="handleActivateBiometric"
                            :disabled="isBiometricRegistering"
                            class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-3 px-4 rounded-xl text-sm flex items-center justify-center gap-2 transition shadow-lg shadow-primary/20 disabled:opacity-50"
                        >
                            <i v-if="isBiometricRegistering" class="ri-loader-4-line animate-spin"></i>
                            <i v-else class="ri-shield-keyhole-line text-lg"></i>
                            <span>{{ isBiometricRegistering ? 'Menghubungkan...' : 'Aktifkan di Perangkat Ini' }}</span>
                        </button>

                        <button
                            v-else
                            @click="handleRemoveBiometric"
                            class="w-full bg-red-50 hover:bg-red-100 text-red-600 font-bold py-3 px-4 rounded-xl text-sm flex items-center justify-center gap-2 transition border border-red-200"
                        >
                            <i class="ri-delete-bin-line text-lg"></i>
                            <span>Hapus dari Perangkat Ini</span>
                        </button>

                        <button
                            @click="isBiometricModalOpen = false"
                            class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2.5 px-4 rounded-xl text-xs transition"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

    </div>
</template>

<style scoped>
.premium-title {
    display: inline-block;
    white-space: nowrap;
    font-size: 0.875rem;
    font-weight: 700;
    /* Animasi geser ping-pong & efek mengkilap */
    animation: ping-pong-scroll 10s ease-in-out infinite, shine 4s linear infinite;
    
    /* Gradient warna untuk efek mengkilap (Premium) */
    background: linear-gradient(110deg, #1f2937 30%, #3b82f6 50%, #1f2937 70%);
    background-size: 200% auto;
    color: transparent;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

@keyframes ping-pong-scroll {
    0%, 15% { transform: translateX(0); }
    /* min(0px, ...) mencegah teks bergeser ke kanan jika teksnya pendek */
    45%, 55% { transform: translateX(min(0px, calc(124px - 100%))); }
    85%, 100% { transform: translateX(0); }
}

@keyframes shine {
    to { background-position: -200% center; }
}
</style>
