<template>
  <span>
    <span v-if="label">{{ label }}</span>
    <span v-else-if="loading" class="italic text-gray-400">Loading label...</span>
    <span v-else-if="error" class="italic text-red-400">No label found</span>
  </span>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { fetchWikimediaRestApi } from '../wikimediaApi.js';

const props = defineProps({
    qid: { type: String, required: true },
    fallback: { type: String, default: '' },
});

const label = ref('');
const loading = ref(false);
const error = ref(false);
const cache = {};

async function fetchWikidataLabel(qid) {
    if (!qid) return null;
    if (cache[qid]) return cache[qid];
    try {
        loading.value = true;
        error.value = false;
        const resp = await fetchWikimediaRestApi('wikidata', {
            endpoint: 'labels_with_language_fallback',
            qid,
            lang: 'en',
        });
        if (resp.ok) {
            const data = await resp.json();
            const labelValue = typeof data === 'string' ? data : data?.value;
            if (labelValue) {
                cache[qid] = labelValue;
                return labelValue;
            }
        }
    } catch (e) {
        // error handled below
    } finally {
        loading.value = false;
    }
    return null;
}

async function updateLabel() {
    label.value = '';
    loading.value = true;
    error.value = false;
    const lbl = await fetchWikidataLabel(props.qid);
    if (lbl) {
        label.value = lbl;
        error.value = false;
    } else if (props.fallback) {
        label.value = props.fallback;
        error.value = false;
    } else {
        error.value = true;
    }
    loading.value = false;
}

watch(() => props.qid, updateLabel, { immediate: false });
onMounted(updateLabel);
</script>
