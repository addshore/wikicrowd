import { ref, watch, onMounted, onUnmounted } from 'vue';

const isOfflineModeEnabled = ref(localStorage.getItem('offlineModeEnabled') === 'true');
const offlineQuestionCount = ref(0);
const offlineAnswerCount = ref(0);

function updateOfflineStats() {
    let qCount = 0;
    let aCount = 0;
    for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key.startsWith('wikicrowd-questions-')) {
            try {
                const questions = JSON.parse(localStorage.getItem(key));
                qCount += questions.length;
            } catch (e) { console.error(e); }
        }
        if (key === 'wikicrowd-answers-offline') {
             try {
                const answers = JSON.parse(localStorage.getItem(key));
                aCount += answers.length;
            } catch (e) { console.error(e); }
        }
    }
    offlineQuestionCount.value = qCount;
    offlineAnswerCount.value = aCount;
}

watch(isOfflineModeEnabled, (newValue) => {
    localStorage.setItem('offlineModeEnabled', newValue.toString());
});

function handleStorageChange(event) {
    if (event.key?.startsWith('wikicrowd-') || event.key === 'offlineModeEnabled') {
        if (event.key === 'offlineModeEnabled') {
            isOfflineModeEnabled.value = event.newValue === 'true';
        }
        updateOfflineStats();
    }
}

export function useOfflineMode() {
    onMounted(() => {
        updateOfflineStats();
        window.addEventListener('storage', handleStorageChange);
    });

    onUnmounted(() => {
        window.removeEventListener('storage', handleStorageChange);
    });

    return {
        isOfflineModeEnabled,
        offlineQuestionCount,
        offlineAnswerCount,
        updateOfflineStats
    };
}
