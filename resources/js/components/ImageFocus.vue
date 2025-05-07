<template>
  <div v-if="question" class="flex flex-col items-center">
    <div class="flex justify-center pt-8">
      <a :id="'current-image-commons-link'" :href="commonsLink" target="_blank">
        <img :id="'current-image'" :src="question.properties.img_url" alt="Current Question Image" class="max-w-full md:max-w-lg lg:max-w-xl xl:max-w-2xl max-h-[60vh] rounded-lg shadow-lg" />
      </a>
    </div>
    <div v-if="question.properties.old_depicts_id" class="flex justify-center pt-8">
      <p class="text-lg leading-7 text-gray-500">
        This image was previously said to depict
        <span id="current-old-depicts-name">{{ question.properties.old_depicts_name }}</span>
        (<a id="current-old-depicts-link" :href="oldDepictsLink" target="_blank"><span id="current-old-depicts-id">{{ question.properties.old_depicts_id }}</span></a>).
      </p>
    </div>
    <div class="flex justify-center pt-8">
      <p class="text-lg leading-7 text-gray-500">
        <span v-if="question.properties.old_depicts_id">
          Does this image actually clearly depict
        </span>
        <span v-else>
          Does this image clearly depict
        </span>
        <span id="current-depicts-name">"{{ question.properties.depicts_name }}"</span>
        (<a id="current-depicts-link" :href="depictsLink" target="_blank"><span id="current-depicts-id">{{ question.properties.depicts_id }}</span></a>)?
      </p>
    </div>
    <YesNoMaybeButtons :question-id="question.id" @answered="onAnswered" />
    <button
      class="ml-2 px-4 py-2 bg-gray-200 text-gray-700 rounded border border-gray-300 hover:bg-gray-300"
      @click="$emit('open-grid')"
      style="margin-top: 2px;"
    >
      Try Grid Mode
    </button>
  </div>
  <div v-else class="text-center p-8">
    <h2 class="text-xl font-semibold text-gray-700">No more questions available in this group right now.</h2>
    <p class="text-gray-500">Please check back later or try a different group.</p>
  </div>
</template>

<script>
import YesNoMaybeButtons from './YesNoMaybeButtons.vue';

export default {
  name: 'ImageFocus',
  components: { YesNoMaybeButtons },
  data() {
    return {
      question: null,
      groupName: document.getElementById('question-container')?.dataset.groupName || null,
      loading: false
    };
  },
  mounted() {
    this.fetchNextQuestion();
  },
  computed: {
    commonsLink() {
      return this.question?.properties?.mediainfo_id
        ? `https://commons.wikimedia.org/wiki/Special:EntityData/${this.question.properties.mediainfo_id}`
        : '#';
    },
    oldDepictsLink() {
      return this.question?.properties?.old_depicts_id
        ? `https://www.wikidata.org/wiki/${this.question.properties.old_depicts_id}`
        : '#';
    },
    depictsLink() {
      return this.question?.properties?.depicts_id
        ? `https://www.wikidata.org/wiki/${this.question.properties.depicts_id}`
        : '#';
    }
  },
  methods: {
    async fetchNextQuestion() {
      this.loading = true;
      let url = `/api/questions/${this.groupName}`;
      if (this.question) {
        url += `?seen_ids=${this.question.id}`;
      }
      const headers = { 'Accept': 'application/json' };
      if (window.apiToken) headers['Authorization'] = `Bearer ${window.apiToken}`;
      try {
        const response = await fetch(url, { headers });
        if (!response.ok) {
          this.question = null;
          return;
        }
        const data = await response.json();
        this.question = data.question || null;
      } catch (e) {
        this.question = null;
      }
      this.loading = false;
    },
    onAnswered() {
      this.fetchNextQuestion();
    }
  }
};
</script>
