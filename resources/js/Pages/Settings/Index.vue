<script setup>
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import SwipeableTabs from '@/Components/SwipeableTabs.vue';
import { useTabCache } from '@/Composables/useTabCache';

const props = defineProps({
    app: Object,
    company: Object,
    users: Array,
    expeditions: { type: Array, default: () => [] },
    roles: Array,
    warehouses: Array,
    github: { type: Object, default: () => ({ repo: '', branch: 'main', current_sha: null }) },
});

// -- TAB STATE --
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
const page = usePage();
const isSuperAdmin = computed(() => page.props.auth?.is_super_admin);

const topTabs = computed(() => {
    if (isSuperAdmin.value) {
        return [
            { id: 'tabApp', label: 'Informasi Aplikasi', shortLabel: 'Aplikasi', icon: 'ri-global-line' },
            { id: 'tabUsers', label: 'Pengguna Sistem', shortLabel: 'Pengguna', icon: 'ri-user-settings-line' },
            { id: 'tabUpdate', label: 'Pembaruan Sistem', shortLabel: 'Update', icon: 'ri-refresh-line' },
        ];
    }
    return [
        { id: 'tabCompany', label: 'Informasi Perusahaan', shortLabel: 'Perusahaan', icon: 'ri-building-4-line' },
        { id: 'tabUsers', label: 'Staf & Supir', shortLabel: 'Staf', icon: 'ri-team-line' },
        { id: 'tabWarehouses', label: 'Gudang Cabang', shortLabel: 'Gudang', icon: 'ri-store-2-line' },
        { id: 'tabUpdate', label: 'Pembaruan Sistem', shortLabel: 'Update', icon: 'ri-refresh-line' },
    ];
});

const defaultTab = computed(() => isSuperAdmin.value ? 'tabApp' : 'tabCompany');
const { activeTab: currentTopTab } = useTabCache('settings-top-tab', defaultTab.value);

// =======================
// APP (PLATFORM OWNER) LOGIC
// =======================
const appForm = useForm({
    name: props.app?.name || '',
    tagline: props.app?.tagline || '',
    contact: props.app?.contact || '',
    description: props.app?.description || '',
    theme: props.app?.theme || '#8B5A2B',
});

const submitApp = () => {
    appForm.post(route('settings.app.update'), {
        preserveScroll: true,
        onSuccess: () => {
            window.location.reload();
        },
    });
};

// =======================
// COMPANY (TENANT) LOGIC
// =======================
const companyForm = useForm({
    name: props.company.name || '',
    contact: props.company.contact || '',
    receipt_code: props.company.receipt_code || '',
    address: props.company.address || '',
    tagline: props.company.tagline || '',
    theme: props.company.theme || '#8B5A2B',
});

const companyInnerTabs = [
    { id: 'data', label: 'Data Perusahaan', shortLabel: 'Data', icon: 'ri-building-4-line' },
    { id: 'style', label: 'Style & Tema', shortLabel: 'Style', icon: 'ri-palette-line' },
];
const { activeTab: activeCompanyTab } = useTabCache('settings-company-inner-tab', 'data');

const submitCompany = () => {
    companyForm.post(route('settings.company.update'), {
        preserveScroll: true,
        onSuccess: () => {
            window.location.reload();
        },
    });
};

// =======================
// USERS LOGIC
// =======================
const isUserModalOpen = ref(false);
const editingUser = ref(null);
const selectedExpeditionFilter = ref('ALL');

const availableRoles = computed(() => {
    if (isSuperAdmin.value) {
        return [
            { id: 'SUPER_ADMIN', name: 'Super Admin (Agregator Platform)', desc: 'Akses penuh seluruh sistem & ekspedisi' },
            { id: 'ADMIN', name: 'Admin Ekspedisi', desc: 'Kelola operasional ekspedisi tertentu' },
            { id: 'DRIVER_PICKUP', name: 'Supir Pickup (Dalam Kota)', desc: 'Ambil & jemput koli dari pengrajin/toko' },
            { id: 'DRIVER_LINTAS', name: 'Supir Lintas Kota', desc: 'Bawa armada antar gudang' },
            { id: 'GUDANG', name: 'Staff Gudang', desc: 'Inbound, muat manifest & verifikasi koli' },
        ];
    }
    return [
        { id: 'ADMIN', name: 'Admin Ekspedisi', desc: 'Kelola operasional ekspedisi' },
        { id: 'DRIVER_PICKUP', name: 'Supir Pickup (Dalam Kota)', desc: 'Ambil & jemput koli dari pengrajin/toko' },
        { id: 'DRIVER_LINTAS', name: 'Supir Lintas Kota', desc: 'Bawa armada antar gudang' },
        { id: 'GUDANG', name: 'Staff Gudang', desc: 'Inbound, muat manifest & verifikasi koli' },
    ];
});

const filteredUsers = computed(() => {
    if (!isSuperAdmin.value || selectedExpeditionFilter.value === 'ALL') {
        return props.users;
    }
    if (selectedExpeditionFilter.value === 'PLATFORM') {
        return props.users.filter(u => !u.expedition_id);
    }
    return props.users.filter(u => u.expedition_id === Number(selectedExpeditionFilter.value));
});

const userForm = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    role: 'ADMIN',
    expedition_id: '',
    warehouse_id: '',
});

const openUserModal = (user = null) => {
    editingUser.value = user;
    if (user) {
        userForm.name = user.name;
        userForm.email = user.email;
        userForm.phone = user.phone || '';
        userForm.password = '';
        
        // Cek apakah akun Super Admin
        if (!user.expedition_id && user.role === 'ADMIN') {
            userForm.role = 'SUPER_ADMIN';
        } else {
            userForm.role = user.role || 'ADMIN';
        }

        userForm.expedition_id = user.expedition_id || '';
        userForm.warehouse_id = user.warehouse_id || '';
    } else {
        userForm.reset();
        userForm.role = isSuperAdmin.value ? 'ADMIN' : 'DRIVER_PICKUP';
        userForm.expedition_id = isSuperAdmin.value ? (props.expeditions[0]?.id || '') : '';
    }
    isUserModalOpen.value = true;
};

const closeUserModal = () => {
    isUserModalOpen.value = false;
    userForm.reset();
    userForm.clearErrors();
};

const saveUser = () => {
    if (editingUser.value) {
        userForm.put(route('settings.users.update', editingUser.value.id), {
            onSuccess: () => closeUserModal(),
        });
    } else {
        userForm.post(route('settings.users.store'), {
            onSuccess: () => closeUserModal(),
        });
    }
};

const deleteUser = (user) => {
    if (confirm(`Apakah Anda yakin ingin menghapus pengguna ${user.name}?`)) {
        router.delete(route('settings.users.destroy', user.id));
    }
};

// =======================
// WAREHOUSES LOGIC
// =======================
const isWarehouseModalOpen = ref(false);
const editingWarehouse = ref(null);

const warehouseForm = useForm({
    name: '',
    city: '',
    address: '',
    phone: '',
    maps_url: '',
});

const openWarehouseModal = (warehouse = null) => {
    editingWarehouse.value = warehouse;
    if (warehouse) {
        warehouseForm.name = warehouse.name;
        warehouseForm.city = warehouse.city;
        warehouseForm.address = warehouse.address || '';
        warehouseForm.phone = warehouse.phone || '';
        warehouseForm.maps_url = warehouse.maps_url || '';
    } else {
        warehouseForm.reset();
    }
    isWarehouseModalOpen.value = true;
};

const closeWarehouseModal = () => {
    isWarehouseModalOpen.value = false;
    warehouseForm.reset();
    warehouseForm.clearErrors();
};

const saveWarehouse = () => {
    if (editingWarehouse.value) {
        warehouseForm.put(route('settings.warehouses.update', editingWarehouse.value.id), {
            onSuccess: () => closeWarehouseModal(),
        });
    } else {
        warehouseForm.post(route('settings.warehouses.store'), {
            onSuccess: () => closeWarehouseModal(),
        });
    }
};

const deleteWarehouse = (warehouse) => {
    if (confirm(`Apakah Anda yakin ingin menghapus gudang ${warehouse.name}?`)) {
        router.delete(route('settings.warehouses.destroy', warehouse.id));
    }
};

// ==========================================
// PEMBARUAN SISTEM DARI GITHUB (MANUAL SYNC)
// ==========================================
const githubConfig = ref({
    repo: props.github?.repo || '',
    branch: props.github?.branch || 'main',
    token: '',
});

const isSavingConfig = ref(false);
const saveConfigSuccess = ref(false);

const isCheckingUpdate = ref(false);
const updateCheckResult = ref(null);
const checkError = ref(null);

const isUpdating = ref(false);
const currentStep = ref(0);
const updateProgress = ref(0);
const updateStatusText = ref('');
const updateError = ref(null);
const updateLogs = ref([]);

const saveGithubConfig = async () => {
    if (!githubConfig.value.repo) {
        alert('Silakan masukkan nama repository GitHub (contoh: username/jepara-cargo-app).');
        return;
    }
    isSavingConfig.value = true;
    saveConfigSuccess.value = false;
    try {
        await window.axios.post(route('settings.update.config'), githubConfig.value);
        saveConfigSuccess.value = true;
        setTimeout(() => { saveConfigSuccess.value = false; }, 3000);
    } catch (err) {
        alert(err.response?.data?.message || 'Gagal menyimpan konfigurasi.');
    } finally {
        isSavingConfig.value = false;
    }
};

const checkUpdate = async () => {
    if (!githubConfig.value.repo) {
        alert('Silakan masukkan nama repository GitHub terlebih dahulu.');
        return;
    }
    isCheckingUpdate.value = true;
    checkError.value = null;
    updateCheckResult.value = null;
    try {
        const response = await window.axios.post(route('settings.update.check'), {
            repo: githubConfig.value.repo,
            branch: githubConfig.value.branch,
        });
        updateCheckResult.value = response.data;
    } catch (err) {
        checkError.value = err.response?.data?.message || 'Gagal terhubung ke GitHub.';
    } finally {
        isCheckingUpdate.value = false;
    }
};

const runManualUpdate = async () => {
    if (!confirm('Apakah Anda yakin ingin memperbarui aplikasi dari GitHub sekarang?\n\nSistem akan otomatis mencadangkan database dan menimpa berkas aplikasi secara aman.')) {
        return;
    }

    isUpdating.value = true;
    updateError.value = null;
    updateLogs.value = [];

    try {
        // Fase 1: Backup
        currentStep.value = 1;
        updateProgress.value = 25;
        updateStatusText.value = 'Fase 1/4: Mengamankan database & file penting...';
        updateLogs.value.push(`[${new Date().toLocaleTimeString()}] Fase 1: Mencadangkan database...`);
        const resStep1 = await window.axios.post(route('settings.update.step'), { step: 1 });
        updateLogs.value.push(`[${new Date().toLocaleTimeString()}] ${resStep1.data.message}`);

        // Fase 2: Download ZIP dari GitHub
        currentStep.value = 2;
        updateProgress.value = 50;
        updateStatusText.value = `Fase 2/4: Mengunduh paket kode terbaru dari branch '${githubConfig.value.branch}'...`;
        updateLogs.value.push(`[${new Date().toLocaleTimeString()}] Fase 2: Mengunduh arsip ZIP dari GitHub...`);
        const resStep2 = await window.axios.post(route('settings.update.step'), {
            step: 2,
            repo: githubConfig.value.repo,
            branch: githubConfig.value.branch,
        });
        updateLogs.value.push(`[${new Date().toLocaleTimeString()}] ${resStep2.data.message}`);

        // Fase 3: Ekstrak & Timpa File Kode
        currentStep.value = 3;
        updateProgress.value = 75;
        updateStatusText.value = 'Fase 3/4: Mengekstrak paket dan menimpa berkas sistem (proteksi .env & database aman)...';
        updateLogs.value.push(`[${new Date().toLocaleTimeString()}] Fase 3: Mengekstrak file dan menimpa sistem...`);
        const resStep3 = await window.axios.post(route('settings.update.step'), { step: 3 });
        updateLogs.value.push(`[${new Date().toLocaleTimeString()}] ${resStep3.data.message}`);

        // Fase 4: Migrasi & Reset Cache
        currentStep.value = 4;
        updateProgress.value = 100;
        updateStatusText.value = 'Fase 4/4: Menjalankan migrasi database & mereset cache...';
        updateLogs.value.push(`[${new Date().toLocaleTimeString()}] Fase 4: Menjalankan artisan migrate & optimize:clear...`);
        const latestSha = updateCheckResult.value?.commit_sha || null;
        const resStep4 = await window.axios.post(route('settings.update.step'), {
            step: 4,
            commit_sha: latestSha,
        });
        updateLogs.value.push(`[${new Date().toLocaleTimeString()}] ${resStep4.data.message}`);
        updateStatusText.value = 'Pembaruan Berhasil 100%! Memuat ulang aplikasi...';

        setTimeout(() => {
            window.location.reload();
        }, 2500);

    } catch (err) {
        isUpdating.value = false;
        updateProgress.value = 0;
        currentStep.value = 0;
        updateError.value = err.response?.data?.message || err.message || 'Terjadi kesalahan pada proses pembaruan.';
        updateLogs.value.push(`[ERROR] ${updateError.value}`);
    }
};
</script>

<template>
    <AdminLayout title="Pengaturan">
        <template #header_tabs>
            <nav class="flex bg-gray-100/80 p-0.5 md:p-1 rounded-lg md:rounded-xl border border-gray-200/50 items-center shrink-0" aria-label="Tabs">
                <button 
                    v-for="tab in topTabs"
                    :key="tab.id"
                    @click="currentTopTab = tab.id" 
                    type="button"
                    class="px-2 md:px-4 py-1 md:py-1.5 rounded-md md:rounded-lg font-bold text-[10px] md:text-sm transition-all flex items-center gap-1"
                    :class="currentTopTab === tab.id ? 'bg-primary text-white shadow-md shadow-primary/30' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50 font-medium'"
                >
                    <i :class="tab.icon" class="text-xs md:text-base"></i>
                    <span class="hidden sm:inline">{{ tab.label }}</span>
                    <span class="sm:hidden">{{ tab.shortLabel }}</span>
                </button>
            </nav>
        </template>

        <!-- Flash Message -->
        <div v-if="$page.props.flash && $page.props.flash.success" class="max-w-5xl mx-auto bg-green-50 text-green-700 p-4 rounded-xl border border-green-100 flex items-start gap-3 mb-6">
            <i class="ri-checkbox-circle-fill text-xl shrink-0"></i>
            <div>
                <h4 class="font-bold">Berhasil</h4>
                <p class="text-sm mt-0.5">{{ $page.props.flash.success }}</p>
            </div>
        </div>

        <SwipeableTabs v-model="currentTopTab" :tabs="topTabs">
            <!-- TAB: APP (OWNER / PLATFORM UTAMA) -->
            <template v-if="isSuperAdmin" #tabApp>
                <div class="max-w-3xl mx-auto">
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <i class="ri-global-line text-xl"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-bold text-dark text-lg leading-tight">Informasi Aplikasi Utama</h3>
                                        <span class="px-2 py-0.5 text-[11px] font-bold bg-primary/10 text-primary rounded-md">Owner / Agregator</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-0.5">Atur nama aplikasi, tagline, dan tema warna utama yang tampil di Beranda/Welcome dan Halaman Login.</p>
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent="submitApp" class="p-5 md:p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-1.5 md:col-span-2">
                                    <label class="text-sm font-bold text-gray-700">Nama Aplikasi Utama <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                            <i class="ri-apps-2-line"></i>
                                        </span>
                                        <input v-model="appForm.name" type="text" class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-semibold" placeholder="Contoh: Jepara Cargo Network" required>
                                    </div>
                                    <p class="text-xs text-gray-500">Nama ini akan menjadi judul utama aplikasi di halaman Welcome dan Login.</p>
                                    <InputError class="mt-1" :message="appForm.errors.name" />
                                </div>

                                <div class="space-y-1.5 md:col-span-2">
                                    <label class="text-sm font-bold text-gray-700">Tagline / Slogan Utama</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                            <i class="ri-chat-quote-line"></i>
                                        </span>
                                        <input v-model="appForm.tagline" type="text" class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" placeholder="Contoh: Sistem Ekspedisi & Logistik Terpadu">
                                    </div>
                                    <InputError class="mt-1" :message="appForm.errors.tagline" />
                                </div>

                                <div class="space-y-1.5 md:col-span-2">
                                    <label class="text-sm font-bold text-gray-700">Kontak Resmi Platform (CS / WhatsApp)</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                            <i class="ri-whatsapp-line"></i>
                                        </span>
                                        <input v-model="appForm.contact" type="text" class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" placeholder="Contoh: 081234567890">
                                    </div>
                                    <InputError class="mt-1" :message="appForm.errors.contact" />
                                </div>

                                <div class="space-y-1.5 md:col-span-2">
                                    <label class="text-sm font-bold text-gray-700">Deskripsi Singkat Platform</label>
                                    <textarea v-model="appForm.description" rows="2" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" placeholder="Penjelasan singkat mengenai platform agregator..."></textarea>
                                    <InputError class="mt-1" :message="appForm.errors.description" />
                                </div>

                                <div class="space-y-1.5 md:col-span-2">
                                    <label class="text-sm font-bold text-gray-700">Warna Aksen Platform <span class="text-red-500">*</span></label>
                                    <div class="flex items-center gap-4">
                                        <input type="color" v-model="appForm.theme" class="w-12 h-12 rounded cursor-pointer border-0 p-0 bg-transparent">
                                        <input v-model="appForm.theme" type="text" class="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl font-mono text-sm" placeholder="#8B5A2B" required>
                                    </div>
                                    <InputError class="mt-1" :message="appForm.errors.theme" />
                                </div>
                            </div>

                            <div class="pt-6 border-t border-gray-100 flex justify-end">
                                <button type="submit" class="px-6 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 hover:shadow-lg hover:shadow-primary/20 transition-all flex items-center gap-2" :disabled="appForm.processing">
                                    <i class="ri-save-3-line"></i>
                                    {{ appForm.processing ? 'Menyimpan...' : 'Simpan Informasi Aplikasi' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>

            <!-- TAB: COMPANY (KHUSUS ADMIN EKSPEDISI) -->
            <template v-if="!isSuperAdmin" #tabCompany>
                <div class="max-w-3xl mx-auto">
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-gray-100 bg-gray-50 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 flex-wrap">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <i class="ri-building-4-fill text-xl"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-bold text-dark text-lg leading-tight">Informasi Perusahaan</h3>
                                        <span v-if="$page.props.auth?.is_super_admin" class="px-2 py-0.5 text-[11px] font-bold bg-slate-800 text-white rounded-md">
                                            Platform Agregator
                                        </span>
                                        <span v-else-if="$page.props.auth?.user?.expedition" class="px-2 py-0.5 text-[11px] font-bold bg-primary/10 text-primary rounded-md">
                                            {{ $page.props.auth.user.expedition.name }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ $page.props.auth?.is_super_admin ? 'Atur identitas default platform agregator pusat.' : 'Atur identitas, nama, resi, dan tema khusus ekspedisi Anda.' }}
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Internal Company Tabs -->
                            <nav class="flex bg-gray-200/50 p-1 rounded-xl items-center shrink-0" aria-label="Tabs">
                                <button 
                                    v-for="tab in companyInnerTabs"
                                    :key="tab.id"
                                    @click="activeCompanyTab = tab.id" 
                                    type="button"
                                    class="px-4 py-2 rounded-lg font-bold text-sm transition-all flex items-center gap-1.5"
                                    :class="activeCompanyTab === tab.id ? 'bg-white text-primary shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                >
                                    <i :class="tab.icon"></i>
                                    <span>{{ tab.label }}</span>
                                </button>
                            </nav>
                        </div>
                        
                        <form @submit.prevent="submitCompany" class="p-5 md:p-6 space-y-6">
                            <!-- Inner Tabs Content for Company -->
                            <div v-show="activeCompanyTab === 'data'" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                <div class="space-y-1.5 md:col-span-2">
                                    <label class="text-sm font-bold text-gray-700">Nama Perusahaan <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                            <i class="ri-store-2-line"></i>
                                        </span>
                                        <input v-model="companyForm.name" type="text" class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" placeholder="Contoh: Galang Furniture Cargo" required>
                                    </div>
                                    <InputError class="mt-1" :message="companyForm.errors.name" />
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-sm font-bold text-gray-700">Nomor Kontak / WA <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                            <i class="ri-whatsapp-line"></i>
                                        </span>
                                        <input v-model="companyForm.contact" type="text" class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" placeholder="Contoh: 08123456789" required>
                                    </div>
                                    <InputError class="mt-1" :message="companyForm.errors.contact" />
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-sm font-bold text-gray-700">Kode Resi 3 Huruf <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                            <i class="ri-barcode-box-line"></i>
                                        </span>
                                        <input v-model="companyForm.receipt_code" type="text" maxlength="4" class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm uppercase" placeholder="Contoh: GLG" required>
                                    </div>
                                    <p class="text-xs text-gray-500">Awalan untuk nomor resi (Contoh: GLG001).</p>
                                    <InputError class="mt-1" :message="companyForm.errors.receipt_code" />
                                </div>

                                <div class="space-y-1.5 md:col-span-2">
                                    <label class="text-sm font-bold text-gray-700">Slogan / Tagline (Opsional)</label>
                                    <input v-model="companyForm.tagline" type="text" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" placeholder="Contoh: Solusi Pengiriman Furniture Terbaik">
                                    <InputError class="mt-1" :message="companyForm.errors.tagline" />
                                </div>

                                <div class="space-y-1.5 md:col-span-2">
                                    <label class="text-sm font-bold text-gray-700">Alamat Kantor <span class="text-red-500">*</span></label>
                                    <textarea v-model="companyForm.address" rows="3" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm" placeholder="Masukkan alamat lengkap kantor operasional" required></textarea>
                                    <InputError class="mt-1" :message="companyForm.errors.address" />
                                </div>
                            </div>

                            <div v-show="activeCompanyTab === 'style'" class="mt-4">
                                <div class="space-y-3">
                                    <label class="text-sm font-bold text-gray-700">Warna Tema Aplikasi <span class="text-red-500">*</span></label>
                                    <p class="text-xs text-gray-500 mb-3">Pilih warna utama yang merepresentasikan brand perusahaan Anda. Warna ini akan diterapkan pada tombol dan elemen aplikasi setelah disimpan.</p>
                                    
                                    <div class="flex items-center gap-4">
                                        <input type="color" v-model="companyForm.theme" class="w-14 h-14 rounded cursor-pointer border-0 p-0 bg-transparent">
                                        
                                        <div class="flex-1">
                                            <input v-model="companyForm.theme" type="text" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-mono" placeholder="#8B5A2B" required>
                                        </div>
                                    </div>
                                    <InputError class="mt-1" :message="companyForm.errors.theme" />
                                </div>
                            </div>

                            <div class="pt-6 mt-6 border-t border-gray-100 flex justify-end">
                                <button type="submit" class="px-6 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 hover:shadow-lg hover:shadow-primary/20 transition-all flex items-center gap-2" :disabled="companyForm.processing">
                                    <i class="ri-save-3-line"></i>
                                    {{ companyForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>

            <!-- TAB: USERS -->
            <template #tabUsers>
                <div class="max-w-5xl mx-auto space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h2 class="text-2xl font-bold text-dark flex items-center gap-2">
                                <span>{{ isSuperAdmin ? 'Pengguna Sistem' : 'Staf & Supir Ekspedisi' }}</span>
                                <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-primary/10 text-primary">
                                    {{ filteredUsers.length }} Pengguna
                                </span>
                            </h2>
                            <p class="text-gray-500 text-sm mt-0.5">
                                {{ isSuperAdmin ? 'Kelola akun Super Admin, Admin Ekspedisi, dan Staf di semua cabang.' : 'Kelola akun operasional, supir, dan staf gudang internal Anda.' }}
                            </p>
                        </div>
                        
                        <div class="flex items-center gap-2 shrink-0">
                            <!-- Filter Ekspedisi (Hanya Super Admin) -->
                            <div v-if="isSuperAdmin && expeditions.length > 0" class="relative">
                                <select 
                                    v-model="selectedExpeditionFilter" 
                                    class="text-xs font-semibold bg-white border border-gray-200 rounded-xl px-3 py-2 pr-8 focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm"
                                >
                                    <option value="ALL">Semua Ekspedisi & Pusat</option>
                                    <option value="PLATFORM">Khusus Agregator Pusat</option>
                                    <option v-for="exp in expeditions" :key="exp.id" :value="exp.id">
                                        {{ exp.name }}
                                    </option>
                                </select>
                            </div>

                            <button @click="openUserModal()" class="bg-primary text-white px-4 py-2 rounded-xl font-bold hover:bg-primary/90 transition shadow-sm shadow-primary/20 flex items-center gap-2 text-sm">
                                <i class="ri-user-add-line"></i> Tambah Pengguna
                            </button>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-100 text-xs font-bold text-gray-500 uppercase tracking-wider">
                                        <th class="p-4">Nama Pengguna</th>
                                        <th class="p-4">Kontak / Email</th>
                                        <th class="p-4" v-if="isSuperAdmin">Ekspedisi</th>
                                        <th class="p-4">Peran (Role)</th>
                                        <th class="p-4">Penempatan</th>
                                        <th class="p-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50/60 transition">
                                        <td class="p-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0 shadow-sm">
                                                    {{ user.name.substring(0, 2).toUpperCase() }}
                                                </div>
                                                <div>
                                                    <span class="font-bold text-dark block leading-snug">{{ user.name }}</span>
                                                    <span v-if="user.phone" class="text-xs text-gray-400 flex items-center gap-1 mt-0.5">
                                                        <i class="ri-whatsapp-line text-green-500"></i> {{ user.phone }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-4 text-gray-600 font-mono text-xs">{{ user.email }}</td>
                                        
                                        <!-- Ekspedisi Badge (Hanya tampil untuk Super Admin) -->
                                        <td class="p-4" v-if="isSuperAdmin">
                                            <span v-if="!user.expedition_id" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-900 text-white shadow-sm">
                                                <i class="ri-shield-user-line text-[11px]"></i> Pusat
                                            </span>
                                            <span v-else class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                                <i class="ri-building-line text-[11px]"></i> {{ user.expedition ? user.expedition.name : 'Ekspedisi #' + user.expedition_id }}
                                            </span>
                                        </td>

                                        <!-- Role Badge -->
                                        <td class="p-4">
                                            <span v-if="!user.expedition_id && user.role === 'ADMIN'" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-purple-100 text-purple-700">
                                                Super Admin
                                            </span>
                                            <span v-else-if="user.role === 'ADMIN'" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-blue-100 text-blue-700">
                                                Admin Ekspedisi
                                            </span>
                                            <span v-else-if="user.role === 'DRIVER_PICKUP'" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-100 text-emerald-700">
                                                Supir Pickup
                                            </span>
                                            <span v-else-if="user.role === 'DRIVER_LINTAS'" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-amber-100 text-amber-800">
                                                Supir Lintas
                                            </span>
                                            <span v-else-if="user.role === 'GUDANG'" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-indigo-100 text-indigo-700">
                                                Staff Gudang
                                            </span>
                                            <span v-else class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-gray-100 text-gray-700">
                                                {{ user.role }}
                                            </span>
                                        </td>

                                        <td class="p-4 text-gray-600 text-xs">
                                            <span v-if="user.warehouse" class="inline-flex items-center gap-1 font-medium text-gray-700">
                                                <i class="ri-store-2-line text-gray-400"></i> {{ user.warehouse.name }}
                                            </span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        
                                        <td class="p-4 text-right space-x-1.5 whitespace-nowrap">
                                            <button @click="openUserModal(user)" class="w-8 h-8 rounded-lg bg-gray-100 text-gray-600 hover:bg-primary/10 hover:text-primary inline-flex items-center justify-center transition" title="Edit Pengguna">
                                                <i class="ri-edit-line"></i>
                                            </button>
                                            <button @click="deleteUser(user)" class="w-8 h-8 rounded-lg bg-gray-100 text-gray-600 hover:bg-red-50 hover:text-red-500 inline-flex items-center justify-center transition" title="Hapus Pengguna">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredUsers.length === 0">
                                        <td :colspan="isSuperAdmin ? 6 : 5" class="p-12 text-center text-gray-400">
                                            <i class="ri-user-search-line text-3xl block mb-2 opacity-50"></i>
                                            Belum ada pengguna yang sesuai dengan filter.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>

            <!-- TAB: WAREHOUSES (KHUSUS OPERASIONAL EKSPEDISI) -->
            <template v-if="!isSuperAdmin" #tabWarehouses>
                <div class="max-w-5xl mx-auto">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-dark">Gudang Cabang</h2>
                            <p class="text-gray-500 text-sm mt-1">Kelola daftar gudang drop atau cabang operasional.</p>
                        </div>
                        <button @click="openWarehouseModal()" class="bg-primary text-white px-4 py-2 rounded-xl font-bold hover:bg-primary/90 transition shadow-sm flex items-center gap-2">
                            <i class="ri-store-2-line"></i> Tambah Gudang
                        </button>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-100 text-sm text-gray-500">
                                        <th class="p-4 font-semibold">Nama Gudang</th>
                                        <th class="p-4 font-semibold">Kota / Wilayah</th>
                                        <th class="p-4 font-semibold">Kontak</th>
                                        <th class="p-4 font-semibold text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="w in warehouses" :key="w.id" class="hover:bg-gray-50/50 transition">
                                        <td class="p-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-lg shrink-0">
                                                    <i class="ri-store-2-fill"></i>
                                                </div>
                                                <div>
                                                    <span class="font-bold text-dark block">{{ w.name }}</span>
                                                    <span class="text-xs text-gray-500">{{ w.address || 'Alamat tidak tersedia' }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-4 text-gray-600 font-medium">{{ w.city }}</td>
                                        <td class="p-4 text-gray-600">{{ w.phone || '-' }}</td>
                                        <td class="p-4 text-right space-x-2">
                                            <button @click="openWarehouseModal(w)" class="w-8 h-8 rounded-lg bg-gray-100 text-gray-600 hover:bg-primary/10 hover:text-primary inline-flex items-center justify-center transition">
                                                <i class="ri-edit-line"></i>
                                            </button>
                                            <button @click="deleteWarehouse(w)" class="w-8 h-8 rounded-lg bg-gray-100 text-gray-600 hover:bg-red-50 hover:text-red-500 inline-flex items-center justify-center transition">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="warehouses.length === 0">
                                        <td colspan="4" class="p-8 text-center text-gray-500">
                                            Belum ada gudang terdaftar.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>

            <!-- TAB: PEMBARUAN SISTEM (GITHUB SYNC) -->
            <template #tabUpdate>
                <div class="max-w-4xl mx-auto space-y-6">
                    
                    <!-- Banner Pengantar -->
                    <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 text-white rounded-2xl p-6 shadow-sm border border-slate-700/60 relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 w-48 h-48 bg-primary/20 rounded-full blur-2xl pointer-events-none"></div>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
                            <div>
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/10 text-emerald-400 text-xs font-mono font-bold mb-2">
                                    <i class="ri-git-branch-line"></i> GitHub Auto-Sync & Hosting Deployer
                                </div>
                                <h3 class="font-black text-xl text-white tracking-tight">Pembaruan Sistem Terpusat</h3>
                                <p class="text-xs sm:text-sm text-gray-300 mt-1 max-w-xl leading-relaxed">
                                    Tarik pembaruan kode terbaru langsung dari GitHub ke server hosting dengan satu klik. Database operasional dan file upload dijamin aman.
                                </p>
                            </div>
                            <div class="shrink-0 self-start sm:self-center">
                                <span class="px-3 py-1.5 rounded-xl bg-white/10 text-gray-200 text-xs font-mono border border-white/10">
                                    SHA Aktif: <strong>{{ props.github?.current_sha ? props.github.current_sha.substring(0, 7) : 'Lokal / Dev' }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 1. Konfigurasi Repository -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-gray-100 text-dark flex items-center justify-center text-xl">
                                    <i class="ri-github-fill"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-dark text-base">Konfigurasi Sumber Kode GitHub</h4>
                                    <p class="text-xs text-gray-500">Tentukan repository dan branch target untuk sinkronisasi pembaruan.</p>
                                </div>
                            </div>
                            <span v-if="saveConfigSuccess" class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-lg flex items-center gap-1">
                                <i class="ri-check-line"></i> Tersimpan!
                            </span>
                        </div>

                        <form @submit.prevent="saveGithubConfig" class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
                            <div class="sm:col-span-5">
                                <label class="block text-xs font-bold text-gray-700 mb-1">Repository GitHub <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <i class="ri-git-repository-line"></i>
                                    </span>
                                    <input v-model="githubConfig.repo" type="text" placeholder="username/jepara-cargo-app" class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                                </div>
                            </div>

                            <div class="sm:col-span-3">
                                <label class="block text-xs font-bold text-gray-700 mb-1">Branch Target <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <i class="ri-git-branch-line"></i>
                                    </span>
                                    <input v-model="githubConfig.branch" type="text" placeholder="main" class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                                </div>
                            </div>

                            <div class="sm:col-span-4 flex gap-2">
                                <button type="submit" :disabled="isSavingConfig" class="flex-1 px-4 py-2 bg-gray-900 text-white font-bold rounded-xl text-xs hover:bg-black transition flex items-center justify-center gap-1.5 shadow-sm">
                                    <i class="ri-save-line"></i>
                                    {{ isSavingConfig ? 'Menyimpan...' : 'Simpan Sumber' }}
                                </button>
                                <button type="button" @click="checkUpdate" :disabled="isCheckingUpdate || isUpdating" class="px-4 py-2 bg-primary text-white font-bold rounded-xl text-xs hover:bg-primary/90 transition flex items-center justify-center gap-1.5 shadow-sm shadow-primary/20">
                                    <i :class="isCheckingUpdate ? 'ri-loader-4-line animate-spin' : 'ri-refresh-line'"></i>
                                    {{ isCheckingUpdate ? 'Mengecek...' : 'Cek Update' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- 2. Pesan Error Pengecekan -->
                    <div v-if="checkError" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-2xl flex items-start gap-3 text-xs">
                        <i class="ri-error-warning-fill text-lg shrink-0 mt-0.5"></i>
                        <div>
                            <strong class="font-bold">Gagal Terhubung ke GitHub:</strong>
                            <p class="mt-0.5">{{ checkError }}</p>
                        </div>
                    </div>

                    <!-- 3. Hasil Pengecekan Update -->
                    <div v-if="updateCheckResult" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6 space-y-4">
                        
                        <!-- JIKA SUDAH TERBARU -->
                        <div v-if="updateCheckResult.is_up_to_date" class="p-5 bg-emerald-50 rounded-2xl border border-emerald-100 flex items-start sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5">
                                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center text-xl shrink-0 shadow-sm">
                                    <i class="ri-checkbox-circle-fill"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-emerald-950 text-sm sm:text-base">Sistem Sudah Versi Terbaru!</h4>
                                    <p class="text-xs text-emerald-700 mt-0.5">
                                        Aplikasi di hosting sudah seirama dengan commit terakhir di branch <strong>{{ githubConfig.branch }}</strong> (<code class="font-mono">{{ updateCheckResult.short_sha }}</code>).
                                    </p>
                                </div>
                            </div>
                            <button @click="checkUpdate" class="text-xs font-bold text-emerald-800 bg-emerald-200/60 hover:bg-emerald-200 px-3 py-1.5 rounded-lg transition shrink-0">
                                Cek Ulang
                            </button>
                        </div>

                        <!-- JIKA TERDAPAT PEMBARUAN BARU -->
                        <div v-else class="space-y-4">
                            <div class="p-5 bg-amber-50 rounded-2xl border border-amber-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center text-xl shrink-0 shadow-sm">
                                        <i class="ri-download-cloud-2-fill"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-amber-950 text-base">Pembaruan Kode Ditemukan!</h4>
                                            <span class="px-2 py-0.5 rounded-md bg-amber-200 text-amber-900 font-mono text-[11px] font-bold">
                                                {{ updateCheckResult.short_sha }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                                            <strong>Pesan Commit:</strong> "{{ updateCheckResult.commit_message }}"
                                        </p>
                                        <div class="flex items-center gap-3 text-[11px] text-amber-700 mt-1.5">
                                            <span><i class="ri-user-line"></i> {{ updateCheckResult.author }}</span>
                                            <span>&bull;</span>
                                            <span><i class="ri-time-line"></i> {{ new Date(updateCheckResult.date).toLocaleString('id-ID') }}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <button
                                    type="button"
                                    @click="runManualUpdate"
                                    :disabled="isUpdating"
                                    class="px-5 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-black text-xs hover:from-emerald-700 hover:to-teal-700 transition shadow-md shadow-emerald-600/30 flex items-center justify-center gap-2 shrink-0"
                                >
                                    <i :class="isUpdating ? 'ri-loader-4-line animate-spin' : 'ri-rocket-line'" class="text-base"></i>
                                    {{ isUpdating ? 'Sedang Memperbarui...' : 'Perbarui Sekarang' }}
                                </button>
                            </div>

                            <!-- Proteksi Info -->
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/60 text-xs text-gray-600 flex items-start gap-3">
                                <i class="ri-shield-check-line text-emerald-600 text-base shrink-0 mt-0.5"></i>
                                <div class="space-y-1">
                                    <span class="font-bold text-dark block">Proteksi Keamanan Sistem:</span>
                                    <p>
                                        Proses pembaruan akan otomatis mencadangkan file database ke folder <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">storage/app/backups/</code>. Berkas <code class="bg-gray-200 px-1 py-0.5 rounded font-mono">.env</code> dan file upload foto tidak akan tertimpa.
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- 4. Progress Pembaruan Real-Time (Saat Update Berjalan) -->
                    <div v-if="isUpdating || updateLogs.length > 0" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-dark text-sm flex items-center gap-2">
                                <i :class="isUpdating ? 'ri-loader-4-line animate-spin text-primary' : 'ri-terminal-box-line text-gray-600'"></i>
                                Status Pembaruan Sistem (Tahap {{ currentStep }}/4)
                            </h4>
                            <span class="font-mono text-xs font-bold text-primary">{{ updateProgress }}%</span>
                        </div>

                        <!-- Progress Bar -->
                        <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden p-0.5">
                            <div 
                                class="h-full bg-gradient-to-r from-emerald-500 to-primary rounded-full transition-all duration-500"
                                :style="{ width: `${updateProgress}%` }"
                            ></div>
                        </div>

                        <p class="text-xs font-medium text-gray-600">
                            {{ updateStatusText }}
                        </p>

                        <!-- Error Box jika Gagal -->
                        <div v-if="updateError" class="p-3 bg-red-50 text-red-700 rounded-xl border border-red-200 text-xs flex items-center gap-2">
                            <i class="ri-close-circle-fill text-base shrink-0"></i>
                            <span>{{ updateError }}</span>
                        </div>

                        <!-- Log Console Terminal -->
                        <div class="bg-slate-900 rounded-xl p-4 font-mono text-[11px] text-emerald-400 space-y-1 max-h-48 overflow-y-auto">
                            <div v-for="(log, idx) in updateLogs" :key="idx" class="leading-relaxed">
                                &gt; {{ log }}
                            </div>
                        </div>
                    </div>

                </div>
            </template>
        </SwipeableTabs>

        <!-- Modal Form Pengguna -->
        <div v-if="isUserModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="closeUserModal"></div>
            
            <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl relative flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between shrink-0 bg-gray-50/50">
                    <div>
                        <h3 class="font-bold text-lg text-dark">{{ editingUser ? 'Edit Pengguna' : 'Tambah Pengguna Baru' }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Kelola data login, hak akses, dan unit ekspedisi.</p>
                    </div>
                    <button @click="closeUserModal" class="text-gray-400 hover:text-gray-600 w-8 h-8 rounded-lg flex items-center justify-center hover:bg-gray-100">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
                
                <form @submit.prevent="saveUser" class="overflow-y-auto p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input v-model="userForm.name" type="text" placeholder="Misal: Ahmad Fauzi" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm font-semibold" required>
                        <InputError class="mt-1" :message="userForm.errors.name" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                            <input v-model="userForm.email" type="email" placeholder="nama@ekspedisi.com" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm" required>
                            <InputError class="mt-1" :message="userForm.errors.email" />
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">No. WhatsApp / HP</label>
                            <input v-model="userForm.phone" type="text" placeholder="08xxxxxxxxxx" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm">
                            <InputError class="mt-1" :message="userForm.errors.phone" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">
                            Password
                            <span v-if="editingUser" class="text-gray-400 font-normal text-xs">(Kosongkan jika tidak diubah)</span>
                            <span v-else class="text-red-500">*</span>
                        </label>
                        <input v-model="userForm.password" type="password" placeholder="Minimal 8 karakter" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm" :required="!editingUser">
                        <InputError class="mt-1" :message="userForm.errors.password" />
                    </div>

                    <!-- Role -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Peran / Hak Akses <span class="text-red-500">*</span></label>
                        <select v-model="userForm.role" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm font-medium" required>
                            <option v-for="r in availableRoles" :key="r.id" :value="r.id">
                                {{ r.name }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ availableRoles.find(r => r.id === userForm.role)?.desc }}
                        </p>
                        <InputError class="mt-1" :message="userForm.errors.role" />
                    </div>

                    <!-- Ekspedisi Selection (Hanya untuk Super Admin jika role bukan SUPER_ADMIN) -->
                    <div v-if="isSuperAdmin && userForm.role !== 'SUPER_ADMIN'">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Pilih Ekspedisi <span class="text-red-500">*</span></label>
                        <select v-model="userForm.expedition_id" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm font-medium" required>
                            <option value="" disabled>Pilih Ekspedisi</option>
                            <option v-for="exp in expeditions" :key="exp.id" :value="exp.id">
                                {{ exp.name }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="userForm.errors.expedition_id" />
                    </div>

                    <!-- Penempatan Gudang (Khusus role Staff Gudang) -->
                    <div v-if="userForm.role === 'GUDANG'">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Penempatan Gudang <span class="text-red-500">*</span></label>
                        <select v-model="userForm.warehouse_id" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm font-medium" required>
                            <option value="" disabled>Pilih Gudang Cabang</option>
                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }} ({{ w.city }})</option>
                        </select>
                        <p v-if="warehouses.length === 0" class="text-xs text-orange-500 mt-1">
                            Belum ada gudang terdaftar. Tambahkan gudang terlebih dahulu.
                        </p>
                        <InputError class="mt-1" :message="userForm.errors.warehouse_id" />
                    </div>

                    <div class="pt-4 flex gap-3 border-t border-gray-100 mt-6">
                        <button type="button" @click="closeUserModal" class="flex-1 px-4 py-2.5 rounded-xl font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition text-sm">Batal</button>
                        <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl font-bold text-white bg-primary hover:bg-primary/90 transition text-sm shadow-md shadow-primary/20" :disabled="userForm.processing">
                            {{ userForm.processing ? 'Menyimpan...' : (editingUser ? 'Perbarui Pengguna' : 'Simpan Pengguna') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Form Gudang -->
        <div v-if="isWarehouseModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="closeWarehouseModal"></div>
            
            <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl relative flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
                    <h3 class="font-bold text-lg text-dark">{{ editingWarehouse ? 'Edit Gudang' : 'Tambah Gudang Baru' }}</h3>
                    <button @click="closeWarehouseModal" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
                
                <form @submit.prevent="saveWarehouse" class="overflow-y-auto p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Nama Gudang <span class="text-red-500">*</span></label>
                        <input v-model="warehouseForm.name" type="text" placeholder="Misal: Gudang Transit Jakarta" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                        <InputError class="mt-1" :message="warehouseForm.errors.name" />
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Kota <span class="text-red-500">*</span></label>
                        <input v-model="warehouseForm.city" type="text" placeholder="Misal: Jakarta Pusat" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                        <InputError class="mt-1" :message="warehouseForm.errors.city" />
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Alamat Lengkap</label>
                        <textarea v-model="warehouseForm.address" rows="3" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary"></textarea>
                        <InputError class="mt-1" :message="warehouseForm.errors.address" />
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">No. Telepon / PIC</label>
                        <input v-model="warehouseForm.phone" type="text" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        <InputError class="mt-1" :message="warehouseForm.errors.phone" />
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Link Google Maps <span class="text-xs text-gray-400 font-normal">(Opsional)</span></label>
                        <input v-model="warehouseForm.maps_url" type="url" placeholder="https://maps.google.com/..." class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        <InputError class="mt-1" :message="warehouseForm.errors.maps_url" />
                    </div>

                    <div class="pt-4 flex gap-3 border-t border-gray-100 mt-6">
                        <button type="button" @click="closeWarehouseModal" class="flex-1 px-4 py-2.5 rounded-xl font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition">Batal</button>
                        <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl font-bold text-white bg-primary hover:bg-primary/90 transition" :disabled="warehouseForm.processing">
                            {{ warehouseForm.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
