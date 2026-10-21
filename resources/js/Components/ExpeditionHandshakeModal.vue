<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: { type: Boolean, default: false },
    expeditionId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close']);

// Tab state
const activeTab = ref('qr'); // 'qr' or 'drivers'

// Data states
const isLoading = ref(false);
const expeditionData = ref(null);
const stats = ref({ pending_count: 0, active_count: 0, total: 0 });
const copySuccess = ref(false);
const copyLinkSuccess = ref(false);
const feedbackMsg = ref(null);

// Reset QR states
const showResetConfirm = ref(false);
const isResetting = ref(false);

// Partner Requests states
const driverRequests = ref([]);
const isLoadingRequests = ref(false);
const filterStatus = ref('ALL'); // 'ALL', 'PENDING', 'ACTIVE', 'INACTIVE'
const searchQuery = ref('');
const updatingDriverId = ref(null);

const fetchQrData = async () => {
    try {
        isLoading.value = true;
        const res = await axios.get('/api/expedition/handshake-qr', {
            params: { expedition_id: props.expeditionId }
        });
        if (res.data && res.data.expedition) {
            expeditionData.value = res.data.expedition;
            if (res.data.stats) {
                stats.value = res.data.stats;
            }
        }
    } catch (e) {
        console.warn('Gagal memuat QR Kemitraan ekspedisi:', e);
    } finally {
        isLoading.value = false;
    }
};

const fetchRequests = async () => {
    try {
        isLoadingRequests.value = true;
        const res = await axios.get('/api/expedition/partner-requests', {
            params: { expedition_id: props.expeditionId }
        });
        if (res.data && res.data.requests) {
            driverRequests.value = res.data.requests;
            if (res.data.stats) {
                stats.value = res.data.stats;
            }
        }
    } catch (e) {
        console.warn('Gagal memuat daftar permohonan supir:', e);
    } finally {
        isLoadingRequests.value = false;
    }
};

const loadAllData = async () => {
    await fetchQrData();
    await fetchRequests();
};

watch(() => props.show, (isOpen) => {
    if (isOpen) {
        showResetConfirm.value = false;
        feedbackMsg.value = null;
        loadAllData();
    }
});

const qrImageUrl = computed(() => {
    if (!expeditionData.value?.qr_value) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=350x350&margin=12&data=${encodeURIComponent(expeditionData.value.qr_value)}`;
});

const copyCode = () => {
    if (!expeditionData.value?.partnership_token) return;
    navigator.clipboard.writeText(expeditionData.value.partnership_token);
    copySuccess.value = true;
    setTimeout(() => { copySuccess.value = false; }, 2500);
};

const copyLink = () => {
    if (!expeditionData.value?.qr_value) return;
    navigator.clipboard.writeText(expeditionData.value.qr_value);
    copyLinkSuccess.value = true;
    setTimeout(() => { copyLinkSuccess.value = false; }, 2500);
};

const handleResetQr = async () => {
    try {
        isResetting.value = true;
        const res = await axios.post('/api/expedition/reset-qr', {
            expedition_id: props.expeditionId,
        });

        if (res.data.status === 'success') {
            expeditionData.value = res.data.expedition;
            showResetConfirm.value = false;
            feedbackMsg.value = {
                type: 'success',
                text: res.data.message || 'Kode QR berhasil diganti! QR lama sudah tidak berlaku.'
            };
            setTimeout(() => { feedbackMsg.value = null; }, 5000);
        }
    } catch (e) {
        feedbackMsg.value = {
            type: 'error',
            text: e.response?.data?.message || 'Gagal mereset Kode QR.'
        };
    } finally {
        isResetting.value = false;
    }
};

const handleUpdateStatus = async (partnershipId, newStatus) => {
    try {
        updatingDriverId.value = partnershipId;
        const res = await axios.post(`/api/expedition/partner-requests/${partnershipId}/status`, {
            status: newStatus,
        });

        if (res.data.status === 'success') {
            feedbackMsg.value = { type: 'success', text: res.data.message };
            await fetchRequests();
            await fetchQrData();
            setTimeout(() => { feedbackMsg.value = null; }, 4000);
        }
    } catch (e) {
        feedbackMsg.value = {
            type: 'error',
            text: e.response?.data?.message || 'Gagal memperbarui status supir.'
        };
    } finally {
        updatingDriverId.value = null;
    }
};

const filteredRequests = computed(() => {
    let list = driverRequests.value;

    if (filterStatus.value !== 'ALL') {
        list = list.filter(r => r.status === filterStatus.value);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(r => 
            (r.name && r.name.toLowerCase().includes(q)) ||
            (r.phone && r.phone.toLowerCase().includes(q)) ||
            (r.vehicle_name && r.vehicle_name.toLowerCase().includes(q))
        );
    }

    return list;
});

const printSticker = () => {
    const printWindow = window.open('', '_blank', 'width=700,height=800');
    if (!printWindow) return;

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Stiker QR Kemitraan - ${expeditionData.value?.name || 'Jepara Cargo'}</title>
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 98vh;
                    margin: 0;
                    background: #f4f6f8;
                }
                .card {
                    background: #ffffff;
                    border: 3px solid #1e293b;
                    border-radius: 24px;
                    width: 380px;
                    padding: 32px 24px;
                    text-align: center;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                }
                .badge {
                    display: inline-block;
                    background: #0284c7;
                    color: white;
                    padding: 6px 16px;
                    border-radius: 999px;
                    font-size: 11px;
                    font-weight: 800;
                    letter-spacing: 1px;
                    text-transform: uppercase;
                    margin-bottom: 12px;
                }
                h1 {
                    font-size: 22px;
                    margin: 0 0 6px 0;
                    color: #0f172a;
                    font-weight: 900;
                }
                p.subtitle {
                    font-size: 13px;
                    color: #64748b;
                    margin: 0 0 20px 0;
                }
                .qr-box {
                    background: #ffffff;
                    padding: 12px;
                    border-radius: 16px;
                    border: 2px dashed #cbd5e1;
                    display: inline-block;
                    margin-bottom: 20px;
                }
                .qr-box img {
                    width: 250px;
                    height: 250px;
                    display: block;
                }
                .code-box {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 12px;
                    padding: 10px 14px;
                    margin-bottom: 16px;
                }
                .code-label {
                    font-size: 10px;
                    color: #94a3b8;
                    text-transform: uppercase;
                    font-weight: 700;
                    letter-spacing: 1px;
                }
                .code-val {
                    font-size: 20px;
                    font-family: monospace;
                    font-weight: 900;
                    color: #0f172a;
                    letter-spacing: 2px;
                }
                .footer {
                    font-size: 11px;
                    color: #64748b;
                    line-height: 1.4;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <span class="badge">Kemitraan Resmi Armada</span>
                <h1>${expeditionData.value?.name || 'Ekspedisi Rekanan'}</h1>
                <p class="subtitle">Scan QR dengan Aplikasi Supir untuk bergabung</p>
                <div class="qr-box">
                    <img src="${qrImageUrl.value}" alt="QR Kemitraan" />
                </div>
                <div class="code-box">
                    <div class="code-label">Kode Kemitraan Manual</div>
                    <div class="code-val">${expeditionData.value?.partnership_token}</div>
                </div>
                <div class="footer">
                    Aplikasi Supir Jepara Cargo • Tempel stiker ini di dinding gudang atau pos muat.
                </div>
            </div>
            <script>
                window.onload = function() {
                    window.print();
                }
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="show" 
                class="fixed inset-0 z-[220] flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-sm"
                @click.self="emit('close')"
            >
                <div 
                    class="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-gray-100 relative flex flex-col overflow-hidden max-h-[92vh] animate-scaleUp"
                    @click.stop
                >
                    <!-- Header Modal -->
                    <div class="px-6 py-4 bg-gradient-to-r from-gray-900 to-slate-800 text-white flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-blue-500/20 text-blue-400 border border-blue-400/30 flex items-center justify-center text-xl">
                                <i class="ri-shake-hands-line"></i>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-base text-white flex items-center gap-2">
                                    <span>Kemitraan Armada Supir</span>
                                    <span v-if="stats.pending_count > 0" class="px-2 py-0.5 rounded-full bg-amber-500 text-black text-[10px] font-black animate-pulse">
                                        {{ stats.pending_count }} Permohonan Baru
                                    </span>
                                </h3>
                                <p class="text-xs text-gray-400">
                                    {{ expeditionData?.name || 'Ekspedisi Rekanan' }}
                                </p>
                            </div>
                        </div>

                        <button 
                            @click="emit('close')"
                            class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-gray-300 hover:text-white flex items-center justify-center transition"
                        >
                            <i class="ri-close-line text-lg"></i>
                        </button>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="flex border-b border-gray-100 bg-gray-50/80 px-4 pt-2.5 gap-2 shrink-0">
                        <button
                            type="button"
                            @click="activeTab = 'qr'"
                            class="px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-1.5"
                            :class="activeTab === 'qr' ? 'bg-white text-gray-900 shadow-sm border-t-2 border-primary -mb-px' : 'text-gray-500 hover:text-gray-800'"
                        >
                            <i class="ri-qr-code-line text-sm"></i>
                            <span>Kode QR & Stiker Gudang</span>
                        </button>

                        <button
                            type="button"
                            @click="activeTab = 'drivers'"
                            class="px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-1.5 relative"
                            :class="activeTab === 'drivers' ? 'bg-white text-gray-900 shadow-sm border-t-2 border-primary -mb-px' : 'text-gray-500 hover:text-gray-800'"
                        >
                            <i class="ri-truck-line text-sm"></i>
                            <span>Daftar Supir Mitra</span>
                            <span 
                                v-if="stats.pending_count > 0" 
                                class="px-1.5 py-0.2 rounded-full text-[10px] font-black bg-amber-500 text-white ml-0.5"
                            >
                                {{ stats.pending_count }}
                            </span>
                        </button>
                    </div>

                    <!-- Feedback Alert Message -->
                    <div v-if="feedbackMsg" class="mx-5 mt-3 p-3 rounded-xl text-xs font-semibold flex items-center justify-between" :class="feedbackMsg.type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200'">
                        <div class="flex items-center gap-2">
                            <i :class="feedbackMsg.type === 'success' ? 'ri-checkbox-circle-fill text-emerald-600 text-base' : 'ri-error-warning-fill text-red-600 text-base'"></i>
                            <span>{{ feedbackMsg.text }}</span>
                        </div>
                        <button @click="feedbackMsg = null" class="text-gray-400 hover:text-gray-600">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>

                    <!-- TAB 1: KODE QR & STIKER GUDANG -->
                    <div v-if="activeTab === 'qr'" class="p-5 overflow-y-auto flex-1 flex flex-col items-center text-center">
                        <!-- Loading skeleton -->
                        <div v-if="isLoading" class="w-56 h-56 my-5 bg-gray-100 rounded-2xl animate-pulse flex items-center justify-center text-gray-400 text-xs">
                            Membuat QR Kemitraan...
                        </div>

                        <!-- QR Display Box -->
                        <div v-else-if="expeditionData" class="my-2 p-3 bg-white rounded-2xl border-2 border-dashed border-gray-200 shadow-sm flex flex-col items-center">
                            <img 
                                :src="qrImageUrl" 
                                alt="QR Kemitraan Supir" 
                                class="w-52 h-52 object-contain rounded-xl"
                            />
                            <div class="mt-2 text-[10px] text-gray-400 uppercase tracking-widest font-extrabold flex items-center gap-1">
                                <i class="ri-scan-line text-primary"></i>
                                <span>Scan via Aplikasi Supir di Gudang</span>
                            </div>
                        </div>

                        <!-- Pairing Code Box -->
                        <div v-if="expeditionData" class="w-full bg-gray-50 p-3.5 rounded-2xl border border-gray-100 flex items-center justify-between my-3">
                            <div class="text-left">
                                <span class="text-[10px] text-gray-400 block font-bold uppercase tracking-wider">Kode Kemitraan Manual</span>
                                <span class="text-lg font-black font-mono text-gray-900 tracking-wider">
                                    {{ expeditionData.partnership_token }}
                                </span>
                            </div>
                            <button
                                type="button"
                                @click="copyCode"
                                class="px-3 py-1.5 rounded-xl bg-white border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-100 active:scale-95 transition shadow-sm flex items-center gap-1.5"
                            >
                                <i :class="copySuccess ? 'ri-checkbox-circle-fill text-green-600' : 'ri-file-copy-line'"></i>
                                <span>{{ copySuccess ? 'Tersalin' : 'Salin Kode' }}</span>
                            </button>
                        </div>

                        <!-- Reset QR Warning Dialog -->
                        <div v-if="showResetConfirm" class="w-full bg-amber-50 border border-amber-200 p-4 rounded-2xl text-left my-2 space-y-2.5 animate-fadeIn">
                            <div class="flex items-start gap-2.5">
                                <i class="ri-error-warning-fill text-amber-600 text-lg shrink-0 mt-0.5"></i>
                                <div>
                                    <h5 class="font-extrabold text-xs text-amber-900">Perbarui / Ganti Kode QR?</h5>
                                    <p class="text-[11px] text-amber-800 leading-relaxed mt-0.5">
                                        QR lama di poster/stiker gudang tidak akan bisa di-scan lagi oleh supir baru. 
                                        <strong>Supir yang sudah disetujui sebelumnya tetap aman dan aktif.</strong>
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-2 pt-1">
                                <button
                                    type="button"
                                    @click="showResetConfirm = false"
                                    class="flex-1 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-xl text-xs font-bold hover:bg-gray-50 transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="button"
                                    @click="handleResetQr"
                                    :disabled="isResetting"
                                    class="flex-1 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1"
                                >
                                    <i v-if="isResetting" class="ri-loader-4-line animate-spin"></i>
                                    <span>{{ isResetting ? 'Memproses...' : 'Ya, Buat QR Baru' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div class="w-full space-y-2 mt-2">
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="printSticker"
                                    class="py-2.5 px-3 rounded-xl bg-slate-900 hover:bg-black text-white font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-[0.98] shadow-sm"
                                >
                                    <i class="ri-printer-line text-sm text-primary"></i>
                                    <span>Cetak Stiker Gudang</span>
                                </button>

                                <button
                                    type="button"
                                    @click="copyLink"
                                    class="py-2.5 px-3 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs flex items-center justify-center gap-1.5 transition border border-blue-200/80 active:scale-[0.98]"
                                >
                                    <i :class="copyLinkSuccess ? 'ri-checkbox-circle-fill text-emerald-600' : 'ri-share-line'"></i>
                                    <span>{{ copyLinkSuccess ? 'Tautan Tersalin!' : 'Bagikan Link WA' }}</span>
                                </button>
                            </div>

                            <button
                                v-if="!showResetConfirm"
                                type="button"
                                @click="showResetConfirm = true"
                                class="w-full py-2 px-3 rounded-xl bg-gray-50 hover:bg-amber-50 text-gray-500 hover:text-amber-800 font-semibold text-xs flex items-center justify-center gap-1.5 transition border border-gray-200/60"
                            >
                                <i class="ri-refresh-line"></i>
                                <span>Reset / Ganti Kode QR (Jika Bocor)</span>
                            </button>
                        </div>
                    </div>

                    <!-- TAB 2: DAFTAR & PERSETUJUAN SUPIR MITRA -->
                    <div v-else class="p-4 overflow-y-auto flex-1 space-y-3 flex flex-col">
                        <!-- Filter and Search Bar -->
                        <div class="space-y-2 shrink-0">
                            <!-- Search -->
                            <div class="relative">
                                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                <input
                                    type="text"
                                    v-model="searchQuery"
                                    placeholder="Cari nama supir, HP, armada..."
                                    class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-gray-200 text-gray-800 focus:border-primary focus:ring-1 focus:ring-primary"
                                />
                            </div>

                            <!-- Filter Pills -->
                            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-[11px]">
                                <button
                                    type="button"
                                    @click="filterStatus = 'ALL'"
                                    class="px-2.5 py-1 rounded-lg font-bold transition whitespace-nowrap"
                                    :class="filterStatus === 'ALL' ? 'bg-slate-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                >
                                    Semua ({{ driverRequests.length }})
                                </button>

                                <button
                                    type="button"
                                    @click="filterStatus = 'PENDING'"
                                    class="px-2.5 py-1 rounded-lg font-bold transition whitespace-nowrap flex items-center gap-1"
                                    :class="filterStatus === 'PENDING' ? 'bg-amber-500 text-white shadow-sm' : 'bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200/60'"
                                >
                                    <span>Menunggu</span>
                                    <span class="px-1 py-0.2 bg-black/20 rounded text-[9px]">{{ stats.pending_count }}</span>
                                </button>

                                <button
                                    type="button"
                                    @click="filterStatus = 'ACTIVE'"
                                    class="px-2.5 py-1 rounded-lg font-bold transition whitespace-nowrap"
                                    :class="filterStatus === 'ACTIVE' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                >
                                    Mitra Aktif ({{ stats.active_count }})
                                </button>

                                <button
                                    type="button"
                                    @click="filterStatus = 'INACTIVE'"
                                    class="px-2.5 py-1 rounded-lg font-bold transition whitespace-nowrap"
                                    :class="filterStatus === 'INACTIVE' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                >
                                    Nonaktif
                                </button>
                            </div>
                        </div>

                        <!-- Request list -->
                        <div v-if="isLoadingRequests" class="py-12 text-center text-xs text-gray-400">
                            <i class="ri-loader-4-line animate-spin text-xl block mb-1"></i>
                            Memuat daftar supir mitra...
                        </div>

                        <div v-else-if="filteredRequests.length > 0" class="space-y-2.5 flex-1 overflow-y-auto pr-0.5">
                            <div 
                                v-for="req in filteredRequests" 
                                :key="req.id"
                                class="p-3.5 rounded-2xl border transition shadow-sm bg-white"
                                :class="req.status === 'PENDING' ? 'border-amber-300 ring-1 ring-amber-100 bg-amber-50/20' : 'border-gray-100 hover:border-gray-200'"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <div 
                                            class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm text-white shrink-0 shadow-sm"
                                            :class="req.status === 'ACTIVE' ? 'bg-emerald-600' : (req.status === 'PENDING' ? 'bg-amber-500' : 'bg-gray-400')"
                                        >
                                            {{ req.name.substring(0, 2).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <h4 class="font-bold text-xs text-gray-900 truncate">{{ req.name }}</h4>
                                                <span 
                                                    class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-md uppercase"
                                                    :class="req.status === 'ACTIVE' ? 'bg-emerald-100 text-emerald-800' : (req.status === 'PENDING' ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-gray-100 text-gray-600')"
                                                >
                                                    {{ req.status === 'ACTIVE' ? 'Mitra Aktif' : (req.status === 'PENDING' ? 'Menunggu Persetujuan' : req.status) }}
                                                </span>
                                            </div>

                                            <p class="text-[11px] text-gray-600 mt-0.5 flex items-center gap-2">
                                                <span class="font-medium flex items-center gap-1">
                                                    <i class="ri-truck-line text-gray-400"></i>
                                                    {{ req.vehicle_name }}
                                                </span>
                                                <span class="text-gray-300">•</span>
                                                <span class="text-gray-500 text-[10px]">{{ req.role_label }}</span>
                                            </p>

                                            <div class="mt-1 flex items-center gap-3 text-[10px] text-gray-400">
                                                <span>Scan: {{ req.created_at_human }}</span>
                                                <a 
                                                    v-if="req.wa_url" 
                                                    :href="req.wa_url" 
                                                    target="_blank" 
                                                    class="text-emerald-600 hover:text-emerald-700 font-bold flex items-center gap-0.5"
                                                >
                                                    <i class="ri-whatsapp-line"></i> {{ req.phone }}
                                                </a>
                                                <span v-else>{{ req.phone }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons per Status -->
                                <div class="mt-3 pt-2.5 border-t border-gray-100 flex items-center justify-end gap-2">
                                    <!-- Jika PENDING: Tampilkan Setujui & Tolak -->
                                    <template v-if="req.status === 'PENDING'">
                                        <button
                                            type="button"
                                            @click="handleUpdateStatus(req.id, 'REJECTED')"
                                            :disabled="updatingDriverId === req.id"
                                            class="px-3 py-1.5 rounded-xl border border-gray-300 hover:bg-gray-100 text-gray-600 font-bold text-xs transition disabled:opacity-50"
                                        >
                                            Tolak
                                        </button>
                                        <button
                                            type="button"
                                            @click="handleUpdateStatus(req.id, 'ACTIVE')"
                                            :disabled="updatingDriverId === req.id"
                                            class="px-4 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition flex items-center gap-1 shadow-sm disabled:opacity-50"
                                        >
                                            <i v-if="updatingDriverId === req.id" class="ri-loader-4-line animate-spin"></i>
                                            <i v-else class="ri-check-line font-bold"></i>
                                            <span>Setujui Kemitraan</span>
                                        </button>
                                    </template>

                                    <!-- Jika ACTIVE: Tampilkan Nonaktifkan -->
                                    <template v-else-if="req.status === 'ACTIVE'">
                                        <button
                                            type="button"
                                            @click="handleUpdateStatus(req.id, 'INACTIVE')"
                                            :disabled="updatingDriverId === req.id"
                                            class="px-3 py-1.5 rounded-xl bg-gray-50 hover:bg-red-50 text-gray-500 hover:text-red-600 font-semibold text-xs transition border border-gray-200/80 disabled:opacity-50"
                                        >
                                            Nonaktifkan
                                        </button>
                                    </template>

                                    <!-- Jika REJECTED atau INACTIVE: Tampilkan Aktifkan Kembali -->
                                    <template v-else>
                                        <button
                                            type="button"
                                            @click="handleUpdateStatus(req.id, 'ACTIVE')"
                                            :disabled="updatingDriverId === req.id"
                                            class="px-3 py-1.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs transition disabled:opacity-50"
                                        >
                                            Aktifkan Kembali
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-else class="py-12 text-center text-gray-400 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                            <i class="ri-user-search-line text-3xl block mb-2 text-gray-300"></i>
                            <p class="text-xs font-bold text-gray-600">Tidak ada data supir ditemukan.</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">
                                {{ filterStatus === 'PENDING' ? 'Belum ada permohonan kemitraan baru.' : 'Belum ada armada supir terdaftar.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="p-3 bg-gray-50 border-t border-gray-100 shrink-0 flex items-center justify-between text-xs">
                        <span class="text-gray-400 text-[11px]">
                            Total Terhubung: <strong class="text-gray-700">{{ stats.active_count }} Armada</strong>
                        </span>
                        <button
                            type="button"
                            @click="emit('close')"
                            class="py-1.5 px-4 rounded-xl bg-white border border-gray-200 hover:bg-gray-100 text-gray-700 font-bold text-xs transition"
                        >
                            Tutup
                        </button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.animate-scaleUp {
    animation: scaleUp 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes scaleUp {
    from {
        opacity: 0;
        transform: scale(0.96);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.18s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
