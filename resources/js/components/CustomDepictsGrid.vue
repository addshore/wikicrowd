<template>
  <div class='flex flex-col items-center w-full'>
    <div class='w-full max-w-xl p-6'>
      <h1 class='text-2xl font-bold mb-4 text-center text-gray-900 dark:text-gray-100'>Custom Depicts Grid</h1>
      <form @submit.prevent="generateGrid" class='mb-6 flex flex-col gap-4'>
        <div>
          <label class='block font-semibold mb-1 text-gray-800 dark:text-gray-200'>Commons Category</label>
          <input v-model="manualCategory" @input="updateUrl" class='border rounded px-2 py-1 w-full bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700' placeholder='e.g. Paintings by Vincent van Gogh' required />
        </div>
        <div>
          <label class='block font-semibold mb-1 text-gray-800 dark:text-gray-200'>Wikidata Qid</label>
          <input v-model="manualQid" @input="updateUrl" class='border rounded px-2 py-1 w-full bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700' placeholder='e.g. Q5582' required />
        </div>
        <button type='submit' class='bg-blue-600 dark:bg-blue-700 text-white px-4 py-2 rounded font-bold'>Generate Grid</button>
      </form>
    </div>
    <div class='w-full'>
      <GridMode v-if="showGrid" :manual-category="manualCategory" :manual-qid="manualQid" :manual-mode="true" key="gridKey" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import GridMode from './GridMode.vue';

const manualCategory = ref('Category:Windsurfing');
const manualQid = ref('Q191051');
const showGrid = ref(false);
const gridKey = ref(0); // Used to force re-render of GridMode

function getQueryParam(name) {
  const url = new URL(window.location.href);
  return url.searchParams.get(name);
}

function updateUrl() {
  const url = new URL(window.location.href);
  url.searchParams.set('category', manualCategory.value);
  url.searchParams.set('item', manualQid.value);
  window.history.replaceState({}, '', url);
}

function generateGrid() {
  showGrid.value = false;
  gridKey.value++;
  setTimeout(() => {
    showGrid.value = true;
  }, 0);
}

onMounted(() => {
  const category = getQueryParam('category');
  const item = getQueryParam('item');
  if (category && item) {
    manualCategory.value = category;
    manualQid.value = item;
    showGrid.value = true;
  }
});
</script>
