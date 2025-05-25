require('./bootstrap');

import { createApp } from 'vue';
import HelloWorld from './components/HelloWorld.vue';
import GridMode from './components/GridMode.vue';
import YesNoMaybeButtons from './components/YesNoMaybeButtons.vue';
import ImageFocusOrGrid from './components/ImageFocusOrGrid.vue';
import DepictsGroupsFromYaml from './components/DepictsGroupsFromYaml.vue';
import DepictsGroupsPage from './components/DepictsGroupsPage.vue';

console.log('Vue app is starting...');

const app = createApp({});
app.component('hello-world', HelloWorld);
app.mount('#vue-hello-world');

const imageFocusApp = createApp(ImageFocusOrGrid);
imageFocusApp.mount('#image-focus-vue-root');

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

const depictsGroupsFromYamlApp = createApp(DepictsGroupsFromYaml);
depictsGroupsFromYamlApp.mount('#depicts-groups-from-yaml');

const depictsGroupsPageApp = createApp(DepictsGroupsPage);
depictsGroupsPageApp.mount('#depicts-groups-vue-root');
