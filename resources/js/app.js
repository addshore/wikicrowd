require('./bootstrap');

import { createApp } from 'vue';
import GridMode from './components/GridMode.vue';
import YesNoMaybeButtons from './components/YesNoMaybeButtons.vue';
import DepictsGroupsFromYaml from './components/DepictsGroupsFromYaml.vue';
import DepictsGroupsPage from './components/DepictsGroupsPage.vue';
import CustomDepictsGrid from './components/CustomDepictsGrid.vue';

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
  const customGridApp = createApp(CustomDepictsGrid);
  customGridApp.mount('#custom-depicts-grid-vue-root');
}
