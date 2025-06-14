<template>
  <a :href="'/questions/' + (sub.route_name || sub.name)" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col group hover:bg-blue-50 dark:hover:bg-gray-700 transition cursor-pointer" style="text-decoration: none;">
    <!-- Use YAML name if available -->
    <div class="font-semibold text-lg text-gray-900 dark:text-gray-100 mb-1 flex items-center">
      <span>{{ emojiForDifficulty(sub.difficulty) }}</span>
      <span class="ml-2">{{ sub.name}}</span>
    </div>
    <div v-if="sub.depicts_id" class="text-xs text-blue-700 dark:text-blue-400 mb-1 flex items-center">
      <a :href="getWikidataUrl(sub.depicts_id)" target="_blank" rel="noopener" class="hover:underline flex items-center" @click.stop>
        <span class="mr-1">🔗</span>
        <span>{{ sub.depicts_id }}</span>
        <span class="ml-1">(<WikidataLabel :qid="sub.depicts_id" :fallback="sub.depicts_id" />)</span>
      </a>
    </div>
    <div v-if="sub.depicts_id" class="text-xs text-gray-600 dark:text-gray-400 mb-2">
      <WikidataDescription :qid="sub.depicts_id" />
    </div>
    <div class="flex flex-wrap gap-2 mb-2">
      <template v-if="sub.categories && sub.categories.length">
        <span v-for="cat in sub.categories" :key="cat">
          <a :href="getCategoryUrl(cat)" target="_blank" rel="noopener" class="text-xs text-blue-700 dark:text-blue-400 hover:underline flex items-center" @click.stop>
            <span class="mr-1">📂</span>{{ getCategoryName(cat) }}
          </a>
        </span>
      </template>
    </div>
    <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Unanswered: {{ sub.unanswered }}</div>
    <div v-if="sub.example_question && sub.example_question.properties && sub.example_question.properties.img_url" class="mb-2 flex justify-center">
      <img :src="sub.example_question.properties.img_url" alt="Sample" class="rounded max-h-32 object-contain border" />
    </div>
  </a>
</template>

<script setup>
import WikidataLabel from './WikidataLabel.vue';
import WikidataDescription from './WikidataDescription.vue';
const props = defineProps({
  sub: { type: Object, required: true },
  emojiForDifficulty: { type: Function, required: true },
  getCategoryUrl: { type: Function, required: true },
  getCategoryName: { type: Function, required: true },
  getWikidataUrl: { type: Function, required: true },
});
// Expose props for template destructuring
const { sub, emojiForDifficulty, getCategoryUrl, getCategoryName, getWikidataUrl } = props;
</script>

<style scoped>
</style>
