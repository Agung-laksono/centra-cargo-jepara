<template>
    <Transition name="drawer">
        <div 
            v-if="show" 
            class="fixed inset-x-0 bottom-0 top-0 z-[100] lg:sticky lg:top-[88px] lg:z-0 lg:w-72 xl:w-80 shrink-0 flex flex-col justify-end items-center lg:items-start lg:justify-start bg-gray-900/50 lg:bg-transparent backdrop-blur-sm lg:backdrop-blur-none drawer-wrapper overflow-hidden lg:overflow-visible" 
            @click.self="close"
        >
            <div class="drawer-content mt-auto lg:mt-0 bg-white w-full sm:w-[450px] lg:w-full max-h-[85vh] lg:max-h-[calc(100vh-100px)] rounded-t-3xl lg:rounded-2xl shadow-2xl lg:shadow-sm border-0 lg:border lg:border-gray-100 flex flex-col transform overflow-hidden">
                
                <!-- Drag handle for mobile -->
                <div v-if="!hideDragHandle" class="w-full flex justify-center pt-3 pb-1 lg:hidden bg-white shrink-0">
                    <div class="w-12 h-1.5 bg-gray-300 rounded-full"></div>
                </div>
                
                <!-- Header Drawer -->
                <div v-if="title" class="px-6 py-4 border-b border-gray-100 flex justify-between items-center shrink-0">
                    <h3 class="font-bold text-lg text-dark flex items-center gap-2">
                        <slot name="icon"></slot>
                        {{ title }}
                    </h3>
                    <button @click="close" class="text-gray-400 hover:text-gray-600 transition w-8 h-8 flex items-center justify-center bg-gray-100 rounded-full hover:bg-gray-200">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5 overflow-y-auto flex-1 space-y-6 no-scrollbar">
                    <slot></slot>
                </div>

                <!-- Footer (Optional) -->
                <div v-if="$slots.footer" class="p-5 border-t border-gray-100 shrink-0 bg-white space-y-3">
                    <slot name="footer"></slot>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    title: {
        type: String,
        default: ''
    },
    hideDragHandle: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'close']);

const show = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
});

const close = () => {
    show.value = false;
    emit('close');
};
</script>

<style scoped>
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
