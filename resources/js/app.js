require('./bootstrap');

import { createApp } from 'vue';
import GridMode from './components/GridMode.vue';
import YesNoMaybeButtons from './components/YesNoMaybeButtons.vue';
import DepictsGroupsFromYaml from './components/DepictsGroupsFromYaml.vue';
import DepictsGroupsPage from './components/DepictsGroupsPage.vue';

console.log('Vue app is starting...');

if (document.getElementById('image-focus-vue-root')) {
  const gridApp = createApp(GridMode);
  gridApp.mount('#image-focus-vue-root');
}

if (document.getElementById('yes-no-maybe-buttons')) {
  const yesNoMaybeApp = createApp({
    data() {
      return {
        questionId: window.initialQuestionData?.id || null
      }
    },
    components: { YesNoMaybeButtons },
    methods: {
      handleAnswered(ans) {
        // Optionally, reload the page or fetch the next question here
        window.location.reload();
      }
    },
    template: `<YesNoMaybeButtons v-if="questionId" :question-id="questionId" @answered="handleAnswered" />`
  });
  yesNoMaybeApp.mount('#yes-no-maybe-buttons');
}

if (document.getElementById('depicts-groups-from-yaml')) {
  const depictsGroupsFromYamlApp = createApp(DepictsGroupsFromYaml);
  depictsGroupsFromYamlApp.mount('#depicts-groups-from-yaml');
}

if (document.getElementById('depicts-groups-vue-root')) {
  const depictsGroupsPageApp = createApp(DepictsGroupsPage);
  depictsGroupsPageApp.mount('#depicts-groups-vue-root');
}

if (document.getElementById('custom-depicts-grid-vue-root')) {
  const customGridApp = createApp({
    components: { GridMode },
    data() {
      return {
        manualCategory: 'Category:Windsurfing',
        manualQid: 'Q191051',
        showGrid: false
      };
    },
    template: `
      <div class='flex flex-col items-center w-full'>
        <div class='w-full max-w-xl p-6'>
          <h1 class='text-2xl font-bold mb-4 text-center text-gray-900 dark:text-gray-100'>Custom Depicts Grid</h1>
          <form @submit.prevent="showGrid = true" class='mb-6 flex flex-col gap-4'>
            <div>
              <label class='block font-semibold mb-1 text-gray-800 dark:text-gray-200'>Commons Category</label>
              <input v-model="manualCategory" class='border rounded px-2 py-1 w-full bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700' placeholder='e.g. Paintings by Vincent van Gogh' required />
            </div>
            <div>
              <label class='block font-semibold mb-1 text-gray-800 dark:text-gray-200'>Wikidata Qid</label>
              <input v-model="manualQid" class='border rounded px-2 py-1 w-full bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700' placeholder='e.g. Q5582' required />
            </div>
            <button type='submit' class='bg-blue-600 dark:bg-blue-700 text-white px-4 py-2 rounded font-bold'>Generate Grid</button>
          </form>
        </div>
        <div class='w-full'>
          <GridMode v-if="showGrid" :manual-category="manualCategory" :manual-qid="manualQid" :manual-mode="true" />
        </div>
      </div>
    `
  });
  customGridApp.mount('#custom-depicts-grid-vue-root');
}
