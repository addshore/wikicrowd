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

    <div v-if="isOfflineModeEnabled" class="mb-4">
        <div class="font-semibold text-base mb-1 text-gray-900 dark:text-gray-100">Offline Mode</div>
        <div class="flex gap-2">
            <button @click="exportOfflineData" class="bg-blue-600 text-white px-4 py-2 rounded font-bold hover:bg-blue-700">Export Offline Data</button>
            <input type="file" @change="importOfflineData" accept=".json" class="hidden" ref="importFile">
            <button @click="triggerImport" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700">Import Offline Data</button>
            <button @click="syncOfflineAnswers" class="bg-purple-600 text-white px-4 py-2 rounded font-bold hover:bg-purple-700">Sync Offline Answers</button>
            <button @click="clearOfflineData" class="bg-red-600 text-white px-4 py-2 rounded font-bold hover:bg-red-700">Clear Offline Data</button>
        </div>
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
import { useOfflineMode } from '../composables/useOfflineMode';
import DepictsGroupsFromYaml from '../components/DepictsGroupsFromYaml.vue';
import DepictsCustom from '../components/DepictsCustom.vue';

const { isOfflineModeEnabled, updateOfflineStats } = useOfflineMode();
const stats = ref({ questions: 0, answers: 0, edits: 0, users: 0 });
const isAuthed = ref(false);
const importFile = ref(null);

function exportOfflineData() {
  const data = {};
  for (let i = 0; i < localStorage.length; i++) {
    const key = localStorage.key(i);
    if (key.startsWith('wikicrowd-')) {
      data[key] = localStorage.getItem(key);
    }
  }

  const dataStr = JSON.stringify(data, null, 2);
  const dataBlob = new Blob([dataStr], { type: 'application/json' });
  const url = URL.createObjectURL(dataBlob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'wikicrowd-offline-data.json';
  link.click();
  URL.revokeObjectURL(url);
}

function importOfflineData(event) {
  const file = event.target.files[0];
  if (!file) {
    return;
  }

  const reader = new FileReader();
  reader.onload = (e) => {
    try {
      const data = JSON.parse(e.target.result);
      for (const key in data) {
        if (key.startsWith('wikicrowd-')) {
          localStorage.setItem(key, data[key]);
        }
      }
      alert('Successfully imported offline data.');
      updateOfflineStats();
    } catch (error) {
      console.error('Error importing offline data:', error);
      alert('Failed to import offline data. The file might be corrupted.');
    }
  };
  reader.readAsText(file);
}

function triggerImport() {
  importFile.value.click();
}

async function syncOfflineAnswers() {
  const offlineAnswers = JSON.parse(localStorage.getItem('wikicrowd-answers-offline') || '[]');
  if (offlineAnswers.length === 0) {
    alert('No offline answers to sync.');
    return;
  }

  const regularAnswers = offlineAnswers.filter(a => a.type === 'regular');
  const manualAnswers = offlineAnswers.filter(a => a.type === 'manual');

  let allSucceeded = true;

  if (regularAnswers.length > 0) {
    try {
      const response = await fetch('/api/answers/bulk', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${window.apiToken}`,
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({ answers: regularAnswers.map(({ type, ...rest }) => rest) }),
      });
      if (!response.ok) {
        allSucceeded = false;
        alert('Failed to sync regular answers.');
      }
    } catch (error) {
      allSucceeded = false;
      console.error('Error syncing regular answers:', error);
      alert('An error occurred while syncing regular answers.');
    }
  }

  if (manualAnswers.length > 0) {
    try {
      const response = await fetch('/api/manual-question/bulk-answer', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${window.apiToken}`,
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({ answers: manualAnswers.map(({ type, ...rest }) => rest) }),
      });
      if (!response.ok) {
        allSucceeded = false;
        alert('Failed to sync manual answers.');
      }
    } catch (error) {
      allSucceeded = false;
      console.error('Error syncing manual answers:', error);
      alert('An error occurred while syncing manual answers.');
    }
  }

  if (allSucceeded) {
    localStorage.removeItem('wikicrowd-answers-offline');
    alert('Successfully synced all offline answers.');
    updateOfflineStats();
  }
}

function clearOfflineData() {
    if (confirm('Are you sure you want to delete all offline questions and answers? This cannot be undone.')) {
        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.startsWith('wikicrowd-')) {
                keysToRemove.push(key);
            }
        }
        keysToRemove.forEach(key => localStorage.removeItem(key));
        alert('All offline data has been cleared.');
        updateOfflineStats();
    }
}

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
