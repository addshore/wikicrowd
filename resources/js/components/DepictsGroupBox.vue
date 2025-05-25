<template>
  <a :href="'/questions/' + props.sub.name" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col group hover:bg-blue-50 dark:hover:bg-gray-700 transition cursor-pointer" style="text-decoration: none;">
    <div class="font-semibold text-lg text-gray-900 dark:text-white mb-1 flex items-center">
      <span>{{ props.emojiForDifficulty(props.sub.difficulty) }}</span>
      <span class="ml-2">{{ props.sub.display_name }}</span>
    </div>
    <div v-if="props.sub.depicts_id" class="text-xs text-blue-700 mb-1 flex items-center">
      <a :href="props.getWikidataUrl(props.sub.depicts_id)" target="_blank" rel="noopener" class="hover:underline flex items-center" @click.stop>
        <span class="mr-1">🔗</span>
        <span>{{ props.sub.depicts_id }}</span>
        <span class="ml-1">(<WikidataLabel :qid="props.sub.depicts_id" :fallback="props.sub.depicts_id" />)</span>
      </a>
    </div>
    <div v-if="props.sub.depicts_id" class="text-xs text-gray-600 dark:text-gray-400 mb-2">
      <WikidataDescription :qid="props.sub.depicts_id" />
    </div>
    <div v-if="props.sub.display_description" class="text-sm text-gray-700 dark:text-gray-300 mb-2">{{ props.sub.display_description }}</div>
    <div class="flex flex-wrap gap-2 mb-2">
      <template v-if="props.sub.categories && props.sub.categories.length">
        <span v-for="cat in props.sub.categories" :key="cat">
          <a :href="props.getCategoryUrl(cat)" target="_blank" rel="noopener" class="text-xs text-blue-700 hover:underline flex items-center" @click.stop>
            <span class="mr-1">📂</span>{{ props.getCategoryName(cat) }}
          </a>
        </span>
      </template>
    </div>
    <div class="text-xs text-gray-500 mb-2">Unanswered: {{ props.sub.unanswered }}</div>
    <div v-if="props.sub.example_question && props.sub.example_question.properties && props.sub.example_question.properties.img_url" class="mb-2 flex justify-center">
      <img :src="props.sub.example_question.properties.img_url" alt="Sample" class="rounded max-h-32 object-contain border" />
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
</script>

<style scoped>
</style>
