<template>
    <div class="flex-1 min-w-0 w-full overflow-x-clip min-h-[85vh]" @touchstart="handleTouchStart" @touchmove="handleTouchMove" @touchend="handleTouchEnd">
        <div class="flex items-start" :style="swipeStyle">
            <div 
                v-for="(tab, index) in tabs" 
                :key="tab.id" 
                class="w-full shrink-0 transition-opacity duration-300"
                :class="[
                    index === 0 ? 'pr-3 lg:pr-4' : (index === tabs.length - 1 ? 'pl-3 lg:pl-4' : 'px-3 lg:px-4'),
                    activeTabId === tab.id || isDragging ? 'h-auto overflow-visible opacity-100' : 'h-0 overflow-hidden opacity-0 pointer-events-none'
                ]"
                :style="{ width: `${100 / tabs.length}%` }"
            >
                <slot :name="tab.id"></slot>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        required: true
    },
    tabs: {
        type: Array,
        required: true,
        // example: [{ id: 'antrean' }, { id: 'riwayat' }]
    }
});

const emit = defineEmits(['update:modelValue']);

// Sync with v-model
const activeTabId = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

// Swipe logic for tabs (Dynamic)
const touchStartX = ref(0);
const touchStartY = ref(0);
const touchCurrentX = ref(0);
const isDragging = ref(false);
const isScrolling = ref(false);

const handleTouchStart = (e) => {
    if (e.touches && e.touches.length > 1) return;
    
    const startX = e.changedTouches[0].screenX;
    const viewportWidth = typeof window !== 'undefined' ? window.innerWidth : 400;
    const edgeThreshold = 50;
    
    // Abaikan jika ini dari pinggir layar (dibiarkan untuk navigasi halaman)
    if (startX <= edgeThreshold || startX >= viewportWidth - edgeThreshold) {
        return;
    }

    touchStartX.value = startX;
    touchStartY.value = e.changedTouches[0].screenY;
    touchCurrentX.value = touchStartX.value;
    isDragging.value = false; // Kita belum tahu arahnya
    isScrolling.value = false;
};

const handleTouchMove = (e) => {
    if (isScrolling.value) return; // Abaikan jika ini scroll vertikal

    const currentX = e.changedTouches[0].screenX;
    const currentY = e.changedTouches[0].screenY;
    
    // Jika arah belum ditentukan
    if (!isDragging.value && !isScrolling.value) {
        const diffX = Math.abs(currentX - touchStartX.value);
        const diffY = Math.abs(currentY - touchStartY.value);
        
        // Tunggu jari bergerak setidaknya 5px untuk menentukan arah pasti
        if (diffX > 5 || diffY > 5) {
            if (diffX > diffY) {
                // Sengaja geser horizontal (Swipe Tab)
                isDragging.value = true;
            } else {
                // Sengaja geser vertikal (Scroll Halaman)
                isScrolling.value = true;
                return;
            }
        } else {
            return; // Gerakan belum cukup signifikan
        }
    }
    
    // Jika sudah dipastikan geser horizontal, update posisi X
    if (isDragging.value) {
        touchCurrentX.value = currentX;
    }
};

const handleTouchEnd = (e) => {
    if (isScrolling.value) {
        isScrolling.value = false;
        return;
    }
    if (!isDragging.value) return;
    
    isDragging.value = false;
    touchCurrentX.value = e.changedTouches[0].screenX;
    
    const swipeDistance = touchCurrentX.value - touchStartX.value;
    const minSwipeDistance = 50; 
    
    const currentIndex = props.tabs.findIndex(t => t.id === activeTabId.value);
    
    if (swipeDistance > minSwipeDistance) {
        // Swiped right -> go to previous tab
        if (currentIndex > 0) {
            activeTabId.value = props.tabs[currentIndex - 1].id;
        }
    } else if (swipeDistance < -minSwipeDistance) {
        // Swiped left -> go to next tab
        if (currentIndex < props.tabs.length - 1) {
            activeTabId.value = props.tabs[currentIndex + 1].id;
        }
    }
    
    touchStartX.value = 0;
    touchCurrentX.value = 0;
};

const swipeStyle = computed(() => {
    const currentIndex = props.tabs.findIndex(t => t.id === activeTabId.value);
    const containerWidthPercent = props.tabs.length * 100;
    const sectionWidthPercent = 100 / props.tabs.length;
    
    let baseTranslate = -(currentIndex * sectionWidthPercent);
    
    if (!isDragging.value) {
        return { 
            width: `${containerWidthPercent}%`,
            transform: `translateX(${baseTranslate}%)`,
            transition: 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)' 
        };
    }
    
    const delta = touchCurrentX.value - touchStartX.value;
    const viewportWidth = typeof window !== 'undefined' ? window.innerWidth : 400;
    
    let deltaPercent = (delta / viewportWidth) * sectionWidthPercent; 
    
    // Resistance if pulled past the edges
    if ((currentIndex === 0 && deltaPercent > 0) || (currentIndex === props.tabs.length - 1 && deltaPercent < 0)) {
        deltaPercent *= 0.3;
    }
    
    return {
        width: `${containerWidthPercent}%`,
        transform: `translateX(calc(${baseTranslate}% + ${deltaPercent}%))`,
        transition: 'none',
    };
});
</script>
