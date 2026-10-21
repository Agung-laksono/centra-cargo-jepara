<script setup>
import { ref, computed, onUnmounted } from 'vue';
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
    integration: { type: Object, default: () => null },
    backups: { type: Array, default: () => [] },
});

// -- TAB STATE --
import { usePage } from '@inertiajs/vue3';
const page = usePage();
const isSuperAdmin = computed(() => page.props.auth?.is_super_admin);

const topTabs = computed(() => {
    if (isSuperAdmin.value) {
        return [
            { id: 'tabApp', label: 'Informasi Aplikasi', shortLabel: 'Aplikasi', icon: 'ri-global-line' },
            { id: 'tabUsers', label: 'Pengguna Sistem', shortLabel: 'Pengguna', icon: 'ri-user-settings-line' },
            { id: 'tabIntegrasi', label: 'Integrasi Real-time', shortLabel: 'Integrasi', icon: 'ri-broadcast-line' },
            { id: 'tabBackup', label: 'Cadangan & Pemulihan', shortLabel: 'Backup', icon: 'ri-database-2-line' },
            { id: 'tabUpdate', label: 'Pembaruan Sistem', shortLabel: 'Update', icon: 'ri-refresh-line' },
        ];
    }
    return [
        { id: 'tabCompany', label: 'Informasi Perusahaan', shortLabel: 'Perusahaan', icon: 'ri-building-4-line' },
        { id: 'tabUsers', label: 'Staf & Supir', shortLabel: 'Staf', icon: 'ri-team-line' },
        { id: 'tabWarehouses', label: 'Gudang Cabang', shortLabel: 'Gudang', icon: 'ri-store-2-line' },
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
const isUpdateModalOpen = ref(false);
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
            token: githubConfig.value.token || null,
        });
        updateCheckResult.value = response.data;
    } catch (err) {
        checkError.value = err.response?.data?.message || 'Gagal terhubung ke GitHub.';
    } finally {
        isCheckingUpdate.value = false;
    }
};

// Pengunci Navigasi Browser & Tombol Back saat Pembaruan Berlangsung
const handleBeforeUnload = (e) => {
    if (isUpdating.value) {
        e.preventDefault();
        e.returnValue = 'Pembaruan sistem sedang berlangsung. Jangan tinggalkan halaman ini!';
        return e.returnValue;
    }
};

const handlePopState = () => {
    if (isUpdating.value) {
        window.history.pushState(null, '', window.location.href);
        alert('PERINGATAN KRITIS: Pembaruan sistem sedang berlangsung. Navigasi dan tombol back dikunci sementara demi keamanan data.');
    }
};

const lockBrowserNavigation = () => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    window.history.pushState(null, '', window.location.href);
    window.addEventListener('popstate', handlePopState);
};

const unlockBrowserNavigation = () => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    window.removeEventListener('popstate', handlePopState);
};

const closeUpdateModal = async () => {
    if (isUpdating.value && !updateError.value) return;
    isUpdateModalOpen.value = false;
    isUpdating.value = false;
    updateError.value = null;
    unlockBrowserNavigation();
    try {
        await window.axios.post(route('settings.update.emergency-up'));
    } catch (e) {
        // ignore
    }
};

onUnmounted(() => {
    unlockBrowserNavigation();
});

const runManualUpdate = async () => {
    if (!confirm('Apakah Anda yakin ingin memperbarui aplikasi dari GitHub sekarang?\n\nSistem akan otomatis mencadangkan database dan menimpa berkas aplikasi secara aman.')) {
        return;
    }

    isUpdating.value = true;
    isUpdateModalOpen.value = true;
    updateError.value = null;
    updateLogs.value = [];
    lockBrowserNavigation();

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
            token: githubConfig.value.token || null,
        });
        updateLogs.value.push(`[${new Date().toLocaleTimeString()}] ${resStep2.data.message}`);

        // Fase 3: Ekstrak & Timpa File Kode
        currentStep.value = 3;
        updateProgress.value = 75;
        updateStatusText.value = 'Fase 3/4: Mengekstrak paket dan menimpa berkas sistem (Mode Pemeliharaan aktif)...';
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
            unlockBrowserNavigation();
            window.location.reload();
        }, 2200);

    } catch (err) {
        isUpdating.value = false;
        unlockBrowserNavigation();
        updateError.value = err.response?.data?.message || err.message || 'Terjadi kesalahan pada proses pembaruan.';
        updateLogs.value.push(`[ERROR] ${updateError.value}`);
        try {
            await window.axios.post(route('settings.update.emergency-up'));
        } catch (e) {
            // ignore
        }
    }
};

// =======================
// OFFLINE CACHE MANAGEMENT (PWA)
// =======================
const isOfflineCacheEnabled = ref(
    typeof window !== 'undefined' ? localStorage.getItem('pwa_offline_cache_enabled') !== 'false' : true
);
const isPurgingCache = ref(false);
const cacheToastMessage = ref('');

const toggleOfflineCache = async () => {
    isOfflineCacheEnabled.value = !isOfflineCacheEnabled.value;
    localStorage.setItem('pwa_offline_cache_enabled', isOfflineCacheEnabled.value ? 'true' : 'false');
    
    if (!isOfflineCacheEnabled.value) {
        if ('serviceWorker' in navigator) {
            const registrations = await navigator.serviceWorker.getRegistrations();
            for (let reg of registrations) {
                await reg.unregister();
            }
        }
        if ('caches' in window) {
            const keys = await caches.keys();
            await Promise.all(keys.map(k => caches.delete(k)));
        }
        cacheToastMessage.value = 'Mode Live Network diaktifkan! Cache browser dinonaktifkan. Memuat ulang...';
    } else {
        cacheToastMessage.value = 'Mode Offline-First diaktifkan! Aplikasi akan menyimpan cache untuk supir & gudang. Memuat ulang...';
    }

    setTimeout(() => {
        window.location.reload();
    }, 1000);
};

const purgeAllCache = async () => {
    isPurgingCache.value = true;
    cacheToastMessage.value = 'Sedang membersihkan seluruh cache tersimpan...';
    try {
        if ('serviceWorker' in navigator) {
            const registrations = await navigator.serviceWorker.getRegistrations();
            for (let reg of registrations) {
                await reg.unregister();
            }
        }
        if ('caches' in window) {
            const keys = await caches.keys();
            await Promise.all(keys.map(k => caches.delete(k)));
        }
        cacheToastMessage.value = 'Seluruh cache berhasil dibersihkan! Halaman akan dimuat ulang...';
        setTimeout(() => {
            window.location.reload();
        }, 800);
    } catch (e) {
        isPurgingCache.value = false;
        cacheToastMessage.value = 'Gagal membersihkan cache: ' + (e.message || e);
    }
};

// =======================
// BACKUP & RESTORE LOGIC (SUPER ADMIN)
// =======================
const backupList = ref(props.backups || []);
const isCreatingBackup = ref(false);
const isUploadingBackup = ref(false);
const uploadProgress = ref(0);
const backupFileInput = ref(null);
const backupToast = ref(null);

const isRestoreModalOpen = ref(false);
const selectedBackupForRestore = ref(null);
const restoreAgreed = ref(false);
const isRestoring = ref(false);
const restoreStatusMessage = ref('');

const isDeleteModalOpen = ref(false);
const selectedBackupForDelete = ref(null);
const isDeleting = ref(false);

const totalStorageSizeFormatted = computed(() => {
    let totalBytes = 0;
    for (const b of backupList.value) {
        totalBytes += (b.size_bytes || 0);
    }
    if (totalBytes >= 1048576) {
        return (totalBytes / 1048576).toFixed(2) + ' MB';
    }
    return (totalBytes / 1024).toFixed(2) + ' KB';
});

const latestBackupFormatted = computed(() => {
    return backupList.value.length > 0 ? backupList.value[0].human_date : 'Belum Ada';
});

const showBackupToast = (msg, type = 'success') => {
    backupToast.value = { message: msg, type };
    setTimeout(() => {
        backupToast.value = null;
    }, 4500);
};

const createManualBackup = async () => {
    isCreatingBackup.value = true;
    try {
        const res = await window.axios.post(route('settings.backups.create'));
        if (res.data.status === 'success') {
            backupList.value = res.data.backups;
            showBackupToast(res.data.message || 'Cadangan sistem berhasil dibuat!');
        }
    } catch (err) {
        alert(err.response?.data?.message || err.message || 'Gagal membuat cadangan sistem.');
    } finally {
        isCreatingBackup.value = false;
    }
};

const triggerUploadPicker = () => {
    backupFileInput.value?.click();
};

const handleFileUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > 200 * 1024 * 1024) {
        alert('Ukuran file melebihi batas 200MB!');
        event.target.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('backup_file', file);

    isUploadingBackup.value = true;
    uploadProgress.value = 0;

    try {
        const res = await window.axios.post(route('settings.backups.upload'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (progressEvent) => {
                if (progressEvent.total) {
                    uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                }
            },
        });

        if (res.data.status === 'success') {
            backupList.value = res.data.backups;
            showBackupToast(res.data.message || 'Berkas cadangan berhasil diunggah!');
        }
    } catch (err) {
        alert(err.response?.data?.message || err.message || 'Gagal mengunggah berkas cadangan.');
    } finally {
        isUploadingBackup.value = false;
        uploadProgress.value = 0;
        if (backupFileInput.value) {
            backupFileInput.value.value = '';
        }
    }
};

const downloadBackup = (backup) => {
    window.location.href = route('settings.backups.download', { name: backup.name });
};

const openRestoreModal = (backup) => {
    selectedBackupForRestore.value = backup;
    restoreAgreed.value = false;
    restoreStatusMessage.value = '';
    isRestoreModalOpen.value = true;
};

const closeRestoreModal = () => {
    if (isRestoring.value) return;
    isRestoreModalOpen.value = false;
    selectedBackupForRestore.value = null;
    restoreAgreed.value = false;
};

const confirmRestore = async () => {
    if (!restoreAgreed.value || !selectedBackupForRestore.value) return;

    isRestoring.value = true;
    restoreStatusMessage.value = 'Mengunci sistem (Mode Pemeliharaan) & membuat Safety Snapshot...';

    try {
        const res = await window.axios.post(route('settings.backups.restore'), {
            name: selectedBackupForRestore.value.name,
        });

        if (res.data.status === 'success') {
            restoreStatusMessage.value = 'Pemulihan data berhasil! Memuat ulang sistem...';
            setTimeout(() => {
                window.location.reload();
            }, 1800);
        }
    } catch (err) {
        isRestoring.value = false;
        alert(err.response?.data?.message || err.message || 'Gagal memulihkan cadangan sistem.');
    }
};

const openDeleteModal = (backup) => {
    selectedBackupForDelete.value = backup;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    if (isDeleting.value) return;
    isDeleteModalOpen.value = false;
    selectedBackupForDelete.value = null;
};

const confirmDelete = async () => {
    if (!selectedBackupForDelete.value) return;
    isDeleting.value = true;
    try {
        const res = await window.axios.delete(route('settings.backups.destroy', { name: selectedBackupForDelete.value.name }));
        if (res.data.status === 'success') {
            backupList.value = res.data.backups;
            showBackupToast(res.data.message || 'Berkas cadangan berhasil dihapus.');
            isDeleteModalOpen.value = false;
            selectedBackupForDelete.value = null;
        }
    } catch (err) {
        alert(err.response?.data?.message || err.message || 'Gagal menghapus berkas cadangan.');
    } finally {
        isDeleting.value = false;
    }
};

// =======================
// INTEGRASI REAL-TIME (PUSHER / REVERB) LOGIC
// =======================
import { useEcho } from '@/Composables/useEcho';
const { initEcho, connectionState } = useEcho();

const integrationForm = useForm({
    driver: props.integration?.driver || 'pusher',
    pusher_app_id: props.integration?.pusher_app_id || '',
    pusher_app_key: props.integration?.pusher_app_key || '',
    pusher_app_secret: props.integration?.pusher_app_secret || '',
    pusher_app_cluster: props.integration?.pusher_app_cluster || 'ap1',
    pusher_scheme: props.integration?.pusher_scheme || 'https',
    reverb_app_id: props.integration?.reverb_app_id || '',
    reverb_app_key: props.integration?.reverb_app_key || '',
    reverb_app_secret: props.integration?.reverb_app_secret || '',
    reverb_host: props.integration?.reverb_host || 'localhost',
    reverb_port: props.integration?.reverb_port || '8080',
    reverb_scheme: props.integration?.reverb_scheme || 'https',
});

const isTestingBroadcast = ref(false);
const broadcastTestResult = ref(null);
const integrationToast = ref(null);

const showIntegrationToast = (msg, type = 'success') => {
    integrationToast.value = { message: msg, type };
    setTimeout(() => {
        integrationToast.value = null;
    }, 4500);
};

const submitIntegration = () => {
    integrationForm.post(route('settings.integration.update'), {
        preserveScroll: true,
        onSuccess: () => {
            showIntegrationToast('Konfigurasi Integrasi Real-time berhasil diperbarui!');
            initEcho({
                driver: integrationForm.driver,
                key: integrationForm.driver === 'reverb' ? integrationForm.reverb_app_key : integrationForm.pusher_app_key,
                cluster: integrationForm.pusher_app_cluster,
                host: integrationForm.reverb_host,
                port: integrationForm.reverb_port,
                scheme: integrationForm.driver === 'reverb' ? integrationForm.reverb_scheme : integrationForm.pusher_scheme,
            });
        },
    });
};

const testBroadcastConnection = async () => {
    isTestingBroadcast.value = true;
    broadcastTestResult.value = {
        status: 'testing',
        message: 'Mengirim broadcast event ke server...',
    };

    try {
        const echo = initEcho({
            driver: integrationForm.driver,
            key: integrationForm.driver === 'reverb' ? integrationForm.reverb_app_key : integrationForm.pusher_app_key,
            cluster: integrationForm.pusher_app_cluster,
            host: integrationForm.reverb_host,
            port: integrationForm.reverb_port,
            scheme: integrationForm.driver === 'reverb' ? integrationForm.reverb_scheme : integrationForm.pusher_scheme,
        });

        if (echo) {
            const handleSuccess = (e) => {
                broadcastTestResult.value = {
                    status: 'success',
                    message: (e && e.message) || 'Koneksi real-time berhasil diverifikasi!',
                    receivedAt: (e && e.timestamp) || new Date().toLocaleTimeString(),
                    driver: (e && e.driver) || integrationForm.driver,
                };
                isTestingBroadcast.value = false;
            };

            const channel = echo.channel('test-channel');
            channel.listen('.test.connection', handleSuccess);
            channel.listen('test.connection', handleSuccess);

            if (echo.connector && echo.connector.pusher) {
                try {
                    const rawChan = echo.connector.pusher.subscribe('test-channel');
                    rawChan.bind('test.connection', handleSuccess);
                } catch (e) {}
            }
        }

        const res = await window.axios.post(route('settings.integration.test'), integrationForm.data());
        if (res.data.status === 'success') {
            setTimeout(() => {
                if (broadcastTestResult.value?.status === 'testing') {
                    broadcastTestResult.value = {
                        status: 'warning',
                        message: res.data.message || 'Event terkirim di backend, namun browser belum menerima event. Periksa kembali Key, Cluster/Host, Port, atau pastikan driver aktif.',
                    };
                    isTestingBroadcast.value = false;
                }
            }, 5000);
        }
    } catch (err) {
        broadcastTestResult.value = {
            status: 'error',
            message: err.response?.data?.message || err.message || 'Gagal mengetes koneksi broadcast.',
        };
        isTestingBroadcast.value = false;
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

            <!-- TAB: INTEGRASI REAL-TIME (KHUSUS SUPER ADMIN) -->
            <template v-if="isSuperAdmin" #tabIntegrasi>
                <div class="max-w-4xl mx-auto space-y-6">

                    <!-- Banner Header Integrasi -->
                    <div class="bg-gradient-to-br from-slate-900 via-sky-950 to-slate-900 text-white rounded-2xl p-6 shadow-sm border border-slate-700/60 relative overflow-hidden">
                        <div class="absolute -right-8 -bottom-8 w-56 h-56 bg-sky-500/20 rounded-full blur-3xl pointer-events-none"></div>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
                            <div>
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/10 text-sky-300 text-xs font-mono font-bold mb-2">
                                    <i class="ri-broadcast-line animate-pulse"></i> Real-time WebSockets & Live Dispatcher
                                </div>
                                <h3 class="font-black text-xl text-white tracking-tight">Integrasi Real-time & WebSocket</h3>
                                <p class="text-xs sm:text-sm text-gray-300 mt-1 max-w-xl leading-relaxed">
                                    Aktifkan koneksi dua arah instan untuk sinkronisasi Handshake Supir, pemantauan Live GPS armada di peta, dan status resi tanpa perlu refresh halaman.
                                </p>
                            </div>
                            <div class="shrink-0 flex flex-col items-start sm:items-end gap-2">
                                <span class="px-3 py-1.5 rounded-xl bg-white/10 text-xs font-mono border border-white/10 flex items-center gap-2"
                                      :class="connectionState === 'connected' ? 'text-emerald-300 border-emerald-500/30' : (connectionState === 'connecting' ? 'text-amber-300 border-amber-500/30' : 'text-gray-300')">
                                    <span class="w-2 h-2 rounded-full"
                                          :class="connectionState === 'connected' ? 'bg-emerald-400 animate-ping' : (connectionState === 'connecting' ? 'bg-amber-400 animate-pulse' : 'bg-gray-400')"></span>
                                    <span>Status: <strong class="uppercase">{{ connectionState }}</strong></span>
                                </span>
                                <span class="text-[11px] text-gray-400 font-mono">
                                    Driver Aktif: <strong class="text-white uppercase">{{ integrationForm.driver }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Notifikasi / Toast Integrasi -->
                    <div v-if="integrationToast" class="p-4 rounded-xl border flex items-center justify-between gap-3 text-xs font-medium shadow-sm transition-all"
                         :class="integrationToast.type === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200'">
                        <div class="flex items-center gap-2">
                            <i class="ri-checkbox-circle-fill text-emerald-600 text-base"></i>
                            <span>{{ integrationToast.message }}</span>
                        </div>
                        <button @click="integrationToast = null" class="text-gray-400 hover:text-gray-600">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>

                    <!-- Pemilihan Layanan Broadcaster (Driver Toggle) -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6 space-y-4">
                        <div class="border-b border-gray-100 pb-3">
                            <h4 class="font-bold text-dark text-base">Pilih Layanan WebSocket</h4>
                            <p class="text-xs text-gray-500 mt-0.5">Pilih penyedia jaringan real-time yang sesuai dengan infrastruktur operasional Anda.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                            <!-- Option 1: Pusher Channels -->
                            <div 
                                @click="integrationForm.driver = 'pusher'"
                                class="p-4 rounded-2xl border-2 transition-all cursor-pointer relative flex flex-col justify-between"
                                :class="integrationForm.driver === 'pusher' ? 'border-primary bg-primary/5 shadow-md shadow-primary/10' : 'border-gray-200/70 hover:border-gray-300 bg-white'"
                            >
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                                            <i class="ri-cloud-line"></i>
                                        </div>
                                        <span v-if="integrationForm.driver === 'pusher'" class="w-5 h-5 rounded-full bg-primary text-white flex items-center justify-center text-xs">
                                            <i class="ri-check-line"></i>
                                        </span>
                                    </div>
                                    <h5 class="font-bold text-dark text-sm">Pusher Channels</h5>
                                    <p class="text-xs text-gray-500 leading-relaxed">
                                        Layanan Cloud SaaS resmi tanpa setup daemon. Siap pakai dengan latency rendah di server Singapura (ap1).
                                    </p>
                                </div>
                                <span class="inline-block mt-3 px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 self-start">Rekomendasi Cepat</span>
                            </div>

                            <!-- Option 2: Laravel Reverb -->
                            <div 
                                @click="integrationForm.driver = 'reverb'"
                                class="p-4 rounded-2xl border-2 transition-all cursor-pointer relative flex flex-col justify-between"
                                :class="integrationForm.driver === 'reverb' ? 'border-primary bg-primary/5 shadow-md shadow-primary/10' : 'border-gray-200/70 hover:border-gray-300 bg-white'"
                            >
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                                            <i class="ri-server-line"></i>
                                        </div>
                                        <span v-if="integrationForm.driver === 'reverb'" class="w-5 h-5 rounded-full bg-primary text-white flex items-center justify-center text-xs">
                                            <i class="ri-check-line"></i>
                                        </span>
                                    </div>
                                    <h5 class="font-bold text-dark text-sm">Laravel Reverb</h5>
                                    <p class="text-xs text-gray-500 leading-relaxed">
                                        WebSocket Mandiri (Self-Hosted) di server VPS Anda. Gratis tanpa batas koneksi serentak (high-traffic).
                                    </p>
                                </div>
                                <span class="inline-block mt-3 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 self-start">Skalabilitas Tinggi</span>
                            </div>

                            <!-- Option 3: Nonaktif / Log Mode -->
                            <div 
                                @click="integrationForm.driver = 'log'"
                                class="p-4 rounded-2xl border-2 transition-all cursor-pointer relative flex flex-col justify-between"
                                :class="integrationForm.driver === 'log' ? 'border-primary bg-primary/5 shadow-md shadow-primary/10' : 'border-gray-200/70 hover:border-gray-300 bg-white'"
                            >
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div class="w-10 h-10 rounded-xl bg-gray-100 text-gray-600 flex items-center justify-center text-xl">
                                            <i class="ri-file-text-line"></i>
                                        </div>
                                        <span v-if="integrationForm.driver === 'log'" class="w-5 h-5 rounded-full bg-primary text-white flex items-center justify-center text-xs">
                                            <i class="ri-check-line"></i>
                                        </span>
                                    </div>
                                    <h5 class="font-bold text-dark text-sm">Nonaktif (Log Saja)</h5>
                                    <p class="text-xs text-gray-500 leading-relaxed">
                                        Event real-time hanya ditulis ke file log lokal. Matikan socket jika sedang dalam pemeliharaan.
                                    </p>
                                </div>
                                <span class="inline-block mt-3 px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-700 self-start">Mode Standar</span>
                            </div>
                        </div>
                    </div>

                    <!-- Form Kredensial Pusher Channels -->
                    <div v-if="integrationForm.driver === 'pusher'" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6 space-y-5">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg">
                                    <i class="ri-key-2-line"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-dark text-base">Kredensial Pusher Channels</h4>
                                    <p class="text-xs text-gray-500">Dapatkan kredensial gratis di <a href="https://dashboard.pusher.com" target="_blank" class="text-primary underline font-medium">dashboard.pusher.com</a></p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700">App ID <span class="text-red-500">*</span></label>
                                <input v-model="integrationForm.pusher_app_id" type="text" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: 1948291">
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700">App Key <span class="text-red-500">*</span></label>
                                <input v-model="integrationForm.pusher_app_key" type="text" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Contoh: 8a48b9c1d0e...">
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700">App Secret <span class="text-red-500">*</span></label>
                                <input v-model="integrationForm.pusher_app_secret" type="password" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="••••••••••••••••">
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700">Cluster</label>
                                <input v-model="integrationForm.pusher_app_cluster" type="text" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="ap1 (Singapore / Indonesia)">
                            </div>
                        </div>
                    </div>

                    <!-- Form Kredensial Laravel Reverb -->
                    <div v-if="integrationForm.driver === 'reverb'" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6 space-y-5">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                                    <i class="ri-server-line"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-dark text-base">Konfigurasi Laravel Reverb Server</h4>
                                    <p class="text-xs text-gray-500">Jalankan <code class="px-1.5 py-0.5 bg-gray-100 rounded text-gray-700 font-mono">php artisan reverb:start</code> di server host.</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700">App ID <span class="text-red-500">*</span></label>
                                <input v-model="integrationForm.reverb_app_id" type="text" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="100001">
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700">App Key <span class="text-red-500">*</span></label>
                                <input v-model="integrationForm.reverb_app_key" type="text" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="reverb-key-xyz">
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700">App Secret <span class="text-red-500">*</span></label>
                                <input v-model="integrationForm.reverb_app_secret" type="password" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="••••••••••••••••">
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700">Host Server</label>
                                <input v-model="integrationForm.reverb_host" type="text" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="localhost atau domain.com">
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700">Port WebSocket</label>
                                <input v-model="integrationForm.reverb_port" type="text" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="8080">
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-gray-700">Protokol Skema</label>
                                <select v-model="integrationForm.reverb_scheme" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                    <option value="https">HTTPS / WSS (Secure)</option>
                                    <option value="http">HTTP / WS (Development)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Kotak Uji Coba Koneksi & Tombol Simpan -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-4">
                            <div>
                                <h4 class="font-bold text-dark text-base">Uji Coba & Verifikasi Koneksi</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Kirimkan broadcast sinyal tes dari server ke browser untuk memvalidasi konfigurasi socket.</p>
                            </div>

                            <button 
                                type="button" 
                                @click="testBroadcastConnection" 
                                :disabled="isTestingBroadcast || integrationForm.driver === 'log'"
                                class="px-4 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center justify-center gap-2 shadow-sm"
                                :class="integrationForm.driver === 'log' ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-200'"
                            >
                                <i :class="isTestingBroadcast ? 'ri-loader-4-line animate-spin text-sky-600' : 'ri-radar-line text-sky-600'"></i>
                                <span>{{ isTestingBroadcast ? 'Menguji Sinyal...' : 'Tes Koneksi Real-time' }}</span>
                            </button>
                        </div>

                        <!-- Feedback Hasil Tes -->
                        <div v-if="broadcastTestResult" class="p-4 rounded-xl border text-xs font-medium space-y-1.5"
                             :class="{
                                'bg-sky-50 text-sky-800 border-sky-200': broadcastTestResult.status === 'testing',
                                'bg-emerald-50 text-emerald-800 border-emerald-200': broadcastTestResult.status === 'success',
                                'bg-amber-50 text-amber-800 border-amber-200': broadcastTestResult.status === 'warning',
                                'bg-red-50 text-red-800 border-red-200': broadcastTestResult.status === 'error',
                             }">
                            <div class="flex items-center gap-2 font-bold">
                                <i v-if="broadcastTestResult.status === 'testing'" class="ri-loader-4-line animate-spin text-sky-600 text-base"></i>
                                <i v-else-if="broadcastTestResult.status === 'success'" class="ri-checkbox-circle-fill text-emerald-600 text-base"></i>
                                <i v-else-if="broadcastTestResult.status === 'warning'" class="ri-alert-line text-amber-600 text-base"></i>
                                <i v-else class="ri-close-circle-fill text-red-600 text-base"></i>
                                <span>
                                    {{ broadcastTestResult.status === 'success' ? 'Verifikasi Berhasil!' : (broadcastTestResult.status === 'testing' ? 'Memproses Uji Coba...' : 'Perhatian') }}
                                </span>
                            </div>
                            <p class="leading-relaxed pl-6">{{ broadcastTestResult.message }}</p>
                            <p v-if="broadcastTestResult.receivedAt" class="text-[11px] text-emerald-700 pl-6 font-mono">
                                Waktu diterima browser: {{ broadcastTestResult.receivedAt }} WIB
                            </p>
                        </div>

                        <!-- Tombol Submit Simpan -->
                        <div class="pt-2 flex justify-end">
                            <button 
                                type="button" 
                                @click="submitIntegration" 
                                :disabled="integrationForm.processing"
                                class="px-6 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 hover:shadow-lg hover:shadow-primary/20 transition-all flex items-center gap-2 text-sm"
                            >
                                <i :class="integrationForm.processing ? 'ri-loader-4-line animate-spin' : 'ri-save-3-line'"></i>
                                <span>{{ integrationForm.processing ? 'Menyimpan...' : 'Simpan Pengaturan Integrasi' }}</span>
                            </button>
                        </div>
                    </div>

                </div>
            </template>

            <!-- TAB: CADANGAN & PEMULIHAN SISTEM (KHUSUS SUPER ADMIN) -->
            <template v-if="isSuperAdmin" #tabBackup>
                <div class="max-w-4xl mx-auto space-y-6">

                    <!-- Banner Header Cadangan -->
                    <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white rounded-2xl p-6 shadow-sm border border-slate-700/60 relative overflow-hidden">
                        <div class="absolute -right-8 -bottom-8 w-56 h-56 bg-primary/20 rounded-full blur-3xl pointer-events-none"></div>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10">
                            <div>
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/10 text-indigo-300 text-xs font-mono font-bold mb-2">
                                    <i class="ri-shield-check-line"></i> SQLite & Media Assets Disaster Recovery
                                </div>
                                <h3 class="font-black text-xl text-white tracking-tight">Cadangan & Pemulihan Sistem</h3>
                                <p class="text-xs sm:text-sm text-gray-300 mt-1 max-w-xl leading-relaxed">
                                    Amankan salinan database SQLite, konfigurasi sistem, dan berkas media upload. Anda dapat mengunduh berkas ke komputer, mengunggah arsip baru, atau memulihkan data kapan saja.
                                </p>
                            </div>
                            <div class="shrink-0 flex items-center gap-2">
                                <span class="px-3 py-1.5 rounded-xl bg-white/10 text-indigo-200 text-xs font-mono border border-white/10 flex items-center gap-1.5">
                                    <i class="ri-hard-drive-2-line"></i>
                                    <span>Penyimpanan Aman Lokal</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Ringkasan Statistik Cadangan -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-3.5">
                            <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0">
                                <i class="ri-database-2-line"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-400">Total Berkas Cadangan</p>
                                <p class="text-lg font-black text-dark">{{ backupList.length }} <span class="text-xs font-medium text-gray-500">Berkas</span></p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-3.5">
                            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0">
                                <i class="ri-pie-chart-2-line"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-400">Total Kapasitas</p>
                                <p class="text-lg font-black text-dark">{{ totalStorageSizeFormatted }}</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-3.5">
                            <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shrink-0">
                                <i class="ri-time-line"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-400">Cadangan Terakhir</p>
                                <p class="text-sm font-bold text-dark truncate max-w-[150px]">{{ latestBackupFormatted }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Notifikasi / Toast Cadangan -->
                    <div v-if="backupToast" class="p-4 rounded-xl border flex items-center justify-between gap-3 text-xs font-medium shadow-sm transition-all"
                         :class="backupToast.type === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200'">
                        <div class="flex items-center gap-2">
                            <i :class="backupToast.type === 'success' ? 'ri-checkbox-circle-fill text-emerald-600 text-base' : 'ri-error-warning-fill text-red-600 text-base'"></i>
                            <span>{{ backupToast.message }}</span>
                        </div>
                        <button @click="backupToast = null" class="text-gray-400 hover:text-gray-600">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>

                    <!-- Toolbar Aksi: Buat Cadangan & Unggah Berkas -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h4 class="font-bold text-dark text-base">Kelola Arsip Cadangan</h4>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Format berkas yang didukung adalah <strong>.zip</strong> (database + media) dan <strong>.sqlite</strong>.
                                </p>
                            </div>

                            <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
                                <!-- Tombol Unggah -->
                                <button
                                    type="button"
                                    @click="triggerUploadPicker"
                                    :disabled="isUploadingBackup || isCreatingBackup"
                                    class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 font-bold text-xs transition shadow-sm flex items-center gap-2"
                                >
                                    <i :class="isUploadingBackup ? 'ri-loader-4-line animate-spin text-primary' : 'ri-upload-cloud-2-line text-primary'" class="text-base"></i>
                                    <span>{{ isUploadingBackup ? `Mengunggah (${uploadProgress}%)...` : 'Unggah Backup (.zip)' }}</span>
                                </button>

                                <!-- Tombol Buat Backup Sekarang -->
                                <button
                                    type="button"
                                    @click="createManualBackup"
                                    :disabled="isCreatingBackup || isUploadingBackup"
                                    class="px-4 py-2.5 rounded-xl bg-primary text-white font-bold text-xs hover:bg-primary/90 transition shadow-md shadow-primary/20 flex items-center gap-2"
                                >
                                    <i :class="isCreatingBackup ? 'ri-loader-4-line animate-spin' : 'ri-save-3-line'" class="text-base"></i>
                                    <span>{{ isCreatingBackup ? 'Mencadangkan Data...' : 'Buat Backup Sekarang' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Progress Bar Unggah -->
                        <div v-if="isUploadingBackup" class="pt-2 border-t border-gray-100 space-y-1.5">
                            <div class="flex items-center justify-between text-xs text-gray-600 font-medium">
                                <span>Mengunggah berkas cadangan ke server...</span>
                                <span class="font-bold text-primary">{{ uploadProgress }}%</span>
                            </div>
                            <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-primary rounded-full transition-all duration-150" :style="{ width: `${uploadProgress}%` }"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Daftar Berkas Cadangan -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                            <h4 class="font-bold text-dark text-sm flex items-center gap-2">
                                <i class="ri-folder-zip-line text-primary text-base"></i>
                                Riwayat Berkas Cadangan di Server
                            </h4>
                            <span class="text-xs text-gray-400 font-medium font-mono">storage/app/backups/</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-100 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                                        <th class="p-4">Nama Berkas</th>
                                        <th class="p-4">Tipe Cadangan</th>
                                        <th class="p-4">Tanggal Dibuat</th>
                                        <th class="p-4">Ukuran</th>
                                        <th class="p-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <tr v-for="b in backupList" :key="b.name" class="hover:bg-gray-50/60 transition">
                                        <td class="p-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-lg shrink-0 shadow-sm"
                                                     :class="b.is_zip ? 'bg-indigo-50 text-indigo-600' : 'bg-amber-50 text-amber-600'">
                                                    <i :class="b.is_zip ? 'ri-file-zip-line' : 'ri-database-2-line'"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <span class="font-bold text-dark text-xs block font-mono truncate max-w-[240px] sm:max-w-xs" :title="b.name">
                                                        {{ b.name }}
                                                    </span>
                                                    <span class="text-[11px] text-gray-400 block mt-0.5">
                                                        {{ b.human_date }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="p-4">
                                            <span v-if="b.type === 'Safety Snapshot'" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">
                                                <i class="ri-shield-flash-line text-[10px]"></i> Safety Snapshot
                                            </span>
                                            <span v-else-if="b.type === 'Sistem / Otomatis'" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-800">
                                                <i class="ri-refresh-line text-[10px]"></i> Auto Update
                                            </span>
                                            <span v-else-if="b.type === 'Unggahan'" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-100 text-purple-800">
                                                <i class="ri-upload-2-line text-[10px]"></i> Unggahan
                                            </span>
                                            <span v-else class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                                <i class="ri-checkbox-circle-line text-[10px]"></i> Manual
                                            </span>
                                        </td>

                                        <td class="p-4 text-xs text-gray-600 font-mono">
                                            {{ b.date }}
                                        </td>

                                        <td class="p-4 text-xs font-bold text-gray-700 font-mono">
                                            {{ b.size }}
                                        </td>

                                        <td class="p-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <!-- Download -->
                                                <button
                                                    type="button"
                                                    @click="downloadBackup(b)"
                                                    class="w-8 h-8 rounded-lg bg-gray-100 text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 inline-flex items-center justify-center transition shadow-sm"
                                                    title="Unduh berkas ke komputer"
                                                >
                                                    <i class="ri-download-2-line"></i>
                                                </button>

                                                <!-- Restore -->
                                                <button
                                                    type="button"
                                                    @click="openRestoreModal(b)"
                                                    class="px-2.5 py-1.5 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 text-xs font-bold inline-flex items-center gap-1 transition shadow-sm"
                                                    title="Pulihkan database & aset dari cadangan ini"
                                                >
                                                    <i class="ri-history-line"></i>
                                                    <span>Restore</span>
                                                </button>

                                                <!-- Delete -->
                                                <button
                                                    type="button"
                                                    @click="openDeleteModal(b)"
                                                    class="w-8 h-8 rounded-lg bg-gray-100 text-gray-400 hover:bg-red-50 hover:text-red-600 inline-flex items-center justify-center transition shadow-sm"
                                                    title="Hapus berkas cadangan"
                                                >
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="backupList.length === 0">
                                        <td colspan="5" class="p-8 text-center text-gray-500">
                                            <div class="flex flex-col items-center justify-center space-y-2">
                                                <i class="ri-database-2-line text-4xl text-gray-300"></i>
                                                <p class="font-bold text-dark text-sm">Belum Ada Berkas Cadangan</p>
                                                <p class="text-xs text-gray-400 max-w-sm">
                                                    Klik tombol "Buat Backup Sekarang" untuk membuat salinan pertama atau unggah berkas arsip yang sudah ada.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </template>

            <!-- TAB: PEMBARUAN SISTEM (KHUSUS SUPER ADMIN) -->
            <template v-if="isSuperAdmin" #tabUpdate>
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

                    <!-- Kartu Manajemen Cache & Mode Offline (PWA) -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl transition-colors shrink-0"
                                     :class="isOfflineCacheEnabled ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'">
                                    <i :class="isOfflineCacheEnabled ? 'ri-wifi-off-line' : 'ri-wifi-line'"></i>
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="font-bold text-dark text-base">Mode Offline & Cache Browser (PWA)</h4>
                                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full"
                                              :class="isOfflineCacheEnabled ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                            {{ isOfflineCacheEnabled ? 'Offline-First Aktif' : 'Live Network (Tanpa Cache)' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ isOfflineCacheEnabled 
                                            ? 'Aplikasi disimpan di memori perangkat agar supir & checker tetap bisa bekerja saat sinyal hilang.' 
                                            : 'Semua halaman & aset selalu diambil langsung dari server terbaru (cocok untuk admin kantor & saat develop).' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Pesan Notifikasi jika ada aksi -->
                        <div v-if="cacheToastMessage" class="p-3 bg-blue-50 text-blue-700 rounded-xl text-xs flex items-center gap-2 font-medium">
                            <i class="ri-information-line text-base shrink-0"></i>
                            <span>{{ cacheToastMessage }}</span>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-1">
                            <!-- Switch Toggle -->
                            <div class="flex items-center gap-3">
                                <button 
                                    type="button" 
                                    @click="toggleOfflineCache" 
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                    :class="isOfflineCacheEnabled ? 'bg-emerald-600' : 'bg-gray-300'"
                                    role="switch" 
                                    :aria-checked="isOfflineCacheEnabled"
                                >
                                    <span 
                                        aria-hidden="true" 
                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :class="isOfflineCacheEnabled ? 'translate-x-5' : 'translate-x-0'"
                                    ></span>
                                </button>
                                <span class="text-xs font-bold text-gray-700 cursor-pointer" @click="toggleOfflineCache">
                                    {{ isOfflineCacheEnabled ? 'Nonaktifkan Cache (Ganti ke Live Network)' : 'Aktifkan Cache Offline (Mode Supir/Gudang)' }}
                                </span>
                            </div>

                            <!-- Tombol Bersihkan Cache -->
                            <button
                                type="button"
                                @click="purgeAllCache"
                                :disabled="isPurgingCache"
                                class="px-4 py-2 rounded-xl text-xs font-bold text-gray-700 bg-gray-100 hover:bg-red-50 hover:text-red-700 transition flex items-center justify-center gap-1.5 border border-gray-200/60 shadow-sm"
                            >
                                <i :class="isPurgingCache ? 'ri-loader-4-line animate-spin text-red-600' : 'ri-delete-bin-line text-gray-500'"></i>
                                {{ isPurgingCache ? 'Membersihkan...' : 'Bersihkan Cache Sekarang' }}
                            </button>
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

                        <form @submit.prevent="saveGithubConfig" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                                <div class="sm:col-span-4">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Repository GitHub <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                            <i class="ri-git-repository-line"></i>
                                        </span>
                                        <input v-model="githubConfig.repo" type="text" placeholder="Agung-laksono/centra-cargo-jepara" class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
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

                                <div class="sm:col-span-5">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">
                                        Personal Access Token (PAT)
                                        <span class="text-gray-400 font-normal text-[11px]">(Wajib jika Repo Private)</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                            <i class="ri-key-2-line"></i>
                                        </span>
                                        <input v-model="githubConfig.token" type="password" placeholder="ghp_xxxxxxxxxxxx (kosongkan jika publik)" class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                                <button type="submit" :disabled="isSavingConfig" class="px-4 py-2 bg-gray-900 text-white font-bold rounded-xl text-xs hover:bg-black transition flex items-center justify-center gap-1.5 shadow-sm">
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

        <!-- Hidden File Input for Backup Upload -->
        <input
            type="file"
            ref="backupFileInput"
            @change="handleFileUpload"
            accept=".zip,.sqlite"
            class="hidden"
        />

        <!-- MODAL KONFIRMASI RESTORE CADANGAN -->
        <div v-if="isRestoreModalOpen" class="fixed inset-0 z-[120] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeRestoreModal"></div>

            <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl relative flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <!-- Modal Header -->
                <div class="px-6 py-4 bg-amber-500 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="ri-alert-fill text-2xl"></i>
                        <h3 class="font-black text-lg">Konfirmasi Pemulihan Sistem (Restore)</h3>
                    </div>
                    <button v-if="!isRestoring" @click="closeRestoreModal" class="text-white/80 hover:text-white">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-4 text-sm text-gray-600">
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl space-y-2 text-amber-900 text-xs">
                        <p class="font-bold flex items-center gap-1.5 text-sm text-amber-950">
                            <i class="ri-error-warning-line text-base text-amber-600"></i>
                            Peringatan Kritis: Penimpaan Database & Media
                        </p>
                        <p class="leading-relaxed">
                            Memulihkan cadangan akan <strong>menggantikan seluruh database operasional</strong> dan <strong>berkas foto/media</strong> aktif saat ini dengan data dari cadangan terpilih.
                        </p>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-xl space-y-2 border border-gray-100 font-mono text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Berkas Cadangan:</span>
                            <span class="font-bold text-dark">{{ selectedBackupForRestore?.name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Tanggal Dibuat:</span>
                            <span class="text-gray-700">{{ selectedBackupForRestore?.date }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-400">Ukuran:</span>
                            <span class="text-gray-700">{{ selectedBackupForRestore?.size }}</span>
                        </div>
                    </div>

                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl text-blue-900 text-xs flex items-start gap-2">
                        <i class="ri-shield-check-line text-blue-600 text-base shrink-0 mt-0.5"></i>
                        <p class="leading-relaxed">
                            <strong>Pengaman Otomatis:</strong> Sistem akan otomatis mengaktifkan <strong>Mode Pemeliharaan (Maintenance Mode)</strong> dan membuat <strong>Safety Snapshot</strong> dari database saat ini sebelum penimpaan dilakukan.
                        </p>
                    </div>

                    <!-- Status Loading Saat Restore Berlangsung -->
                    <div v-if="isRestoring" class="p-4 bg-indigo-50 border border-indigo-200 rounded-xl text-center space-y-2">
                        <i class="ri-loader-4-line animate-spin text-3xl text-indigo-600"></i>
                        <p class="font-bold text-indigo-950 text-xs">{{ restoreStatusMessage }}</p>
                        <p class="text-[11px] text-indigo-700">Mohon tidak menutup jendela atau merefresh browser...</p>
                    </div>

                    <!-- Checkbox Konfirmasi -->
                    <div v-else class="pt-2">
                        <label class="flex items-start gap-3 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                v-model="restoreAgreed"
                                class="mt-1 w-4 h-4 text-amber-600 rounded border-gray-300 focus:ring-amber-500"
                            />
                            <span class="text-xs text-gray-700 font-medium leading-relaxed">
                                Saya mengerti risiko ini dan menyetujui pemulihan data sistem ke versi cadangan ini.
                            </span>
                        </label>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 flex gap-3 border-t border-gray-100">
                        <button
                            type="button"
                            @click="closeRestoreModal"
                            :disabled="isRestoring"
                            class="flex-1 px-4 py-2.5 rounded-xl font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition text-sm disabled:opacity-50"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="confirmRestore"
                            :disabled="!restoreAgreed || isRestoring"
                            class="flex-1 px-4 py-2.5 rounded-xl font-bold text-white bg-amber-600 hover:bg-amber-700 transition text-sm shadow-md shadow-amber-600/30 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                        >
                            <i :class="isRestoring ? 'ri-loader-4-line animate-spin' : 'ri-history-line'"></i>
                            <span>{{ isRestoring ? 'Memulihkan Data...' : 'Mulai Pemulihan' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL KONFIRMASI HAPUS CADANGAN -->
        <div v-if="isDeleteModalOpen" class="fixed inset-0 z-[120] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="closeDeleteModal"></div>

            <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl relative flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-6 space-y-4">
                    <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-2xl mx-auto shadow-sm">
                        <i class="ri-delete-bin-line"></i>
                    </div>

                    <div class="text-center space-y-1">
                        <h3 class="font-black text-lg text-dark">Hapus Berkas Cadangan?</h3>
                        <p class="text-xs text-gray-500">Tindakan ini tidak dapat dibatalkan. Berkas akan dihapus permanen dari server.</p>
                    </div>

                    <div class="bg-gray-50 p-3 rounded-xl border border-gray-100 font-mono text-xs text-center font-bold text-gray-700 truncate">
                        {{ selectedBackupForDelete?.name }}
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button
                            type="button"
                            @click="closeDeleteModal"
                            :disabled="isDeleting"
                            class="flex-1 px-4 py-2.5 rounded-xl font-bold text-gray-600 bg-gray-100 hover:bg-gray-200 transition text-sm disabled:opacity-50"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="confirmDelete"
                            :disabled="isDeleting"
                            class="flex-1 px-4 py-2.5 rounded-xl font-bold text-white bg-red-600 hover:bg-red-700 transition text-sm shadow-md shadow-red-600/30 disabled:opacity-50 flex items-center justify-center gap-1.5"
                        >
                            <i :class="isDeleting ? 'ri-loader-4-line animate-spin' : 'ri-delete-bin-line'"></i>
                            <span>{{ isDeleting ? 'Menghapus...' : 'Hapus Sekarang' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL POP-UP PEMBARUAN SISTEM (FULL-SCREEN LOCK OVERLAY) -->
        <div v-if="isUpdateModalOpen" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md select-none">
            <div class="bg-white rounded-3xl w-full max-w-xl shadow-2xl relative flex flex-col overflow-hidden border border-slate-700/30 animate-in fade-in zoom-in-95 duration-200">
                <!-- Modal Header -->
                <div class="px-6 py-5 bg-gradient-to-r from-slate-950 via-indigo-950 to-slate-900 text-white flex items-center justify-between border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl flex items-center justify-center text-xl shrink-0"
                             :class="updateError ? 'bg-red-500/20 text-red-400 border border-red-500/30' : (updateProgress === 100 ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-primary/20 text-emerald-400 border border-emerald-500/30')">
                            <i :class="updateError ? 'ri-error-warning-fill' : (updateProgress === 100 ? 'ri-checkbox-circle-fill' : 'ri-loader-4-line animate-spin')"></i>
                        </div>
                        <div>
                            <h3 class="font-black text-lg text-white leading-tight">
                                {{ updateError ? 'Pembaruan Terhenti' : (updateProgress === 100 ? 'Pembaruan Berhasil 100%!' : 'Pembaruan Sistem Sedang Berjalan') }}
                            </h3>
                            <p class="text-xs text-gray-300 mt-0.5">
                                {{ updateError ? 'Terjadi kendala teknis saat proses berlangsung' : `Tahap ${currentStep} dari 4 • Jangan tutup atau navigasi halaman` }}
                            </p>
                        </div>
                    </div>
                    <span class="font-mono text-xs font-black px-2.5 py-1 rounded-lg bg-white/10 border border-white/10"
                          :class="updateError ? 'text-red-400' : 'text-emerald-400'">
                        {{ updateProgress }}%
                    </span>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-5">
                    <!-- Progress Bar -->
                    <div class="space-y-2">
                        <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden p-0.5 border border-gray-200/50">
                            <div 
                                class="h-full rounded-full transition-all duration-500"
                                :class="updateError ? 'bg-red-500' : 'bg-gradient-to-r from-emerald-500 via-teal-500 to-primary'"
                                :style="{ width: `${updateProgress}%` }"
                            ></div>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-dark flex items-center gap-1.5">
                                <i :class="isUpdating ? 'ri-loader-4-line animate-spin text-primary' : 'ri-information-line text-gray-400'"></i>
                                {{ updateStatusText }}
                            </span>
                            <span class="text-gray-400 font-mono text-[11px]">Langkah {{ currentStep }}/4</span>
                        </div>
                    </div>

                    <!-- Lock Banner Warning -->
                    <div class="p-3 bg-amber-50 border border-amber-200/80 rounded-xl flex items-center gap-2.5 text-xs text-amber-900 font-medium">
                        <i class="ri-lock-line text-base text-amber-600 shrink-0"></i>
                        <span>Aplikasi dan tombol navigasi dikunci sementara demi mencegah inkonsistensi database.</span>
                    </div>

                    <!-- Error Alert Box -->
                    <div v-if="updateError" class="p-4 bg-red-50 text-red-800 rounded-2xl border border-red-200 text-xs space-y-1">
                        <div class="font-bold flex items-center gap-1.5 text-red-900">
                            <i class="ri-close-circle-fill text-base text-red-600"></i>
                            Gagal Memperbarui Sistem
                        </div>
                        <p class="font-mono leading-relaxed pl-5">{{ updateError }}</p>
                    </div>

                    <!-- Terminal Console Log Box -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                            <span>Log Terminal Proses</span>
                            <span class="font-mono text-[10px] text-gray-400">{{ updateLogs.length }} Baris</span>
                        </div>
                        <div class="bg-slate-950 rounded-2xl p-4 font-mono text-[11px] text-emerald-400 space-y-1.5 max-h-48 overflow-y-auto border border-slate-800 shadow-inner">
                            <div v-for="(log, idx) in updateLogs" :key="idx" class="leading-relaxed">
                                <span class="text-slate-500 select-none">&gt;</span> {{ log }}
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer Actions (Hanya muncul jika Error atau selesai) -->
                    <div v-if="updateError" class="pt-2 flex justify-end">
                        <button
                            type="button"
                            @click="closeUpdateModal"
                            class="px-5 py-2.5 rounded-xl font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 transition text-xs shadow-sm flex items-center gap-1.5"
                        >
                            <i class="ri-close-line"></i> Tutup & Kembali
                        </button>
                    </div>

                    <div v-else-if="updateProgress === 100" class="pt-2 text-center text-xs font-bold text-emerald-600 flex items-center justify-center gap-2 animate-pulse">
                        <i class="ri-refresh-line animate-spin"></i>
                        <span>Memuat ulang halaman dalam beberapa detik...</span>
                    </div>

                    <div v-else class="pt-1 text-center text-[11px] text-gray-400 font-medium">
                        Harap tidak mematikan daya perangkat atau memutuskan jaringan internet.
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
