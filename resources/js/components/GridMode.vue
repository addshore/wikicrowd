<template>
  <div class="p-4 w-full max-w-none mx-auto">
    <div class="sticky top-0 z-20 bg-white bg-opacity-95 pb-2 mb-2 shadow">
      <h2 class="text-xl font-bold mb-2 flex flex-col items-center">
        <p class="text-lg leading-7 text-gray-500 mb-1">
          Does this image clearly <a :href="depictsLinkHref" target="_blank" class="text-blue-600 hover:underline">depict</a>
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
          <span class="ml-2 text-sm">
            <a
              :href="depictsUpQueryUrl"
              target="_blank"
              class="text-blue-600 hover:underline"
            >(up)</a>
            <a
              :href="`https://query.wikidata.org/embed.html#SELECT%20%3Fitem%20%3FitemLabel%0AWHERE%0A%7B%0A%20%20%3Fitem%20wdt%3AP31%2Fwdt%3AP279*|wdt%3AP279%2Fwdt%3AP279*%20wd%3A${images[0].properties.depicts_id}.%0A%20%20SERVICE%20wikibase%3Alabel%20%7B%20bd%3AserviceParam%20wikibase%3Alanguage%20%22%5BAUTO_LANGUAGE%5D%2Cmul%2Cen%22.%20%7D%0A%7D`"
              target="_blank"
              class="ml-1 text-blue-600 hover:underline"
            >(down)</a>
          </span>
        </div>
        <div v-if="images[0]?.properties?.depicts_id" class="text-gray-600 text-sm mt-1">
          <WikidataDescription :qid="images[0].properties.depicts_id" />
        </div>
      </h2>
      <div class="flex justify-center mt-2 mb-2">
        <button
          :class="['px-2 py-1 text-sm rounded-l font-bold', answerMode === 'yes-preferred' ? 'bg-green-700 text-white' : 'bg-gray-200 text-gray-700']"
          @click="answerMode = 'yes-preferred'"
        >Prominent (Q)</button>
        <button
          :class="['px-2 py-1 text-sm font-bold', answerMode === 'yes' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700']"
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

      <div class="flex justify-center w-full">
        <small class="text-center">Select Yes, Skip, or No at the top. Clicking on an image will flag it for the selected answer, and save after 10 seconds. You can can click it before saving to undo the answer.</small>
      </div>
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
    
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2" ref="imageGridContainer">
      <div v-for="image in images" :key="image.id"
        :data-image-id="image.id"
        @click="handleClick(image.id, $event)"
        @mousedown.prevent="handleImageMouseDown(image, $event)"
        @touchstart.prevent="handleTouchStart(image, $event)"
        :class="[
          'relative flex flex-col rounded overflow-hidden transition-all',
          answered.has(image.id)
            ? (
                answeredMode[image.id] === 'no' ? 'border-4 border-red-500 cursor-default opacity-80' :
                answeredMode[image.id] === 'skip' ? 'border-4 border-blue-500 cursor-default opacity-80' :
                answeredMode[image.id] === 'yes' ? 'border-4 border-green-500 cursor-default opacity-80' :
                answeredMode[image.id] === 'yes-preferred' ? 'border-4 border-green-700 cursor-default opacity-80' :
                'border-4 border-transparent cursor-default opacity-80' // Should not happen if answeredMode is always set
              )
            : selected.has(image.id)
              ? (
                  selectedMode[image.id] === 'no' ? 'border-4 border-red-500 cursor-pointer' :
                  selectedMode[image.id] === 'skip' ? 'border-4 border-blue-500 cursor-pointer' :
                  selectedMode[image.id] === 'yes' ? 'border-4 border-green-500 cursor-pointer' :
                  selectedMode[image.id] === 'yes-preferred' ? 'border-4 border-green-700 cursor-pointer' :
                  'border-4 border-transparent cursor-pointer' // Fallback if mode not set, though it should be
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
          <div v-if="(countdownTimers.has(image.id) && countdownTimers.get(image.id) > 0) || imageSavingStates.get(image.id)"
               class="absolute top-2 right-2 bg-black bg-opacity-75 text-white text-xs font-bold px-2 py-1 rounded z-10">
            <template v-if="imageSavingStates.get(image.id)">
              Saving...
            </template>
            <template v-else>
              Saving in {{ countdownTimers.get(image.id) }}s
            </template>
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
            :class="answeredMode[image.id] === 'yes-preferred' ? 'bg-green-700' : answeredMode[image.id] === 'yes' ? 'bg-green-500' : answeredMode[image.id] === 'no' ? 'bg-red-500' : answeredMode[image.id] === 'skip' ? 'bg-blue-500' : ''">
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
          <template v-else-if="answeredMode[image.id] === 'yes'">
            <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </template>
          <template v-else-if="answeredMode[image.id] === 'yes-preferred'">
            <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 24 24">
              <polygon points="12,2 15,9 22,9.5 17,14.5 18.5,22 12,18 5.5,22 7,14.5 2,9.5 9,9" />
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
    <!-- Drag Selection Rectangle -->
    <div
      v-if="isDragging && dragSelectionRect.width > 0 && dragSelectionRect.height > 0"
      class="drag-selection-rectangle"
      :style="dragRectangleStyle"
    ></div>
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted, reactive, watch, computed } from 'vue'; // Added computed
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
    manualMode: { type: Boolean, default: false },
    loadAll: { type: Boolean, default: false } // New prop
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
    const imageClickQueue = ref([]); // {id, mode}
    const batchTimer = ref(null);
    const addedImageIds = new Set(); // Set to track added image IDs for manual mode

    // Drag selection state
    const isDragging = ref(false); // Used by both mouse and touch drag
    const dragStartCoordinates = ref({ x: 0, y: 0 }); // Used by both
    const multiSelectedImageIds = ref(new Set()); // Used by both
    const dragSelectionRect = ref({ x: 0, y: 0, width: 0, height: 0 }); // Used by both

    // Mobile long-press drag state
    const longPressTimer = ref(null);
    const touchStartCoordinates = ref({ x: 0, y: 0 });
    const isLongPressActive = ref(false);
    const maxTouchMoveThreshold = ref(10); // Pixels

    // Fullscreen modal state
    const showFullscreen = ref(false);
    const fullscreenImage = ref(null);
    const fullscreenImageUrl = ref('');
    const currentFullscreenIndex = ref(0);

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

    const imageSavingStates = reactive(new Map());

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

    const processClickQueue = () => {
      if (batchTimer.value) {
        clearTimeout(batchTimer.value);
        batchTimer.value = null;
      }
      if (imageClickQueue.value.length > 0) {
        imageClickQueue.value.forEach(item => {
          // Add to pendingAnswers, ensuring no duplicates
          if (!pendingAnswers.value.some(pa => pa.id === item.id)) {
            pendingAnswers.value.push(item);
          } else {
            // If item already exists, update its mode if different
            // This handles rapid clicks changing mode before batch processing
            const existingItem = pendingAnswers.value.find(pa => pa.id === item.id);
            if (existingItem && existingItem.mode !== item.mode) {
              existingItem.mode = item.mode;
            }
          }
        });
        imageClickQueue.value = [];
        if (pendingAnswers.value.length > 0) {
          saveAllPending();
        }
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
      console.log('[CustomGrid] Fetching manual images for category:', props.manualCategory, 'and QID:', props.manualQid);
      loading.value = true;
      let error = '';
      images.value = [];
      addedImageIds.clear(); // Clear the set for new manual fetch
      let foundAny = false;
      try {
        const cat = props.manualCategory.trim().replace(/^Category:/, '');
        // Recursively fetch images from category and all subcategories (max depth 100), progressive display
        await fetchImagesRecursivelyAndPush(cat, new Set(), 0, 100, () => {
          if (!foundAny && images.value.length > 0) {
            loading.value = false;
            foundAny = true;
          }
        }, addedImageIds);
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

    async function fetchImageInfoInBatches(allPageIds) {
      const resultsMap = {};
      const pageIdsCopy = [...allPageIds]; // Operate on a copy

      while (pageIdsCopy.length > 0) {
        const currentChunkPageIds = pageIdsCopy.splice(0, 50); // Take 50 IDs
        const idsParam = currentChunkPageIds.join('|');
        const infoUrl = `https://commons.wikimedia.org/w/api.php?action=query&prop=imageinfo&iiprop=url&iiurlwidth=600&pageids=${idsParam}&format=json&origin=*`;

        try {
          const resp = await fetchWithRetry(infoUrl);
          const data = await resp.json();
          if (data.query && data.query.pages) {
            for (const pageId in data.query.pages) {
              const page = data.query.pages[pageId];
              if (page.imageinfo && page.imageinfo.length > 0) {
                resultsMap[page.pageid] = page.imageinfo[0];
              }
            }
          }
        } catch (error) {
          console.error(`Error fetching image info for page IDs ${idsParam}:`, error);
          // Decide if we want to mark these IDs as failed or skip them
        }
      }
      return resultsMap;
    }

    // Recursive function to fetch images and push to UI as soon as found
    async function fetchImagesRecursivelyAndPush(categoryName, visitedCategories, depth, maxDepth, onImagePushed, currentAddedImageIds, gcmcontinueToken = null) {
      if (depth >= maxDepth) return;

      const normalizedCat = categoryName.replace(/^Category:/, '');
      const fullCatName = `Category:${normalizedCat}`;

      // Initial visit check for the category itself (not for subsequent pages of the same category)
      if (!gcmcontinueToken) { // Only log and sleep on the first fetch for a category
          console.log('[CustomGrid] Iterating category:', categoryName, 'Depth:', depth);
          await new Promise(resolve => setTimeout(resolve, 2000)); // Sleep before starting a new category
          if (visitedCategories.has(fullCatName)) {
              console.log('[CustomGrid] Already visited (or visiting) category:', fullCatName);
              return;
          }
          visitedCategories.add(fullCatName);
      } else {
          console.log('[CustomGrid] Continuing category:', categoryName, 'with token:', gcmcontinueToken);
          await new Promise(resolve => setTimeout(resolve, 500)); // Shorter sleep for paginated calls within the same category
      }

      try {
        let url = `https://commons.wikimedia.org/w/api.php?action=query&generator=categorymembers&gcmtitle=Category:${encodeURIComponent(normalizedCat)}&gcmtype=file|subcat&gcmlimit=50&prop=pageprops&format=json&origin=*`;
        if (gcmcontinueToken) {
          url += `&gcmcontinue=${encodeURIComponent(gcmcontinueToken)}`;
        }

        const resp = await fetchWithRetry(url);
        const data = await resp.json();

        if (!data.query || !data.query.pages) {
          console.warn('[CustomGrid] No query.pages in API response for category:', fullCatName, 'Token:', gcmcontinueToken, data);
          return;
        }

        const pages = Object.values(data.query.pages);
        const filesFromGenerator = pages.filter(p => p.ns === 6);
        const subcategoriesFromGenerator = pages.filter(p => p.ns === 14);
        const nextGcmContinueToken = data.continue?.gcmcontinue;

        if (filesFromGenerator.length > 0) {
          const filePageIds = filesFromGenerator.map(p => p.pageid);
          const imageInfoMap = await fetchImageInfoInBatches(filePageIds);

          const currentBatchFilesWithInfo = filesFromGenerator.map(p => ({
            ...p,
            imageinfo: imageInfoMap[p.pageid] // Attach imageinfo
          })).filter(p => p.imageinfo); // Ensure imageinfo exists (successfully fetched)

          // Filter by allowed file extensions
          const filteredFiles = currentBatchFilesWithInfo.filter(p => {
            const ext = p.title.split('.').pop().toLowerCase();
            if (!IMAGE_FILE_EXTENSIONS.includes(ext)) {
              console.log(`[CustomGrid] Skipping file ${p.pageid} (${p.title}) with unsupported extension: ${ext}`);
              return false;
            }
            return true;
          });

          if (filteredFiles.length > 0) {
            const qidSet = await fetchSubclassesAndInstances(props.manualQid); // Potentially optimize by fetching once per category if QID doesn't change
            const mids = filteredFiles.map(p => {
              let mid = p.pageprops?.wikibase_item || null;
              if (!mid && p.title?.startsWith('File:')) {
                mid = 'M' + p.pageid;
              }
              return mid;
            }).filter(Boolean); // Ensure MID exists

            const depictsMap = await fetchDepictsForMediaInfoIds(mids);

            for (const p of filteredFiles) {
              let mediainfo_id = p.pageprops?.wikibase_item || null;
              if (!mediainfo_id && p.title?.startsWith('File:')) {
                mediainfo_id = 'M' + p.pageid;
              }
              const imageId = mediainfo_id || ('M' + p.pageid); // Fallback to M+pageid if wikibase_item is missing

              if (p?.imageinfo?.url) {
                const depicted = depictsMap[mediainfo_id] || [];
                if (!depicted.some(qid => qidSet.has(qid))) {
                  if (currentAddedImageIds.has(imageId)) {
                    console.log(`[CustomGrid] Duplicate image ID ${imageId} found in category ${categoryName}. Skipping.`);
                    continue;
                  }
                  images.value.push({
                    id: imageId,
                    properties: {
                      mediainfo_id: imageId,
                      img_url: p.imageinfo.thumburl,
                      depicts_id: props.manualQid,
                      manual: true,
                      category: props.manualCategory, // The root category
                      source_category: fullCatName // The category this image was found in
                    },
                    title: p.title
                  });
                  currentAddedImageIds.add(imageId);
                  if (loading.value && images.value.length > 0) {
                    loading.value = false;
                  }
                  if (onImagePushed) onImagePushed();
                }
              } else {
                console.warn(`[CustomGrid] Image ${imageId} (${p.title}) has no valid imageinfo URL, got :`, p.imageinfo);
              }
            }
          }
        }

        // Scroll-pause logic: After processing all files from the current gcm batch
        if (!props.loadAll) {
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
        }

        // Process subcategories from the current gcm batch
        for (let i = 0; i < subcategoriesFromGenerator.length; i++) {
          const subcat = subcategoriesFromGenerator[i];
          const subcatName = subcat.title;
          // Pass null for gcmcontinueToken for new subcategory exploration
          await fetchImagesRecursivelyAndPush(subcatName, visitedCategories, depth + 1, maxDepth, onImagePushed, currentAddedImageIds, null);
          if (i < subcategoriesFromGenerator.length - 1) {
            await new Promise(resolve => setTimeout(resolve, 200)); // Brief pause between subcategories
          }
        }

        // If there's a gcmcontinue token, fetch the next batch for the current category
        if (nextGcmContinueToken) {
          await fetchImagesRecursivelyAndPush(categoryName, visitedCategories, depth, maxDepth, onImagePushed, currentAddedImageIds, nextGcmContinueToken);
        }

      } catch (error) {
        console.error(`Error fetching from category ${fullCatName} (token: ${gcmcontinueToken}):`, error);
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
      const answersToProcess = [...pendingAnswers.value];
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
          const answers = answersToProcess.map(({ id, mode }) => {
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
            const currentSavedItems = [...pendingAnswers.value]; // Copy before clearing
            for (const {id, mode} of currentSavedItems) {
              answered.value.add(id);
              answeredMode[id] = mode;
              cleanupImageState(id); // Replaced cleanup logic
            }
            pendingAnswers.value = []; // Clear pending answers only on full success
            console.log('[GridMode] saveAllPending complete for manual mode, UI updated.');
            toastStore.addToast({ message: `${answers.length} manual answers saved successfully!`, type: 'success' });
          } else {
            const messageBase = `Manual bulk save failed. Status: ${responseManual.status}.`;
            const failedItemsInBatch = [...answersToProcess]; // Items that were attempted

            const failedMids = failedItemsInBatch.map(answerData => {
              const img = images.value.find(i => i.id === answerData.id); // answerData.id is mediainfo_id in this context
              return img ? img.properties.mediainfo_id : answerData.id;
            });

            console.error(`saveAllPending: ${messageBase} Failed MIDs: ${failedMids.join(', ')}`);
            toastStore.addToast({ message: `${messageBase} MIDs: ${failedMids.slice(0,3).join(', ')}${failedMids.length > 3 ? '...' : ''}.`, type: 'error' });

            failedItemsInBatch.forEach(answerData => {
              cleanupImageState(answerData.id); // Replaced cleanup logic
            });
            pendingAnswers.value = []; // Clear pending answers after a failed batch
          }
        } else { // Regular mode
          const answersToSubmit = answersToProcess.map(({ id, mode }) => ({
            question_id: id, // id here is question_id
            answer: mode,
          }));
          console.log('[GridMode] Sending regular bulk answers:', answersToSubmit);
          const regularUrl = '/api/answers/bulk';
          const regularOptions = {
            method: 'POST',
            headers,
            body: JSON.stringify({ answers: answersToSubmit }),
          };
          const responseRegular = await fetchAnswerWithRetry(regularUrl, regularOptions);

          console.log('[GridMode] Regular bulk answer response:', responseRegular.status, responseRegular.statusText);
          if (responseRegular.ok) {
            console.log('saveAllPending: Regular bulk answers successfully sent.');
            const currentSavedItems = [...pendingAnswers.value]; // Copy before clearing
            for (const {id, mode} of currentSavedItems) { // id here is question_id
              answered.value.add(id);
              answeredMode[id] = mode;
              cleanupImageState(id); // Replaced cleanup logic
            }
            pendingAnswers.value = []; // Clear pending answers only on full success
            console.log('[GridMode] saveAllPending complete for regular mode, UI updated.');
            toastStore.addToast({ message: `${answersToSubmit.length} answers saved successfully!`, type: 'success' });
          } else {
            const messageBase = `Regular bulk save failed. Status: ${responseRegular.status}.`;
            const failedItemsInBatch = [...answersToProcess]; // Items that were attempted (question_id, mode)

            const failedMids = failedItemsInBatch.map(answerData => {
              const img = images.value.find(i => i.id === answerData.id); // answerData.id is question_id
              return img ? img.properties.mediainfo_id : answerData.id;
            });

            console.error(`saveAllPending: ${messageBase} Failed MIDs: ${failedMids.join(', ')}`);
            toastStore.addToast({ message: `${messageBase} MIDs: ${failedMids.slice(0,3).join(', ')}${failedMids.length > 3 ? '...' : ''}.`, type: 'error' });

            failedItemsInBatch.forEach(answerData => {
              cleanupImageState(answerData.id); // Replaced cleanup logic
            });
            pendingAnswers.value = []; // Clear pending answers after a failed batch
          }
        }
        // The general loop for UI updates and clearing timers is removed from here,
        // as it's now handled within success/failure blocks.
      } catch (e) {
        // This catch block handles network errors or other critical failures from fetchAnswerWithRetry
        const messageBase = `Bulk save failed due to network/critical error: ${e.message}`;
        const failedItemsInBatch = [...answersToProcess]; // Items that were attempted

        const failedMids = failedItemsInBatch.map(answerData => {
            const img = images.value.find(i => i.id === answerData.id);
            return img ? img.properties.mediainfo_id : answerData.id;
        });

        console.error(`[GridMode] Error in saveAllPending: ${messageBase}. Failed MIDs: ${failedMids.join(', ')}`, e);
        toastStore.addToast({ message: `${messageBase} MIDs: ${failedMids.slice(0,3).join(', ')}${failedMids.length > 3 ? '...' : ''}.`, type: 'error' });

        failedItemsInBatch.forEach(answerData => {
            cleanupImageState(answerData.id); // Replaced cleanup logic
        });
        pendingAnswers.value = []; // Clear pending answers after a failed batch due to exception
      }
    };

    const handleClick = (id, event) => {
      if (event.shiftKey || isDragging.value) { // If shift was held, it might be end of drag, or if isDragging is still true.
        // event.stopPropagation(); // Optional: if needed to prevent further click processing, though mousedown.prevent should handle most.
        return;
      }
      // Proceed with normal toggleSelect if not a shift-click related action
      toggleSelect(id);
    };

    const toggleSelect = (id) => {
      if (isDragging.value) return; // Prevent selection during drag operation
      if (answered.value.has(id)) return;
      const image = images.value.find(img => img.id === id); // Get image ref once

      if (selected.value.has(id)) { // Unselecting
        cleanupImageState(id); // Replaced cleanup logic

        // Remove from imageClickQueue if present
        imageClickQueue.value = imageClickQueue.value.filter(item => item.id !== id);

        // Remove from pendingAnswers if present
        pendingAnswers.value = pendingAnswers.value.filter(a => a.id !== id);

      } else { // Selecting an image
        selected.value.add(id);
        selectedMode[id] = answerMode.value; // Set/update the mode for this selection

        if (autoSave.value && image) {
          // If an auto-save timer is NOT already running for this image, start it.
          if (!timers.has(id)) {
            const autoSaveTimerForImage = setTimeout(() => {
              // Timer expired, add to imageClickQueue using the latest selectedMode
              const currentMode = selectedMode[id] || answerMode.value; // Fallback just in case

              imageSavingStates.set(id, true);
              // Remove any existing entry for this id from queue to use the latest one from autoSaveDelay
              imageClickQueue.value = imageClickQueue.value.filter(item => item.id !== id);
              imageClickQueue.value.push({ id, mode: currentMode });

              // Clear its own functional timer. Visual countdown clearing is handled elsewhere or not needed once "Saving..." shows.
              timers.delete(id);

              // Start/reset the main 1-second batchTimer
              if (batchTimer.value) clearTimeout(batchTimer.value);
              batchTimer.value = setTimeout(processClickQueue, 1000);
            }, autoSaveDelay.value * 1000);
            timers.set(id, autoSaveTimerForImage);

            // Start visual countdown only when a new timer is set
            countdownTimers.set(id, autoSaveDelay.value);
            // Clear any existing interval for this image before starting a new one
            if (countdownIntervals.has(id)) { // Should not happen if timer was not set, but good practice
                clearInterval(countdownIntervals.get(id));
            }
            const intervalId = setInterval(() => {
              if (countdownTimers.has(id)) {
                const currentTime = countdownTimers.get(id);
                if (currentTime > 1) {
                  countdownTimers.set(id, currentTime - 1);
                } else { // Countdown reaches 1 (will be 0 after this tick effectively)
                  clearInterval(countdownIntervals.get(id)); // Clear interval
                  countdownIntervals.delete(id);
                  // countdownTimers.delete(id) will be handled by the main timer's expiry
                }
              } else { // Image unselected or saved manually during countdown
                clearInterval(intervalId);
                if(countdownIntervals.has(id)) {
                  countdownIntervals.delete(id);
                }
              }
            }, 1000);
            countdownIntervals.set(id, intervalId);
          }
          // If a timer is already running, its countdown continues.
          // selectedMode[id] has already been updated above, so it will pick the new mode when its timer expires.
        } else { // autoSave is OFF
          // Remove existing entry for this id from queue to use the latest one
          imageClickQueue.value = imageClickQueue.value.filter(item => item.id !== id);
          imageClickQueue.value.push({ id, mode: selectedMode[id] }); // Use selectedMode[id] which is answerMode.value

          // Start/reset the main 1-second batchTimer
        }
      }
    };

    const clearAnswered = () => {
      const imagesToClear = images.value.filter(img => answered.value.has(img.id));
      const clearedCount = imagesToClear.length;

      if (clearedCount === 0) {
        toastStore.addToast({ message: "No answered images to clear.", type: 'info' });
        return;
      }

      const answerCounts = {
        'yes': 0,
        'no': 0,
        'skip': 0,
        'yes-preferred': 0,
        unknown: 0
      };

      imagesToClear.forEach(img => {
        const mode = answeredMode[img.id];
        if (mode === 'yes') {
          answerCounts.yes++;
        } else if (mode === 'no') {
          answerCounts.no++;
        } else if (mode === 'skip') {
          answerCounts.skip++;
        } else if (mode === 'yes-preferred') {
          answerCounts['yes-preferred']++;
        } else {
          answerCounts.unknown++;
        }
      });

      let message = `Cleared ${clearedCount} image${clearedCount > 1 ? 's' : ''}: `;
      message += `${answerCounts.yes} yes, ${answerCounts['yes-preferred']} prominent, ${answerCounts.no} no, ${answerCounts.skip} skip.`;
      if (answerCounts.unknown > 0) {
        message += ` (${answerCounts.unknown} unknown mode)`;
      }

      toastStore.addToast({ message, type: 'info' });

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
      currentFullscreenIndex.value = images.value.findIndex(img => img.id === image.id);
      showFullscreen.value = true;
      
      // Disable scrolling on body
      document.body.style.overflow = 'hidden';
    };

    // Close fullscreen modal
    const closeFullscreen = () => {
      showFullscreen.value = false;
      fullscreenImage.value = null;
      fullscreenImageUrl.value = '';
      currentFullscreenIndex.value = 0;
      
      // Re-enable scrolling on body
      document.body.style.overflow = '';
    };

    const nextImage = async () => {
      if (!showFullscreen.value) return;
      currentFullscreenIndex.value = (currentFullscreenIndex.value + 1) % images.value.length;
      fullscreenImage.value = images.value[currentFullscreenIndex.value];
      fullscreenImageUrl.value = await getFullSizeImageUrl(fullscreenImage.value);
    };

    const prevImage = async () => {
      if (!showFullscreen.value) return;
      currentFullscreenIndex.value = (currentFullscreenIndex.value - 1 + images.value.length) % images.value.length;
      fullscreenImage.value = images.value[currentFullscreenIndex.value];
      fullscreenImageUrl.value = await getFullSizeImageUrl(fullscreenImage.value);
    };

    // Handle image load errors with retry logic
    const handleImgError = (image, event) => {
      const filename = image.properties.img_url.substring(image.properties.img_url.lastIndexOf('/') + 1);
      // Increment and check general retry count
      const generalRetryCount = imageRetries[image.id] || 0;

      if (generalRetryCount < MAX_IMAGE_RETRIES) {
        imageRetries[image.id] = generalRetryCount + 1;
        // Use the existing exponentialBackoff or a simple fixed delay for retries
        const delay = exponentialBackoff(generalRetryCount); // or a fixed delay like 2000 * (generalRetryCount + 1)

        imageLoadingStates[image.id] = {
          state: 'error',
          filename: filename,
          reason: `Load error. Retrying in ${delay/1000}s... (${generalRetryCount + 1}/${MAX_IMAGE_RETRIES})`
        };

        setTimeout(() => {
          const imgElement = document.querySelector(`img[alt="Image ${image.id}"]`);
          if (imgElement) {
            // Reset src to attempt reload
            const originalSrc = image.properties.img_url;
            imgElement.src = ''; // Clear src
            // Vue might need a tick or a slight delay to re-trigger the load event
            setTimeout(() => {
              imgElement.src = originalSrc;
            }, 50);
          }
        }, delay);
      } else {
        // Max general retries reached
        imageLoadingStates[image.id] = {
          state: 'error',
          filename: filename,
          reason: `Failed to load after ${MAX_IMAGE_RETRIES} retries.`
        };
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

    // --- Add computed property for the (up) SPARQL query link ---
    const depictsUpQueryUrl = computed(() => {
      const depictsId = images.value[0]?.properties?.depicts_id;
      if (!depictsId) return '';
      const sparql = `SELECT DISTINCT ?item ?itemLabel WHERE { {wd:${depictsId} (wdt:P31/wdt:P279)+ ?item.} UNION {wd:${depictsId} (wdt:P31/wdt:P279|wdt:P279)+ ?item .} SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],mul,en". } }`;
      return 'https://query.wikidata.org/embed.html#' + encodeURIComponent(sparql);
    });

    const depictsLinkHref = computed(() => {
      if (images.value.length > 0 && images.value[0]?.properties?.depicts_id) {
        return `https://commons.wikimedia.org/w/index.php?title=Special%3AMediaSearch&search=haswbstatement%3AP180%3D${images.value[0].properties.depicts_id}&type=image`;
      }
      // Fallback URL should also be updated or kept generic if a direct MediaSearch equivalent isn't suitable for a general fallback.
      // For now, let's keep the old fallback, but ideally, this would also point to a relevant MediaSearch or a general help page.
      return 'https://commons.wikimedia.org/wiki/Commons:Depicts';
    });

    // On mount, always add keyboard shortcuts
    let keydownHandler;

    const imageGridContainer = ref(null);

    // --- Dynamic Styling for Drag Selection ---
    const answerModeStyles = {
      'yes': { classSuffix: 'yes', color: 'rgba(74, 170, 74, 0.7)', lightColor: 'rgba(74, 170, 74, 0.2)', outlineColor: 'rgba(74, 170, 74, 0.9)' },
      'no': { classSuffix: 'no', color: 'rgba(220, 53, 69, 0.7)', lightColor: 'rgba(220, 53, 69, 0.2)', outlineColor: 'rgba(220, 53, 69, 0.9)' },
      'skip': { classSuffix: 'skip', color: 'rgba(0, 123, 255, 0.7)', lightColor: 'rgba(0, 123, 255, 0.2)', outlineColor: 'rgba(0, 123, 255, 0.9)' },
      'yes-preferred': { classSuffix: 'yes-preferred', color: 'rgba(30, 120, 30, 0.8)', lightColor: 'rgba(30, 120, 30, 0.3)', outlineColor: 'rgba(30, 120, 30, 0.9)' }
    };

    /**
     * Removes all possible drag highlight classes from an image element.
     * @param {string|number} imageId - The ID of the image to unhighlight.
     */
    const removeDragHighlight = (imageId) => {
      const element = document.querySelector(`[data-image-id="${imageId}"]`);
      if (element) {
        Object.values(answerModeStyles).forEach(style => {
          element.classList.remove(`is-drag-highlighted-${style.classSuffix}`);
        });
      }
    };

    /**
     * Applies a visual highlight to an image element based on the current answerMode.
     * @param {string|number} imageId - The ID of the image to highlight.
     */
    const applyDragHighlight = (imageId) => {
      removeDragHighlight(imageId); // Clear existing highlights first
      const element = document.querySelector(`[data-image-id="${imageId}"]`);
      if (element) {
        const currentStyleKey = answerMode.value || 'skip'; // Default to 'skip' style if answerMode.value is undefined
        const currentStyle = answerModeStyles[currentStyleKey];
        if (currentStyle) {
          element.classList.add(`is-drag-highlighted-${currentStyle.classSuffix}`);
        }
      }
    };

    const dragRectangleStyle = computed(() => {
      const baseStyle = {
        left: dragSelectionRect.value.x + 'px',
        top: dragSelectionRect.value.y + 'px',
        width: dragSelectionRect.value.width + 'px',
        height: dragSelectionRect.value.height + 'px',
      };
      // Default to 'skip' style (blue) if answerMode.value is not a key in answerModeStyles
      const currentStyleKey = answerMode.value && answerModeStyles[answerMode.value] ? answerMode.value : 'skip';
      const modeStyle = answerModeStyles[currentStyleKey];

      return {
        ...baseStyle,
        backgroundColor: modeStyle.lightColor,
        borderColor: modeStyle.color,
      };
    });

    /**
     * Handles the mousedown event on an image, initiating drag selection if Shift key is pressed.
     * @param {object} image - The image object.
     * @param {MouseEvent} event - The mousedown event.
     */
    const handleImageMouseDown = (image, event) => {
      // Only proceed if Shift key is pressed
      if (!event.shiftKey) return;

      isDragging.value = true;
      dragStartCoordinates.value = { x: event.clientX, y: event.clientY };

      // Clear any existing multi-selection and highlights from previous drags
      multiSelectedImageIds.value.clear();
      images.value.forEach(img => removeDragHighlight(img.id));

      // If the clicked image is not already answered, add it to the selection and highlight it
      if (!answered.value.has(image.id)) {
        multiSelectedImageIds.value.add(image.id);
        applyDragHighlight(image.id);
      }

      // Initialize the drag selection rectangle at the mouse position
      dragSelectionRect.value = {
        x: event.clientX,
        y: event.clientY,
        width: 0,
        height: 0,
      };
    };

    /**
     * Handles the mousemove event, updating the drag selection rectangle and highlighting images.
     * This is a global listener attached to the window.
     * @param {MouseEvent} event - The mousemove event.
     */
    const handleMouseMove = (event) => {
      if (!isDragging.value) return; // Only run if dragging is active

      const currentX = event.clientX;
      const currentY = event.clientY;

      // Update the visual selection rectangle's dimensions and position
      dragSelectionRect.value = {
        x: Math.min(dragStartCoordinates.value.x, currentX),
        y: Math.min(dragStartCoordinates.value.y, currentY),
        width: Math.abs(currentX - dragStartCoordinates.value.x),
        height: Math.abs(currentY - dragStartCoordinates.value.y),
      };

      // Check each image for intersection with the selection rectangle
      images.value.forEach(img => {
        const element = document.querySelector(`[data-image-id="${img.id}"]`);
        if (!element) return;

        const rect = element.getBoundingClientRect(); // DOMRect of the image
        const selectionRect = dragSelectionRect.value; // Current drag selection area

        // Determine if the image intersects with the drag selection rectangle
        const intersects = rect.left < selectionRect.x + selectionRect.width &&
                           rect.left + rect.width > selectionRect.x &&
                           rect.top < selectionRect.y + selectionRect.height &&
                           rect.top + rect.height > selectionRect.y;

        if (intersects && !answered.value.has(img.id)) {
          // If intersects and not answered, add to selection and highlight (if not already)
          if (!multiSelectedImageIds.value.has(img.id)) {
            multiSelectedImageIds.value.add(img.id);
            applyDragHighlight(img.id);
          }
        } else {
          // If it does not intersect (or is answered), remove from selection and unhighlight
          if (multiSelectedImageIds.value.has(img.id)) {
            multiSelectedImageIds.value.delete(img.id);
            removeDragHighlight(img.id);
          }
        }
      });
    };

    /**
     * Handles the mouseup event, finalizing the drag selection.
     * This is a global listener attached to the window.
     * @param {MouseEvent} event - The mouseup event.
     */
    const handleMouseUp = (event) => {
      if (!isDragging.value) return; // Only run if dragging was active

      const itemsToProcess = Array.from(multiSelectedImageIds.value);
      let itemsAddedToQueueCount = 0;

      itemsToProcess.forEach(imageId => {
        const image = images.value.find(img => img.id === imageId);
        if (image && !answered.value.has(imageId)) {
          // 1. Mark as selected (core of original toggleSelect's selection part)
          selected.value.add(imageId);
          selectedMode[imageId] = answerMode.value; // answerMode is the component's current answer mode

          // 2. Set up auto-save timer if autoSave is ON
          if (autoSave.value) {
            // Clear any pre-existing functional timer or countdown for this image.
            if (timers.has(imageId)) {
              clearTimeout(timers.get(imageId));
              timers.delete(imageId);
            }
            if (countdownIntervals.has(imageId)) {
              clearInterval(countdownIntervals.get(imageId));
              countdownIntervals.delete(imageId);
            }

            // Set up the main auto-save timer
            const autoSaveTimerForImage = setTimeout(() => {
              // Timer expired, add to imageClickQueue using the latest selectedMode
              const currentMode = selectedMode[imageId] || answerMode.value; // Fallback just in case

              imageSavingStates.set(imageId, true);
              // Remove any existing entry for this id from queue to use the latest one from autoSaveDelay
              imageClickQueue.value = imageClickQueue.value.filter(item => item.id !== imageId);
              imageClickQueue.value.push({ id: imageId, mode: currentMode });

              // Clear its own functional timer. Visual countdown clearing is handled elsewhere or not needed once "Saving..." shows.
              timers.delete(imageId);

              // Start/reset the main 1-second batchTimer
              if (batchTimer.value) clearTimeout(batchTimer.value);
              batchTimer.value = setTimeout(processClickQueue, 1000);
            }, autoSaveDelay.value * 1000);
            timers.set(imageId, autoSaveTimerForImage);

            // Start the visual countdown.
            countdownTimers.set(imageId, autoSaveDelay.value);
            const intervalId = setInterval(() => {
              if (countdownTimers.has(imageId)) {
                const currentTime = countdownTimers.get(imageId);
                // Only decrement if still selected and not yet answered
                if (currentTime > 1 && selected.value.has(imageId) && !answered.value.has(imageId)) {
                  countdownTimers.set(imageId, currentTime - 1);
                } else {
                  clearInterval(intervalId);
                  countdownIntervals.delete(imageId);
                  // countdownTimers.delete(id) will be handled by the main timer's expiry
                }
              } else {
                // Image unselected or saved manually during countdown
                clearInterval(intervalId);
                if (countdownIntervals.has(imageId)) {
                  countdownIntervals.delete(imageId);
                }
              }
            }, 1000);
            countdownIntervals.set(imageId, intervalId);
          } else {
            // 3. If autoSave is OFF, add to imageClickQueue for manual saving
            if (!imageClickQueue.value.some(item => item.id === imageId)) {
              imageClickQueue.value.push({ id: imageId, mode: answerMode.value });
              itemsAddedToQueueCount++;
            }
          }
        }
        // Unconditionally remove drag highlight after processing the item.
        removeDragHighlight(imageId);
      });


      // Reset dragging state
      isDragging.value = false;
      multiSelectedImageIds.value.clear();
      dragSelectionRect.value = { x: 0, y: 0, width: 0, height: 0 };
    };

    /**
     * Handles the touchstart event on an image, initiating long-press drag selection.
     * @param {object} image - The image object.
     * @param {TouchEvent} event - The touchstart event.
     */
    const handleTouchStart = (image, event) => {
      // Ignore multi-touch gestures that could interfere
      if (event.touches.length > 1) {
        clearTimeout(longPressTimer.value);
        return;
      }
      clearTimeout(longPressTimer.value); // Clear any existing long-press timer

      // Store initial touch coordinates
      touchStartCoordinates.value = { x: event.touches[0].clientX, y: event.touches[0].clientY };
      isLongPressActive.value = false; // Reset long-press active state

      // Start a timer to detect a long press
      longPressTimer.value = setTimeout(() => {
        isLongPressActive.value = true; // Long press confirmed
        isDragging.value = true;        // Enable general dragging logic

        // Use touch start coordinates for the drag origin
        dragStartCoordinates.value = { x: touchStartCoordinates.value.x, y: touchStartCoordinates.value.y };

        // Clear previous multi-selection and highlights
        multiSelectedImageIds.value.clear();
        images.value.forEach(img => removeDragHighlight(img.id));

        // If the touched image isn't answered, add it to selection and highlight
        if (!answered.value.has(image.id)) {
          multiSelectedImageIds.value.add(image.id);
          applyDragHighlight(image.id);
        }
        // Initialize drag selection rectangle at the touch point
        dragSelectionRect.value = {
          x: dragStartCoordinates.value.x,
          y: dragStartCoordinates.value.y,
          width: 0,
          height: 0,
        };

        // Optional: Haptic feedback for a more tactile response
        if (navigator.vibrate) {
          navigator.vibrate(50); // Vibrate for 50ms
        }
      }, 500); // 500ms delay to qualify as a long press
    };

    /**
     * Handles the touchmove event, updating drag selection for long-press dragging.
     * This is a global listener attached to the window.
     * @param {TouchEvent} event - The touchmove event.
     */
    const handleTouchMove = (event) => {
      if (event.touches.length === 0) return; // Should not happen if a touch is active
      const touch = event.touches[0];

      // If long press hasn't been activated yet, check if user is scrolling
      if (!isLongPressActive.value) {
        const deltaX = Math.abs(touch.clientX - touchStartCoordinates.value.x);
        const deltaY = Math.abs(touch.clientY - touchStartCoordinates.value.y);
        // If movement exceeds threshold, cancel long press (user is likely scrolling the page)
        if (deltaX > maxTouchMoveThreshold.value || deltaY > maxTouchMoveThreshold.value) {
          clearTimeout(longPressTimer.value);
        }
        return; // Don't proceed if long press is not active
      }

      if (!isDragging.value) return; // Dragging should be active if long press was confirmed

      // Update selection rectangle based on touch movement (similar to handleMouseMove)
      dragSelectionRect.value = {
        x: Math.min(dragStartCoordinates.value.x, touch.clientX),
        y: Math.min(dragStartCoordinates.value.y, touch.clientY),
        width: Math.abs(touch.clientX - dragStartCoordinates.value.x),
        height: Math.abs(touch.clientY - dragStartCoordinates.value.y),
      };

      // Check for intersection with images
      images.value.forEach(img => {
        const element = document.querySelector(`[data-image-id="${img.id}"]`);
        if (!element) return;
        const rect = element.getBoundingClientRect();
        const selectionRect = dragSelectionRect.value;
        const intersects = rect.left < selectionRect.x + selectionRect.width &&
                           rect.left + rect.width > selectionRect.x &&
                           rect.top < selectionRect.y + selectionRect.height &&
                           rect.top + rect.height > selectionRect.y;

        if (intersects && !answered.value.has(img.id)) {
          if (!multiSelectedImageIds.value.has(img.id)) {
            multiSelectedImageIds.value.add(img.id);
            applyDragHighlight(img.id);
          }
        } else {
          if (multiSelectedImageIds.value.has(img.id)) {
            multiSelectedImageIds.value.delete(img.id);
            removeDragHighlight(img.id);
          }
        }
      });
    };

    /**
     * Handles the touchend event, finalizing or canceling long-press drag selection.
     * This is a global listener attached to the window.
     * @param {TouchEvent} event - The touchend event.
     */
    const handleTouchEnd = (event) => {
      clearTimeout(longPressTimer.value); // Always clear the long press timer

      // If a drag operation was active (due to successful long press)
      if (isDragging.value && isLongPressActive.value) {
        event.preventDefault(); // Crucial: Prevent click event from firing after a drag selection

        const itemsToProcess = Array.from(multiSelectedImageIds.value);
        let itemsAddedToQueueCount = 0;

        itemsToProcess.forEach(imageId => {
          const image = images.value.find(img => img.id === imageId);
          if (image && !answered.value.has(imageId)) {
            // 1. Mark as selected
            selected.value.add(imageId);
            selectedMode[imageId] = answerMode.value;

            // 2. Set up auto-save timer if autoSave is ON
            if (autoSave.value) {
              if (timers.has(imageId)) {
                clearTimeout(timers.get(imageId));
                timers.delete(imageId);
              }
              if (countdownIntervals.has(imageId)) {
                clearInterval(countdownIntervals.get(imageId));
                countdownIntervals.delete(imageId);
              }

              // Set up the main auto-save timer
              const autoSaveTimerForImage = setTimeout(() => {
                // Timer expired, add to imageClickQueue using the latest selectedMode
                const currentMode = selectedMode[imageId] || answerMode.value; // Fallback just in case

                imageSavingStates.set(imageId, true);
                // Remove any existing entry for this id from queue to use the latest one from autoSaveDelay
                imageClickQueue.value = imageClickQueue.value.filter(item => item.id !== imageId);
                imageClickQueue.value.push({ id: imageId, mode: currentMode });

                // Clear its own functional timer. Visual countdown clearing is handled elsewhere or not needed once "Saving..." shows.
                timers.delete(imageId);

                // Start/reset the main 1-second batchTimer
                if (batchTimer.value) clearTimeout(batchTimer.value);
                batchTimer.value = setTimeout(processClickQueue, 1000);
              }, autoSaveDelay.value * 1000);
              timers.set(imageId, autoSaveTimerForImage);

              // Start the visual countdown
              countdownTimers.set(imageId, autoSaveDelay.value);
              const intervalId = setInterval(() => {
                if (countdownTimers.has(imageId)) {
                  const currentTime = countdownTimers.get(imageId);
                  if (currentTime > 1 && selected.value.has(imageId) && !answered.value.has(imageId)) {
                    countdownTimers.set(imageId, currentTime - 1);
                  } else {
                    clearInterval(intervalId);
                    countdownIntervals.delete(imageId);
                    // countdownTimers.delete(id) will be handled by the main timer's expiry
                  }
                } else {
                  // Image unselected or saved manually during countdown
                  clearInterval(intervalId);
                  if (countdownIntervals.has(imageId)) {
                    countdownIntervals.delete(imageId);
                  }
                }
              }, 1000);
              countdownIntervals.set(imageId, intervalId);
            } else {
              // 3. If autoSave is OFF, add to imageClickQueue for manual saving
              if (!imageClickQueue.value.some(item => item.id === imageId)) {
                imageClickQueue.value.push({ id: imageId, mode: answerMode.value });
                itemsAddedToQueueCount++;
              }
            }
          }
          // Unconditionally remove drag highlight after processing the item.
          removeDragHighlight(imageId);
        });
      }

      // Reset all relevant states, regardless of whether it was a drag or just a cancelled/short tap
      isDragging.value = false;
      isLongPressActive.value = false;
      multiSelectedImageIds.value.clear();

      // Ensure any remaining highlights are cleared (e.g., if touchend occurred unexpectedly)
      images.value.forEach(img => {
        const elem = document.querySelector(`[data-image-id="${img.id}"]`);
        if (elem?.classList.contains('is-drag-highlighted')) {
            removeDragHighlight(img.id);
        }
      });
      dragSelectionRect.value = { x: 0, y: 0, width: 0, height: 0 }; // Reset selection rectangle
    };

    onMounted(() => {
      window.addEventListener('mousemove', handleMouseMove);
      window.addEventListener('mouseup', handleMouseUp);
      window.addEventListener('touchmove', handleTouchMove, { passive: false });
      window.addEventListener('touchend', handleTouchEnd);


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
    if (showFullscreen.value) {
      // Fullscreen navigation
      if (e.key === 'ArrowRight') {
        nextImage();
      } else if (e.key === 'ArrowLeft') {
        prevImage();
      }
    } else {
      // Answer mode shortcuts (only when not in fullscreen)
      if (e.key.toLowerCase() === 'q') answerMode.value = 'yes-preferred';
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
      window.removeEventListener('mousemove', handleMouseMove);
      window.removeEventListener('mouseup', handleMouseUp);
      window.removeEventListener('touchmove', handleTouchMove);
      window.removeEventListener('touchend', handleTouchEnd);
    });
    // Also call ensureViewportFilled after each fetch
    const sendAnswerToUse = props.manualMode ? sendAnswerManual : sendAnswer;
    console.log('[GridMode] manualMode:', props.manualMode, 'Using', props.manualMode ? '/api/manual-question/answer' : '/api/answers');

    const onSaveClickHandler = () => {
      console.log('[GridMode] onSaveClickHandler called');

      // 1. Process imageClickQueue immediately
      if (batchTimer.value) {
        clearTimeout(batchTimer.value);
        batchTimer.value = null;
      }
      if (imageClickQueue.value.length > 0) {
        imageClickQueue.value.forEach(item => {
          if (!pendingAnswers.value.some(pa => pa.id === item.id)) {
            pendingAnswers.value.push(item);
          } else {
            const existingItem = pendingAnswers.value.find(pa => pa.id === item.id);
            if (existingItem && existingItem.mode !== item.mode) {
              existingItem.mode = item.mode;
            }
          }
        });
        imageClickQueue.value = [];
      }

      const idsToSetSavingState = [];

      // 2. Ensure all selected (but not yet answered) images are added to pendingAnswers
      selected.value.forEach(id => {
        if (!answered.value.has(id)) {
          const mode = selectedMode[id] || answerMode.value;
          if (!pendingAnswers.value.some(a => a.id === id)) {
            pendingAnswers.value.push({ id, mode });
          } else {
            const existingPending = pendingAnswers.value.find(a => a.id === id);
            if (existingPending.mode !== mode) {
              existingPending.mode = mode;
            }
          }

          // Mark this ID for setting the saving state later, as it's part of this manual save batch.
          idsToSetSavingState.push(id);

          // Clear any running auto-save timer for this id as we are saving manually.
          // cleanupImageState will clear timers, countdowns, and also any pre-existing imageSavingStates.
          // This is fine because we will re-set imageSavingStates for manual saves just after this loop.
          cleanupImageState(id);
        }
      });

      // Set the "Saving..." state for all images that were processed by this manual save click.
      idsToSetSavingState.forEach(id => {
        imageSavingStates.set(id, true);
      });

      // 3. Call saveAllPending if there's anything to save
      if (pendingAnswers.value.length > 0) {
        saveAllPending();
      } else {
        console.log('[GridMode] onSaveClickHandler: No pending answers to save after processing queue and selected items.');
      }
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
        // Move items from imageClickQueue to pendingAnswers
        if (imageClickQueue.value.length > 0) {
          imageClickQueue.value.forEach(item => {
            if (!pendingAnswers.value.some(pa => pa.id === item.id)) {
              pendingAnswers.value.push(item);
            } else {
              // Update mode if item exists and mode is different
              const existingItem = pendingAnswers.value.find(pa => pa.id === item.id);
              if (existingItem && existingItem.mode !== item.mode) {
                existingItem.mode = item.mode;
              }
            }
          });
          imageClickQueue.value = [];
          console.log('[GridMode] autoSave turned off. Moved items from imageClickQueue to pendingAnswers.');
        }
        // Also clear the batch timer if it's running
        if (batchTimer.value) {
          clearTimeout(batchTimer.value);
          batchTimer.value = null;
          console.log('[GridMode] autoSave turned off. Cleared batchTimer.');
        }
      }
    });

    /**
     * Cleans up all state associated with a given image ID.
     * This includes removing the image from selection, clearing any
     * auto-save timers, countdowns, and saving indicators.
     *
     * @param {string|number} id - The ID of the image to clean up state for.
     */
    const cleanupImageState = (id) => {
      selected.value.delete(id);
      delete selectedMode[id];

      if (timers.has(id)) {
        clearTimeout(timers.get(id));
        timers.delete(id);
      }
      imageSavingStates.delete(id);

      if (countdownIntervals.has(id)) {
        clearInterval(countdownIntervals.get(id));
        countdownIntervals.delete(id);
      }
      countdownTimers.delete(id);
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
      handleImgError,
      handleImgLoad,
      markImageLoading,
      imageLoadingStates,
      showFullscreen,
      fullscreenImage,
      fullscreenImageUrl,
      openFullscreen,
      closeFullscreen,
      nextImage,
      prevImage,
      countdownTimers, // Added for template access
      depictsUpQueryUrl, // Added computed property
      depictsLinkHref, // Added computed property
      imageSavingStates,
      cleanupImageState, // Added new function
      // Drag selection refs
      isDragging,
      dragStartCoordinates,
      multiSelectedImageIds,
      dragSelectionRect,
      // Drag selection methods
      handleImageMouseDown,
      handleClick, // New click handler
      imageGridContainer,
      // Touch drag refs and methods
      longPressTimer,
      touchStartCoordinates,
      isLongPressActive,
      maxTouchMoveThreshold,
      handleTouchStart,
      // handleTouchMove and handleTouchEnd are global listeners
      dragRectangleStyle, // For dynamic rectangle styling
    };
  },
};
</script>

<style scoped>
/* Mode-specific highlights for individual images */
.is-drag-highlighted-yes {
  outline: 3px dashed rgba(74, 170, 74, 0.9); /* Green */
  outline-offset: -2px;
  box-shadow: 0 0 0 3px rgba(74, 170, 74, 0.4), inset 0 0 0 3px rgba(74, 170, 74, 0.4);
}

.is-drag-highlighted-no {
  outline: 3px dashed rgba(220, 53, 69, 0.9); /* Red */
  outline-offset: -2px;
  box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.4), inset 0 0 0 3px rgba(220, 53, 69, 0.4);
}

.is-drag-highlighted-skip {
  outline: 3px dashed rgba(0, 123, 255, 0.9); /* Blue */
  outline-offset: -2px;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.4), inset 0 0 0 3px rgba(0, 123, 255, 0.4);
}

.is-drag-highlighted-yes-preferred {
  outline: 3px dashed rgba(30, 120, 30, 0.9); /* Darker Green */
  outline-offset: -2px;
  box-shadow: 0 0 0 3px rgba(30, 120, 30, 0.4), inset 0 0 0 3px rgba(30, 120, 30, 0.4);
}

.drag-selection-rectangle {
  position: fixed;
  pointer-events: none;
  z-index: 100;
  border-width: 1px;
  border-style: solid;
  /* Dynamic styles (backgroundColor, borderColor, left, top, width, height) are applied via :style binding */
}
</style>
