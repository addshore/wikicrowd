<template>
  <!-- Fullscreen Modal -->
  <div v-if="isVisible && image"
       class="fixed inset-0 z-50 bg-black bg-opacity-90 flex items-center justify-center p-4"
       @click="close">
    <div class="relative max-w-full max-h-full flex flex-col">
      <!-- Close button -->
      <button
        @click="close"
        class="absolute top-4 right-4 bg-black bg-opacity-60 hover:bg-opacity-80 text-white p-2 rounded-full z-10"
        title="Close fullscreen"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>

      <!-- Main image -->
      <div class="relative"> <!-- Added relative positioning for overlay -->
        <!-- Thumbnail image (loads first for immediate display) -->
        <img
          v-if="thumbnailUrl && !highQualityLoaded"
          :src="thumbnailUrl"
          :alt="`Thumbnail ${image.id}`"
          class="max-h-[80vh] max-w-full object-contain cursor-pointer blur-sm"
          @click="close"
          draggable="false"
        />
        <!-- High quality image (loads in background) -->
        <img
          :src="imageUrl"
          :alt="`Fullscreen Image ${image.id}`"
          class="max-h-[80vh] max-w-full object-contain cursor-pointer"
          :class="{ 'opacity-0': !highQualityLoaded }"
          @click="close"
          draggable="false"
          @load="onHighQualityImageLoad"
          @error="onImageError"
          style="transition: opacity 0.3s ease-in-out;"
        />
        <!-- Loading Overlay -->
        <div v-if="isImageLoading && !thumbnailUrl" class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-70 z-20">
          <div class="text-white text-2xl font-bold">Loading...</div>
        </div>
        <!-- Answer/Saving Overlay -->
        <div
          v-if="isAnswered || isSaving"
          class="absolute inset-0 flex items-center justify-center pointer-events-none"
          :class="{
            'bg-green-700 bg-opacity-60': answeredWithMode === 'yes-preferred',
            'bg-green-500 bg-opacity-60': answeredWithMode === 'yes',
            'bg-red-500 bg-opacity-60': answeredWithMode === 'no',
            'bg-blue-500 bg-opacity-60': answeredWithMode === 'skip',
            'bg-gray-700 bg-opacity-70': isSaving && !isAnswered, 
          }"
        >
          <template v-if="isSaving">
            <div class="text-white text-2xl font-bold">Saving...</div>
          </template>
          <template v-else-if="isAnswered">
            <template v-if="answeredWithMode === 'no'">
              <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </template>
            <template v-else-if="answeredWithMode === 'skip'">
              <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24">
                <text x="12" y="20" text-anchor="middle" font-size="18" font-family="Arial" dy="-2">?</text>
              </svg>
            </template>
            <template v-else-if="answeredWithMode === 'yes'">
              <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </template>
            <template v-else-if="answeredWithMode === 'yes-preferred'">
              <svg class="w-24 h-24 text-white" fill="currentColor" viewBox="0 0 24 24">
                <polygon points="12,2 15,9 22,9.5 17,14.5 18.5,22 12,18 5.5,22 7,14.5 2,9.5 9,9" />
              </svg>
            </template>
          </template>
        </div>
      </div>

      <!-- Image info -->
      <div class="mt-4 text-white text-center">
        <a
          :href="'https://commons.wikimedia.org/wiki/Special:EntityData/' + image.properties?.mediainfo_id"
          target="_blank"
          class="text-blue-300 hover:text-blue-100 underline"
        >
          {{ image.properties?.mediainfo_id || image.id }}
        </a>
      </div>

      <!-- Answer Buttons -->
      <div class="mt-4 flex justify-center space-x-2" @click.stop>
        <button
          @click="handleAnswer('yes-preferred')"
          :disabled="isAnswered || isSaving"
          :class="['px-3 py-1.5 text-sm font-bold text-white rounded',
                   (isAnswered || isSaving) ? 'bg-gray-500 cursor-not-allowed' : 'bg-green-700 hover:bg-green-800']"
        >
          Prominent (Q)
        </button>
        <button
          @click="handleAnswer('yes')"
          :disabled="isAnswered || isSaving"
          :class="['px-3 py-1.5 text-sm font-bold text-white rounded',
                   (isAnswered || isSaving) ? 'bg-gray-500 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700']"
        >
          YES (1)
        </button>
        <button
          @click="handleAnswer('skip')"
          :disabled="isAnswered || isSaving"
          :class="['px-3 py-1.5 text-sm font-bold text-white rounded',
                   (isAnswered || isSaving) ? 'bg-gray-500 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700']"
        >
          SKIP (E)
        </button>
        <button
          @click="handleAnswer('no')"
          :disabled="isAnswered || isSaving"
          :class="['px-3 py-1.5 text-sm font-bold text-white rounded',
                   (isAnswered || isSaving) ? 'bg-gray-500 cursor-not-allowed' : 'bg-red-600 hover:bg-red-700']"
        >
          NO (2)
        </button>
      </div>

      <!-- Navigation Buttons -->
      <button
        @click.stop="prev"
        class="absolute left-4 top-1/2 -translate-y-1/2 bg-black bg-opacity-50 hover:bg-opacity-75 text-white p-3 rounded-full focus:outline-none"
        title="Previous image"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
      </button>
      <button
        @click.stop="next"
        class="absolute right-4 top-1/2 -translate-y-1/2 bg-black bg-opacity-50 hover:bg-opacity-75 text-white p-3 rounded-full focus:outline-none"
        title="Next image"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
        </svg>
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'FullscreenImageView',
  props: {
    image: {
      type: Object,
      required: true,
    },
    imageUrl: {
      type: String,
      required: true,
    },
    thumbnailUrl: {
      type: String,
      required: false,
      default: null,
    },
    isVisible: {
      type: Boolean,
      default: false,
    },
    isAnswered: {
      type: Boolean,
      default: false,
    },
    answeredWithMode: {
      type: String,
      default: null,
    },
    isSaving: {
      type: Boolean,
      default: false,
    },
    nextImageUrl: {
      type: String,
      required: false,
      default: null,
    },
    prevImageUrl: {
      type: String,
      required: false,
      default: null,
    }
  },
  data() {
    return {
      isImageLoading: true,
      highQualityLoaded: false,
      preloadedImages: {},
    };
  },
  emits: ['close', 'next', 'prev', 'answer'],
  methods: {
    close() {
      this.$emit('close');
    },
    next() {
      this.$emit('next');
    },
    prev() {
      this.$emit('prev');
    },
    handleAnswer(mode) {
      this.$emit('answer', { image: this.image, mode: mode });
      // Decide on auto-close behavior later. For now, it stays open.
      // this.close();
    },
    onHighQualityImageLoad() {
      this.isImageLoading = false;
      this.highQualityLoaded = true;
      this.preloadedImages[this.imageUrl] = true;
    },
    onImageError() {
      this.isImageLoading = false;
      this.highQualityLoaded = true; // Show whatever we have, even if it's just thumbnail
    },
    preloadImage(url) {
      if (!url || this.preloadedImages[url]) return;
      const img = new window.Image();
      img.onload = () => {
        // Mark as preloaded and update loading state if this is the current imageUrl
        this.preloadedImages[url] = true;
        if (url === this.imageUrl) {
          this.isImageLoading = false;
        }
      };
      img.onerror = () => {
        // If error, still allow to hide loading overlay to avoid stuck state
        if (url === this.imageUrl) {
          this.isImageLoading = false;
        }
      };
      img.src = url;
    },
  },
  // Watch for visibility changes to manage body scroll
  watch: {
    isVisible(newVal) {
      if (newVal) {
        document.body.style.overflow = 'hidden';
      } else {
        document.body.style.overflow = '';
      }
    },
    image() {
      this.isImageLoading = !this.thumbnailUrl; // Don't show loading if we have a thumbnail
      this.highQualityLoaded = false;
    },
    imageUrl() {
      // If already preloaded, skip loading overlay
      if (this.preloadedImages[this.imageUrl]) {
        this.isImageLoading = false;
        this.highQualityLoaded = true;
      } else {
        this.isImageLoading = !this.thumbnailUrl; // Don't show loading if we have a thumbnail
        this.highQualityLoaded = false;
      }
    },
    nextImageUrl: {
      immediate: true,
      handler(newUrl) {
        this.preloadImage(newUrl);
      }
    },
    prevImageUrl: {
      immediate: true,
      handler(newUrl) {
        this.preloadImage(newUrl);
      }
    }
  },
  // Handle keyboard navigation
  mounted() {
    // Preload initial images
    this.preloadImage(this.nextImageUrl);
    this.preloadImage(this.prevImageUrl);
    
    this.keydownHandler = (e) => {
      if (!this.isVisible) return;

      // If an overlay is visible (answered or saving), perhaps only Escape should work
      // or navigation should be disabled. For now, allow all.
      // Consider adding: if (this.isAnswered || this.isSaving) { if (e.key === 'Escape') this.close(); return; }


      if (e.key === 'Escape') {
        this.close();
      } else if (e.key === 'ArrowRight') {
        this.next();
      } else if (e.key === 'ArrowLeft') {
        this.prev();
      } else if (!this.isAnswered && !this.isSaving) { // Only allow answer shortcuts if not already answered/saving
        if (e.key.toLowerCase() === 'q') {
          this.handleAnswer('yes-preferred');
        } else if (e.key === '1') {
          this.handleAnswer('yes');
        } else if (e.key === '2') {
          this.handleAnswer('no');
        } else if (e.key.toLowerCase() === 'e') {
          this.handleAnswer('skip');
        }
      }
    };
    window.addEventListener('keydown', this.keydownHandler);
  },
  beforeUnmount() {
    window.removeEventListener('keydown', this.keydownHandler);
    // Ensure body scroll is reset if component is unmounted while visible
    if (this.isVisible) {
      document.body.style.overflow = '';
    }
  },
};
</script>

<style scoped>
/* Scoped styles for FullscreenImageView */
/* Ensure the modal is above other content */
.fixed {
  z-index: 1000; /* Or a value higher than other elements */
}

/* Additional styling for navigation buttons for better visibility and interaction */
.absolute {
  transition: background-color 0.2s ease-in-out;
}
</style>
