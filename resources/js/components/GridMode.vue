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
    
    <!-- Loading message -->
    <div v-if="loading" class="flex justify-center items-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mr-3"></div>
      <span class="text-gray-600">Loading images...</span>
    </div>
    
    <!-- No images message -->
    <div v-else-if="!loading && images.length === 0" class="text-center py-8">
      <div class="text-gray-500 text-lg">No images available to review.</div>
    </div>
    
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
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
        <div class="relative w-full h-[22vw] min-h-[180px] max-h-[320px] bg-gray-100">
          <!-- Loading spinner -->
          <div v-if="!imageLoadingStates[image.id] || imageLoadingStates[image.id] === 'loading'" 
               class="absolute inset-0 flex items-center justify-center bg-gray-100">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          </div>
          
          <!-- Error state -->
          <div v-if="imageLoadingStates[image.id]?.state === 'error'"
               class="absolute inset-0 flex items-center justify-center bg-gray-100">
            <div class="text-center text-gray-500 px-2"> <!-- Added padding for better text display -->
              <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"> <!-- Slightly smaller icon -->
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <p class="text-xs font-semibold truncate" :title="imageLoadingStates[image.id].filename">{{ imageLoadingStates[image.id].filename }}</p>
              <p class="text-xs">{{ imageLoadingStates[image.id].reason }}</p>
            </div>
          </div>
          
          <img
            :src="image.properties.img_url"
            :alt="`Image ${image.id}`"
            draggable="false"
            class="object-contain align-top w-full h-full"
            style="object-position:top"
            @load="handleImgLoad(image)"
            @error="handleImgError(image, $event)"
            @loadstart="markImageLoading(image)"
          />
          <!-- Countdown Timer Overlay -->
          <div v-if="countdownTimers.has(image.id) && countdownTimers.get(image.id) > 0"
               class="absolute top-2 right-2 bg-black bg-opacity-75 text-white text-xs font-bold px-2 py-1 rounded z-10">
            Saving in {{ countdownTimers.get(image.id) }}s
          </div>
        </div>
        
        <!-- Magnifying glass icon -->
        <button 
          @click="(e) => openFullscreen(image, e)"
          class="absolute bottom-8 right-2 bg-black bg-opacity-60 hover:bg-opacity-80 text-white p-1.5 rounded-full transition-all z-10"
          title="View fullscreen"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
          </svg>
        </button>
        <div class="image-title px-2 py-1 text-xs text-center truncate bg-white bg-opacity-80 w-full"
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
            <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </template>
        </div>
        <div v-else-if="selected.has(image.id)" class="absolute inset-0 pointer-events-none"></div>
      </div>
    </div>
    <div class="flex justify-center mt-6" v-if="!allLoaded && !loading && !isFetchingMore && !manualMode">
      <button class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold rounded shadow" @click="fetchNextImages(10)">
        Load More
      </button>
    </div>
    
    <!-- Fullscreen Modal -->
    <div v-if="showFullscreen && fullscreenImage" 
         class="fixed inset-0 z-50 bg-black bg-opacity-90 flex items-center justify-center p-4"
         @click="closeFullscreen">
      <div class="relative max-w-full max-h-full flex flex-col">
        <!-- Close button -->
        <button 
          @click="closeFullscreen"
          class="absolute top-4 right-4 bg-black bg-opacity-60 hover:bg-opacity-80 text-white p-2 rounded-full z-10"
          title="Close fullscreen"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
        
        <!-- Main image -->
        <img 
          :src="fullscreenImageUrl" 
          :alt="`Fullscreen Image ${fullscreenImage.id}`"
          class="max-h-[80vh] max-w-full object-contain cursor-pointer"
          @click="closeFullscreen"
          draggable="false"
        />
        
        <!-- Image info -->
        <div class="mt-4 text-white text-center">
          <a 
            :href="'https://commons.wikimedia.org/wiki/Special:EntityData/' + fullscreenImage.properties?.mediainfo_id" 
            target="_blank"
            class="text-blue-300 hover:text-blue-100 underline"
          >
            {{ fullscreenImage.properties?.mediainfo_id || fullscreenImage.id }}
          </a>
        </div>
      </div>
    </div>

    <!-- Always show Loading... under the grid if loading or (manualMode and recursion ongoing) -->
    <div v-if="loading || (manualMode && !allLoaded)" class="text-center py-4">
      <span class="text-gray-600">Loading more images, please wait...</span>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted, reactive, watch } from 'vue'; // Added watch
import WikidataLabel from './WikidataLabel.vue';
import WikidataDescription from './WikidataDescription.vue';
import { fetchSubclassesAndInstances, fetchDepictsForMediaInfoIds } from './depictsUtils';
import { toastStore } from '../toastStore.js';

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
    const countdownTimers = reactive(new Map()); // Added: Stores remaining seconds for countdown
    const countdownIntervals = reactive(new Map()); // Added: Stores setInterval IDs for countdowns
    const answeredMode = reactive({});
    const selectedMode = reactive({});
    const groupName = document.getElementById('question-container')?.dataset.groupName;
    const apiToken = window.apiToken || null;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const answerMode = ref('yes');
    const autoSave = ref(true);
    const autoSaveDelay = ref(10); // seconds
    const pendingAnswers = ref([]); // {id, mode}

    // Fullscreen modal state
    const showFullscreen = ref(false);
    const fullscreenImage = ref(null);
    const fullscreenImageUrl = ref('');

    // Batch for progressive fill
    const batch = ref([]);
    const BATCH_SIZE = 100;

    // Track image load retries
    const imageRetries = reactive({});
    const MAX_IMAGE_RETRIES = 3;

    // Track image loading states and backoff
    const imageLoadingStates = reactive({});
    const fetchRetryCount = ref(0);
    const MAX_FETCH_RETRIES = 5; // Used by existing fetchWithRetry

    // Only allow certain image file extensions
    const IMAGE_FILE_EXTENSIONS = [
      'jpg', 'jpeg', 'png', 'gif', 'svg', 'tiff'
    ];

    // Exponential backoff utility
    const exponentialBackoff = (retryCount) => {
      return Math.min(1000 * Math.pow(2, retryCount), 30000); // Max 30 seconds
    };

    // New helper function for retrying answer submissions
    const fetchAnswerWithRetry = async (url, options = {}) => {
      const retryDelays = [1000, 5000, 10000]; // Defined delays in milliseconds
      // MAX_ANSWER_RETRIES is implicitly retryDelays.length

      for (let attempt = 0; attempt < retryDelays.length; attempt++) {
        try {
          const response = await fetch(url, options);
          if (response.ok) {
            return response; // Successful call
          }

          // Only retry on 5xx server errors
          if (response.status >= 500 && response.status < 600) {
            if (attempt < retryDelays.length - 1) {
              const delay = retryDelays[attempt];
              toastStore.addToast({
                message: `Server error (status ${response.status}). Retrying in ${delay / 1000}s... (Attempt ${attempt + 1}/${retryDelays.length})`,
                type: 'warning',
                duration: delay
              });
              await new Promise(resolve => setTimeout(resolve, delay));
              continue; // to the next attempt
            } else {
              // Last attempt failed with 5xx
              console.error(`API call failed with status ${response.status} after ${retryDelays.length} attempts (URL: ${url})`);
              return response; // Return the error response
            }
          } else {
            // For 4xx client errors or other non-5xx errors, don't retry, return response immediately
            return response;
          }
        } catch (error) { // Network error or fetch itself failed
          if (attempt < retryDelays.length - 1) {
            const delay = retryDelays[attempt];
            toastStore.addToast({
              message: `Network error: ${error.message}. Retrying in ${delay / 1000}s... (Attempt ${attempt + 1}/${retryDelays.length})`,
              type: 'warning',
              duration: delay
            });
            await new Promise(resolve => setTimeout(resolve, delay));
            continue; // to the next attempt
          } else {
            console.error(`API call failed with network error: ${error.message} after ${retryDelays.length} attempts (URL: ${url})`);
            // To align with how other errors are returned (as a response object),
            // we might need to construct a mock error response or re-throw and catch higher up.
            // For now, re-throwing to be caught by the calling function's try-catch.
            throw error;
          }
        }
      }
      // Fallback if loop completes without returning (should ideally not happen with the logic above)
      // This implies all retries (defined by retryDelays.length) have been exhausted.
      // The actual error response or thrown error would have been returned/thrown inside the loop.
      // To satisfy a return path, though, we might throw a generic error or return a synthetic error response.
      // However, the logic above should ensure a response or error is always returned/thrown from within the loop.
      // For safety, if somehow reached:
      throw new Error(`fetchAnswerWithRetry exhausted all attempts for ${url}`);
    };

    // Existing Fetch with retry and exponential backoff (mostly for image fetching from wikimedia)
    const fetchWithRetry = async (url, options = {}, retryCount = 0) => {
      try {
        const response = await fetch(url, options);
        
        if (response.status === 429) {
          if (retryCount < MAX_FETCH_RETRIES) {
            const delay = exponentialBackoff(retryCount);
            console.log(`Rate limited (429), retrying in ${delay}ms (attempt ${retryCount + 1})`);
            await new Promise(resolve => setTimeout(resolve, delay));
            return fetchWithRetry(url, options, retryCount + 1);
          } else {
            throw new Error('Max retries exceeded for rate limiting');
          }
        }
        
        if (!response.ok) {
          // This is the part that differs significantly from fetchAnswerWithRetry's needs
          // For general image fetching, any non-ok might be a hard stop.
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response;
      } catch (error) {
        if (retryCount < MAX_FETCH_RETRIES && error.name === 'TypeError') { // Typically network errors
          const delay = exponentialBackoff(retryCount);
          console.log(`Network error, retrying in ${delay}ms (attempt ${retryCount + 1})`);
          await new Promise(resolve => setTimeout(resolve, delay));
          return fetchWithRetry(url, options, retryCount + 1);
        }
        throw error; // Re-throw if not a TypeError or retries exhausted
      }
    };

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
      console.log(`fillImagesFromBatch: added ${added} images, ${images.value.length} total, ${batch.value.length} remaining in batch`);
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
        const response = await fetchWithRetry(url, { headers });
        const data = await response.json();
        console.log(`fetchBatchAndFill: received ${data?.questions?.length || 0} questions`);
        if (data && Array.isArray(data.questions)) {
          // Log if any questions are missing mediainfo_id
          for (const question of data.questions) {
            if (!question.properties?.mediainfo_id) {
              console.warn('Image from API is missing mediainfo_id in properties:', JSON.parse(JSON.stringify(question)));
            }
          }
          batch.value.push(...data.questions);
          // If we got no questions or fewer than expected, we've reached the end
          if (data.questions.length === 0) {
            console.log('fetchBatchAndFill: no more questions available, setting allLoaded=true');
            allLoaded.value = true;
          }
          fillImagesFromBatch(count);
        } else {
          console.log('fetchBatchAndFill: invalid response, setting allLoaded=true');
          allLoaded.value = true;
        }
      } catch (e) {
        console.error('Failed to fetch images after retries:', e);
        allLoaded.value = true;
      }
      isFetchingMore.value = false;
      loading.value = false;
    };

    // Replace fetchNextImages to use batch logic
    const fetchNextImages = async (count = 4) => {
      if (allLoaded.value) return;
      const added = fillImagesFromBatch(count);
      if (added < count && !allLoaded.value) {
        await fetchBatchAndFill(count - added);
      }
      // Use setTimeout to prevent infinite recursion
      setTimeout(() => {
        ensureViewportFilled();
      }, 100);
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
      if (allLoaded.value || loading.value || isFetchingMore.value) return;
      
      // Prevent infinite loops by checking if we have any images to show
      if (images.value.length === 0 && batch.value.length === 0) {
        loading.value = false;
        return;
      }
      
      const visible = window.innerHeight;
      const pageHeight = document.documentElement.scrollHeight;
      // If page is not scrollable, load more images
      if (pageHeight <= visible + 100) {
        fetchNextImages(10);
      }
    };

    const sendAnswer = async (image, modeOverride = null) => {
      // UI updates are now deferred until after successful API call.
      // The mode that will be used if the call is successful.
      const finalAnswerMode = modeOverride || selectedMode[image.id] || answerMode.value;

      const url = '/api/answers';
      const options = {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(apiToken ? { 'Authorization': `Bearer ${apiToken}` } : {}),
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
          question_id: image.id,
          answer: finalAnswerMode // Use the determined mode for the API call
        })
      };

      try {
        const response = await fetchAnswerWithRetry(url, options);

        if (response.ok) {
          console.log(`sendAnswer: Successfully answered ${image.id} with mode ${finalAnswerMode}`);
          // Apply UI updates now that API call was successful
          answered.value.add(image.id);
          answeredMode[image.id] = finalAnswerMode;
          selected.value.delete(image.id);
          // selectedMode[image.id] was already set by toggleSelect,
          // and should be cleared if the item is successfully answered.
          delete selectedMode[image.id];
          // No success toast for individual auto-saves to avoid noise. UI change is the feedback.
        } else {
          const mid = image.properties?.mediainfo_id;
          if (!mid) {
            console.warn('MediaInfo ID (MID) is missing from image properties when generating error toast for image:', JSON.parse(JSON.stringify(image)));
          }
          let message;
          if (mid) {
            const link = `<a href="https://commons.wikimedia.org/wiki/Special:EntityData/${mid}" target="_blank" rel="noopener noreferrer" class="text-white underline hover:opacity-80">${mid}</a>`;
            message = `Failed to save answer for image ${link}. Status: ${response.status}.`;
          } else {
            message = `Failed to save answer for image [MID not found]. Status: ${response.status}.`;
          }
          console.error(`sendAnswer: Failed to save answer for image ${mid || image.id}. Status: ${response.status}.`);
          toastStore.addToast({ message, type: 'error' });
          // console.warn underlying UI message is removed as toast is user-facing.
          if (timers.has(image.id)) { // Check if it was an auto-save timer
             console.warn(`UI: Auto-save for image ${image.id} failed. Image remains selected.`);
          }
          // Revert UI on failure
          selected.value.delete(image.id);
          delete selectedMode[image.id];
        }
      } catch (error) {
        const mid = image.properties?.mediainfo_id;
        if (!mid) {
          console.warn('MediaInfo ID (MID) is missing from image properties when generating error toast for image:', JSON.parse(JSON.stringify(image)));
        }
        let message;
        if (mid) {
          const link = `<a href="https://commons.wikimedia.org/wiki/Special:EntityData/${mid}" target="_blank" rel="noopener noreferrer" class="text-white underline hover:opacity-80">${mid}</a>`;
          message = `Failed to save answer for image ${link} due to network/critical error: ${error.message}`;
        } else {
          message = `Failed to save answer for image [MID not found] due to network/critical error: ${error.message}`;
        }
        console.error(`sendAnswer: Failed to save answer for image ${mid || image.id} due to network/critical error: ${error.message}`, error);
        toastStore.addToast({ message, type: 'error' });
        if (timers.has(image.id)) {
             console.warn(`UI: Auto-save for image ${image.id} failed due to network/critical error. Image remains selected.`);
        }
        // Revert UI on failure
        selected.value.delete(image.id);
        delete selectedMode[image.id];
      }
    };

    // If manualMode, fetch images from Commons API instead of API
    async function fetchManualImages() {
      loading.value = true;
      let error = '';
      images.value = [];
      let foundAny = false;
      try {
        const cat = props.manualCategory.trim().replace(/^Category:/, '');
        // Recursively fetch images from category and all subcategories (max depth 100), progressive display
        await fetchImagesRecursivelyAndPush(cat, new Set(), 0, 100, () => {
          if (!foundAny && images.value.length > 0) {
            loading.value = false;
            foundAny = true;
          }
        });
        // After recursion, filter out images that already depict the QID or a more specific one
        const qidSet = await fetchSubclassesAndInstances(props.manualQid);
        const mids = images.value.map(img => img.properties.mediainfo_id);
        const depictsMap = await fetchDepictsForMediaInfoIds(mids);
        console.log(`[CustomGrid] Depicts map for ${mids.length} images:`, depictsMap);
        images.value = images.value.filter(img => {
          const mids = img.properties.mediainfo_id;
          const depicted = depictsMap[mids] || [];
          console.log(`[CustomGrid] Filtering image ${img.id} (${mids}) with depicts:`, depicted);
          return !depicted.some(qid => qidSet.has(qid));
        });
        allLoaded.value = true;
      } catch (e) {
        console.error('Error fetching manual images:', e);
        error = e.message || 'Failed to load images';
        allLoaded.value = true;
      } finally {
        if (!foundAny) loading.value = false;
      }
    }

    // Recursive function to fetch images and push to UI as soon as found
    async function fetchImagesRecursivelyAndPush(categoryName, visitedCategories, depth, maxDepth, onImagePushed) {
      if (depth >= maxDepth) return;
      // Log category being iterated
      console.log('[CustomGrid] Iterating category:', categoryName);
      // Sleep 2 seconds before starting each new category
      await new Promise(resolve => setTimeout(resolve, 2000));
      const normalizedCat = categoryName.replace(/^Category:/, '');
      const fullCatName = `Category:${normalizedCat}`;
      if (visitedCategories.has(fullCatName)) return;
      visitedCategories.add(fullCatName);
      try {
        const url = `https://commons.wikimedia.org/w/api.php?action=query&generator=categorymembers&gcmtitle=Category:${encodeURIComponent(normalizedCat)}&gcmtype=file|subcat&gcmlimit=500&prop=imageinfo|pageprops&iiprop=url&iiurlwidth=300&format=json&origin=*`;
        const resp = await fetchWithRetry(url);
        const data = await resp.json();
        if (!data.query || !data.query.pages) return;
        const pages = Object.values(data.query.pages);
        const files = [];
        const subcategories = [];
        for (const page of pages) {
          if (page.ns === 6) files.push(page);
          else if (page.ns === 14) subcategories.push(page);
        }
        // --- Filtering logic: fetch depicts for all files, filter before pushing ---
        if (files.length > 0) {
          // Filter by allowed file extensions
          const filteredFiles = files.filter(p => {
            // Only keep files with valid image extensions IMAGE_FILE_EXTENSIONS
            const ext = p.title.split('.').pop().toLowerCase();
            if (!IMAGE_FILE_EXTENSIONS.includes(ext)) {
              console.log(`[CustomGrid] Skipping file ${p.pageid} (${p.title}) with unsupported extension: ${ext}`);
              return false;
            }
            return true;
          });
          const qidSet = await fetchSubclassesAndInstances(props.manualQid);
          const mids = filteredFiles.map(p => {
            let mid = p.pageprops && p.pageprops.wikibase_item ? p.pageprops.wikibase_item : null;
            if (!mid && p.title && p.title.startsWith('File:')) {
              mid = 'M' + p.pageid;
            }
            return mid;
          });
          const depictsMap = await fetchDepictsForMediaInfoIds(mids);
          for (const p of filteredFiles) {
            let mediainfo_id = p.pageprops && p.pageprops.wikibase_item ? p.pageprops.wikibase_item : null;
            if (!mediainfo_id && p.title && p.title.startsWith('File:')) {
              mediainfo_id = 'M' + p.pageid;
            }
            if (p.imageinfo?.[0]?.url) {
              const depicted = depictsMap[mediainfo_id] || [];
              // Only push if none of the depicted QIDs are in the ignore tree
              if (!depicted.some(qid => qidSet.has(qid))) {
                images.value.push({
                  id: mediainfo_id || ('M' + p.pageid),
                  properties: {
                    mediainfo_id: mediainfo_id || ('M' + p.pageid),
                    img_url: p.imageinfo[0].url,
                    depicts_id: props.manualQid,
                    manual: true,
                    category: props.manualCategory,
                    source_category: fullCatName
                  },
                  title: p.title
                });
                // Hide loading spinner as soon as any images are found
                if (loading.value && images.value.length > 0) {
                  loading.value = false;
                }
                // If too many images are loaded below the fold, pause until user scrolls
                const visible = window.innerHeight;
                const pageHeight = document.documentElement.scrollHeight;
                const scrollY = window.scrollY || window.pageYOffset;
                const atBottom = (scrollY + visible + 10) >= pageHeight;
                if (pageHeight > visible + 1200 && !atBottom) {
                  await new Promise(resolve => {
                    function onScroll() {
                      const scrollY2 = window.scrollY || window.pageYOffset;
                      const pageHeight2 = document.documentElement.scrollHeight;
                      if (scrollY2 + visible + 1000 >= pageHeight2 || (scrollY2 + visible + 10) >= pageHeight2) {
                        window.removeEventListener('scroll', onScroll);
                        resolve();
                      }
                    }
                    window.addEventListener('scroll', onScroll);
                  });
                }
                if (onImagePushed) onImagePushed();
              }
            }
          }
        }
        // Only after top-level images are pushed, recurse into subcategories
        for (let i = 0; i < subcategories.length; i++) {
          const subcat = subcategories[i];
          const subcatName = subcat.title;
          try {
            await fetchImagesRecursivelyAndPush(subcatName, visitedCategories, depth + 1, maxDepth, onImagePushed);
            if (i < subcategories.length - 1) {
              await new Promise(resolve => setTimeout(resolve, 200));
            }
          } catch (error) {
            console.error(`Error processing subcategory ${subcatName}:`, error);
          }
        }
      } catch (error) {
        console.error(`Error fetching from category ${fullCatName}:`, error);
      }
    }

    // Override sendAnswer for manual mode
    const sendAnswerManual = async (image, modeOverride = null) => {
      // UI updates are now deferred until after successful API call.
      const finalAnswerMode = modeOverride || selectedMode[image.id] || answerMode.value;

      const url = '/api/manual-question/answer';
      const options = {
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
          answer: finalAnswerMode, // Use the determined mode
          manual: true
        })
      };

      try {
        const response = await fetchAnswerWithRetry(url, options);

        if (response.ok) {
          console.log(`sendAnswerManual: Successfully answered ${image.id} with mode ${finalAnswerMode}`);
          // Apply UI updates now that API call was successful
          answered.value.add(image.id);
          answeredMode[image.id] = finalAnswerMode;
          selected.value.delete(image.id);
          delete selectedMode[image.id];
          // No success toast for individual auto-saves to avoid noise. UI change is the feedback.
        } else {
          const mid = image.properties?.mediainfo_id;
          if (!mid) {
            console.warn('MediaInfo ID (MID) is missing from image properties when generating error toast for image:', JSON.parse(JSON.stringify(image)));
          }
          let message;
          if (mid) {
            const link = `<a href="https://commons.wikimedia.org/wiki/Special:EntityData/${mid}" target="_blank" rel="noopener noreferrer" class="text-white underline hover:opacity-80">${mid}</a>`;
            message = `Failed to save manual answer for image ${link}. Status: ${response.status}.`;
          } else {
            message = `Failed to save manual answer for image [MID not found]. Status: ${response.status}.`;
          }
          console.error(`sendAnswerManual: Failed to save manual answer for image ${mid || image.id}. Status: ${response.status}.`);
          toastStore.addToast({ message, type: 'error' });
          if (timers.has(image.id)) {
             console.warn(`UI: Auto-save for image ${image.id} failed. Image remains selected.`);
          }
          // Revert UI on failure
          selected.value.delete(image.id);
          delete selectedMode[image.id];
        }
      } catch (error) {
        const mid = image.properties?.mediainfo_id;
        if (!mid) {
          console.warn('MediaInfo ID (MID) is missing from image properties when generating error toast for image:', JSON.parse(JSON.stringify(image)));
        }
        let message;
        if (mid) {
          const link = `<a href="https://commons.wikimedia.org/wiki/Special:EntityData/${mid}" target="_blank" rel="noopener noreferrer" class="text-white underline hover:opacity-80">${mid}</a>`;
          message = `Failed to save manual answer for image ${link} due to network/critical error: ${error.message}`;
        } else {
          message = `Failed to save manual answer for image [MID not found] due to network/critical error: ${error.message}`;
        }
        console.error(`sendAnswerManual: Failed to save manual answer for image ${mid || image.id} due to network/critical error: ${error.message}`, error);
        toastStore.addToast({ message, type: 'error' });
        if (timers.has(image.id)) {
             console.warn(`UI: Auto-save for image ${image.id} failed due to network/critical error. Image remains selected.`);
        }
        // Revert UI on failure
        selected.value.delete(image.id);
        delete selectedMode[image.id];
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
          const manualUrl = '/api/manual-question/bulk-answer';
          const manualOptions = {
            method: 'POST',
            headers,
            body: JSON.stringify({ answers }),
          };
          const responseManual = await fetchAnswerWithRetry(manualUrl, manualOptions);

          console.log('[GridMode] Manual bulk answer response:', responseManual.status, responseManual.statusText);
          if (responseManual.ok) {
            console.log('saveAllPending: Manual bulk answers successfully sent.');
            // Apply UI updates for each successfully saved item
            for (const {id, mode} of pendingAnswers.value) {
              answered.value.add(id);
              answeredMode[id] = mode;
              selected.value.delete(id);
              delete selectedMode[id];
            }
            pendingAnswers.value = []; // Clear pending answers only on full success
            console.log('[GridMode] saveAllPending complete for manual mode, UI updated.');
            toastStore.addToast({ message: `${answers.length} manual answers saved successfully!`, type: 'success' });
          } else {
            const message = `Manual bulk save failed. Status: ${responseManual.status}.`;
            console.error(`saveAllPending: ${message}`);
            toastStore.addToast({ message, type: 'error' });
            // console.warn underlying UI message is removed.
          }
        } else {
          const answersToSubmit = pendingAnswers.value.map(({ id, mode }) => ({
            question_id: id,
            answer: mode,
          }));
          console.log('[GridMode] Sending regular bulk answers:', answersToSubmit);
          const regularUrl = '/api/answers/bulk';
          const regularOptions = {
            method: 'POST',
            headers,
            body: JSON.stringify({ answers: answersToSubmit }), // Ensure payload matches API expectation if it's { "answers": [...] }
          };
          const responseRegular = await fetchAnswerWithRetry(regularUrl, regularOptions);

          console.log('[GridMode] Regular bulk answer response:', responseRegular.status, responseRegular.statusText);
          if (responseRegular.ok) {
            console.log('saveAllPending: Regular bulk answers successfully sent.');
            // Apply UI updates for each successfully saved item
            for (const {id, mode} of pendingAnswers.value) {
              answered.value.add(id);
              answeredMode[id] = mode;
              selected.value.delete(id);
              delete selectedMode[id];
            }
            pendingAnswers.value = []; // Clear pending answers only on full success
            console.log('[GridMode] saveAllPending complete for regular mode, UI updated.');
            toastStore.addToast({ message: `${answersToSubmit.length} answers saved successfully!`, type: 'success' });
          } else {
            const message = `Regular bulk save failed. Status: ${responseRegular.status}.`;
            console.error(`saveAllPending: ${message}`);
            toastStore.addToast({ message, type: 'error' });
          }
        }
        // Mark as answered in UI and clear timers/intervals for all processed IDs
        const answersToProcess = [...pendingAnswers.value]; // Iterate over a copy
        for (const {id, mode} of answersToProcess) {
          answered.value.add(id);
          answeredMode[id] = mode;
          selected.value.delete(id); // Ensure it's removed from selection
          delete selectedMode[id];   // And from selection mode mapping

          // Clear any auto-save timer that might still exist for this ID
          if (timers.has(id)) {
            clearTimeout(timers.get(id));
            timers.delete(id);
          }
          // Clear any countdown display and interval for this ID
          if (countdownIntervals.has(id)) {
            clearInterval(countdownIntervals.get(id));
            countdownIntervals.delete(id);
          }
          countdownTimers.delete(id);
        }
        pendingAnswers.value = []; // Clear the list after processing
        console.log('[GridMode] saveAllPending complete, UI updated.');
      } catch (e) {
        const message = `Bulk save failed due to network/critical error: ${e.message}`;
        console.error('[GridMode] Error in saveAllPending:', e);
        toastStore.addToast({ message, type: 'error' });
      }
    };

    const toggleSelect = (id) => {
      if (answered.value.has(id)) return;
      const image = images.value.find(img => img.id === id); // Get image ref once

      if (selected.value.has(id)) {
        selected.value.delete(id);
        delete selectedMode[id];

        if (timers.has(id)) {
          clearTimeout(timers.get(id));
          timers.delete(id);
        }
        // Clear countdown interval and timer display on unselect
        if (countdownIntervals.has(id)) {
          clearInterval(countdownIntervals.get(id));
          countdownIntervals.delete(id);
        }
        countdownTimers.delete(id);

        // Remove from pending if present
        pendingAnswers.value = pendingAnswers.value.filter(a => a.id !== id);
      } else {
        selected.value.add(id);
        selectedMode[id] = answerMode.value;

        if (autoSave.value && image) {
          // Set main auto-save timer
          const timer = setTimeout(() => {
            (props.manualMode ? sendAnswerManual : sendAnswer)(image); // Pass the image object
            timers.delete(image.id); // Use image.id
            // Ensure countdown is cleared when auto-save fires
            if (countdownIntervals.has(image.id)) {
                clearInterval(countdownIntervals.get(image.id));
                countdownIntervals.delete(image.id);
            }
            countdownTimers.delete(image.id);
          }, autoSaveDelay.value * 1000);
          timers.set(id, timer);

          // Start visual countdown
          countdownTimers.set(id, autoSaveDelay.value);
          // Clear any existing interval for this image before starting a new one
          if (countdownIntervals.has(id)) {
            clearInterval(countdownIntervals.get(id));
          }
          const intervalId = setInterval(() => {
            if (countdownTimers.has(id)) {
              const currentTime = countdownTimers.get(id);
              if (currentTime > 1) {
                countdownTimers.set(id, currentTime - 1);
              } else {
                // Timer reaches 0 (or 1 and will then hit 0 via normal save)
                clearInterval(countdownIntervals.get(id));
                countdownIntervals.delete(id);
                countdownTimers.delete(id); // Remove when it effectively hits zero.
              }
            } else {
              // Safeguard: if somehow countdownTimers doesn't have id, or id is no longer in countdownIntervals, clear the specific interval.
              clearInterval(intervalId); // Corrected: use intervalId from closure
              if(countdownIntervals.has(id)) { // defensive deletion
                countdownIntervals.delete(id);
              }
            }
          }, 1000);
          countdownIntervals.set(id, intervalId);

        } else {
          // Not auto-saving, add to pending answers
          // Ensure no duplicates if user clicks multiple times without autoSave
          if (!pendingAnswers.value.some(a => a.id === id)) {
            pendingAnswers.value.push({id, mode: answerMode.value});
          }
        }
      }
    };

    const clearAnswered = () => {
      images.value = images.value.filter(img => !answered.value.has(img.id));
    }

    // Get full-size image URL from Commons
    const getFullSizeImageUrl = async (image) => {
      try {
        // If this is a manual mode image, we need to get the full size URL
        if (props.manualMode && image.title) {
          const filename = image.title.replace('File:', '');
          const url = `https://commons.wikimedia.org/w/api.php?action=query&titles=File:${encodeURIComponent(filename)}&prop=imageinfo&iiprop=url&format=json&origin=*`;
          const response = await fetch(url);
          const data = await response.json();
          const pages = data.query?.pages;
          if (pages) {
            const page = Object.values(pages)[0];
            return page.imageinfo?.[0]?.url || image.properties.img_url;
          }
        }
        
        // For regular images, try to get full size by removing width parameter
        const currentUrl = image.properties.img_url;
        if (currentUrl.includes('/thumb/') && currentUrl.includes('px-')) {
          // Remove the thumbnail part to get original
          return currentUrl.replace(/\/thumb\/(.+?)\/\d+px-.+$/, '/$1');
        }
        
        return currentUrl;
      } catch (error) {
        console.error('Error getting full size image:', error);
        return image.properties.img_url;
      }
    };

    // Open fullscreen modal
    const openFullscreen = async (image, event) => {
      event.stopPropagation(); // Prevent triggering the image selection
      fullscreenImage.value = image;
      fullscreenImageUrl.value = await getFullSizeImageUrl(image);
      showFullscreen.value = true;
      
      // Disable scrolling on body
      document.body.style.overflow = 'hidden';
    };

    // Close fullscreen modal
    const closeFullscreen = () => {
      showFullscreen.value = false;
      fullscreenImage.value = null;
      fullscreenImageUrl.value = '';
      
      // Re-enable scrolling on body
      document.body.style.overflow = '';
    };

    // Handle image load errors with retry logic
    const handleImgError = (image, event) => {
      const retryCount = imageRetries[image.id] || 0;
      if (retryCount < MAX_IMAGE_RETRIES) {
        imageRetries[image.id] = retryCount + 1;
        // Add a small delay before retry
        setTimeout(() => {
          const imgElement = document.querySelector(`img[alt="Image ${image.id}"]`);
          if (imgElement) {
            // Force reload by resetting src to itself (browser will retry)
            imgElement.src = '';
            setTimeout(() => {
              imgElement.src = image.properties.img_url;
            }, 50);
          }
        }, 1000 * (retryCount*2));
      } else {
        // Mark as failed to load initially
        const filename = image.properties.img_url.substring(image.properties.img_url.lastIndexOf('/') + 1);
        imageLoadingStates[image.id] = { state: 'error', filename: filename, reason: 'Loading error details...' };

        // Asynchronously fetch more details about the error
        (async () => {
          let determinedReason = 'Failed to load';
          try {
            const response = await fetch(image.properties.img_url, { method: 'HEAD' });
            // Even if response.ok is false, we got a response from the server
            determinedReason = `HTTP ${response.status}: ${response.statusText}`;
          } catch (e) {
            // Network error or other fetch-related issue
            if (e instanceof TypeError) { // TypeError is often a network error (e.g. CORS, DNS)
                determinedReason = 'Network error';
            } else {
                determinedReason = 'Failed to fetch details'; // Or a more generic error
            }
            console.error(`HEAD request failed for ${image.properties.img_url}:`, e);
          }
          // Update with the more specific reason
          imageLoadingStates[image.id] = { state: 'error', filename: filename, reason: determinedReason };
        })();
      }
    };

    // Handle successful image load
    const handleImgLoad = (image) => {
      imageLoadingStates[image.id] = 'loaded';
    };

    // Mark image as loading when it starts
    const markImageLoading = (image) => {
      imageLoadingStates[image.id] = 'loading';
    };

    // On mount, always add keyboard shortcuts
    let keydownHandler;
    onMounted(() => {
      if (props.manualMode) {
        fetchManualImages().then(() => {
          setTimeout(() => {
            ensureViewportFilled();
          }, 100);
        });
      } else {
        // Estimate how many images are needed to fill the viewport, plus 2 extra rows for preloading
        const imageHeight = 250; // px, including padding/margin
        const columns = 5; // max columns in grid
        const rows = Math.ceil(window.innerHeight / imageHeight);
        const initialCount = Math.max(1, (rows + 2) * columns); // Ensure at least 1 image
        fetchNextImages(initialCount).then(() => {
          setTimeout(() => {
            ensureViewportFilled();
          }, 100);
        });
        window.addEventListener('scroll', handleScroll);
      }
      // Keyboard shortcuts for answer mode (always add)
      keydownHandler = (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        // Close fullscreen on Escape
        if (e.key === 'Escape' && showFullscreen.value) {
          closeFullscreen();
          return;
        }
        // Answer mode shortcuts (only when not in fullscreen)
        if (!showFullscreen.value) {
          if (e.key === '1') answerMode.value = 'yes';
          if (e.key === '2') answerMode.value = 'no';
          if (e.key.toLowerCase() === 'e') answerMode.value = 'skip';
        }
      };
      window.addEventListener('keydown', keydownHandler);
    });
    onUnmounted(() => {
      if (keydownHandler) window.removeEventListener('keydown', keydownHandler);
      window.removeEventListener('scroll', handleScroll);
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
          // Clear countdown for items being manually saved via "Save" button
          if (countdownIntervals.has(id)) {
            clearInterval(countdownIntervals.get(id));
            countdownIntervals.delete(id);
          }
          countdownTimers.delete(id);
        }
      });
      saveAllPending(); // saveAllPending will handle its own items if any were missed.
    };
    // Use a wrapper to ensure Vue always updates the handler
    const onSaveClickHandler = (...args) => {
      console.log('[GridMode] onSaveClickHandler called', args);
      onSaveClick();
    };

    // Watch for changes in autoSave to clear timers if disabled
    watch(autoSave, (newValue, oldValue) => {
      if (newValue === false) {
        console.log('[GridMode] autoSave turned off. Clearing all active auto-save timers and countdowns.');
        // Clear all countdown intervals
        for (const intervalId of countdownIntervals.values()) {
          clearInterval(intervalId);
        }
        countdownIntervals.clear();

        // Clear all main auto-save timers
        for (const timerId of timers.values()) {
          clearTimeout(timerId);
        }
        timers.clear();

        // Clear all visual countdown displays
        countdownTimers.clear();

        // Optional: move currently selected items to pendingAnswers, or clear selection.
        // For now, just clearing timers. Users will need to re-engage if they want to save.
        // selected.value.forEach(id => {
        //   if (!pendingAnswers.value.some(a => a.id === id)) {
        //     pendingAnswers.value.push({ id, mode: selectedMode[id] || answerMode.value });
        //   }
        // });
        // selected.value.clear(); // Or just clear selection
      }
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
      autoSaveDelay,
      pendingAnswers,
      saveAllPending,
      sendAnswer: sendAnswerToUse,
      onSaveClickHandler,
      handleImgError,
      handleImgLoad,
      markImageLoading,
      imageLoadingStates,
      showFullscreen,
      fullscreenImage,
      fullscreenImageUrl,
      openFullscreen,
      closeFullscreen,
      countdownTimers, // Added for template access
    };
  },
};
</script>

<style scoped>
</style>
