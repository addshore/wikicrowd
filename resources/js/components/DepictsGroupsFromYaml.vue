<template>
  <div>
    <div class="mb-4">
      <div class="font-semibold text-base mb-1">Important notes</div>
      <ul class="list-disc pl-6 text-sm text-gray-700 dark:text-gray-300">
        <li>Using this tool will result in edits being made for your account, you are responsible for these edits.</li>
        <li>Familiarize yourself with the Qid concept that you are tagging before you begin. <b>Read the labels and descriptions in your own language.</b></li>
        <li class="font-bold text-red-600">A statue, or painting of a thing, is not the thing itself (does not depict)</li>
        <li>Familiarize yourself with <a href="https://commons.wikimedia.org/wiki/Commons:Depicts" target="_blank" rel="noopener" class="text-blue-700 underline">https://commons.wikimedia.org/wiki/Commons:Depicts</a></li>
      </ul>
    </div>
    <div v-if="difficultyFilters.length > 0 || hasUnrated" class="flex flex-col gap-2 mb-4">
      <div class="font-semibold text-base mb-1">Filter by difficulty</div>
      <div v-for="(level, key) in levels" :key="key" class="flex items-center">
        <button class="px-3 py-1 rounded border mr-2" :class="difficultyButtonClass(key)" @click="toggleFilter(key)" :title="level.desc">
          <span>{{ emojiForDifficulty(key) }}</span> {{ level.name }}
        </button>
        <span class="text-gray-600 text-sm">{{ level.desc }}</span>
      </div>
      <div v-if="hasUnrated" class="flex items-center">
        <button class="px-3 py-1 rounded border mr-2" :class="difficultyButtonClass('UNRATED')" @click="toggleFilter('UNRATED')" title="Show unrated questions">
          ❓ Unrated
        </button>
        <span class="text-gray-600 text-sm">Unrated groups have not been assigned a difficulty yet.</span>
      </div>
    </div>
    <div v-for="(group, groupKey) in visibleGroups" :key="groupKey" class="mb-8">
      <h2 class="text-xl font-bold mb-2">{{ group.display_name }}</h2>
      <div v-if="group.display_description" class="mb-2 text-gray-600 text-sm">{{ group.display_description }}</div>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <DepictsGroupBox
          v-for="sub in group.filteredSubGroups"
          :key="sub.id"
          :sub="sub"
          :emojiForDifficulty="emojiForDifficulty"
          :getCategoryUrl="getCategoryUrl"
          :getCategoryName="getCategoryName"
          :getWikidataUrl="getWikidataUrl"
        />
      </div>
    </div>
    <div v-if="unratedGroups.length > 0" class="mb-8">
      <h2 class="text-xl font-bold mb-2">❓ Unrated</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <DepictsGroupBox
          v-for="sub in unratedGroups"
          :key="sub.id"
          :sub="sub"
          :emojiForDifficulty="emojiForDifficulty"
          :getCategoryUrl="getCategoryUrl"
          :getCategoryName="getCategoryName"
          :getWikidataUrl="getWikidataUrl"
        />
      </div>
    </div>
    <div class="mt-12 border-t pt-8">
      <h2 class="text-xl font-bold mb-4">Regenerate</h2>
      <span class="block mt-2 text-sm text-gray-600">Config for this tool can be found at
        <a href="https://commons.wikimedia.org/wiki/User:Addshore/wikicrowd.yaml" target="_blank" rel="noopener" class="text-blue-700 underline">
          https://commons.wikimedia.org/wiki/User:Addshore/wikicrowd.yaml
        </a>
      </span>
      <span class="block mt-2 text-sm text-gray-600">All questions should already regenerate every 6 hours...</span>
      <span class="block mt-2 text-sm text-gray-600">
        If you have made changes to the YAML file, you can regenerate questions here.</span>
      <div v-if="yamlData && yamlData.questions && yamlData.questions.length">
        <table class="min-w-full text-sm border">
          <thead>
            <tr class="bg-gray-100">
              <th class="p-2 border">Name</th>
              <th class="p-2 border">Depicts</th>
              <th class="p-2 border">Categories</th>
              <th class="p-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="q in yamlData.questions" :key="q.depictsId + '-' + q.name">
              <td class="p-2 border">{{ q.name }}</td>
              <td class="p-2 border">
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
              <td class="p-2 border">
                <template v-if="q.categories && q.categories.length">
                  <span v-for="(cat, idx) in q.categories" :key="cat">
                    <a :href="getCategoryUrl(cat)" target="_blank" class="text-blue-700 hover:underline">{{ getCategoryName(cat) }}</a><span v-if="idx < q.categories.length - 1">, </span>
                  </span>
                </template>
                <template v-else-if="Array.isArray(q.category) && q.category.length">
                  <span v-for="(cat, idx) in q.category" :key="cat">
                    <a :href="getCategoryUrl(cat)" target="_blank" class="text-blue-700 hover:underline">{{ getCategoryName(cat) }}</a><span v-if="idx < q.category.length - 1">, </span>
                  </span>
                </template>
                <template v-else-if="typeof q.category === 'string' && q.category">
                  <a :href="getCategoryUrl(q.category)" target="_blank" class="text-blue-700 hover:underline">{{ getCategoryName(q.category) }}</a>
                </template>
                <template v-else>-</template>
              </td>
              <td class="p-2 border">
                <button
                  class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700"
                  @click="regenerateJob(q)"
                >
                  <span v-if="regenerating[q.depictsId + '-' + q.name]">Regenerating...</span>
                  <span v-else>Regenerate</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="text-gray-500">No YAML questions found.</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import DepictsGroupBox from './DepictsGroupBox.vue';
import WikidataLabel from './WikidataLabel.vue';
import WikidataDescription from './WikidataDescription.vue';

const yamlData = ref(null);
const levels = ref({});
const filters = ref([]);
const hasUnrated = ref(false);
const unratedGroups = ref([]);
const difficultyFilters = ref([]);
const regenerating = ref({});
const groupsApiData = ref([]);

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

const visibleGroups = computed(() => {
  // Group questions by 'group' field from YAML only
  const result = [];
  unratedGroups.value = [];
  hasUnrated.value = false;
  if (!yamlData.value || !yamlData.value.questions) return result;
  // Build a map: groupName -> { display_name, display_description, filteredSubGroups: [] }
  const groupMap = {};
  for (const q of yamlData.value.questions) {
    const groupName = q.group || 'Other';
    if (!groupMap[groupName]) {
      groupMap[groupName] = {
        display_name: groupName,
        display_description: '', // Optionally add description if present in YAML
        filteredSubGroups: []
      };
    }
    // Determine difficulty
    const difficulty = q.difficulty || 'UNRATED';
    if (difficulty === 'UNRATED') hasUnrated.value = true;
    if (filters.value.length === 0 || filters.value.includes(difficulty)) {
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
      if (groupsApiData.value && typeof groupsApiData.value === 'object') {
        outer: for (const apiGroup of Object.values(groupsApiData.value)) {
          if (!apiGroup.subGroups) continue;
          for (const sub of apiGroup.subGroups) {
            // Match by depictsId (Qid) or name
            const qid = typeof q.depictsId === 'string' ? q.depictsId.replace(/\{\{Q\|([^}]+)\}\}/, '$1') : '';
            const subQid = sub.depicts_id || '';
            if (qid && subQid && qid === subQid) { apiSub = sub; break outer; }
            if (q.name && (sub.display_name === q.name || sub.name === q.name)) { apiSub = sub; break outer; }
          }
        }
      }
      // Build subGroup object, merging API data if found
      const sub = {
        ...q,
        ...(apiSub || {}), // merge API display data (label, description, unanswered, image, etc.)
        difficulty,
        categories: cats,
        categoryUrl: cats.length ? getCategoryUrl(cats[0]) : null,
        wikidataUrl: getWikidataUrl(q.depictsId),
        name: q.name,
        route_name: q.depictsId ? `depicts/${q.depictsId.replace(/\{\{Q\|([^}]+)\}\}/, '$1')}` : q.name,
        id: (q.depictsId || '') + '-' + (q.name || '')
      };
      // Only show boxes with unanswered > 0
      if (sub.unanswered && sub.unanswered > 0) {
        if (difficulty === 'UNRATED') {
          unratedGroups.value.push(sub);
        } else {
          groupMap[groupName].filteredSubGroups.push(sub);
        }
      }
    }
  }
  // Convert map to array, only include groups with questions
  for (const group of Object.values(groupMap)) {
    if (group.filteredSubGroups.length > 0) {
      group.filteredSubGroups.sort((a, b) => (b.unanswered || 0) - (a.unanswered || 0));
      result.push(group);
    }
  }
  return result;
});

onMounted(async () => {
  // Fetch both YAML and API groups from local endpoints
  const [groupsResp, yamlResp] = await Promise.all([
    fetch('/api/groups'),
    fetch('/api/depicts/yaml-spec')
  ]);
  groupsApiData.value = await groupsResp.json();
  yamlData.value = await yamlResp.json();
  levels.value = yamlData.value.global?.levels || {};
  // Set up difficulty filters (only those with questions)
  const allDiffs = new Set();
  for (const q of yamlData.value.questions || []) {
    if (q.difficulty) { allDiffs.add(q.difficulty); }
  }
  difficultyFilters.value = Array.from(allDiffs);
});
</script>

<style scoped>
/* Add any component-specific styles here */
</style>
