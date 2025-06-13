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
      let title = r.title.replace(/^Category:/i, '');
      return 'Category:' + title;
    });
    showCategoryDropdown.value = true;
  }, 250);
  // onCategoryInput should only handle search suggestions and URL updates
}

async function handleCategoryRedirectByParsing(isManualInput = false) {
  const originalUserInput = manualCategory.value;
  let categoryToParse = manualCategory.value;

  if (!categoryToParse || categoryToParse.trim() === '') {
    if (autoError.value.includes("redirected from")) autoError.value = '';
    return false;
  }

  if (!/^Category:/i.test(categoryToParse)) {
    categoryToParse = 'Category:' + categoryToParse;
  }

  try {
    const apiUrl = `https://commons.wikimedia.org/w/api.php?action=parse&page=${encodeURIComponent(categoryToParse)}&prop=wikitext&format=json&origin=*`;
    const response = await fetch(apiUrl);
    if (!response.ok) {
      // Do not set autoError here for common cases like page not found, let specific callers handle it.
      console.warn(`API request failed to fetch wikitext for "${categoryToParse}": ${response.statusText}`);
      if (autoError.value.includes("redirected from")) autoError.value = ''; // Clear previous redirect message only
      return false;
    }
    const data = await response.json();

    if (data.error) {
      console.warn(`API error for "${categoryToParse}" (parse): ${data.error.info}`);
      if (autoError.value.includes("redirected from")) autoError.value = '';
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
      let targetCategory = match[1].trim();
      if (!/^Category:/i.test(targetCategory)) {
        targetCategory = 'Category:' + targetCategory;
      }

      let normalizedOriginalForComparison = originalUserInput;
      if (!/^Category:/i.test(normalizedOriginalForComparison)) {
          normalizedOriginalForComparison = 'Category:' + normalizedOriginalForComparison;
      }

      if (targetCategory.toLowerCase() !== normalizedOriginalForComparison.toLowerCase()) {
        manualCategory.value = targetCategory;
        // Use originalUserInput for the "from" part to reflect what user typed/selected initially for this action
        const fromMsg = (isManualInput && !/^Category:/i.test(originalUserInput)) ? originalUserInput : normalizedOriginalForComparison;
        autoError.value = `Category redirected from "${fromMsg}" to "${targetCategory}".`;
        updateUrl();
        return true; // Redirect applied
      } else {
        if (autoError.value.includes("redirected from")) autoError.value = '';
        return false; // No actual change needed
      }
    } else {
      if (autoError.value.includes("redirected from")) autoError.value = '';
      return false; // No redirect template found
    }
  } catch (error) {
    console.error("Error in handleCategoryRedirectByParsing:", error);
    if (autoError.value.includes("redirected from")) autoError.value = '';
    return false;
  }
}

async function selectCategory(val) {
  manualCategory.value = val; // Set value from selection
  // updateUrl(); // updateUrl will be called by handleCategoryRedirectByParsing if redirect happens, or can be called after.

  showCategoryDropdown.value = false;
  categoryResults.value = []; // Clear results

  await handleCategoryRedirectByParsing(false); // isManualInput = false
  updateUrl(); // Ensure URL is updated with final category name
}

async function onCategoryBlur() {
  // Timeout to allow click on dropdown to register before checking redirect & hiding
  setTimeout(async () => {
    const activeElementIsDropdownItem = document.activeElement && document.activeElement.closest('.absolute.z-10');
    // Only run if dropdown is not active (i.e., category not selected from it, which calls selectCategory)
    // and if the input field itself is not the active element (relevant if user clicks away quickly)
    if (!showCategoryDropdown.value && !activeElementIsDropdownItem) {
       await handleCategoryRedirectByParsing(true); // isManualInput = true
    }
    // Ensure dropdown is hidden if it somehow remained open, e.g. user clicks outside input AND dropdown
    if (showCategoryDropdown.value && !activeElementIsDropdownItem) {
       hideCategoryDropdown();
    }
  }, 200); // 200ms timeout
}

function hideCategoryDropdown() {
  // Simplified: just hides the dropdown.
  // The @blur event on input calls onCategoryBlur, which then calls this if needed.
  showCategoryDropdown.value = false;
  // categoryResults.value = []; // Optionally clear results when dropdown is hidden by other means than selection
}
const categoryUrl = computed(() => manualCategory.value ? `https://commons.wikimedia.org/wiki/${encodeURIComponent(manualCategory.value)}` : '#');

// --- Qid search logic ---
// Point 5: onQidInput already calls checkIfCategoryQid for valid QID formats.
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
    // Point 6: Call redirect handling for the auto-filled category
    const wasRedirected = await handleCategoryRedirectByParsing(false); // isManualInput = false for auto-filled category
    // checkIfCategoryQid was already called by the QID input that triggered this,
    // but if category changed, QID might need re-eval or relation check.
    // For now, primary goal is redirect check.
    // autoError might be set by redirect logic or previous errors.
    if (!wasRedirected && !autoError.value) { // If no redirect and no prior error, clear.
        // autoError.value = ''; // Already cleared at the start unless redirect sets it.
    } else if (e && !autoError.value.includes("redirected from")) { // P373 fetch error, and not a redirect message
        autoError.value = 'Failed to fetch Commons category from Wikidata.';
    }
    // If wasRedirected, autoError contains the redirect message.
    // If P373 fetch failed, this new error handling needs to be careful.
  } catch (e) {
    // Catch error from wbgetentities (P373 fetch)
    if (!autoError.value.includes("redirected from")) { // Preserve redirect message if one was somehow set before error
        autoError.value = 'Failed to fetch Commons category from Wikidata.';
    }
  }
}

// Point 4: Renaming and modifying autoFillQidFromCategory to onClickAutoFillQidFromCategory
async function onClickAutoFillQidFromCategory() {
  // autoError.value = ''; // Clear previous errors before starting the whole process.
  // Let redirect handler manage its specific messages first.
  // If redirect doesn't happen or doesn't set a message, then we clear for QID part.

  const wasRedirected = await handleCategoryRedirectByParsing(true); // isManualInput = true
  const redirectMessage = (wasRedirected && autoError.value.includes("redirected from")) ? autoError.value : null;

  if (!redirectMessage) { // If no redirect occurred and set a message, clear autoError for QID fetching part.
    autoError.value = '';
  }

  let cat = manualCategory.value.trim();
  if (!cat) {
    autoError.value = 'Please enter a category first.'; // This will overwrite redirect message if cat becomes empty.
    return;
  }
  // Prefix is already handled by handleCategoryRedirectByParsing if needed,
  // but ensure it for direct API call if not already prefixed.
  if (!/^Category:/i.test(cat)) {
    cat = 'Category:' + cat;
  }

  try {
    const url = `https://www.wikidata.org/w/api.php?action=wbgetentities&sites=commonswiki&titles=${encodeURIComponent(cat)}&format=json&origin=*`;
    const resp = await fetch(url);
    const data = await resp.json();
    const entities = data.entities || {};
    const qids = Object.keys(entities).filter(k => k.startsWith('Q'));

    if (qids.length === 0) {
      autoError.value = 'No Wikidata item found for this category.'; // Overwrites redirect message
      return;
    }
    manualQid.value = qids[0];
    updateUrl();

    const isProblematicQid = await checkIfCategoryQid(qids[0]); // This uses categoryQidWarning

    if (redirectMessage && !isProblematicQid) {
      // If a redirect happened AND the new QID is valid, keep the redirect message.
      autoError.value = redirectMessage;
    } else if (!redirectMessage && !isProblematicQid) {
      // If no redirect message AND QID is valid, ensure autoError is clear.
      autoError.value = '';
    }
    // If isProblematicQid, categoryQidWarning is shown. autoError might show redirect or be empty.
    // If QID fetch itself failed (above), that error is shown.

  } catch (e) {
    console.error("Error fetching QID from category:", e);
    autoError.value = 'Failed to fetch Wikidata Qid from category.'; // Overwrites redirect message
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
    // Treat category from URL as manual input for redirect handling purposes
    await handleCategoryRedirectByParsing(true);
    // manualCategory.value may have been updated by redirect
  }

  if (itemParam) {
    manualQid.value = itemParam;
    // No direct redirect check here, but QID validity will be checked later.
  }

  // Auto-fill logic if 'auto=1'
  if (autoParam === '1') {
    if (manualCategory.value && !manualQid.value) {
      // Category is present (possibly after redirect), QID is missing.
      // onClickAutoFillQidFromCategory will perform another redirect check, then QID fetch.
      // This is slightly redundant on the redirect check if categoryParam was present, but ensures consistency.
      await onClickAutoFillQidFromCategory();
    } else if (!manualCategory.value && manualQid.value) {
      // QID is present, Category is missing.
      await autoFillCategoryFromQid(); // This fetches P373 and then checks redirect for that category.
    }
  }

  // Final QID validation if a QID is set, either from param or auto-fill
  if (manualQid.value) {
    await checkIfCategoryQid(manualQid.value);
  }

  loadAll.value = false; // Default to dynamic grid mode on load

  // Show grid if all conditions met (valid QID, category present, no blocking warnings)
  // canShowGrid checks: !isCategoryQid.value && manualQid.value && manualCategory.value;
  // autoError might have redirect messages, which are not errors for showing grid.
  // categoryQidWarning is the one that might block.
  if (canShowGrid() && !categoryQidWarning.value) { // Ensure no QID warning is blocking
     showGrid.value = true;
  } else if (canShowGrid() && categoryQidWarning.value && autoError.value.includes("redirected from")){
    // If there's a redirect message AND a QID warning, still show grid if other conditions are met.
    // This might be too permissive, but QID warning is separate.
    // Let's stick to canShowGrid primarily. The categoryQidWarning is a separate display.
    showGrid.value = true;
  } else {
    showGrid.value = false;
  }

  // If, after all this, we have a category and QID, but also an error (not redirect)
  // that wasn't a QID warning, don't show grid.
  // This is tricky. Let's simplify: show grid if canShowGrid is true.
  // Warnings and non-blocking errors can co-exist with the grid.
  showGrid.value = canShowGrid();

});
</script>

<style>
.input-category-qid-bad {
  border-color: #e53e3e; /* red-600 */
  background-color: #fff5f5; /* red-50 */
}
</style>
