<template>
  <div class='flex flex-col items-center w-full'>
    <div class='w-full max-w-xl p-6'>
      <h1 class='text-2xl font-bold mb-4 text-center text-gray-900 dark:text-gray-100'>Custom Depicts Grid</h1>
      <form @submit.prevent="generateGrid" class='mb-6 flex flex-col gap-4'>
        <div class="relative">
          <label class='block font-semibold mb-1 text-gray-800 dark:text-gray-200'>Commons Category</label>
          <div class="flex items-center gap-2">
            <input v-model="manualCategory" @input="onCategoryInput" @focus="showCategoryDropdown = true" @blur="onCategoryBlur" class='border rounded px-2 py-1 w-full bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700' placeholder='e.g. Paintings by Vincent van Gogh' required autocomplete="off" />
            <a v-if="manualCategory" :href="categoryUrl" target="_blank" rel="noopener" class="ml-1 text-blue-600 dark:text-blue-400" title="Open category"><svg xmlns="http://www.w3.org/2000/svg" class="inline w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 3h7v7m0 0L10 21l-7-7L21 10z"/></svg></a>
            <button type="button" @click="onClickAutoFillQidFromCategory" class="ml-1 px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600" title="Auto-fill Qid from category">Auto Wikidata ID</button>
          </div>
          <ul v-if="showCategoryDropdown && categoryResults.length" class="absolute z-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 w-full mt-1 rounded shadow max-h-60 overflow-auto">
            <li v-for="(result, idx) in categoryResults" :key="result" @mousedown.prevent="selectCategory(result)" class="px-3 py-2 hover:bg-blue-100 dark:hover:bg-blue-900 cursor-pointer text-gray-900 dark:text-gray-200">{{ result }}</li>
          </ul>
        </div>
        <div class="relative">
          <label class='block font-semibold mb-1 text-gray-800 dark:text-gray-200'>Wikidata Qid</label>
          <div class="flex items-center gap-2">
            <input v-model="manualQid" @input="onQidInput" @focus="showQidDropdown = true" @blur="hideQidDropdown" :class="['border rounded px-2 py-1 w-full bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700', isCategoryQid ? 'input-category-qid-bad' : '']" placeholder='e.g. Q5582' required autocomplete="off" />
            <a v-if="manualQid" :href="qidUrl" target="_blank" rel="noopener" class="ml-1 text-blue-600 dark:text-blue-400" title="Open item"><svg xmlns="http://www.w3.org/2000/svg" class="inline w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 3h7v7m0 0L10 21l-7-7L21 10z"/></svg></a>
            <button type="button" @click="autoFillCategoryFromQid" class="ml-1 px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600" title="Auto-fill category from Qid">Auto Commons Category</button>
          </div>
          <ul v-if="showQidDropdown && qidResults.length" class="absolute z-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 w-full mt-1 rounded shadow max-h-60 overflow-auto">
            <li v-for="item in qidResults" :key="item.id" @mousedown.prevent="selectQid(item)" class="px-3 py-2 hover:bg-blue-100 dark:hover:bg-blue-900 cursor-pointer text-gray-900 dark:text-gray-200">
              <span class="font-mono">{{ item.id }}</span> - {{ item.label }}
              <span v-if="item.description" class="block text-xs text-gray-600 dark:text-gray-400 ml-6">{{ item.description }}</span>
            </li>
          </ul>
        </div>
        <div class="flex gap-2">
          <button type='submit' class='flex-1 bg-blue-600 dark:bg-blue-700 text-white px-4 py-2 rounded font-bold' title="Loads images dynamically, a bit below the fold" :disabled="isDownloading">Dynamic grid</button>
          <button type='button' @click="generateFullGrid" class='flex-1 bg-green-600 dark:bg-green-700 text-white px-4 py-2 rounded font-bold' title="Loads all images in the category and its subcategories" :disabled="isDownloading">Full grid</button>
          <button v-if="isOfflineModeEnabled && !isDownloading" type='button' @click="downloadQuestions" class='flex-1 bg-purple-600 dark:bg-purple-700 text-white px-4 py-2 rounded font-bold' title="Download all questions for this custom grid">Download</button>
        </div>
        <div v-if="isDownloading" class="mt-2 text-center text-gray-700 dark:text-gray-300">{{ downloadProgress }}</div>
        <div v-if="autoError" class="mt-2 text-red-600 dark:text-red-400 text-sm">{{ autoError }}</div>
        <div v-if="categoryQidWarning" class="mt-2 text-red-600 dark:text-red-400 text-sm">{{ categoryQidWarning }}</div>
      </form>
    </div>
    <div class='w-full'>
      <GridMode v-if="showGrid && canShowGrid()" :manual-category="manualCategory" :manual-qid="manualQid" :manual-mode="true" :load-all="loadAll" :key="gridKey" @questions-loaded="onQuestionsLoaded" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useOfflineMode } from '../composables/useOfflineMode';
import GridMode from './GridMode.vue';

const { isOfflineModeEnabled, updateOfflineStats } = useOfflineMode();
const isDownloading = ref(false);
const downloadProgress = ref('');
const manualCategory = ref('');
const manualQid = ref('');
const showGrid = ref(false);
const gridKey = ref(0); // Used to force re-render of GridMode
const loadAll = ref(false); // New data property
const loadedQuestions = ref([]);

function onQuestionsLoaded(questions) {
  loadedQuestions.value = questions;
}

async function downloadQuestions() {
  if (loadedQuestions.value.length === 0) {
    alert('No questions loaded to download.');
    return;
  }

  isDownloading.value = true;
  downloadProgress.value = 'Saving question data...';

  try {
    const groupName = `custom-${manualCategory.value.replace('Category:', '')}-${manualQid.value}`;
    localStorage.setItem(`wikicrowd-questions-${groupName}`, JSON.stringify(loadedQuestions.value));
    updateOfflineStats();

    // Now download images
    const imageUrls = loadedQuestions.value.map(q => q.properties.img_url).filter(Boolean);
    const totalImages = imageUrls.length;
    const cache = await caches.open('wikicrowd-images-v1');

    for (let i = 0; i < totalImages; i++) {
        const url = imageUrls[i];
        downloadProgress.value = `Downloading image ${i + 1} of ${totalImages}...`;
        try {
            const imageResponse = await fetch(url);
            if (imageResponse.ok) {
                await cache.put(url, imageResponse);
            }
        } catch (e) {
            console.error(`Failed to fetch and cache image ${url}:`, e);
        }
    }

    alert(`Successfully downloaded ${loadedQuestions.value.length} questions and attempted to download ${totalImages} images for the custom grid.`);
  } catch (error) {
      console.error('Error downloading custom questions:', error);
      alert('An error occurred while downloading the custom questions.');
  } finally {
      isDownloading.value = false;
      downloadProgress.value = '';
  }
}

// --- Auto error state ---
const autoError = ref('');

// --- Category search state ---
const categoryResults = ref([]);
const showCategoryDropdown = ref(false);
let categorySearchTimeout;

// --- Qid search state ---
const qidResults = ref([]);
const showQidDropdown = ref(false);
let qidSearchTimeout;

// --- Category Qid check ---
const isCategoryQid = ref(false);
const categoryQidWarning = ref('');


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

function triggerGridGeneration() {
  showGrid.value = false;
  gridKey.value++;
  setTimeout(() => {
    showGrid.value = true;
  }, 0);
}

function generateDynamicGrid() {
  loadAll.value = false;
  triggerGridGeneration();
}

function generateFullGrid() {
  loadAll.value = true;
  triggerGridGeneration();
}

// The form submission will now call generateDynamicGrid
const generateGrid = generateDynamicGrid;


// --- Category search logic ---
function onCategoryInput() {
  updateUrl(); // Can be called immediately
  clearTimeout(categorySearchTimeout);

  if (!manualCategory.value.trim()) {
    categoryResults.value = [];
    showCategoryDropdown.value = false; // Hide dropdown if input is empty
    return;
  }

  categorySearchTimeout = setTimeout(async () => {
    const search = manualCategory.value.replace(/^Category:/i, '');
    const url = `https://commons.wikimedia.org/w/api.php?action=query&list=search&srsearch=${encodeURIComponent(search)}&srnamespace=14&srlimit=10&format=json&origin=*`;
    try {
      const resp = await fetch(url, { redirect: 'follow' });
      const data = await resp.json();
      categoryResults.value = (data.query?.search || []).map(r => {
        let title = r.title.replace(/^Category:/i, '');
        return 'Category:' + title;
      });
      if (categoryResults.value.length > 0) {
        showCategoryDropdown.value = true;
      } else {
        showCategoryDropdown.value = false;
      }
    } catch (error) {
      console.error("Error fetching category suggestions:", error);
      categoryResults.value = [];
      showCategoryDropdown.value = false;
    }
  }, 300); // Debounce delay of 300ms
}

async function handleCategoryRedirectByParsing(isManualInputOrBlur = false) {
  const originalManualCategory = manualCategory.value; // Store for message
  if (!manualCategory.value.trim()) {
    if (autoError.value.includes("redirected from")) autoError.value = '';
    return false;
  }

  const categoryForAPI = manualCategory.value.startsWith('Category:')
    ? manualCategory.value
    : 'Category:' + manualCategory.value;

  try {
    const apiUrl = `https://commons.wikimedia.org/w/api.php?action=parse&page=${encodeURIComponent(categoryForAPI)}&prop=wikitext&format=json&origin=*`;
    const response = await fetch(apiUrl, { redirect: 'follow' });
    if (!response.ok) {
      console.error('API error in handleCategoryRedirectByParsing (fetch not ok):', response.statusText);
      if (autoError.value.includes("redirected from")) autoError.value = '';
      return false;
    }
    const data = await response.json();

    if (data.error) {
      console.warn('API error in handleCategoryRedirectByParsing (data.error):', data.error.info);
      // Specific error for page not found, which is common and not a "failure" for this function's purpose.
      if (data.error.code === 'missingtitle') {
         if (autoError.value.includes("redirected from") && autoError.value.includes(originalManualCategory)) {
            autoError.value = ''; // Clear if it was a redirect message for this specific category
         }
      } else if (autoError.value.includes("redirected from")) {
        autoError.value = ''; // Clear other redirect messages on new API error
      }
      return false;
    }

    const wikitext = data.parse?.wikitext?.['*'];
    if (!wikitext) {
      if (autoError.value.includes("redirected from")) autoError.value = '';
      return false;
    }

    const redirectRegex = /\{\{\s*(?:Category redirect|seecat|see cat|categoryredirect|cat redirect|catredirect)\s*\|\s*(?:Category:)?([^}]+)\s*\}\}/i;
    const match = wikitext.match(redirectRegex);

    if (match && match[1]) {
      let targetCategoryName = match[1].trim();
      if (!targetCategoryName.startsWith('Category:')) {
        targetCategoryName = 'Category:' + targetCategoryName;
      }

      const currentCategoryNormalized = categoryForAPI; // Already prefixed
      if (targetCategoryName.toLowerCase() !== currentCategoryNormalized.toLowerCase()) {
        manualCategory.value = targetCategoryName;
        autoError.value = `Category redirected from '${originalManualCategory}' to '${targetCategoryName}'.`;
        updateUrl();
        return true;
      } else {
        // Redirect target is the same as the (normalized) input. Clear any message for this category.
        if (autoError.value.includes(`redirected from '${originalManualCategory}'`)) autoError.value = '';
        return false;
      }
    } else {
      // No redirect template found. Clear any message related to *this* category being redirected.
      if (autoError.value.includes(`redirected from '${originalManualCategory}'`)) autoError.value = '';
      return false;
    }
  } catch (error) {
    console.error('Exception in handleCategoryRedirectByParsing:', error);
    autoError.value = 'API error during redirect check.';
    return false;
  }
}

async function onCategoryBlur() {
  setTimeout(async () => {
    const activeElementIsDropdownItem = document.activeElement && document.activeElement.closest('.absolute.z-10');
    if (showCategoryDropdown.value && activeElementIsDropdownItem) {
      return;
    }
    if (!showCategoryDropdown.value || !activeElementIsDropdownItem) {
      await handleCategoryRedirectByParsing(true); // isManualInputOrBlur = true
      // manualCategory.value would be updated by the function if redirect occurs
      // updateUrl() is called within handleCategoryRedirectByParsing if redirect occurs
    }
    // Ensure dropdown is hidden if it was not a click on a dropdown item
    if (showCategoryDropdown.value && !activeElementIsDropdownItem) {
       hideCategoryDropdown();
    }
  }, 200); // Timeout to allow click on suggestion to register
}

async function selectCategory(val) {
  manualCategory.value = val;
  showCategoryDropdown.value = false;
  categoryResults.value = []; // Clear results after selection

  await handleCategoryRedirectByParsing(false); // isManualInputOrBlur = false
  updateUrl(); // Ensure URL reflects the final category
}

function hideCategoryDropdown() {
  // setTimeout(() => { showCategoryDropdown.value = false; }, 150); // Original timeout
  // Reduced timeout as blur/selection should handle most cases. This is a fallback.
  showCategoryDropdown.value = false;
}
const categoryUrl = computed(() => manualCategory.value ? `https://commons.wikimedia.org/wiki/${encodeURIComponent(manualCategory.value)}` : '#');

// --- Qid search logic ---
function onQidInput() {
  updateUrl();
  clearTimeout(qidSearchTimeout);
  const val = manualQid.value.trim();
  if (!val || /^Q\d+$/i.test(val)) {
    qidResults.value = [];
    if (/^Q\d+$/i.test(val)) {
      checkIfCategoryQid(val);
    } else {
      isCategoryQid.value = false;
      categoryQidWarning.value = '';
    }
    return;
  }
  isCategoryQid.value = false;
  categoryQidWarning.value = '';
  qidSearchTimeout = setTimeout(async () => {
    // Use wbsearchentities API instead of REST endpoint
    const url = `https://www.wikidata.org/w/api.php?action=wbsearchentities&search=${encodeURIComponent(val)}&language=en&format=json&origin=*`;
    const resp = await fetch(url, { redirect: 'follow' });
    const data = await resp.json();
    qidResults.value = (data.search || []).map(p => ({
      id: p.id,
      label: p.label,
      description: p.description || ''
    }));
    showQidDropdown.value = true;
  }, 250);
}

async function checkIfCategoryQid(qid) {
  try {
    const url = `https://www.wikidata.org/w/api.php?action=wbgetentities&ids=${encodeURIComponent(qid)}&props=claims&format=json&origin=*`;
    const resp = await fetch(url, { redirect: 'follow' });
    const data = await resp.json();
    const entity = data.entities?.[qid];
    const p31 = entity?.claims?.P31;
    if (Array.isArray(p31)) {
      // Check for Wikimedia category
      if (p31.some(s => s.mainsnak?.datavalue?.value?.id === 'Q4167836')) {
        const p301 = entity?.claims?.P301;
        if (Array.isArray(p301) && p301[0]?.mainsnak?.datavalue?.value?.id) {
          const mainTopicQid = p301[0].mainsnak.datavalue.value.id;
          isCategoryQid.value = true;
          categoryQidWarning.value = `This Qid is a Wikimedia category (not a real thing). Main topic found: ${mainTopicQid}. Switching to it...`;
          window.alert(`The Qid you entered is a Wikimedia category. Automatically switching to its main topic: ${mainTopicQid}`);
          manualQid.value = mainTopicQid;
          updateUrl();
          setTimeout(async () => {
            const result = await checkIfCategoryQid(mainTopicQid);
            if (!result) {
              showGrid.value = true;
              isCategoryQid.value = false;
              categoryQidWarning.value = '';
            }
          }, 0);
          return true;
        } else {
          isCategoryQid.value = true;
          categoryQidWarning.value = 'This Qid is a Wikimedia category (not a real thing). No main topic (P301) found. Please use the main topic instead.';
          showGrid.value = false;
          return true;
        }
      }
      // Check for Wikimedia disambiguation page
      if (p31.some(s => s.mainsnak?.datavalue?.value?.id === 'Q4167410')) {
        isCategoryQid.value = true;
        categoryQidWarning.value = 'This Qid is a Wikimedia disambiguation page. Please use a specific item instead.';
        showGrid.value = false;
        return true;
      }
      // Check for Wikimedia disambiguation category
      if (p31.some(s => s.mainsnak?.datavalue?.value?.id === 'Q15407973')) {
        isCategoryQid.value = true;
        categoryQidWarning.value = 'This Qid is a Wikimedia disambiguation category. Please use a specific item instead.';
        showGrid.value = false;
        return true;
      }
    }
    isCategoryQid.value = false;
    categoryQidWarning.value = '';
    return false;
  } catch (e) {
    isCategoryQid.value = false;
    categoryQidWarning.value = '';
    return false;
  }
}

function selectQid(item) {
  manualQid.value = item.id;
  showQidDropdown.value = false;
  updateUrl();
  checkIfCategoryQid(item.id);
}

function hideQidDropdown() {
  setTimeout(() => { showQidDropdown.value = false; }, 150);
}

const qidUrl = computed(() => manualQid.value ? `https://www.wikidata.org/wiki/${manualQid.value}` : '#');

// --- Auto-fill logic ---

async function autoFillCategoryFromQid() {
  autoError.value = ''; // Clear errors at the start of the action
  const qid = manualQid.value.trim();
  if (!/^Q\d+$/i.test(qid)) {
    autoError.value = 'Please enter a valid Wikidata Qid first.';
    return;
  }
  try {
    const url = `https://www.wikidata.org/w/api.php?action=wbgetentities&ids=${encodeURIComponent(qid)}&props=claims&format=json&origin=*`;
    const resp = await fetch(url, { redirect: 'follow' });
    const data = await resp.json();
    const entity = data.entities?.[qid];
    const p373 = entity?.claims?.P373;
    if (!p373 || !Array.isArray(p373) || p373.length === 0) {
      autoError.value = 'No Commons category (P373) found for this Qid.';
      return;
    }
    if (p373.length > 1) {
      autoError.value = 'Multiple Commons categories (P373) found for this Qid.';
      return;
    }
    const value = p373[0]?.mainsnak?.datavalue?.value;
    if (!value) {
      autoError.value = 'Commons category (P373) value missing.';
      return;
    }
    manualCategory.value = 'Category:' + value;
    updateUrl(); // Update URL with P373 category
    await handleCategoryRedirectByParsing(false); // isManualInputOrBlur = false
    // manualCategory.value is now potentially updated by redirect
    updateUrl(); // Update URL again if redirect happened
  } catch (e) {
    console.error("Error in autoFillCategoryFromQid (P373 fetch):", e);
    // autoError might have been set by handleCategoryRedirectByParsing if it failed.
    // If not, set a generic error for this function.
    if (!autoError.value.includes("redirected from") && !autoError.value.includes("API error during redirect check")) {
        autoError.value = 'Failed to fetch Commons category from Wikidata.';
    }
  }
}

// Renamed from autoFillQidFromCategory to match template call onClickAutoFillQidFromCategory
async function onClickAutoFillQidFromCategory() {
  const originalAutoError = autoError.value; // Preserve current autoError (e.g. redirect message)
  autoError.value = ''; // Clear for this operation, will be restored or replaced.

  const categoryBeforeRedirect = manualCategory.value;
  const wasRedirected = await handleCategoryRedirectByParsing(true); // isManualInputOrBlur = true
  // manualCategory.value is now updated if redirect occurred.
  // autoError will be set by handleCategoryRedirectByParsing if redirect occurs or API error.

  const currentCategoryValue = manualCategory.value;

  if (!currentCategoryValue.trim()) {
    // If category is empty after redirect attempt.
    // If handleCategoryRedirectByParsing set a message (e.g. API error), keep it.
    // Otherwise, set "Please enter category".
    if (!autoError.value) {
        autoError.value = 'Please enter a category first.';
    }
    return;
  }

  const categoryForQidLookup = currentCategoryValue.startsWith('Category:')
    ? currentCategoryValue
    : 'Category:' + currentCategoryValue;

  try {
    const url = `https://www.wikidata.org/w/api.php?action=wbgetentities&sites=commonswiki&titles=${encodeURIComponent(categoryForQidLookup)}&format=json&origin=*`;
    const resp = await fetch(url, { redirect: 'follow' });
    const data = await resp.json();
    const entities = data.entities || {};
    const qids = Object.keys(entities).filter(k => k.startsWith('Q'));
    if (qids.length === 0) {
      autoError.value = 'No Wikidata item found for this category.';
      return;
    }
    manualQid.value = qids[0];
    updateUrl();
    await checkIfCategoryQid(qids[0]); // This uses categoryQidWarning

    // Error handling for QID fetch:
    // If a redirect message was set by handleCategoryRedirectByParsing, and QID is fine, keep it.
    // If QID fetch failed, that error takes precedence.
    // If everything is fine, clear autoError.
    const redirectMessageAfterRedirectCheck = autoError.value.includes("redirected from") ? autoError.value : null;

    if (qids.length === 0) { // This check is effectively done above, but good for clarity
        autoError.value = 'No Wikidata item found for this category.';
    } else if (categoryQidWarning.value) { // Qid is problematic
        if(redirectMessageAfterRedirectCheck) autoError.value = redirectMessageAfterRedirectCheck;
        // else autoError is already empty or shows QID specific error (not from here)
    } else if (redirectMessageAfterRedirectCheck) {
        // Redirect happened, QID is fine
        autoError.value = redirectMessageAfterRedirectCheck;
    } else {
        // No redirect, QID is fine
        autoError.value = '';
    }

  } catch (e) {
    console.error("Error in onClickAutoFillQidFromCategory (QID fetch):", e);
    // A QID fetch error should generally override a previous redirect message.
    autoError.value = 'Failed to fetch Wikidata Qid from category.';
  }
}

function canShowGrid() {
  return !isCategoryQid.value && manualQid.value && manualCategory.value;
}

onMounted(async () => {
  const categoryParam = getQueryParam('category');
  const itemParam = getQueryParam('item');
  const autoParam = getQueryParam('auto');

  autoError.value = ''; // Clear errors on load
  categoryQidWarning.value = '';

  if (categoryParam) {
    manualCategory.value = categoryParam;
    await handleCategoryRedirectByParsing(true); // isManualInputOrBlur = true
    // manualCategory.value is updated by handleCategoryRedirectByParsing
    updateUrl(); // Reflect potential redirect in URL
  }

  if (itemParam) {
    manualQid.value = itemParam;
    updateUrl(); // Ensure QID is in URL if category also updated it
  }

  if (autoParam === '1') {
    if (manualCategory.value && !manualQid.value) {
      // Category is present (possibly after redirect), QID is missing.
      await onClickAutoFillQidFromCategory();
    } else if (!manualCategory.value && manualQid.value) {
      // QID is present, Category is missing.
      await autoFillCategoryFromQid();
    }
  }

  // Final QID validation if a QID is set (either from param or auto-fill)
  if (manualQid.value) {
    await checkIfCategoryQid(manualQid.value);
  }

  loadAll.value = false; // Default to dynamic grid mode

  showGrid.value = canShowGrid();
});
</script>

<style>
.input-category-qid-bad {
  border-color: #e53e3e; /* red-600 */
  background-color: #fff5f5; /* red-50 */
}
</style>
