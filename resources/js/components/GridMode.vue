<template>
  <div class="p-4 max-w-[90vw] mx-auto">
    <div class="sticky top-0 z-20 bg-white bg-opacity-95 pb-2 mb-2 shadow">
      <h2 class="text-xl font-bold mb-2">
        Which images clearly depict
        <span class="text-blue-700">"{{ images[0]?.properties?.depicts_name || '...' }}"</span>
        <span v-if="images[0]?.properties?.depicts_id"> ({{ images[0].properties.depicts_id }})</span>?
      </h2>
      <small>
        Select Yes, Skip, or No at the top. Clicking on an image will flag it for the selected answer, and save after 10 seconds. You can can click it before saving to undo the answer.
      </small>
      <div class="flex justify-center mt-2 mb-2">
        <button
          :class="['px-2 py-1 text-sm rounded-l font-bold', answerMode === 'yes' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700']"
          @click="answerMode = 'yes'"
        >YES (1)</button>
        <button
          :class="['px-2 py-1 text-sm font-bold', answerMode === 'skip' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']"
          @click="answerMode = 'skip'"
        >SKIP (e)</button>
        <button
          :class="['px-2 py-1 text-sm rounded-r font-bold', answerMode === 'no' ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700']"
          @click="answerMode = 'no'"
        >NO (2)</button>
        <button
          class="px-2 py-1 text-sm rounded-l rounded-t bg-gray-200 text-gray-700 border border-gray-300 hover:bg-gray-300 ml-2"
          @click="$emit('disable-grid')"
          style="margin-left: 12px;"
        >Single Mode</button>
        <button
          class="px-2 py-1 text-sm bg-gray-200 text-gray-700 border border-gray-300 hover:bg-gray-300 ml-2 rounded"
          @click="clearAnswered"
        >
          Clear Done
        </button>
        <label class="flex items-center ml-4 cursor-pointer select-none">
          <input type="checkbox" v-model="autoSave" class="mr-1" />
          Auto Save
        </label>
        <button
          v-if="!autoSave && pendingAnswers.length > 0"
          class="ml-2 px-3 py-1 bg-blue-600 text-white rounded font-bold border border-blue-700 hover:bg-blue-700"
          @click="saveAllPending"
        >Save Now ({{ pendingAnswers.length }})</button>
      </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
      <div v-for="image in images" :key="image.id"
        @click="!answered.has(image.id) && toggleSelect(image.id)"
        :class="[
          'relative rounded overflow-hidden transition-all',
          answered.has(image.id)
            ? (
                answeredMode[image.id] === 'no' ? 'border-4 border-red-500 cursor-default opacity-80' :
                answeredMode[image.id] === 'skip' ? 'border-4 border-blue-500 cursor-default opacity-80' :
                'border-4 border-green-500 cursor-default opacity-80'
              )
            : selected.has(image.id)
              ? (
                  selectedMode[image.id] === 'no' ? 'border-4 border-red-500 cursor-pointer' :
                  selectedMode[image.id] === 'skip' ? 'border-4 border-blue-500 cursor-pointer' :
                  'border-4 border-green-500 cursor-pointer'
                )
              : 'border-4 border-transparent cursor-pointer'
        ]"
      >
        <img
          :src="image.properties.img_url"
          :alt="`Image ${image.id}`"
          class="object-contain align-top w-full h-[22vw] min-h-[180px] max-h-[320px]"
          style="object-position:top"
        />
        <div class="image-title px-2 py-1 text-xs text-center truncate bg-white bg-opacity-80 absolute bottom-0 left-0 w-full"
          @click.stop
        >
          <a :href="'https://commons.wikimedia.org/wiki/Special:EntityData/' + image.properties?.mediainfo_id" target="_blank">{{ image.properties?.mediainfo_id || image.id }}</a>
        </div>
        <div v-if="answered.has(image.id)" class="absolute inset-0 flex items-center justify-center bg-opacity-60 pointer-events-none"
          :class="answeredMode[image.id] === 'no' ? 'bg-red-500' : answeredMode[image.id] === 'skip' ? 'bg-blue-500' : 'bg-green-500'">
          <template v-if="answeredMode[image.id] === 'no'">
            <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </template>
          <template v-else-if="answeredMode[image.id] === 'skip'">
            <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24">
              <text x="12" y="20" text-anchor="middle" font-size="18" font-family="Arial" dy="-2">?</text>
            </svg>
          </template>
          <template v-else>
            <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </template>
        </div>
        <div v-else-if="selected.has(image.id)" class="absolute inset-0 pointer-events-none"></div>
      </div>
    </div>
    <div class="flex justify-center mt-6" v-if="!allLoaded && !loading && !isFetchingMore">
      <button class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold rounded shadow" @click="fetchNextImages(10)">
        Load More
      </button>
    </div>
    <button class="mt-4 px-4 py-2 bg-gray-300 text-white rounded" @click="$emit('disable-grid')">Disable Grid Mode</button>
  </div>
</template>

<script>
import { ref, onMounted, reactive } from 'vue';

export default {
  name: 'GridMode',
  setup() {
    const images = ref([]);
    const seenIds = ref([]);
    const allLoaded = ref(false);
    const isFetchingMore = ref(false);
    const loading = ref(true);
    const selected = ref(new Set());
    const answered = ref(new Set());
    const timers = reactive(new Map());
    const answeredMode = reactive({});
    const selectedMode = reactive({});
    const groupName = document.getElementById('question-container')?.dataset.groupName;
    const apiToken = window.apiToken || null;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const answerMode = ref('yes');
    const autoSave = ref(true);
    const pendingAnswers = ref([]); // {id, mode}

    // Batch for progressive fill
    const batch = ref([]);
    const BATCH_SIZE = 100;

    // Helper to move from batch to images
    function fillImagesFromBatch(count) {
      let added = 0;
      while (batch.value.length > 0 && added < count) {
        const q = batch.value.shift();
        if (!seenIds.value.includes(q.id)) {
          images.value.push(q);
          seenIds.value.push(q.id);
          added++;
        }
      }
      return added;
    }

    // Fetch a batch and fill images
    const fetchBatchAndFill = async (count) => {
      if (allLoaded.value || isFetchingMore.value) return;
      isFetchingMore.value = true;
      let url = `/api/questions/${groupName}?count=${BATCH_SIZE}`;
      if (seenIds.value.length > 0) {
        url += `&seen_ids=${encodeURIComponent(seenIds.value.join(','))}`;
      }
      const headers = { 'Accept': 'application/json' };
      if (apiToken) headers['Authorization'] = `Bearer ${apiToken}`;
      try {
        const response = await fetch(url, { headers });
        if (!response.ok) {
          allLoaded.value = true;
          isFetchingMore.value = false;
          return;
        }
        const data = await response.json();
        if (data && Array.isArray(data.questions)) {
          batch.value.push(...data.questions);
          if (batch.value.length === 0) allLoaded.value = true;
          fillImagesFromBatch(count);
        } else {
          allLoaded.value = true;
        }
      } catch (e) {
        allLoaded.value = true;
      }
      isFetchingMore.value = false;
      loading.value = false;
    };

    // Replace fetchNextImages to use batch logic
    const fetchNextImages = async (count = 4) => {
      if (allLoaded.value) return;
      // Try to fill from batch first
      const added = fillImagesFromBatch(count);
      if (added < count) {
        await fetchBatchAndFill(count - added);
      }
    };

    const handleScroll = () => {
      if (allLoaded.value || loading.value) return;
      const scrollY = window.scrollY || window.pageYOffset;
      const visible = window.innerHeight;
      const pageHeight = document.documentElement.scrollHeight;
      if (scrollY + visible + 200 >= pageHeight) {
        fetchNextImages(4);
      }
    };

    const sendAnswer = async (image, modeOverride = null) => {
      answered.value.add(image.id);
      answeredMode[image.id] = modeOverride || selectedMode[image.id] || answerMode.value;
      selected.value.delete(image.id);
      delete selectedMode[image.id];
      try {
        await fetch('/api/answers', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(apiToken ? { 'Authorization': `Bearer ${apiToken}` } : {}),
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({
            question_id: image.id,
            answer: answeredMode[image.id]
          })
        });
      } catch (error) {
        // handle error
      }
    };

    const saveAllPending = async () => {
      if (pendingAnswers.value.length === 0) return;
      // Use bulk API
      try {
        await fetch('/api/answers/bulk', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(apiToken ? { 'Authorization': `Bearer ${apiToken}` } : {}),
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({
            answers: pendingAnswers.value.map(({id, mode}) => ({
              question_id: id,
              answer: mode
            }))
          })
        });
        // Mark as answered in UI
        for (const {id, mode} of pendingAnswers.value) {
          answered.value.add(id);
          answeredMode[id] = mode;
          selected.value.delete(id);
          delete selectedMode[id];
        }
        pendingAnswers.value = [];
      } catch (e) {
        // handle error
      }
    };

    const toggleSelect = (id) => {
      if (answered.value.has(id)) return;
      if (selected.value.has(id)) {
        selected.value.delete(id);
        if (timers.has(id)) {
          clearTimeout(timers.get(id));
          timers.delete(id);
        }
        delete selectedMode[id];
        // Remove from pending if present
        pendingAnswers.value = pendingAnswers.value.filter(a => a.id !== id);
      } else {
        selected.value.add(id);
        selectedMode[id] = answerMode.value;
        const image = images.value.find(img => img.id === id);
        if (autoSave.value) {
          const timer = setTimeout(() => {
            sendAnswer(image);
            timers.delete(id);
          }, 10000);
          timers.set(id, timer);
        } else {
          // Add to pending
          pendingAnswers.value.push({id, mode: answerMode.value});
        }
      }
    };

    function clearAnswered() {
      images.value = images.value.filter(img => !answered.value.has(img.id));
    }

    onMounted(() => {
      // Estimate how many images are needed to fill the viewport, plus 2 extra rows for preloading
      const imageHeight = 250; // px, including padding/margin
      const columns = 5; // max columns in grid
      const rows = Math.ceil(window.innerHeight / imageHeight);
      const initialCount = (rows + 2) * columns; // Preload 2 extra rows
      fetchNextImages(initialCount);
      window.addEventListener('scroll', handleScroll);
      // Keyboard shortcuts for answer mode
      window.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        if (e.key === '1') answerMode.value = 'yes';
        if (e.key === '2') answerMode.value = 'no';
        if (e.key.toLowerCase() === 'e') answerMode.value = 'skip';
      });
    });

    return {
      images,
      selected,
      answered,
      toggleSelect,
      answerMode,
      answeredMode,
      selectedMode,
      fetchNextImages,
      allLoaded,
      loading,
      isFetchingMore,
      clearAnswered,
      autoSave,
      pendingAnswers,
      saveAllPending,
    };
  },
};
</script>

<style scoped>
</style>
