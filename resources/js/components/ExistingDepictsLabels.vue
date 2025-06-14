<template>
  <div v-if="loading" class="text-xs text-gray-500 dark:text-gray-400 italic">Loading depicts...</div>
  <div v-else-if="depicts && depicts.length > 0" class="flex flex-wrap gap-1 mt-1 justify-center">
    <span 
      v-for="qid in depicts" 
      :key="qid"
      class="inline-block bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-100 text-xs px-2 py-0.5 rounded-full border transition-all hover:bg-blue-200 dark:hover:bg-blue-700"
      :title="`View ${qid} on Wikidata`"
    >
      <a 
        :href="`https://www.wikidata.org/wiki/${qid}`" 
        target="_blank" 
        class="text-inherit no-underline hover:underline"
        @click.stop
      >
        <WikidataLabel :qid="qid" :fallback="qid" />
      </a>
    </span>
  </div>
  <div v-else-if="!loading && depicts.length === 0" class="text-xs text-gray-400 italic">No existing depicts</div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import WikidataLabel from './WikidataLabel.vue';
import { fetchDepictsForMediaInfoIds } from './depictsUtils.js';

const props = defineProps({
  mediaInfoId: {
    type: String,
    required: true
  }
});

const depicts = ref([]);
const loading = ref(false);

async function fetchDepicts() {
  if (!props.mediaInfoId) {
    depicts.value = [];
    return;
  }
  
  loading.value = true;
  try {
    const depictsMap = await fetchDepictsForMediaInfoIds([props.mediaInfoId]);
    depicts.value = depictsMap[props.mediaInfoId] || [];
  } catch (error) {
    console.error('Error fetching depicts for', props.mediaInfoId, error);
    depicts.value = [];
  } finally {
    loading.value = false;
  }
}

watch(() => props.mediaInfoId, fetchDepicts, { immediate: true });
</script>
