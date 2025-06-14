<template>
  <span>
    <span v-if="description">{{ description }}</span>
    <span v-else-if="loading" class="italic text-gray-400">Loading description...</span>
    <span v-else-if="error" class="italic text-red-400">No description found</span>
  </span>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
  qid: { type: String, required: true },
  fallback: { type: String, default: '' },
});

const description = ref(props.fallback || '');
const loading = ref(false);
const error = ref(false);
const cache = {};

async function fetchWikidataDescription(qid) {
  if (!qid) return null;
  if (cache[qid]) return cache[qid];
  try {
    loading.value = true;
    error.value = false;
    const resp = await fetch(`https://www.wikidata.org/w/rest.php/wikibase/v1/entities/items/${qid}/descriptions_with_language_fallback/en`, {
      redirect: 'follow'
    });
    if (resp.ok) {
      const data = await resp.json();
      if (typeof data === 'string') {
        cache[qid] = data;
        return data;
      }
      if (data.value) {
        cache[qid] = data.value;
        return data.value;
      }
    }
  } catch (e) {
    error.value = true;
  } finally {
    loading.value = false;
  }
  error.value = true;
  return null;
}

async function updateDescription() {
  if (props.fallback) {
    description.value = props.fallback;
    loading.value = false;
    error.value = false;
    return;
  }
  description.value = '';
  loading.value = true;
  error.value = false;
  const desc = await fetchWikidataDescription(props.qid);
  if (desc) {
    description.value = desc;
    error.value = false;
  } else {
    error.value = true;
  }
  loading.value = false;
}

watch(() => props.qid, updateDescription, { immediate: true });
</script>
