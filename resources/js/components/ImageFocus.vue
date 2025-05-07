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
    <YesNoMaybeButtons :key="question.id" :question-id="question.id" @answered="onAnswered" />
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
      questionQueue: [], // queue of questions
      groupName: document.getElementById('question-container')?.dataset.groupName || null,
      loading: false,
      prefetchedImageUrls: new Set(), // track prefetched images
      knownQuestionIds: new Set(), // track known questions
    };
  },
  mounted() {
    this.prefetchQuestions(10).then(() => {
      if (!this.question && this.questionQueue.length > 0) {
        this.showNextQuestion();
      }
    });
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
    async prefetchQuestions(n) {
      // Only fetch if we need more
      if (this.loading || (this.questionQueue.length >= n)) return;
      this.loading = true;
      let seenIds = Array.from(this.knownQuestionIds);
      if (this.question) seenIds.unshift(this.question.id);
      let url = `/api/questions/${this.groupName}`;
      if (seenIds.length) {
        url += `?seen_ids=${seenIds.join(',')}`;
      }
      const headers = { 'Accept': 'application/json' };
      if (window.apiToken) headers['Authorization'] = `Bearer ${window.apiToken}`;
      try {
        const response = await fetch(url + `?count=${n - this.questionQueue.length}` , { headers });
        if (response.ok) {
          const data = await response.json();
          let newQuestions = [];
          if (data.questions && Array.isArray(data.questions)) {
            newQuestions = data.questions.filter(q => !this.knownQuestionIds.has(q.id));
          } else if (data.question && !this.knownQuestionIds.has(data.question.id)) {
            newQuestions = [data.question];
          }
          newQuestions.forEach(q => this.knownQuestionIds.add(q.id));
          this.questionQueue.push(...newQuestions);
          this.prefetchImages();
          // If no question is currently shown, show the first one
          if (!this.question && this.questionQueue.length > 0) {
            this.showNextQuestion();
          }
        }
      } catch (e) {}
      this.loading = false;
    },
    prefetchImages() {
      // Prefetch next 3 images, but only if not already prefetched
      for (let i = 0; i < 3 && i < this.questionQueue.length; i++) {
        const imgUrl = this.questionQueue[i].properties.img_url;
        if (imgUrl && !this.prefetchedImageUrls.has(imgUrl)) {
          const img = new window.Image();
          img.src = imgUrl;
          this.prefetchedImageUrls.add(imgUrl);
        }
      }
    },
    showNextQuestion() {
      if (this.questionQueue.length > 0) {
        this.question = this.questionQueue.shift();
        this.prefetchQuestions(10);
        this.prefetchImages();
      } else {
        this.question = null;
        this.prefetchQuestions(10);
      }
    },
    onAnswered(ans) {
      this.showNextQuestion();
    }
  }
};
</script>
