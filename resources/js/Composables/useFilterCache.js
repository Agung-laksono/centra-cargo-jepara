import { ref, watch } from 'vue';

export function useFilterCache(storageKey, defaultState) {
    let initialState = defaultState;
    
    // Coba baca dari localStorage
    try {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem(storageKey);
            if (saved !== null) {
                // Check if the default state was an object
                if (typeof defaultState === 'object' && defaultState !== null) {
                    initialState = { ...defaultState, ...JSON.parse(saved) };
                } else {
                    initialState = JSON.parse(saved);
                }
            }
        }
    } catch (e) {}

    const filterState = ref(initialState);

    // Watch perubahan state
    watch(filterState, (newVal) => {
        try {
            if (typeof window !== 'undefined') {
                localStorage.setItem(storageKey, JSON.stringify(newVal));
            }
        } catch (e) {}
    }, { deep: true });

    return filterState;
}
