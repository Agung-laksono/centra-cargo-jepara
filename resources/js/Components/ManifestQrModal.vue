<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    manifest: {
        type: Object,
        default: () => null,
    },
});

const emit = defineEmits(['close']);

const copySuccess = ref(false);

const manifestToken = computed(() => {
    return props.manifest?.token || props.manifest?.magic_link_token || '';
});

const driverLink = computed(() => {
    if (!props.manifest) return '';
    const isPickup = props.manifest.type === 'PICKUP' || props.manifest.manifest_type === 'PICKUP';
    const baseUrl = isPickup ? '/supir/pickup' : '/supir/lintas';
    return `${window.location.origin}${baseUrl}?token=${manifestToken.value}`;
});

const qrImageUrl = computed(() => {
    if (!manifestToken.value) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=8&data=${encodeURIComponent(manifestToken.value)}`;
});

const handleCopyToken = () => {
    if (!manifestToken.value) return;
    navigator.clipboard.writeText(manifestToken.value);
    copySuccess.value = true;
    setTimeout(() => { copySuccess.value = false; }, 2500);
};

const handlePrint = () => {
    if (!props.manifest?.id) return;
    const printUrl = `/manifest/${props.manifest.id}/print?autoprint=1`;
    window.open(printUrl, '_blank', 'width=900,height=800,menubar=no,toolbar=no,location=no');
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
                v-if="show && manifest" 
                class="fixed inset-0 z-[220] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto"
                @click.self="emit('close')"
            >
                <div class="bg-white w-full max-w-sm rounded-3xl p-5 shadow-2xl border border-gray-100 text-center relative animate-slide-up">
                    
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
                        <div class="flex items-center gap-2 text-left">
                            <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                                <i class="ri-qr-code-line text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-extrabold text-sm text-gray-900 leading-tight">QR Handshake Surat Jalan</h4>
                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                                    MNF-{{ String(manifest.id).padStart(4, '0') }} • {{ manifest.type === 'PICKUP' ? 'Pickup' : 'Lintas' }}
                                </p>
                            </div>
                        </div>
                        <button 
                            @click="emit('close')" 
                            class="w-7 h-7 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-400 hover:text-gray-700 flex items-center justify-center transition cursor-pointer"
                        >
                            <i class="ri-close-line text-lg"></i>
                        </button>
                    </div>

                    <!-- QR Box -->
                    <div class="relative bg-gradient-to-b from-gray-50 to-white p-4 rounded-2xl border-2 border-dashed border-gray-200 flex flex-col items-center justify-center mb-3.5 shadow-inner">
                        <img 
                            :src="qrImageUrl" 
                            alt="QR Handshake Manifest" 
                            class="w-52 h-52 object-contain rounded-xl shadow-sm bg-white p-1"
                        />
                        <div class="mt-2.5 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-extrabold uppercase">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Scan via HP Supir
                        </div>
                    </div>

                    <!-- Instruksi Supir -->
                    <p class="text-xs text-gray-600 font-medium leading-relaxed mb-3">
                        Arahkan kamera HP supir ke QR di atas untuk serah terima & mengambil tugas pengiriman ini secara otomatis.
                    </p>

                    <!-- Info Supir & Muatan -->
                    <div class="bg-gray-50 rounded-xl p-2.5 border border-gray-100 text-left text-xs mb-4 space-y-1">
                        <div class="flex justify-between items-center text-gray-500 text-[11px]">
                            <span>Supir Armada:</span>
                            <strong class="text-gray-800">
                                {{ (manifest.driver && manifest.driver !== 'Vendor') ? (manifest.driver?.name || manifest.driver) : (manifest.vendor_name ? 'Vendor: ' + manifest.vendor_name : 'Bebas (Tentukan via Handshake)') }}
                            </strong>
                        </div>
                        <div class="flex justify-between items-center text-gray-500 text-[11px]">
                            <span>Total Muatan:</span>
                            <strong class="text-primary font-bold">{{ manifest.total_koli }} Koli</strong>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="space-y-2">
                        <!-- Tombol Cetak Dokumen Resmi -->
                        <button 
                            type="button" 
                            @click="handlePrint"
                            class="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-black text-white font-bold text-xs flex items-center justify-center gap-2 transition active:scale-[0.98] shadow-md cursor-pointer"
                        >
                            <i class="ri-printer-line text-sm text-primary"></i>
                            <span>Cetak Surat Jalan Fisik (Print)</span>
                        </button>

                        <div class="grid grid-cols-2 gap-2">
                            <!-- Tombol Salin Token -->
                            <button 
                                type="button" 
                                @click="handleCopyToken"
                                class="py-2 px-3 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs flex items-center justify-center gap-1.5 transition active:scale-95 cursor-pointer"
                            >
                                <i :class="copySuccess ? 'ri-checkbox-circle-fill text-green-600' : 'ri-file-copy-line text-gray-500'"></i>
                                <span>{{ copySuccess ? 'Tersalin!' : 'Salin Kode' }}</span>
                            </button>

                            <!-- Tombol Buka Layar Supir -->
                            <a 
                                :href="driverLink" 
                                target="_blank"
                                class="py-2 px-3 rounded-xl bg-primary/10 hover:bg-primary/20 text-primary font-bold text-xs flex items-center justify-center gap-1.5 border border-primary/20 transition active:scale-95 text-center"
                            >
                                <i class="ri-external-link-line"></i>
                                <span>Layar Supir</span>
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.animate-slide-up {
    animation: slideUp 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes slideUp {
    from {
        transform: translateY(20px) scale(0.97);
        opacity: 0;
    }
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}
</style>
