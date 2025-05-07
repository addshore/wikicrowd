<template>
  <div class="p-4">
    <h2 class="text-xl font-bold mb-4">
      Which images clearly depict
      <span class="text-blue-700">"{{ images[0]?.properties?.depicts_name || '...' }}"</span>
      <span v-if="images[0]?.properties?.depicts_id"> ({{ images[0].properties.depicts_id }})</span>?
    </h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
      <div v-for="image in images" :key="image.id"
        @click="!answered.has(image.id) && toggleSelect(image.id)"
        :class="[
          'relative rounded overflow-hidden transition-all',
          answered.has(image.id)
            ? 'border-4 border-green-500 cursor-default opacity-80'
            : selected.has(image.id)
              ? 'border-4 border-green-500 cursor-pointer'
              : 'border-4 border-transparent cursor-pointer'
        ]"
      >
        <img
          :src="image.properties.img_url"
          :alt="`Image ${image.id}`"
          class="object-contain align-top w-full h-56"
          style="object-position:top"
        />
        <div class="image-title px-2 py-1 text-xs text-center truncate bg-white bg-opacity-80 absolute bottom-0 left-0 w-full">
          <a :href="'https://commons.wikimedia.org/wiki/Special:EntityData/' + image.properties?.mediainfo_id" target="_blank">{{ image.properties?.mediainfo_id || image.id }}</a>
        </div>
        <div v-if="answered.has(image.id)" class="absolute inset-0 flex items-center justify-center bg-green-500 bg-opacity-60 pointer-events-none">
          <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <div v-else-if="selected.has(image.id)" class="absolute inset-0 pointer-events-none"></div>
      </div>
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
    const groupName = document.getElementById('question-container')?.dataset.groupName;
    const apiToken = window.apiToken || null;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const fetchNextImages = async (count = 4) => {
      if (allLoaded.value || isFetchingMore.value) return;
      isFetchingMore.value = true;
      let fetched = 0;
      while (fetched < count && !allLoaded.value) {
        let url = `/api/questions/${groupName}`;
        if (seenIds.value.length > 0) {
          url += `?seen_ids=${encodeURIComponent(seenIds.value.join(','))}`;
        }
        const headers = { 'Accept': 'application/json' };
        if (apiToken) headers['Authorization'] = `Bearer ${apiToken}`;
        try {
          const response = await fetch(url, { headers });
          if (!response.ok) {
            allLoaded.value = true;
            break;
          }
          const data = await response.json();
          if (data && data.question && data.question.id && !seenIds.value.includes(data.question.id)) {
            images.value.push(data.question);
            seenIds.value.push(data.question.id);
            fetched++;
          } else {
            allLoaded.value = true;
            break;
          }
        } catch (e) {
          allLoaded.value = true;
          break;
        }
      }
      isFetchingMore.value = false;
      loading.value = false;
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

    const sendAnswer = async (image) => {
      // Mark as answered immediately for UI feedback
      answered.value.add(image.id);
      selected.value.delete(image.id);
      try {
        const response = await fetch('/api/answers', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(apiToken ? { 'Authorization': `Bearer ${apiToken}` } : {}),
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({
            question_id: image.id,
            answer: 'yes'
          })
        });
        if (!response.ok) {
          console.error('Failed to submit answer:', response.status, response.statusText);
        }
      } catch (error) {
        console.error('Error submitting answer:', error);
      }
    };

    const toggleSelect = (id) => {
      if (answered.value.has(id)) return; // Don't allow interaction if already answered
      if (selected.value.has(id)) {
        // Unselect and clear timer
        selected.value.delete(id);
        if (timers.has(id)) {
          clearTimeout(timers.get(id));
          timers.delete(id);
        }
      } else {
        // Select and start timer
        selected.value.add(id);
        const image = images.value.find(img => img.id === id);
        const timer = setTimeout(() => {
          sendAnswer(image);
          timers.delete(id);
        }, 10000);
        timers.set(id, timer);
      }
    };

    onMounted(() => {
      // Estimate how many images are needed to fill the viewport
      const imageHeight = 250; // px, including padding/margin
      const rows = Math.ceil(window.innerHeight / imageHeight);
      const columns = 5; // max columns in grid
      const initialCount = rows * columns;
      fetchNextImages(initialCount);
      window.addEventListener('scroll', handleScroll);
    });

    return {
      images,
      selected,
      answered,
      toggleSelect,
    };
  },
};
</script>

<style scoped>
</style>
