<template>
  <h2 class="text-xl font-bold mb-2 text-gray-900 dark:text-gray-100">Precalculated Grid</h2>
  <div class="text-gray-600 dark:text-gray-400 text-sm">
    Questions calculated on the server, based on
    <a
      href="https://commons.wikimedia.org/wiki/User:Addshore/wikicrowd.yaml"
      target="_blank"
      rel="noopener"
      class="text-blue-700 dark:text-blue-400 underline"
    >
      wikicrowd.yaml
    </a>
  </div>
  <div>
    <div v-if="difficultyFilters.length > 0 || hasUnrated" class="flex flex-col gap-2 mb-4">
      <div class="font-semibold text-base mb-1 text-gray-900 dark:text-gray-100">Filter by difficulty</div>
      <div class="flex flex-wrap gap-2 mb-2">
      <template v-for="(level, key) in levels" :key="key">
        <button class="px-3 py-1 rounded border" :class="difficultyButtonClass(key)" @click="toggleFilter(key)" :title="level.desc">
        <span>{{ emojiForDifficulty(key) }}</span> {{ level.name }}
        </button>
      </template>
      <template v-if="hasUnrated">
        <button class="px-3 py-1 rounded border" :class="difficultyButtonClass('UNRATED')" @click="toggleFilter('UNRATED')" title="Show unrated questions">
        ❓ Unrated
        </button>
      </template>
      </div>
      <div v-if="hasUnrated" class="text-gray-600 dark:text-gray-400 text-sm">
      Unrated groups have not been assigned a difficulty yet.
      </div>
    </div>
    <div v-if="groupsApiData === null || yamlData === null" class="mb-8">
      <div class="flex items-center justify-center py-8">
        <div class="text-gray-500 dark:text-gray-400">Loading groups...</div>
      </div>
    </div>
    <template v-else>
      <div v-for="(group, groupKey) in groupedQuestions" :key="groupKey" class="mb-8">
        <h2 class="text-xl font-bold mb-2 text-gray-900 dark:text-gray-100">{{ group.display_name }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          <DepictsGroupBox
            v-for="sub in group.questions"
            :key="sub.id"
            :sub="sub"
            :emojiForDifficulty="emojiForDifficulty"
            :getCategoryUrl="getCategoryUrl"
            :getCategoryName="getCategoryName"
            :getWikidataUrl="getWikidataUrl"
          />
        </div>
      </div>
      <div v-if="unratedQuestions.length > 0" class="mb-8">
        <h2 class="text-xl font-bold mb-2 text-gray-900 dark:text-gray-100">❓ Unrated</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          <DepictsGroupBox
            v-for="sub in unratedQuestions"
            :key="sub.id"
            :sub="sub"
            :emojiForDifficulty="emojiForDifficulty"
            :getCategoryUrl="getCategoryUrl"
            :getCategoryName="getCategoryName"
            :getWikidataUrl="getWikidataUrl"
          />
        </div>
      </div>
    </template>
    <div class="mt-12 border-t pt-8">
      <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-gray-100">Regenerate</h2>
      <span class="block mt-2 text-sm text-gray-700 dark:text-gray-300">Config for this tool can be found at
        <a href="https://commons.wikimedia.org/wiki/User:Addshore/wikicrowd.yaml" target="_blank" rel="noopener" class="text-blue-700 dark:text-blue-400 underline">
          https://commons.wikimedia.org/wiki/User:Addshore/wikicrowd.yaml
        </a>
      </span>
      <span class="block mt-2 text-sm text-gray-700 dark:text-gray-300">
        If you have made changes to the YAML file, you can regenerate questions here.</span>
    </div>
    <div v-if="mergedQuestions.length">
      <table class="min-w-full text-sm border">
        <thead>
          <tr class="bg-gray-100 dark:bg-gray-800">
            <th class="p-2 border text-gray-900 dark:text-gray-100">Name</th>
            <th class="p-2 border text-gray-900 dark:text-gray-100">Depicts</th>
            <th class="p-2 border text-gray-900 dark:text-gray-100">Categories</th>
            <th class="p-2 border text-gray-900 dark:text-gray-100">Conflict Links</th>
            <th class="p-2 border text-gray-900 dark:text-gray-100 flex items-center" @click="toggleSort('unanswered')" style="cursor: pointer;">
              <span>Unanswered</span>
              <span class="ml-1">
                <span :class="{'text-gray-800 dark:text-white font-bold': sortDirection === 'asc' && sortColumn === 'unanswered', 'text-gray-400 dark:text-gray-600': !(sortDirection === 'asc' && sortColumn === 'unanswered')}">↑</span>
                <span :class="{'text-gray-800 dark:text-white font-bold': sortDirection === 'desc' && sortColumn === 'unanswered', 'text-gray-400 dark:text-gray-600': !(sortDirection === 'desc' && sortColumn === 'unanswered')}">↓</span>
              </span>
            </th>
            <th class="p-2 border text-gray-900 dark:text-gray-100">Go to</th>
            <th class="p-2 border text-gray-900 dark:text-gray-100">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="q in sortedMergedQuestions" :key="q.id">
            <td class="p-2 border text-gray-900 dark:text-gray-100">
              <span>{{ emojiForDifficulty(q.difficulty) }}</span> {{ q.name }}
            </td>
            <td class="p-2 border text-gray-900 dark:text-gray-100">
              <template v-if="q.depictsId">
                <a :href="getWikidataUrl(q.depictsId)" target="_blank" class="text-blue-700 hover:underline font-mono">
                  {{ q.depictsId.replace(/\{\{Q\|([^}]+)\}\}/, '$1') }}
                </a>
                <span class="ml-1">(<WikidataLabel :qid="q.depictsId.replace(/\{\{Q\|([^}]+)\}\}/, '$1')" :fallback="q.name" />)</span>
                <div class="text-xs text-gray-600 mt-1">
                  <WikidataDescription :qid="q.depictsId.replace(/\{\{Q\|([^}]+)\}\}/, '$1')" />
                </div>
              </template>
              <template v-else>-</template>
            </td>
            <td class="p-2 border text-gray-900 dark:text-gray-100">
              <template v-if="q.categories && q.categories.length">
                <span v-for="(cat, idx) in q.categories" :key="cat">
                  <a :href="getCategoryUrl(cat)" target="_blank" class="text-blue-700 hover:underline">{{ getCategoryName(cat) }}</a><span v-if="idx < q.categories.length - 1">, </span>
                </span>
              </template>
              <template v-else>-</template>
            </td>
            <td class="p-2 border text-gray-900 dark:text-gray-100">
              <template v-if="q.depictsId">
                <div class="text-xs">
                  <a
                    :href="getDepictsUpQueryUrl(q.depictsId)"
                    target="_blank"
                    rel="noopener"
                    class="text-blue-700 hover:underline"
                  >(up)</a>
                  <a
                    :href="getDepictsDownQueryUrl(q.depictsId)"
                    target="_blank"
                    rel="noopener"
                    class="ml-1 text-blue-700 hover:underline"
                  >(down)</a>
                </div>
              </template>
              <template v-else>-</template>
            </td>
            <td class="p-2 border text-center text-gray-900 dark:text-gray-100">
              <span v-if="typeof q.unanswered === 'number'">{{ q.unanswered }}</span>
              <span v-else-if="typeof q.unanswered !== 'number'">-</span>
            </td>
            <td class="p-2 border text-gray-900 dark:text-gray-100">
              <div class="flex gap-2">
                <a :href="`/questions/${q.route_name}`" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 font-bold" title="Go to start questions">Start</a>
              </div>
            </td>
            <td class="p-2 border text-gray-900 dark:text-gray-100">
              <div class="flex gap-2">
                <button
                  class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                  @click="regenerateJob(q)"
                  :disabled="regenerating[q.id]"
                >
                  <span v-if="regenerating[q.id]">Regenerating...</span>
                  <span v-else>Regenerate</span>
                </button>
                <button
                  v-if="typeof q.unanswered === 'number' && q.unanswered > 0"
                  class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50"
                  @click="clearUnanswered(q)"
                  :disabled="clearing[q.id] || clearing.__allDisabled"
                >
                  <span v-if="clearing[q.id]">Clearing...</span>
                  <span v-else>Clear</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="groupsApiData === null || yamlData === null">
      <span class="text-gray-500">Loading....</span>
    </div>
    <div v-else class="text-gray-500">No YAML questions found.</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import DepictsGroupBox from './DepictsGroupBox.vue';
import WikidataLabel from './WikidataLabel.vue';
import { generateDepictsUpQueryUrl, generateDepictsDownQueryUrl } from '../sparqlQueries.js';

const yamlData = ref(null);
const groupsApiData = ref(null);
const levels = ref({});
const filters = ref([]);
const difficultyFilters = ref([]);
const regenerating = ref({});
const clearing = ref({});

const sortColumn = ref(null);
const sortDirection = ref('none'); // 'asc', 'desc', 'none'

// The single merged data structure for all display logic
const mergedQuestions = ref([]);

const emojiForDifficulty = (diff) => {
  switch (diff) {
    case 'EASY': return '🟢';
    case 'MEDIUM': return '🟡';
    case 'HARD': return '🔴';
    case 'EXPERT': return '🧠';
    case 'UNRATED': return '❓';
    default: return '❓';
  }
};

const difficultyButtonClass = (diff) => {
  return filters.value.includes(diff)
    ? 'bg-blue-200 border-blue-400'
    : 'bg-white border-gray-300';
};

const toggleFilter = (diff) => {
  if (filters.value.includes(diff)) {
    filters.value = filters.value.filter(f => f !== diff);
  } else {
    filters.value.push(diff);
  }
};

const getCategoryUrl = (cat) => {
  if (!cat || typeof cat !== 'string') return null;
  // Remove any prefix like 'Category:' or '[[:Category:' and any trailing ']' or whitespace
  let catName = cat.replace(/^\[\[:]?Category:/, '').replace(/\]+$/, '').trim();
  return `https://commons.wikimedia.org/wiki/Category:${encodeURIComponent(catName)}`;
};
const getCategoryName = (cat) => {
  if (!cat || typeof cat !== 'string') return '';
  // Remove any prefix like 'Category:' or '[[:Category:' and any trailing ']' or whitespace
  return cat.replace(/^\[\[:]?Category:/, '').replace(/\]+$/, '').trim();
};
const getWikidataUrl = (qid) => {
  if (!qid) return null;
  const match = qid.match(/Q\d+/);
  return match ? `https://www.wikidata.org/wiki/${match[0]}` : null;
};

// Build mergedQuestions on mount
onMounted(async () => {
  try {
    // Start both requests simultaneously and parse responses in parallel
    const [groupsResp, yamlResp] = await Promise.all([
      fetch('/api/groups'),
      fetch('/api/depicts/yaml-spec')
    ]);
    
    // Parse both responses in parallel
    const [groupsData, yamlSpecData] = await Promise.all([
      groupsResp.json(),
      yamlResp.json()
    ]);
    
    // Assign the parsed data
    groupsApiData.value = groupsData;
    yamlData.value = yamlSpecData;
    levels.value = yamlSpecData.global?.levels || {};
  } catch (error) {
    console.error('Error loading data:', error);
    // Set empty values so loading state clears
    groupsApiData.value = {};
    yamlData.value = { questions: [] };
    levels.value = {};
  }

  // Build mergedQuestions
  const apiSubs = [];
  if (groupsApiData.value && typeof groupsApiData.value === 'object') {
    for (const apiGroup of Object.values(groupsApiData.value)) {
      if (apiGroup.subGroups) {
        apiSubs.push(...apiGroup.subGroups);
      }
    }
  }
  mergedQuestions.value = (yamlData.value.questions || []).map(q => {
    // Normalize categories
    let cats = [];
    if (Array.isArray(q.categories)) {
      cats = q.categories;
    } else if (Array.isArray(q.category)) {
      cats = q.category;
    } else if (q.category) {
      cats = [q.category];
    }
    // Find matching API subGroup for extra display data
    let apiSub = null;
    const qid = typeof q.depictsId === 'string' ? q.depictsId.replace(/\{\{Q\|([^}]+)\}\}/, '$1') : '';
    apiSub = apiSubs.find(sub => {
      const subQid = sub.depicts_id || '';
      if (qid && subQid && qid === subQid) return true;
      if (q.name && (sub.display_name === q.name || sub.name === q.name)) return true;
      return false;
    });
    const difficulty = q.difficulty || 'UNRATED';

    // Calculate unanswered for main question
    let mainUnanswered = typeof (apiSub && apiSub.unanswered) === 'number' ? apiSub.unanswered : null;

    return {
      ...q,
      ...(apiSub || {}),
      difficulty,
      categories: cats,
      categoryUrl: cats.length ? getCategoryUrl(cats[0]) : null,
      wikidataUrl: getWikidataUrl(q.depictsId),
      name: q.name,
      route_name: `depicts/${qid}`,
      id: (q.depictsId || '') + '-' + (q.name || ''),
      group: q.group || 'Other',
      unanswered: mainUnanswered
    };
  });

  // Set up difficulty filters (only those with questions)
  const allDiffs = new Set();
  for (const q of mergedQuestions.value) {
    if (q.difficulty) allDiffs.add(q.difficulty);
  }
  difficultyFilters.value = Array.from(allDiffs);
});

// Grouped for main display (by YAML group, only unanswered > 0)
const groupedQuestions = computed(() => {
  const groupMap = {};
  for (const q of mergedQuestions.value) {
    if (q.difficulty !== 'UNRATED' && typeof q.unanswered === 'number' && q.unanswered > 0) {
      if (!groupMap[q.group]) {
        groupMap[q.group] = {
          display_name: q.group,
          questions: []
        };
      }
      if (filters.value.length === 0 || filters.value.includes(q.difficulty)) {
        groupMap[q.group].questions.push(q);
      }
    }
  }
  // Sort by unanswered descending
  for (const group of Object.values(groupMap)) {
    group.questions.sort((a, b) => (b.unanswered || 0) - (a.unanswered || 0));
  }
  return Object.values(groupMap);
});

// Unrated questions (unanswered > 0)
const unratedQuestions = computed(() => {
  return mergedQuestions.value.filter(q => q.difficulty === 'UNRATED' && typeof q.unanswered === 'number' && q.unanswered > 0);
});

// Dummy for hasUnrated (for filter UI)
const hasUnrated = computed(() => mergedQuestions.value.some(q => q.difficulty === 'UNRATED'));

const sortedMergedQuestions = computed(() => {
  if (sortDirection.value === 'none' || !sortColumn.value) {
    return mergedQuestions.value;
  }

  return [...mergedQuestions.value].sort((a, b) => {
    let valA = a[sortColumn.value];
    let valB = b[sortColumn.value];

    // Treat null or undefined 'unanswered' as 0 for sorting
    if (sortColumn.value === 'unanswered') {
      valA = valA == null ? 0 : valA;
      valB = valB == null ? 0 : valB;
    }

    if (valA < valB) {
      return sortDirection.value === 'asc' ? -1 : 1;
    }
    if (valA > valB) {
      return sortDirection.value === 'asc' ? 1 : -1;
    }
    return 0;
  });
});

const regenerateJob = async (q) => {
  const key = (q.depictsId || '') + '-' + (q.name || '');
  regenerating.value[key] = true;
  alert('Regenerating happens via a background queue, and it may take some time for questions to start appearing.');
  // Extract Qid if in {{Q|...}} format
  let depictsId = q.depictsId;
  const match = typeof depictsId === 'string' && depictsId.match(/^\{\{Q\|(.+)\}\}$/);
  if (match) depictsId = match[1];
  try {
    const resp = await fetch('/api/regenerate-question', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${window.apiToken}`,
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
      },
      body: JSON.stringify({
        depictsId
      })
    });
    if (!resp.ok) {
      if (resp.status === 401) {
        alert('You must be logged in with an API token to regenerate questions.');
        return;
      }
      alert('Failed to trigger regeneration.');
    }
  } catch (e) {
    console.error('Error triggering regeneration:', e);
  }
  // Do not re-enable the button
};

const toggleSort = (columnKey) => {
  if (sortColumn.value === columnKey) {
    if (sortDirection.value === 'asc') {
      sortDirection.value = 'desc';
    } else if (sortDirection.value === 'desc') {
      sortDirection.value = 'none';
      sortColumn.value = null; // Reset column when going to 'none'
    } else { // 'none'
      sortDirection.value = 'asc';
    }
  } else {
    sortColumn.value = columnKey;
    sortDirection.value = 'asc';
  }
};

const clearUnanswered = async (q) => {
  const key = (q.depictsId || '') + '-' + (q.name || '');
  // Disable the clear button for this question for the rest of the session
  clearing.value[key] = true;
  // Also mark a global flag to prevent any further clears
  clearing.value.__allDisabled = true;
  alert('Questions clearing in the background, this may take some time...');
  try {
    // Always clear the main depicts group
    const requests = [
      fetch('/api/clear-unanswered', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${window.apiToken}`,
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({
          groupName: q.route_name // Use the actual group name as used in the backend
        })
      })
    ];
    await Promise.all(requests);
  } catch (e) {
    console.error('Error clearing unanswered questions:', e);
  }
  // Do not re-enable the button
};

const getDepictsDownQueryUrl = (depictsId) => {
  if (!depictsId) return '';
  const cleanId = depictsId.replace(/\{\{Q\|([^}]+)\}\}/, '$1');
  return generateDepictsDownQueryUrl(cleanId);
};

const getDepictsUpQueryUrl = (depictsId) => {
  if (!depictsId) return '';
  const cleanId = depictsId.replace(/\{\{Q\|([^}]+)\}\}/, '$1');
  return generateDepictsUpQueryUrl(cleanId);
};

</script>

<style scoped>
/* Add any component-specific styles here */
a[href].text-blue-700.dark\:text-blue-400,
a.text-blue-700.dark\:text-blue-400,
a.text-blue-700:hover.dark\:text-blue-400,
a.text-blue-700.hover\:underline.dark\:text-blue-400 {
  /* Lighter blue for dark mode links */
  color: #60a5fa !important; /* tailwind blue-400 */
}

a.text-blue-700:hover,
a.text-blue-700.hover\:underline {
  color: #2563eb !important; /* tailwind blue-600 for hover */
}

/* For font-mono links in dark mode */
a.font-mono.text-blue-700.dark\:text-blue-400 {
  color: #60a5fa !important;
}
</style>
