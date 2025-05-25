<template>
  <a :href="'/questions/' + sub.name" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col group hover:bg-blue-50 dark:hover:bg-gray-700 transition cursor-pointer" style="text-decoration: none;">
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
</template>

<script setup>
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
