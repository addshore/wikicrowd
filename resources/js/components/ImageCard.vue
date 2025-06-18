<template>
  <div
    :data-image-id="image.id"
    @click="$emit('click', image.id, $event)"
    @mousedown.prevent="$emit('mousedown', image, $event)"
    @touchstart.prevent="$emit('touchstart', image, $event)"
    :class="[
      'relative flex flex-col rounded overflow-hidden transition-all',
      isAnswered
        ? getAnsweredBorderClass()
        : isSelected
          ? getSelectedBorderClass()
          : 'border-4 border-transparent cursor-pointer'
    ]"
  >
    <div :class="['relative w-full bg-gray-100', imageHeightClass]">
      <!-- Loading spinner -->
      <div v-if="!imageLoadingState || imageLoadingState === 'loading'" 
           class="absolute inset-0 flex items-center justify-center bg-gray-100">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
      
      <!-- Error state -->
      <div v-if="imageLoadingState?.state === 'error'"
           class="absolute inset-0 flex items-center justify-center bg-gray-100">
        <div class="text-center text-gray-500 px-2">
          <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <p class="text-xs font-semibold truncate" :title="imageLoadingState.filename">{{ imageLoadingState.filename }}</p>
          <p class="text-xs">{{ imageLoadingState.reason }}</p>
        </div>
      </div>
      
      <img
        :src="image.properties.img_url"
        :alt="`Image ${image.id}`"
        draggable="false"
        class="object-contain align-top w-full h-full"
        style="object-position:top"
        @load="$emit('imgLoad', image)"
        @error="$emit('imgError', image, $event)"
        @loadstart="$emit('imgLoadStart', image)"
      />
      
      <!-- Countdown Timer Overlay -->
      <div v-if="(countdownTime && countdownTime > 0) || isSaving"
           class="absolute top-2 right-2 bg-black bg-opacity-75 text-white text-xs font-bold px-2 py-1 rounded z-10">
        <template v-if="isSaving">
          Saving...
        </template>
        <template v-else>
          Saving in {{ countdownTime }}s
        </template>
      </div>
    </div>
    
    <!-- Magnifying glass icon -->
    <button 
      @click="$emit('openFullscreen', image, $event)"
      class="absolute bottom-8 right-2 bg-black bg-opacity-60 hover:bg-opacity-80 text-white p-1.5 rounded-full transition-all z-10"
      title="View fullscreen"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
      </svg>
    </button>
    
    <div class="image-title px-2 py-1 text-xs text-center bg-white bg-opacity-80 w-full"
      @click.stop
    >
      <div class="truncate mb-1">
        <a :href="'https://commons.wikimedia.org/wiki/' + (image.title || image.properties?.page_title)" target="_blank">{{ image.properties?.mediainfo_id || image.id }}</a>
      </div>
      <ExistingDepictsLabels :media-info-id="image.properties?.mediainfo_id || image.id" />
    </div>
    
    <!-- Answer overlay -->
    <div v-if="isAnswered" class="absolute inset-0 flex items-center justify-center bg-opacity-60 pointer-events-none"
        :class="getAnsweredOverlayClass()">
      <template v-if="answeredMode === 'no'">
        <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </template>
      <template v-else-if="answeredMode === 'skip'">
        <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24">
          <text x="12" y="20" text-anchor="middle" font-size="18" font-family="Arial" dy="-2">?</text>
        </svg>
      </template>
      <template v-else-if="answeredMode === 'yes'">
        <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
      </template>
      <template v-else-if="answeredMode === 'yes-preferred'">
        <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 24 24">
          <polygon points="12,2 15,9 22,9.5 17,14.5 18.5,22 12,18 5.5,22 7,14.5 2,9.5 9,9" />
        </svg>
      </template>
    </div>
    
    <div v-else-if="isSelected" class="absolute inset-0 pointer-events-none"></div>
  </div>
</template>

<script>
import ExistingDepictsLabels from './ExistingDepictsLabels.vue';

export default {
  name: 'ImageCard',
  components: {
    ExistingDepictsLabels
  },
  props: {
    image: {
      type: Object,
      required: true
    },
    isAnswered: {
      type: Boolean,
      default: false
    },
    answeredMode: {
      type: String,
      default: null
    },
    isSelected: {
      type: Boolean,
      default: false
    },
    selectedMode: {
      type: String,
      default: null
    },
    imageLoadingState: {
      type: [String, Object],
      default: null
    },
    countdownTime: {
      type: Number,
      default: 0
    },
    isSaving: {
      type: Boolean,
      default: false
    },
    imageHeightClass: {
      type: String,
      required: true
    }
  },
  emits: ['click', 'mousedown', 'touchstart', 'imgLoad', 'imgError', 'imgLoadStart', 'openFullscreen'],
  methods: {
    getAnsweredBorderClass() {
      const modeMap = {
        'no': 'border-4 border-red-500 cursor-default opacity-80',
        'skip': 'border-4 border-blue-500 cursor-default opacity-80',
        'yes': 'border-4 border-green-500 cursor-default opacity-80',
        'yes-preferred': 'border-4 border-green-700 cursor-default opacity-80'
      };
      return modeMap[this.answeredMode] || 'border-4 border-transparent cursor-default opacity-80';
    },
    getSelectedBorderClass() {
      const modeMap = {
        'no': 'border-4 border-red-500 cursor-pointer',
        'skip': 'border-4 border-blue-500 cursor-pointer',
        'yes': 'border-4 border-green-500 cursor-pointer',
        'yes-preferred': 'border-4 border-green-700 cursor-pointer'
      };
      return modeMap[this.selectedMode] || 'border-4 border-transparent cursor-pointer';
    },
    getAnsweredOverlayClass() {
      const modeMap = {
        'yes-preferred': 'bg-green-700',
        'yes': 'bg-green-500',
        'no': 'bg-red-500',
        'skip': 'bg-blue-500'
      };
      return modeMap[this.answeredMode] || '';
    }
  },
  computed: {
    // No computed properties needed for now
  }
};
</script>
