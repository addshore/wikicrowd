<template>
  <div>
    <div class="flex flex-col gap-2 mb-4" v-if="difficultyFilters.length > 0 || hasUnrated">
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
        <a v-for="sub in group.filteredSubGroups" :key="sub.id" :href="'/questions/' + sub.name" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col group hover:bg-blue-50 dark:hover:bg-gray-700 transition cursor-pointer" style="text-decoration: none;">
          <div class="font-semibold text-lg text-gray-900 dark:text-white mb-1 flex items-center">
            <span>{{ emojiForDifficulty(sub.difficulty) }}</span>
            <span class="ml-2">{{ sub.display_name }}</span>
          </div>
          <div v-if="sub.display_description" class="text-sm text-gray-700 dark:text-gray-300 mb-2">{{ sub.display_description }}</div>
          <div v-if="sub.example_question && sub.example_question.properties && sub.example_question.properties.img_url" class="mb-2 flex justify-center">
            <img :src="sub.example_question.properties.img_url" alt="Sample" class="rounded max-h-32 object-contain border" />
          </div>
          <div class="flex flex-wrap gap-2 mb-2">
            <template v-if="sub.categories && sub.categories.length">
              <span v-for="cat in sub.categories" :key="cat">
                <a :href="getCategoryUrl(cat)" target="_blank" rel="noopener" class="text-xs text-blue-700 hover:underline flex items-center" @click.stop>
                  <span class="mr-1">📂</span>{{ getCategoryName(cat) }}
                </a>
              </span>
            </template>
            <a v-if="sub.depicts_id" :href="getWikidataUrl(sub.depicts_id)" target="_blank" rel="noopener" class="text-xs text-blue-700 hover:underline flex items-center" @click.stop>
              <span class="mr-1">🔗</span>{{ sub.depicts_id }}
            </a>
          </div>
          <div class="text-xs text-gray-500 mb-2">Unanswered: {{ sub.unanswered }}</div>
        </a>
      </div>
    </div>
    <div v-if="unratedGroups.length > 0" class="mb-8">
      <h2 class="text-xl font-bold mb-2">❓ Unrated</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <a v-for="sub in unratedGroups" :key="sub.id" :href="'/questions/' + sub.name" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col group hover:bg-blue-50 dark:hover:bg-gray-700 transition cursor-pointer" style="text-decoration: none;">
          <div class="font-semibold text-lg text-gray-900 dark:text-white mb-1 flex items-center">
            <span>{{ emojiForDifficulty('UNRATED') }}</span>
            <span class="ml-2">{{ sub.display_name }}</span>
          </div>
          <div v-if="sub.display_description" class="text-sm text-gray-700 dark:text-gray-300 mb-2">{{ sub.display_description }}</div>
          <div v-if="sub.example_question && sub.example_question.properties && sub.example_question.properties.img_url" class="mb-2 flex justify-center">
            <img :src="sub.example_question.properties.img_url" alt="Sample" class="rounded max-h-32 object-contain border" />
          </div>
          <div class="flex flex-wrap gap-2 mb-2">
            <template v-if="sub.categories && sub.categories.length">
              <span v-for="cat in sub.categories" :key="cat">
                <a :href="getCategoryUrl(cat)" target="_blank" rel="noopener" class="text-xs text-blue-700 hover:underline flex items-center" @click.stop>
                  <span class="mr-1">📂</span>{{ getCategoryName(cat) }}
                </a>
              </span>
            </template>
            <a v-if="sub.depicts_id" :href="getWikidataUrl(sub.depicts_id)" target="_blank" rel="noopener" class="text-xs text-blue-700 hover:underline flex items-center" @click.stop>
              <span class="mr-1">🔗</span>{{ sub.depicts_id }}
            </a>
          </div>
          <div class="text-xs text-gray-500 mb-2">Unanswered: {{ sub.unanswered }}</div>
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const groups = ref({});
const yamlData = ref(null);
const levels = ref({});
const filters = ref([]);
const hasUnrated = ref(false);
const unratedGroups = ref([]);
const difficultyFilters = ref([]);

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
  if (!cat) return null;
  // Remove any prefix like 'Category:' or '[[:Category:' and any trailing ']' or whitespace
  let catName = cat.replace(/^\[\[:]?Category:/, '').replace(/\]+$/, '').trim();
  return `https://commons.wikimedia.org/wiki/Category:${encodeURIComponent(catName)}`;
};
const getCategoryName = (cat) => {
  if (!cat) return '';
  // Remove any prefix like 'Category:' or '[[:Category:' and any trailing ']' or whitespace
  return cat.replace(/^\[\[:]?Category:/, '').replace(/\]+$/, '').trim();
};
const getWikidataUrl = (qid) => {
  if (!qid) return null;
  const match = qid.match(/Q\d+/);
  return match ? `https://www.wikidata.org/wiki/${match[0]}` : null;
};

const visibleGroups = computed(() => {
  // Merge YAML and group API data
  const result = [];
  unratedGroups.value = [];
  hasUnrated.value = false;
  if (!groups.value || !yamlData.value) return result;
  for (const [groupKey, group] of Object.entries(groups.value)) {
    const filteredSubGroups = [];
    for (const sub of group.subGroups) {
      // Find YAML question for this group by depictsId or name
      let yamlQ = null;
      if (yamlData.value.questions) {
        yamlQ = yamlData.value.questions.find(q => {
          // Try to match depictsId (strip {{Q|...}})
          if (q.depictsId && sub.name.endsWith(q.depictsId.replace(/\{\{Q\|([^}]+)\}\}/, '$1'))) return true;
          // Or match by name
          if (q.name && sub.display_name && q.name === sub.display_name) return true;
          return false;
        });
      }
      const difficulty = yamlQ?.difficulty || 'UNRATED';
      if (difficulty === 'UNRATED') hasUnrated.value = true;
      // Filter by difficulty
      if (filters.value.length === 0 || filters.value.includes(difficulty)) {
        const subWithDiff = { ...sub, difficulty };
        // Add category and wikidata links, and categories array
        if (yamlQ) {
          // Support both 'category' and 'categories' (array)
          let cats = [];
          if (Array.isArray(yamlQ.categories)) cats = yamlQ.categories;
          else if (yamlQ.category) cats = [yamlQ.category];
          subWithDiff.categories = cats;
          subWithDiff.categoryUrl = cats.length ? getCategoryUrl(cats[0]) : null;
          subWithDiff.wikidataUrl = getWikidataUrl(yamlQ.depictsId);
          // No getSampleImage here; sample image comes from sub.example_question
        } else {
          subWithDiff.categories = sub.categories || [];
          subWithDiff.categoryUrl = null;
          subWithDiff.wikidataUrl = null;
          // No sampleImage here; sample image comes from sub.example_question
        }
        if (difficulty === 'UNRATED') {
          unratedGroups.value.push(subWithDiff);
        } else {
          filteredSubGroups.push(subWithDiff);
        }
      }
    }
    if (filteredSubGroups.length > 0) {
      result.push({ ...group, filteredSubGroups });
    }
  }
  return result;
});

onMounted(async () => {
  const [groupsResp, yamlResp] = await Promise.all([
    fetch('/api/groups'),
    fetch('/api/depicts/yaml-spec')
  ]);
  groups.value = await groupsResp.json();
  yamlData.value = await yamlResp.json();
  levels.value = yamlData.value.global?.levels || {};
  // Set up difficulty filters (only those with questions)
  const allDiffs = new Set();
  for (const q of yamlData.value.questions || []) {
    if (q.difficulty) allDiffs.add(q.difficulty);
  }
  difficultyFilters.value = Array.from(allDiffs);
});
</script>

<style scoped>
</style>
