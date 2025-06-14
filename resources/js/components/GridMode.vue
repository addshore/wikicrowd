<template>
  <div class="p-4 w-full max-w-none mx-auto">
    <div class="sticky top-0 z-20 bg-white bg-opacity-95 pb-2 mb-2 shadow">
      <DepictsHeader
        :depicts-id="images[0]?.properties?.depicts_id"
        :depicts-name="images[0]?.properties?.depicts_name"
        :depicts-up-query-url="depictsUpQueryUrl"
        :depicts-link-href="depictsLinkHref"
      />
      <AnswerModeButtons
        v-model:answer-mode="answerMode"
      />
      
      <div class="flex justify-center mt-2 mb-2">
        <GridControlBar
          :pending-answers-count="pendingAnswers.length"
          :selected-count="Array.from(selected).filter(id => !answered.has(id)).length"
          @save="onSaveClickHandler"
          @clear-answered="clearAnswered"
        />
        
        <AutoSaveSettings
          v-model:auto-save="autoSave"
          v-model:auto-save-delay="autoSaveDelay"
        />
      </div>

      <ImageSizeControl v-model:image-size="imageSize" />

      <div class="flex justify-center w-full">
        <small class="text-center">Select Yes, Skip, or No at the top. Clicking on an image will flag it for the selected answer, and save after 10 seconds. You can can click it before saving to undo the answer.</small>
      </div>
    </div>
    
    <LoadingSpinner
      :loading="loading"
      message="Loading images..."
    />
    
    <EmptyState
      v-if="!loading && images.length === 0"
      message="No images available to review."
    />
    
    <div v-else-if="!loading && images.length > 0" :class="gridClasses">
      <ImageCard
        v-for="image in images" 
        :key="image.id"
        :image="image"
        :is-answered="answered.has(image.id)"
        :answered-mode="answeredMode[image.id]"
        :is-selected="selected.has(image.id)"
        :selected-mode="selectedMode[image.id]"
        :image-loading-state="imageLoadingStates[image.id]"
        :countdown-time="countdownTimers.get(image.id)"
        :is-saving="imageSavingStates.get(image.id)"
        :image-height-class="imageHeightClass"
        @click="handleClick"
        @mousedown="handleImageMouseDown"
        @touchstart="handleTouchStart"
        @img-load="handleImgLoad"
        @img-error="handleImgError"
        @img-load-start="markImageLoading"
        @open-fullscreen="openFullscreen"
      />
    </div>
    <div class="flex justify-center mt-6" v-if="!allLoaded && !loading && !isFetchingMore && !manualMode">
      <button class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold rounded shadow" @click="fetchNextImages(10)">
        Load More
      </button>
    </div>
    
    <FullscreenImageView
      v-if="fullscreenImage"
      :image="fullscreenImage"
      :image-url="fullscreenImageUrl"
      :thumbnail-url="fullscreenThumbnailUrl"
      :next-image-url="nextImageUrl"
      :prev-image-url="prevImageUrl"
      :is-visible="showFullscreen"
      :is-answered="answered.has(fullscreenImage?.id)"
      :answered-with-mode="fullscreenImage ? answeredMode[fullscreenImage.id] : null"
      :is-saving="fullscreenImage ? imageSavingStates.get(fullscreenImage.id) : false"
      @close="closeFullscreen"
      @next="nextImage"
      @prev="prevImage"
      @answer="handleFullscreenAnswer"
    />

    <LoadingSpinner
      :loading="loading || (manualMode && !allLoaded)"
      message="Loading more images, please wait..."
    />
    <DragSelectionOverlay
      :is-dragging="isDragging"
      :x="dragSelectionRect.x"
      :y="dragSelectionRect.y"
      :width="dragSelectionRect.width"
      :height="dragSelectionRect.height"
      :answer-mode-styles="answerModeStyles"
      :current-answer-mode="answerMode"
    />
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted, reactive, watch, computed } from 'vue';
import FullscreenImageView from './FullscreenImageView.vue';
import AnswerModeButtons from './AnswerModeButtons.vue';
import ImageSizeControl from './ImageSizeControl.vue';
import AutoSaveSettings from './AutoSaveSettings.vue';
import GridControlBar from './GridControlBar.vue';
import DepictsHeader from './DepictsHeader.vue';
import LoadingSpinner from './LoadingSpinner.vue';
import DragSelectionOverlay from './DragSelectionOverlay.vue';
import ImageCard from './ImageCard.vue';
import EmptyState from './EmptyState.vue';
import { fetchSubclassesAndInstances, fetchDepictsForMediaInfoIds } from './depictsUtils';
import { generateDepictsUpQueryUrl } from '../sparqlQueries.js';
import { toastStore } from '../toastStore.js';

export default {
  name: 'GridMode',
  components: { 
    FullscreenImageView,
    AnswerModeButtons,
    ImageSizeControl,
    AutoSaveSettings,
    GridControlBar,
    DepictsHeader,
    LoadingSpinner,
    DragSelectionOverlay,
    ImageCard,
    EmptyState
  },
  props: {
    manualCategory: { type: String, default: '' },
    manualQid: { type: String, default: '' },
    manualMode: { type: Boolean, default: false },
    loadAll: { type: Boolean, default: false }
  },
  setup(props) {
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

    // Image size control
    const imageSize = ref(5); // 1=largest, 12=smallest (matches grid-cols-1 to grid-cols-12)

    // Fullscreen modal state
    const showFullscreen = ref(false); // Controls visibility of the FullscreenImageView component
    const fullscreenImage = ref(null); // The image object for the FullscreenImageView
    const fullscreenImageUrl = ref(''); // The image URL for the FullscreenImageView
    const currentFullscreenIndex = ref(0); // To keep track of the current image for next/prev logic

    // Batch for progressive fill
    const batch = ref([]);
    const BATCH_SIZE = 100;

    // Track image load retries
    const imageRetries = reactive({});
    const MAX_IMAGE_RETRIES = 3;

    // Track image loading states and backoff
    const imageLoadingStates = reactive({});
    const MAX_FETCH_RETRIES = 5; // Used by existing fetchWithRetry

    const imageSavingStates = reactive(new Map());

    // Only allow certain image file extensions
    const IMAGE_FILE_EXTENSIONS = [
      'jpg', 'jpeg', 'png', 'gif', 'svg', 'tiff'
    ];

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
            throw error;
          }
        }
      }
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
    };

    const closeFullscreen = () => {
      showFullscreen.value = false;
    };

    const nextImage = async () => {
      if (images.value.length === 0) return;
      currentFullscreenIndex.value = (currentFullscreenIndex.value + 1) % images.value.length;
      const nextImg = images.value[currentFullscreenIndex.value];
      fullscreenImage.value = nextImg;
      fullscreenImageUrl.value = await getFullSizeImageUrl(nextImg);
    };

    const prevImage = async () => {
      if (images.value.length === 0) return;
      currentFullscreenIndex.value = (currentFullscreenIndex.value - 1 + images.value.length) % images.value.length;
      const prevImg = images.value[currentFullscreenIndex.value];
      fullscreenImage.value = prevImg;
      fullscreenImageUrl.value = await getFullSizeImageUrl(prevImg);
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
      return generateDepictsUpQueryUrl(depictsId);
    });

    const depictsLinkHref = computed(() => {
      if (images.value.length > 0 && images.value[0]?.properties?.depicts_id) {
        return `https://commons.wikimedia.org/w/index.php?title=Special%3AMediaSearch&search=haswbstatement%3AP180%3D${images.value[0].properties.depicts_id}&type=image`;
      }
      // Fallback URL should also be updated or kept generic if a direct MediaSearch equivalent isn't suitable for a general fallback.
      // For now, let's keep the old fallback, but ideally, this would also point to a relevant MediaSearch or a general help page.
      return 'https://commons.wikimedia.org/wiki/Commons:Depicts';
    });

    // Computed properties for next/prev image URLs and thumbnails
    const nextImageUrl = computed(() => {
      if (images.value.length === 0 || currentFullscreenIndex.value === -1) return null;
      const nextIndex = (currentFullscreenIndex.value + 1) % images.value.length;
      const nextImg = images.value[nextIndex];
      if (!nextImg) return null;
      
      // Use the same logic as getFullSizeImageUrl but synchronously
      if (props.manualMode && nextImg.title) {
        // For manual mode, we'll use the thumbnail URL and let preloading handle the full size
        const currentUrl = nextImg.properties.img_url;
        if (currentUrl.includes('/thumb/') && currentUrl.includes('px-')) {
          return currentUrl.replace(/\/thumb\/(.+?)\/\d+px-.+$/, '/$1');
        }
        return currentUrl;
      }
      
      // For regular images, remove width parameter to get full size
      const currentUrl = nextImg.properties.img_url;
      if (currentUrl.includes('/thumb/') && currentUrl.includes('px-')) {
        return currentUrl.replace(/\/thumb\/(.+?)\/\d+px-.+$/, '/$1');
      }
      return currentUrl;
    });

    const prevImageUrl = computed(() => {
      if (images.value.length === 0 || currentFullscreenIndex.value === -1) return null;
      const prevIndex = (currentFullscreenIndex.value - 1 + images.value.length) % images.value.length;
      const prevImg = images.value[prevIndex];
      if (!prevImg) return null;
      
      // Use the same logic as getFullSizeImageUrl but synchronously
      if (props.manualMode && prevImg.title) {
        // For manual mode, we'll use the thumbnail URL and let preloading handle the full size
        const currentUrl = prevImg.properties.img_url;
        if (currentUrl.includes('/thumb/') && currentUrl.includes('px-')) {
          return currentUrl.replace(/\/thumb\/(.+?)\/\d+px-.+$/, '/$1');
        }
        return currentUrl;
      }
      
      // For regular images, remove width parameter to get full size
      const currentUrl = prevImg.properties.img_url;
      if (currentUrl.includes('/thumb/') && currentUrl.includes('px-')) {
        return currentUrl.replace(/\/thumb\/(.+?)\/\d+px-.+$/, '/$1');
      }
      return currentUrl;
    });

    const fullscreenThumbnailUrl = computed(() => {
      return fullscreenImage.value?.properties?.img_url || null;
    });

    // Dynamic grid classes based on image size
    const gridClasses = computed(() => {
      const sizeMap = {
        1: 'grid-cols-1',
        2: 'grid-cols-1 sm:grid-cols-2',
        3: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3', 
        4: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
        5: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5',
        6: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6',
        7: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7',
        8: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8',
        9: 'grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 2xl:grid-cols-9',
        10: 'grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8 2xl:grid-cols-10',
        11: 'grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-7 xl:grid-cols-9 2xl:grid-cols-11',
        12: 'grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 2xl:grid-cols-12'
      };
      return `grid ${sizeMap[imageSize.value]} gap-2`;
    });

    // Dynamic image height classes based on grid size
    const imageHeightClass = computed(() => {
      const heightMap = {
        1: 'h-[70vh] max-h-[600px] min-h-[300px]',    // Very large - 1 column
        2: 'h-[45vh] max-h-[400px] min-h-[250px]',    // Large - 2 columns
        3: 'h-[35vh] max-h-[320px] min-h-[220px]',    // Medium-large - 3 columns
        4: 'h-[30vh] max-h-[280px] min-h-[200px]',    // Medium - 4 columns
        5: 'h-[25vh] max-h-[250px] min-h-[180px]',    // Medium-small - 5 columns
        6: 'h-[22vh] max-h-[220px] min-h-[160px]',    // Small - 6 columns
        7: 'h-[20vh] max-h-[200px] min-h-[150px]',    // Smaller - 7 columns
        8: 'h-[18vh] max-h-[180px] min-h-[140px]',    // Very small - 8 columns
        9: 'h-[16vh] max-h-[160px] min-h-[130px]',    // Tiny - 9 columns
        10: 'h-[15vh] max-h-[150px] min-h-[120px]',   // Very tiny - 10 columns
        11: 'h-[14vh] max-h-[140px] min-h-[110px]',   // Micro - 11 columns
        12: 'h-[13vh] max-h-[130px] min-h-[100px]'    // Ultra-micro - 12 columns
      };
      return heightMap[imageSize.value] || heightMap[3]; // default to 3 columns
    });

    // On mount, always add keyboard shortcuts
    let keydownHandler;

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
            countdownIntervals.set(imageId, intervalId);            } else {
              // 3. If autoSave is OFF, add to imageClickQueue for manual saving
              if (!imageClickQueue.value.some(item => item.id === imageId)) {
                imageClickQueue.value.push({ id: imageId, mode: answerMode.value });
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
        const columns = imageSize.value; // max columns in grid based on current setting
        const rows = Math.ceil(window.innerHeight / imageHeight);
        const initialCount = Math.max(1, (rows + 2) * columns); // Ensure at least 1 image
        fetchNextImages(initialCount).then(() => {
          setTimeout(() => {
            ensureViewportFilled();
          }, 100);
        });
        window.addEventListener('scroll', handleScroll);
      }
      // Keyboard shortcuts
      keydownHandler = (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        // Fullscreen navigation is handled by FullscreenImageView.vue when it's visible.
    // GridMode should only handle its own shortcuts when fullscreen is not active.
    if (!showFullscreen.value) {
      if (e.key.toLowerCase() === 'q') answerMode.value = 'yes-preferred';
      else if (e.key === '1') answerMode.value = 'yes';
      else if (e.key === '2') answerMode.value = 'no';
      else if (e.key.toLowerCase() === 'e') answerMode.value = 'skip';
    }
    // Note: The Escape keydown for closing fullscreen is now managed by FullscreenImageView.vue
    // and it emits a 'close' event. GridMode's keydownHandler no longer needs to check for Escape.
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

    const handleFullscreenAnswer = async ({ image, mode }) => {
      if (!image || !image.id) {
        console.error('handleFullscreenAnswer: Invalid image data received.');
        return;
      }
      console.log(`Answer received from fullscreen: Image ID ${image.id}, Mode: ${mode}`);

      // Set saving state
      imageSavingStates.set(image.id, true);

      try {
        // Call the existing sendAnswerToUse function, providing the image and mode directly.
        await sendAnswerToUse(image, mode);
        // If sendAnswerToUse is successful, it will update `answered` and `answeredMode`
        // and also clear selected states.
      } catch (error) {
        // Error handling is ideally done within sendAnswerToUse (e.g., showing toasts)
        // If not, add additional error handling here.
        console.error(`Error in handleFullscreenAnswer for image ${image.id}:`, error);
      } finally {
        // Clear saving state regardless of success or failure
        imageSavingStates.delete(image.id);
      }
    };

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
      countdownTimers,
      depictsUpQueryUrl,
      depictsLinkHref,
      nextImageUrl,
      prevImageUrl,
      fullscreenThumbnailUrl,
      imageSavingStates,
      cleanupImageState,
      isDragging,
      dragStartCoordinates,
      multiSelectedImageIds,
      dragSelectionRect,
      handleImageMouseDown,
      handleClick,
      longPressTimer,
      touchStartCoordinates,
      isLongPressActive,
      maxTouchMoveThreshold,
      handleTouchStart,
      handleFullscreenAnswer,
      imageSize,
      gridClasses,
      imageHeightClass,
      answerModeStyles,
    };
  },
};
</script>
