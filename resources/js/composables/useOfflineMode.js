import { ref, watch } from 'vue';

const isOfflineModeEnabled = ref(localStorage.getItem('offlineModeEnabled') === 'true');

watch(isOfflineModeEnabled, (newValue) => {
    localStorage.setItem('offlineModeEnabled', newValue);
});

export function useOfflineMode() {
    return {
        isOfflineModeEnabled
    };
}
