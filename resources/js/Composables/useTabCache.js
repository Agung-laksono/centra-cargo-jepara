import { ref, watch, onMounted } from 'vue';

export function useTabCache(storageKey, defaultTab) {
    let initialTab = defaultTab;
    
    // Coba baca dari localStorage
    try {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem(storageKey);
            if (saved) initialTab = saved;
        }
    } catch (e) {}

    const activeTab = ref(initialTab);
    const scrollPositions = ref({});

    // Watch perubahan tab
    watch(activeTab, (newVal, oldVal) => {
        if (typeof window === 'undefined') return;
        
        // Simpan posisi scroll tab lama
        if (oldVal) {
            scrollPositions.value[oldVal] = window.scrollY;
        }
        
        // Simpan tab yang aktif ke localStorage
        try {
            localStorage.setItem(storageKey, newVal);
        } catch (e) {}

        // Kembalikan posisi scroll tab baru
        requestAnimationFrame(() => {
            window.scrollTo({
                top: scrollPositions.value[newVal] || 0,
                behavior: 'instant'
            });
        });
    });

    return {
        activeTab,
        scrollPositions
    };
}
