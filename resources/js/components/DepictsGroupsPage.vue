<template>
  <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">WikiCrowd (Depicts)</h1>
        <p class="text-sm text-gray-500">Quick and easy micro contributions to the wiki space, showing what images depict.<br>Using this tool will result in edits being made for your account.</p>
      </div>
      <x-top-right-navbar/>
    </div>

    <DepictsGroupsFromYaml />

    <div class="flex justify-center mt-4 sm:items-center sm:justify-between">
      <div class="ml-4 text-sm text-gray-500 sm:ml-0">
        <p>Developed by <a href="https://twitter.com/addshore" target="_blank">Addshore</a> (<a href="https://github.com/addshore/wikicrowd" target="_blank">source code</a>)</p>
        <p>Questions: {{ stats.questions }} | Answers: {{ stats.answers }} | Edits: {{ stats.edits }} | Users: {{ stats.users }}</p>
        <p>
          Wikidata:&nbsp;
          <a target="_blank" href="https://www.wikidata.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2">All edits</a>
          <span v-if="isAuthed">/
            <a target="_blank" href="https://www.wikidata.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2&hidebyothers=1">Your edits</a>
          </span>
        </p>
        <p>
          Commons:&nbsp;
          <a target="_blank" href="https://commons.wikimedia.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2">All edits</a>
          <span v-if="isAuthed">/
            <a target="_blank" href="https://commons.wikimedia.org/w/index.php?hidebots=1&translations=filter&hideWikibase=1&tagfilter=OAuth+CID%3A+2642&limit=500&days=7&title=Special:RecentChanges&urlversion=2&hidebyothers=1">Your edits</a>
          </span>
        </p>
        <p>
          <a href="/api/docs">API Documentation</a>
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
  // Optionally fetch stats from an API if needed
  // const resp = await fetch('/api/stats');
  // stats.value = await resp.json();
  // isAuthed.value = ...
});
</script>

<style scoped>
</style>
