<script setup>
defineProps({
    loading: {
        type: Boolean,
        default: false
    },
    hasMore: {
        type: Boolean,
        default: true
    },
    text: {
        type: String,
        default: 'Muat Lebih Banyak'
    },
    loadingText: {
        type: String,
        default: 'Memuat...'
    }
});

defineEmits(['load-more']);
</script>

<template>
    <div v-if="hasMore" class="p-4 flex justify-center border-t border-gray-100 bg-gray-50/30">
        <button 
            @click="$emit('load-more')" 
            :disabled="loading"
            class="px-6 py-2.5 bg-white border border-gray-200 text-primary font-bold rounded-xl hover:bg-orange-50 hover:border-primary transition-all disabled:opacity-50 flex items-center gap-2 shadow-sm"
        >
            <i v-if="loading" class="ri-loader-4-line animate-spin"></i>
            <i v-else class="ri-refresh-line"></i>
            {{ loading ? loadingText : text }}
        </button>
    </div>
</template>
