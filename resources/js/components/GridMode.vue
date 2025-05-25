<template>
  <div class="p-4 w-full max-w-none mx-auto">
    <div class="sticky top-0 z-20 bg-white bg-opacity-95 pb-2 mb-2 shadow">
      <h2 class="text-xl font-bold mb-2 flex flex-col items-center">
        <p class="text-lg leading-7 text-gray-500 mb-1">
          Does this image clearly depict
        </p>
        <div v-if="images[0]?.properties?.depicts_id" class="text-lg font-semibold flex items-center mb-1">
          <a
            :href="'https://www.wikidata.org/wiki/' + images[0].properties.depicts_id"
            target="_blank"
            class="mr-2 text-blue-600 hover:underline"
          >
            {{ images[0].properties.depicts_id }}
          </a>
          <span class="ml-1">(<WikidataLabel :qid="images[0].properties.depicts_id" :fallback="images[0].properties.depicts_name" />)</span>
        </div>
        <div v-if="images[0]?.properties?.depicts_id" class="text-gray-600 text-sm mt-1">
          <WikidataDescription :qid="images[0].properties.depicts_id" />
        </div>
      </h2>
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
          class="ml-2 px-3 py-1 bg-blue-600 text-white rounded font-bold border border-blue-700 hover:bg-blue-700"
          :disabled="pendingAnswers.length === 0 && Array.from(selected).filter(id => !answered.has(id)).length === 0"
          @click="onSaveClickHandler"
        >Save</button>
        <button
          class="ml-2 px-2 py-1 text-sm bg-gray-200 text-gray-700 border border-gray-300 hover:bg-gray-300 rounded"
          @click="clearAnswered"
        >
          Clear Done
        </button>
        <label class="flex items-center ml-4 cursor-pointer select-none">
          <input type="checkbox" v-model="autoSave" class="mr-1" />
          Auto Save
          <span class="ml-2 flex items-center">
            after
            <input
              type="number"
              min="1"
              v-model.number="autoSaveDelay"
              class="mx-1 w-12 px-1 py-0.5 border border-gray-300 rounded text-center text-sm"
              style="width:3.5em;"
            />
            seconds
          </span>
        </label>
      </div>

      <small>
        Select Yes, Skip, or No at the top. Clicking on an image will flag it for the selected answer, and save after 10 seconds. You can can click it before saving to undo the answer.
      </small>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
      <div v-for="image in images" :key="image.id"
        @click="!answered.has(image.id) && toggleSelect(image.id)"
        :class="[
          'relative flex flex-col rounded overflow-hidden transition-all',
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
          draggable="false"
          class="object-contain align-top w-full h-[22vw] min-h-[180px] max-h-[320px]"
          style="object-position:top"
        />
        <div class="image-title px-2 py-1 text-xs text-center truncate bg-white bg-opacity-80 w-full"
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
  </div>
</template>

<script>
import { ref, onMounted, reactive } from 'vue';
import WikidataLabel from './WikidataLabel.vue';
import WikidataDescription from './WikidataDescription.vue';
import { fetchSubclassesAndInstances, fetchDepictsForMediaInfoIds } from './depictsUtils';

export default {
  name: 'GridMode',
  components: { WikidataLabel, WikidataDescription },
  props: {
    manualCategory: { type: String, default: '' },
    manualQid: { type: String, default: '' },
    manualMode: { type: Boolean, default: false }
  },
  setup(props, { emit }) {
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
    const autoSaveDelay = ref(10); // seconds
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
      const added = fillImagesFromBatch(count);
      if (added < count) {
        await fetchBatchAndFill(count - added);
      }
      ensureViewportFilled();
    };

    const PRELOAD_SCROLL_THRESHOLD = 800; // px from bottom to start preloading next images

    const handleScroll = () => {
      if (allLoaded.value || loading.value) return;
      const scrollY = window.scrollY || window.pageYOffset;
      const visible = window.innerHeight;
      const pageHeight = document.documentElement.scrollHeight;
      // Preload earlier: start loading when user is PRELOAD_SCROLL_THRESHOLD px from bottom
      if (scrollY + visible + PRELOAD_SCROLL_THRESHOLD >= pageHeight) {
        fetchNextImages(10);
      }
    };

    // Helper: after images load, if viewport is not filled, load more
    const ensureViewportFilled = () => {
      if (allLoaded.value || loading.value) return;
      const visible = window.innerHeight;
      const pageHeight = document.documentElement.scrollHeight;
      // If page is not scrollable, load more images
      if (pageHeight <= visible + 100) {
        fetchNextImages(10);
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

    // If manualMode, fetch images from Commons API instead of API
    async function fetchManualImages() {
      loading.value = true;
      let error = '';
      images.value = [];
      try {
        const cat = props.manualCategory.trim().replace(/^Category:/, '');
        const url = `https://commons.wikimedia.org/w/api.php?action=query&generator=categorymembers&gcmtitle=Category:${encodeURIComponent(cat)}&gcmtype=file&gcmlimit=30&prop=imageinfo|pageprops&iiprop=url&iiurlwidth=300&format=json&origin=*`;
        const resp = await fetch(url);
        const data = await resp.json();
        if (!data.query || !data.query.pages) throw new Error('No images found in category');
        let rawImages = Object.values(data.query.pages).map(p => {
          let mediainfo_id = p.pageprops && p.pageprops.wikibase_item ? p.pageprops.wikibase_item : null;
          if (!mediainfo_id && p.title && p.title.startsWith('File:')) {
            mediainfo_id = 'M' + p.pageid;
          }
          return {
            id: mediainfo_id || ('M' + p.pageid),
            properties: {
              mediainfo_id: mediainfo_id || ('M' + p.pageid),
              img_url: p.imageinfo?.[0]?.url,
              depicts_id: props.manualQid,
              manual: true,
              category: props.manualCategory
            },
            title: p.title
          };
        }).filter(img => img.properties.img_url);

        // --- Filter out images that already depict the QID or a more specific one ---
        // 1. Get all relevant QIDs (target + subclasses/instances)
        const qidSet = await fetchSubclassesAndInstances(props.manualQid);
        // 2. Get all mediainfo IDs
        const mids = rawImages.map(img => img.properties.mediainfo_id);
        // 3. Fetch depicts for all images
        const depictsMap = await fetchDepictsForMediaInfoIds(mids);
        // 4. Filter
        images.value = rawImages.filter(img => {
          const mids = img.properties.mediainfo_id;
          const depicted = depictsMap[mids] || [];
          // If any depicted QID is in the set, filter out
          return !depicted.some(qid => qidSet.has(qid));
        });
        allLoaded.value = true;
      } catch (e) {
        error = e.message || 'Failed to load images';
      } finally {
        loading.value = false;
      }
    }

    // Override sendAnswer for manual mode
    const sendAnswerManual = async (image, modeOverride = null) => {
      answered.value.add(image.id);
      answeredMode[image.id] = modeOverride || selectedMode[image.id] || answerMode.value;
      selected.value.delete(image.id);
      delete selectedMode[image.id];
      try {
        await fetch('/api/manual-question/answer', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(apiToken ? { 'Authorization': `Bearer ${apiToken}` } : {}),
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({
            category: props.manualCategory,
            qid: props.manualQid,
            mediainfo_id: image.properties.mediainfo_id, // always Mxxx
            img_url: image.properties.img_url,
            answer: answeredMode[image.id],
            manual: true
          })
        });
      } catch (error) {
        // handle error
      }
    };

    const saveAllPending = async () => {
      console.log('[GridMode] saveAllPending called, pendingAnswers:', JSON.parse(JSON.stringify(pendingAnswers.value)));
      if (pendingAnswers.value.length === 0) {
        console.log('[GridMode] No pending answers to save.');
        return;
      }
      try {
        const headers = {
          'Content-Type': 'application/json',
        };
        if (window.apiToken) {
          headers['Authorization'] = `Bearer ${window.apiToken}`;
        }
        if (!window.apiToken && csrfToken) {
          headers['X-CSRF-TOKEN'] = csrfToken;
        }
        if (props.manualMode) {
          // Bulk save for manual/custom questions
          const answers = pendingAnswers.value.map(({ id, mode }) => {
            const img = images.value.find(img => img.id === id);
            return {
              category: props.manualCategory,
              qid: props.manualQid,
              mediainfo_id: img.properties.mediainfo_id,
              img_url: img.properties.img_url,
              answer: mode,
            };
          });
          console.log('[GridMode] Sending manual bulk answers:', answers);
          const resp = await fetch('/api/manual-question/bulk-answer', {
            method: 'POST',
            headers,
            body: JSON.stringify({ answers }),
          });
          console.log('[GridMode] Manual bulk answer response:', resp.status, resp.statusText);
        } else {
          const answers = pendingAnswers.value.map(({ id, mode }) => ({
            question_id: id,
            answer: mode,
          }));
          console.log('[GridMode] Sending bulk answers:', answers);
          const resp = await fetch('/api/answers/bulk', {
            method: 'POST',
            headers,
            body: JSON.stringify({ answers }),
          });
          console.log('[GridMode] Bulk answer response:', resp.status, resp.statusText);
        }
        // Mark as answered in UI
        for (const {id, mode} of pendingAnswers.value) {
          answered.value.add(id);
          answeredMode[id] = mode;
          selected.value.delete(id);
          delete selectedMode[id];
        }
        pendingAnswers.value = [];
        console.log('[GridMode] saveAllPending complete, UI updated.');
      } catch (e) {
        console.error('[GridMode] Error in saveAllPending:', e);
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
            // Always use the correct sendAnswer function for the current mode
            (props.manualMode ? sendAnswerManual : sendAnswer)(image);
            timers.delete(id);
          }, autoSaveDelay.value * 1000);
          timers.set(id, timer);
        } else {
          // Add to pending
          pendingAnswers.value.push({id, mode: answerMode.value});
        }
      }
    };

    const clearAnswered = () => {
      images.value = images.value.filter(img => !answered.value.has(img.id));
    }

    // On mount, if manualMode, fetch manual images
    onMounted(() => {
      if (props.manualMode) {
        fetchManualImages().then(ensureViewportFilled);
      } else {
        // Estimate how many images are needed to fill the viewport, plus 2 extra rows for preloading
        const imageHeight = 250; // px, including padding/margin
        const columns = 5; // max columns in grid
        const rows = Math.ceil(window.innerHeight / imageHeight);
        const initialCount = (rows + 2) * columns; // Preload 2 extra rows
        fetchNextImages(initialCount).then(ensureViewportFilled);
        window.addEventListener('scroll', handleScroll);
        // Keyboard shortcuts for answer mode
        window.addEventListener('keydown', (e) => {
          if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
          if (e.key === '1') answerMode.value = 'yes';
          if (e.key === '2') answerMode.value = 'no';
          if (e.key.toLowerCase() === 'e') answerMode.value = 'skip';
        });
      }
    });
    // Also call ensureViewportFilled after each fetch
    const sendAnswerToUse = props.manualMode ? sendAnswerManual : sendAnswer;
    console.log('[GridMode] manualMode:', props.manualMode, 'Using', props.manualMode ? '/api/manual-question/answer' : '/api/answers');
    // Add a wrapper to ensure timers are cleared and answers are saved immediately
    const onSaveClick = () => {
      console.log('[GridMode] onSaveClick called');
      // For all selected images that are not answered, ensure they're in pendingAnswers
      selected.value.forEach(id => {
        if (!answered.value.has(id)) {
          // If not already in pendingAnswers, add it
          if (!pendingAnswers.value.some(a => a.id === id)) {
            pendingAnswers.value.push({ id, mode: selectedMode[id] || answerMode.value });
          }
          // Clear any running timer for this id
          if (timers.has(id)) {
            clearTimeout(timers.get(id));
            timers.delete(id);
          }
        }
      });
      saveAllPending();
    };
    // Use a wrapper to ensure Vue always updates the handler
    const onSaveClickHandler = (...args) => {
      console.log('[GridMode] onSaveClickHandler called', args);
      onSaveClick();
    };
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
      autoSaveDelay,
      pendingAnswers,
      saveAllPending,
      sendAnswer: sendAnswerToUse,
      onSaveClickHandler,
    };
  },
};
</script>

<style scoped>
</style>
