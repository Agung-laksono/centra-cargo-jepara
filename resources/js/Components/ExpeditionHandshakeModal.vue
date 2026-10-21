<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    show: { type: Boolean, default: false },
    expeditionId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close']);

const isLoading = ref(false);
const expeditionData = ref(null);
const copySuccess = ref(false);
const copyLinkSuccess = ref(false);

const fetchQrData = async () => {
    try {
        isLoading.value = true;
        const res = await axios.get('/api/expedition/handshake-qr', {
            params: { expedition_id: props.expeditionId }
        });
        if (res.data && res.data.expedition) {
            expeditionData.value = res.data.expedition;
        }
    } catch (e) {
        console.warn('Gagal memuat QR Kemitraan ekspedisi:', e);
    } finally {
        isLoading.value = false;
    }
};

watch(() => props.show, (isOpen) => {
    if (isOpen) {
        fetchQrData();
    }
});

const qrImageUrl = computed(() => {
    if (!expeditionData.value?.qr_value) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=${encodeURIComponent(expeditionData.value.qr_value)}`;
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

const printQrFlyer = () => {
    window.print();
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
                class="fixed inset-0 z-[220] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
                @click.self="emit('close')"
            >
                <div 
                    class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-gray-100 relative text-center flex flex-col items-center"
                    @click.stop
                >
                    <!-- Close button -->
                    <button 
                        @click="emit('close')"
                        class="absolute top-4 right-4 w-8 h-8 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 flex items-center justify-center transition"
                    >
                        <i class="ri-close-line text-lg"></i>
                    </button>

                    <!-- Icon & Title -->
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl mb-2">
                        <i class="ri-qr-code-line"></i>
                    </div>
                    <h3 class="font-extrabold text-base text-gray-900">QR Kemitraan Armada Supir</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ expeditionData?.name || 'Ekspedisi Rekanan' }}
                    </p>

                    <!-- Loading skeleton -->
                    <div v-if="isLoading" class="w-56 h-56 my-5 bg-gray-100 rounded-2xl animate-pulse flex items-center justify-center text-gray-400 text-xs">
                        Membuat QR Kemitraan...
                    </div>

                    <!-- QR Display Box -->
                    <div v-else-if="expeditionData" class="my-4 p-3 bg-white rounded-2xl border-2 border-dashed border-gray-200 shadow-sm flex flex-col items-center">
                        <img 
                            :src="qrImageUrl" 
                            alt="QR Kemitraan Supir" 
                            class="w-52 h-52 object-contain rounded-xl"
                        />
                        <div class="mt-2 text-[10px] text-gray-400 uppercase tracking-widest font-bold">
                            Scan via Aplikasi Supir
                        </div>
                    </div>

                    <!-- Pairing Code Box -->
                    <div v-if="expeditionData" class="w-full bg-gray-50 p-3 rounded-2xl border border-gray-100 flex items-center justify-between mb-4">
                        <div class="text-left">
                            <span class="text-[10px] text-gray-400 block font-bold uppercase tracking-wider">Kode Kemitraan Manual</span>
                            <span class="text-base font-black font-mono text-gray-900 tracking-wider">
                                {{ expeditionData.partnership_token }}
                            </span>
                        </div>
                        <button
                            type="button"
                            @click="copyCode"
                            class="px-3 py-1.5 rounded-xl bg-white border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-100 active:scale-95 transition shadow-sm flex items-center gap-1"
                        >
                            <i :class="copySuccess ? 'ri-checkbox-circle-fill text-green-600' : 'ri-file-copy-line'"></i>
                            <span>{{ copySuccess ? 'Tersalin' : 'Salin' }}</span>
                        </button>
                    </div>

                    <!-- Action buttons -->
                    <div class="w-full space-y-2">
                        <button
                            type="button"
                            @click="copyLink"
                            class="w-full py-2.5 px-3 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs flex items-center justify-center gap-1.5 transition border border-blue-200/80 active:scale-[0.98]"
                        >
                            <i :class="copyLinkSuccess ? 'ri-checkbox-circle-fill text-emerald-600' : 'ri-links-line'"></i>
                            <span>{{ copyLinkSuccess ? 'Link Pendaftaran Tersalin!' : 'Salin Tautan WhatsApp Supir' }}</span>
                        </button>

                        <button
                            type="button"
                            @click="emit('close')"
                            class="w-full py-2 px-3 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold text-xs transition"
                        >
                            Tutup
                        </button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>
