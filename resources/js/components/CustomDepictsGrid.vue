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
  }, 300); // Updated debounce delay to 300ms
}

async function resolveCategoryRedirect(categoryNameInitial) {
  if (!categoryNameInitial || !categoryNameInitial.trim()) {
    if (autoError.value.includes("redirected from")) autoError.value = '';
    return categoryNameInitial;
  }

  let categoryName = categoryNameInitial.startsWith('Category:') ? categoryNameInitial : 'Category:' + categoryNameInitial;

  try {
    const apiUrl = `https://commons.wikimedia.org/w/api.php?action=query&titles=${encodeURIComponent(categoryName)}&redirects=1&format=json&origin=*`;
    const response = await fetch(apiUrl);
    if (!response.ok) {
      console.error("API error in resolveCategoryRedirect (fetch not ok):", response.statusText);
      if (autoError.value.includes("redirected from")) autoError.value = '';
      return categoryName; // Return original on error
    }
    const data = await response.json();

    if (data.query && data.query.pages) {
      const pages = data.query.pages;
      const pageId = Object.keys(pages)[0];
      const page = pages[pageId];

      // Check if the final page title is different from the (potentially prefixed) categoryName
      // This indicates a redirect has been resolved by MediaWiki.
      if (page.title && page.title !== categoryName) {
        manualCategory.value = page.title; // Update the global reactive ref
        autoError.value = `Category redirected from "${categoryNameInitial}" to "${page.title}".`;
        updateUrl();
        return page.title; // Return the new target
      }
    }
    // No redirect, or target is same as input (after prefixing)
    if (autoError.value.includes("redirected from") && autoError.value.includes(`from "${categoryNameInitial}"`)) {
         autoError.value = ''; // Clear if it was a message for this specific category
    }
    return categoryName; // Return original (potentially prefixed) name if no redirect occurred or target is same
  } catch (error) {
    console.error("Error in resolveCategoryRedirect:", error);
    if (autoError.value.includes("redirected from")) autoError.value = '';
    return categoryName; // Return original on error
  }
}

async function selectCategory(val) {
  manualCategory.value = val; // Set value from selection
  showCategoryDropdown.value = false;
  categoryResults.value = [];

  await resolveCategoryRedirect(manualCategory.value);
  // manualCategory.value is updated by resolveCategoryRedirect if needed
  updateUrl(); // Ensure URL is updated with final category name
}

async function onCategoryBlur() {
  setTimeout(async () => {
    const activeElementIsDropdownItem = document.activeElement && document.activeElement.closest('.absolute.z-10');
    if (showCategoryDropdown.value && activeElementIsDropdownItem) {
      return;
    }
    if (!showCategoryDropdown.value || !activeElementIsDropdownItem) {
        await resolveCategoryRedirect(manualCategory.value);
        // manualCategory.value updated by resolveCategoryRedirect if needed
        updateUrl(); // Update URL after potential redirect
    }
    if (showCategoryDropdown.value && !activeElementIsDropdownItem) {
       hideCategoryDropdown();
    }
  }, 200);
}

function hideCategoryDropdown() {
  setTimeout(() => { showCategoryDropdown.value = false; }, 150); // Keep original timeout for hide
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
    // Point 3 (callers): autoFillCategoryFromQid
    manualCategory.value = await resolveCategoryRedirect(manualCategory.value); // Update with resolved category
    updateUrl(); // Update URL again if category changed
  } catch (e) {
    console.error("Error in autoFillCategoryFromQid (P373 fetch):", e);
    autoError.value = 'Failed to fetch Commons category from Wikidata.';
  }
}

async function onClickAutoFillQidFromCategory() {
  const redirectMessageBeforeQidFetch = autoError.value.includes("redirected from") ? autoError.value : null;
  if (!redirectMessageBeforeQidFetch) {
    autoError.value = ''; // Clear other previous errors if no redirect message active
  }

  // Point 3 (callers): onClickAutoFillQidFromCategory - Step 1: Resolve redirect
  // manualCategory.value is passed to resolveCategoryRedirect, which updates it directly.
  await resolveCategoryRedirect(manualCategory.value);
  const currentResolvedCategory = manualCategory.value; // This is the (potentially redirected) category

  let cat = currentResolvedCategory.trim();
  if (!cat) {
    // If category is empty (e.g. user cleared it, or redirect resulted in empty if error in resolve fn)
    // A redirect message might be in autoError from resolveCategoryRedirect, preserve it.
    if (!autoError.value.includes("redirected from")) {
        autoError.value = 'Please enter a category first.';
    }
    return;
  }
  if (!/^Category:/i.test(cat)) { // Should generally be prefixed by now.
    cat = 'Category:' + cat;
  }

  // Point 3 (callers): onClickAutoFillQidFromCategory - Step 2: Fetch QID
  try {
    const url = `https://www.wikidata.org/w/api.php?action=wbgetentities&sites=commonswiki&titles=${encodeURIComponent(cat)}&format=json&origin=*`;
    const resp = await fetch(url);
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

    // Preserve redirect message if one was active from resolveCategoryRedirect,
    // and no new QID error, and QID itself is not problematic.
    const currentRedirectMessage = autoError.value.includes("redirected from") ? autoError.value : null;
    if (currentRedirectMessage && !categoryQidWarning.value) {
      autoError.value = currentRedirectMessage;
    } else if (!currentRedirectMessage && !categoryQidWarning.value) {
      // No redirect message, no QID issue, ensure autoError is clear.
      autoError.value = '';
    }
    // If categoryQidWarning.value is set, it will be displayed.
    // If a QID fetch error occurred, autoError is already set to that.

  } catch (e) {
    console.error("Error fetching QID from category:", e);
    // A QID fetch error should override a previous redirect message as it's more specific to this action.
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

  // Point 3 (callers): onMounted
  if (categoryParam) {
    manualCategory.value = categoryParam;
    manualCategory.value = await resolveCategoryRedirect(manualCategory.value);
    updateUrl(); // Update URL after potential redirect
  }

  if (itemParam) {
    manualQid.value = itemParam;
    updateUrl(); // Update URL in case category also changed it
  }

  if (autoParam === '1') {
    if (manualCategory.value && !manualQid.value) {
      // Category is present (possibly redirected), QID is missing.
      await onClickAutoFillQidFromCategory();
    } else if (!manualCategory.value && manualQid.value) {
      // QID is present, Category is missing.
      await autoFillCategoryFromQid();
    }
  }

  if (manualQid.value) {
    await checkIfCategoryQid(manualQid.value);
  }

  loadAll.value = false;

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
