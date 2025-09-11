<template>
  <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WikiCrowd (Depicts)</h1>
        <p class="text-sm text-gray-500 dark:text-gray-300">Quick and easy micro contributions to the wiki space, showing what images depict.</p>
      </div>
    </div>

    <div class="mb-4">
      <div class="font-semibold text-base mb-1 text-gray-900 dark:text-gray-100">Important notes</div>
      <ul class="list-disc pl-6 text-sm text-gray-700 dark:text-gray-300">
        <li>Using this tool will result in edits being made for your account, you are responsible for these edits.</li>
        <li>Familiarize yourself with the Qid concept that you are tagging before you begin. <b>Read the labels and descriptions in your own language.</b></li>
        <li>Familiarize yourself with <a href="https://commons.wikimedia.org/wiki/Commons:Depicts" target="_blank" rel="noopener" class="text-blue-700 dark:text-blue-400 underline">https://commons.wikimedia.org/wiki/Commons:Depicts</a></li>
      </ul>
    </div>

    <DepictsGroupsFromYaml />

    <DepictsCustom />

    <div class="flex justify-center mt-4 sm:items-center sm:justify-between">
      <div class="ml-4 text-sm text-gray-500 dark:text-gray-300 sm:ml-0">
        <p>Developed by <a href="https://twitter.com/addshore" target="_blank" class="text-blue-700 dark:text-blue-400 underline">Addshore</a> (<a href="https://github.com/addshore/wikicrowd" target="_blank" class="text-blue-700 dark:text-blue-400 underline">source code</a>)</p>
        <p>Questions: {{ stats.questions }} | Answers: {{ stats.answers }} | Edits: {{ stats.edits }} | Users: {{ stats.users }}</p>
        <p>Jobs: High: {{ stats.jobs_high }} | Default: {{ stats.jobs_default }} | Low: {{ stats.jobs_low }}</p>
        <p>
          Commons:&nbsp;
          <a target="_blank" href="https://commons.wikimedia.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2" class="text-blue-700 dark:text-blue-400 underline">All edits</a>
          <a target="_blank" href="https://editgroups-commons.toolforge.org/?tool=wikicrowd" class="text-blue-700 dark:text-blue-400 underline">Edit groups</a>
          <span v-if="isAuthed">/
            <a target="_blank" href="https://commons.wikimedia.org/w/index.php?hidebyothers=1&hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special%3ARecentChanges&urlversion=2" class="text-blue-700 dark:text-blue-400 underline">Your edits</a>
          </span>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import DepictsGroupsFromYaml from '../components/DepictsGroupsFromYaml.vue';
import DepictsCustom from '../components/DepictsCustom.vue';

const stats = ref({ questions: 0, answers: 0, edits: 0, users: 0 });
const isAuthed = ref(false);

onMounted(async () => {
  isAuthed.value = window.apiToken !== null;
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
