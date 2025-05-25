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
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import DepictsGroupBox from './DepictsGroupBox.vue';

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
          subWithDiff.name = yamlQ.name || sub.display_name || sub.name;
          // No getSampleImage here; sample image comes from sub.example_question
        } else {
          subWithDiff.categories = sub.categories || [];
          subWithDiff.categoryUrl = null;
          subWithDiff.wikidataUrl = null;
          subWithDiff.name = sub.display_name || sub.name;
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
