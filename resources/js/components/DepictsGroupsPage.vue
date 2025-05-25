<template>
  <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WikiCrowd (Depicts)</h1>
        <p class="text-sm text-gray-500 dark:text-gray-300">Quick and easy micro contributions to the wiki space, showing what images depict.</p>
      </div>
    </div>

    <!-- Filter by difficulty heading -->
    <!-- This is inside DepictsGroupsFromYaml, already fixed -->

    <DepictsGroupsFromYaml />

    <div class="flex justify-center mt-4 sm:items-center sm:justify-between">
      <div class="ml-4 text-sm text-gray-500 dark:text-gray-300 sm:ml-0">
        <p>Developed by <a href="https://twitter.com/addshore" target="_blank" class="text-blue-700 dark:text-blue-400 underline">Addshore</a> (<a href="https://github.com/addshore/wikicrowd" target="_blank" class="text-blue-700 dark:text-blue-400 underline">source code</a>)</p>
        <p>Questions: {{ stats.questions }} | Answers: {{ stats.answers }} | Edits: {{ stats.edits }} | Users: {{ stats.users }}</p>
        <p>
          Commons:&nbsp;
          <a target="_blank" href="https://commons.wikimedia.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2" class="text-blue-700 dark:text-blue-400 underline">All edits</a>
          <span v-if="isAuthed">/
            <a target="_blank" href="https://commons.wikimedia.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2&hidebyothers=1" class="text-blue-700 dark:text-blue-400 underline">Your edits</a>
          </span>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import DepictsGroupsFromYaml from '../components/DepictsGroupsFromYaml.vue';

const stats = ref({ questions: 0, answers: 0, edits: 0, users: 0 });
const isAuthed = ref(false);

onMounted(async () => {
  try {
    const resp = await fetch('/api/stats');
    if (resp.ok) {
      stats.value = await resp.json();
    }
  } catch (e) {
    // Optionally handle error
  }
});
</script>
