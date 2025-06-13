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
          <button type='submit' class='flex-1 bg-blue-600 dark:bg-blue-700 text-white px-4 py-2 rounded font-bold' title="Loads images dynamically, a bit below the fold">Dynamic grid</button>
          <button type='button' @click="generateFullGrid" class='flex-1 bg-green-600 dark:bg-green-700 text-white px-4 py-2 rounded font-bold' title="Loads all images in the category and its subcategories">Full grid</button>
        </div>
        <div v-if="autoError" class="mt-2 text-red-600 dark:text-red-400 text-sm">{{ autoError }}</div>
        <div v-if="categoryQidWarning" class="mt-2 text-red-600 dark:text-red-400 text-sm">{{ categoryQidWarning }}</div>
      </form>
    </div>
    <div class='w-full'>
      <GridMode v-if="showGrid && canShowGrid()" :manual-category="manualCategory" :manual-qid="manualQid" :manual-mode="true" :load-all="loadAll" :key="gridKey" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import GridMode from './GridMode.vue';

const manualCategory = ref('');
const manualQid = ref('');
const showGrid = ref(false);
const gridKey = ref(0); // Used to force re-render of GridMode
const loadAll = ref(false); // New data property

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
  updateUrl();
  clearTimeout(categorySearchTimeout);
  if (!manualCategory.value) {
    categoryResults.value = [];
    return;
  }
  categorySearchTimeout = setTimeout(async () => {
    const search = manualCategory.value.replace(/^Category:/i, '');
    const url = `https://commons.wikimedia.org/w/api.php?action=query&list=search&srsearch=${encodeURIComponent(search)}&srnamespace=14&srlimit=10&format=json&origin=*`;
    const resp = await fetch(url);
    const data = await resp.json();
    categoryResults.value = (data.query?.search || []).map(r => {
      // Remove any leading 'Category:' from the result title, then add it once
      let title = r.title.replace(/^Category:/i, '');
      return 'Category:' + title;
    });
    showCategoryDropdown.value = true;
  }, 250);
}

// 1. `handleCategoryRedirectByParsing(isManualInputOrBlur)` function:
async function handleCategoryRedirectByParsing(isManualInputOrBlur = false) {
  if (!manualCategory.value.trim()) {
    // Clear any previous redirect message if category becomes empty
    if (autoError.value.includes("redirected from")) autoError.value = '';
    return false;
  }

  const originalCategoryForAPI = manualCategory.value.startsWith('Category:') ? manualCategory.value : 'Category:' + manualCategory.value;
  const originalDisplayCategory = manualCategory.value; // What the user typed or selected, for messages

  try {
    const apiUrl = `https://commons.wikimedia.org/w/api.php?action=parse&page=${encodeURIComponent(originalCategoryForAPI)}&prop=wikitext&format=json&origin=*`;
    const response = await fetch(apiUrl);
    if (!response.ok) {
      // Do not set autoError for common issues like 404 here, let specific callers decide.
      // Clear previous redirect message if API fails for a new check.
      if (autoError.value.includes("redirected from")) autoError.value = '';
      return false;
    }
    const data = await response.json();

    if (data.error) {
      if (autoError.value.includes("redirected from")) autoError.value = '';
      return false; // e.g. page does not exist
    }

    const wikitext = data.parse?.wikitext?.['*'];
    if (!wikitext) {
      if (autoError.value.includes("redirected from")) autoError.value = '';
      return false;
    }

    const redirectRegex = /\{\{\s*(?:Category redirect|seecat|see cat|categoryredirect|cat redirect|catredirect)\s*\|\s*(?:Category:)?([^}]+)\s*\}\}/i;
    const match = wikitext.match(redirectRegex);

    if (match && match[1]) {
      let targetCategory = match[1].trim();
      if (!targetCategory.startsWith('Category:')) {
        targetCategory = 'Category:' + targetCategory;
      }

      if (targetCategory.toLowerCase() !== originalCategoryForAPI.toLowerCase()) {
        manualCategory.value = targetCategory;
        autoError.value = `Category redirected from "${originalDisplayCategory}" to "${targetCategory}".`;
        updateUrl();
        return true;
      } else {
        if (autoError.value.includes("redirected from")) autoError.value = ''; // Clear if it was a message for this category but now resolved to same
        return false; // Effectively no change
      }
    } else {
      if (autoError.value.includes("redirected from")) autoError.value = ''; // Clear if previous redirect message existed
      return false;
    }
  } catch (error) {
    console.error("Error in handleCategoryRedirectByParsing (fetch error or other exception):", error, 'Category:', originalCategoryForAPI);
    autoError.value = 'API error during redirect check.'; // Generic error for unexpected issues
    return false;
  }
}

// 2. `onCategoryBlur()` function:
async function onCategoryBlur() {
  // Using a timeout to allow click on dropdown to register first
  setTimeout(async () => {
    const activeElementIsDropdownItem = document.activeElement && document.activeElement.closest('.absolute.z-10');

    if (showCategoryDropdown.value && activeElementIsDropdownItem) {
      // If dropdown is visible and click was on item, selectCategory will handle redirect.
      return;
    }

    // If dropdown is not shown OR click was not on a dropdown item, then proceed with blur logic.
    if (!showCategoryDropdown.value || !activeElementIsDropdownItem) {
        await handleCategoryRedirectByParsing(true); // true for isManualInputOrBlur
    }

    // Ensure dropdown is hidden if it wasn't closed by selection (e.g., user clicks away)
     if (showCategoryDropdown.value && !activeElementIsDropdownItem) {
       hideCategoryDropdown(); // General hide, not specific to selection.
    }
  }, 200); // 200ms timeout to allow click to register.
}

async function selectCategory(val) {
  manualCategory.value = val;
  showCategoryDropdown.value = false; // Hide dropdown immediately
  categoryResults.value = []; // Clear results

  await handleCategoryRedirectByParsing(false); // false for isManualInputOrBlur (selected from list)
  updateUrl(); // Ensure URL is updated with final category name
}

function hideCategoryDropdown() {
  // This is a simpler hide, usually called when focus is lost or selection made.
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
    const resp = await fetch(url);
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
    const resp = await fetch(url);
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
  autoError.value = '';
  const qid = manualQid.value.trim();
  if (!/^Q\d+$/i.test(qid)) {
    autoError.value = 'Please enter a valid Wikidata Qid first.';
    return;
  }
  try {
    const url = `https://www.wikidata.org/w/api.php?action=wbgetentities&ids=${encodeURIComponent(qid)}&props=claims&format=json&origin=*`;
    const resp = await fetch(url);
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
    updateUrl();
    await handleCategoryRedirectByParsing(false); // false as it's not direct user input on category field
    // Original checkIfCategoryQid(qid) was for the QID, not necessarily related to category redirect.
    // The main purpose here is to ensure the category is correct.
    // Any error from handleCategoryRedirectByParsing would set autoError.
  } catch (e) {
    console.error("Error in autoFillCategoryFromQid (P373 fetch):", e);
    autoError.value = 'Failed to fetch Commons category from Wikidata.';
  }
}

// Renamed from autoFillQidFromCategory to match template call
async function onClickAutoFillQidFromCategory() {
  autoError.value = ''; // Clear previous errors like "No QID found" or "No Cat found"

  const wasRedirected = await handleCategoryRedirectByParsing(true); // true for isManualInputOrBlur
  const currentCategoryValue = manualCategory.value; // Use the potentially redirected category

  if (!currentCategoryValue.trim()) {
    // If category is empty after redirect check (e.g., was invalid and cleared), set error.
    // Do not overwrite a redirect message if one was just set and category somehow became empty.
    if (!autoError.value.includes("redirected from")) {
        autoError.value = 'Please enter a category first.';
    }
    return;
  }

  const categoryForQidLookup = currentCategoryValue.startsWith('Category:') ? currentCategoryValue : 'Category:' + currentCategoryValue;

  try {
    const url = `https://www.wikidata.org/w/api.php?action=wbgetentities&sites=commonswiki&titles=${encodeURIComponent(categoryForQidLookup)}&format=json&origin=*`;
    const resp = await fetch(url);
    const data = await resp.json();
    const entities = data.entities || {};
    const qids = Object.keys(entities).filter(k => k.startsWith('Q'));

    if (qids.length === 0) {
      // Only set error if no redirect message is more prominent
      if (!autoError.value.includes("redirected from")) {
        autoError.value = 'No Wikidata item found for this category.';
      }
      return;
    }
    manualQid.value = qids[0];
    updateUrl();

    const isProblematicQid = await checkIfCategoryQid(qids[0]);

    // Manage autoError: if a redirect happened and QID is fine, keep redirect message.
    // Otherwise, clear error if QID is fine. If QID problematic, that shows in categoryQidWarning.
    if (wasRedirected && autoError.value.includes("redirected from") && !isProblematicQid) {
      // Keep the redirect message
    } else if (!isProblematicQid) {
      // QID is fine, no redirect message was prominent or needed to be kept. Clear error.
      autoError.value = '';
    }
    // If isProblematicQid, categoryQidWarning will be displayed. autoError might show redirect or be empty.

  } catch (e) {
    console.error('[DEBUG] onClickAutoFillQidFromCategory: Error fetching QID:', e);
    // Preserve redirect message if it exists and error is for QID fetch
    if (!autoError.value.includes("redirected from")) {
        autoError.value = 'Failed to fetch Wikidata Qid from category.';
    }
  }
  }
}

function canShowGrid() {
  return !isCategoryQid.value && manualQid.value && manualCategory.value;
}

onMounted(async () => {
  const categoryParam = getQueryParam('category');
  const itemParam = getQueryParam('item');
  const autoParam = getQueryParam('auto');

  // Clear any errors at start of load
  autoError.value = '';
  categoryQidWarning.value = '';

  if (categoryParam) {
    manualCategory.value = categoryParam;
    await handleCategoryRedirectByParsing(true); // true as it's like a manual input
  }

  if (itemParam) {
    manualQid.value = itemParam;
  }

  // Auto-fill logic if 'auto=1'
  if (autoParam === '1') {
    if (manualCategory.value && !manualQid.value) {
      await onClickAutoFillQidFromCategory();
    } else if (!manualCategory.value && manualQid.value) {
      await autoFillCategoryFromQid();
    }
  }

  // Final QID validation if a QID is set
  if (manualQid.value) {
    await checkIfCategoryQid(manualQid.value);
  }

  loadAll.value = false; // Default to dynamic grid mode

  showGrid.value = canShowGrid();
});
        showGrid.value = true;
      }
    }
  }
});
</script>

<style>
.input-category-qid-bad {
  border-color: #e53e3e; /* red-600 */
  background-color: #fff5f5; /* red-50 */
}
</style>
