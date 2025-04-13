<template>
  <div>
    <NavBar />
    <v-container>
      <h1>
        Depicts: {{ category }}
        <a 
          :href="`https://www.wikidata.org/wiki/${depictsId}`" 
          target="_blank" 
          rel="noopener noreferrer"
          class="ml-2"
        >{{ depictsId }}</a>
      </h1>
      <v-btn @click="goBackToCategory" prepend-icon="mdi-arrow-left" class="mb-4">
        Back to {{ category }}
      </v-btn>

      <div v-if="loading" class="mt-4">Loading item configuration...</div>
      <div v-else-if="error" class="mt-4 error-message">Error: {{ error }}</div>

      <div v-if="itemData?.category" class="mt-6">
        <h2>
          Images from Commons 
          <a 
            :href="`https://commons.wikimedia.org/wiki/${itemData.category}`" 
            target="_blank" 
            rel="noopener noreferrer"
          >
            {{ itemData.category }}
          </a>
        </h2>
        <!-- Use state from composable -->
        <div v-if="commonsLoading" class="mt-4">Loading category images...</div>
        <div v-else-if="commonsError" class="mt-4 error-message">Error loading Commons images: {{ commonsError }}</div>
        <v-row v-else-if="commonsImages.length > 0" class="mt-2">
          <v-col
            v-for="image in commonsImages"
            :key="image.title"
            cols="6"
            sm="4"
            md="3"
            lg="2"
          >
            <v-card
              @click="selectImage(image.title)"
              :class="{ 'selected-image': selectedImageTitles.has(image.title) }"
              class="image-card"
            >
              <v-img
                :src="image.thumbnailUrl"
                :lazy-src="image.thumbnailUrl"
                aspect-ratio="1"
                cover
                class="bg-grey-lighten-2"
              >
                <template v-slot:placeholder>
                  <v-row class="fill-height ma-0" align="center" justify="center">
                    <v-progress-circular indeterminate color="grey-lighten-5"></v-progress-circular>
                  </v-row>
                </template>
              </v-img>
              <v-card-text class="image-title">
                 <a :href="`https://commons.wikimedia.org/wiki/${image.title}`" target="_blank" rel="noopener noreferrer">
                    {{ image.title.replace('File:', '').substring(0, 30) }}{{ image.title.length > 30 ? '...' : '' }}
                 </a>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
        <div v-else-if="!commonsLoading && !isFetchingMore" class="mt-4"> <!-- Adjusted condition -->
          No images found in this category on Commons, or the category does not exist.
        </div>
        <!-- Loading indicator for subsequent fetches -->
        <div v-if="isFetchingMore && !commonsLoading" class="mt-4 text-center">
          <v-progress-circular indeterminate color="primary"></v-progress-circular>
          <p>Loading more images...</p>
        </div>
         <!-- Message indicating no more images can be fetched -->
        <div v-if="!commonsLoading && !isFetchingMore && !canFetchMore && commonsImages.length > 0" class="mt-4 text-center text-grey">
          All reachable images loaded.
        </div>
      </div>

      <v-card v-if="itemData" class="mt-4" hover>
        <v-card-title>Debug Info</v-card-title>
        <v-card-text>
          <p v-if="itemData.exclude"><strong>Exclude Categories:</strong> {{ itemData.exclude.join(', ') }}</p>
          <p v-if="itemData.excludeRegex"><strong>Exclude Regex:</strong> {{ itemData.excludeRegex }}</p>
        </v-card-text>
      </v-card>
      <div v-else-if="!loading" class="mt-4"> <!-- Adjusted condition -->
        Item configuration not found in {{ category }}.yaml for {{ depictsId }}.
      </div>

    </v-container>
  </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'; // Removed nextTick
import { useRoute, useRouter } from 'vue-router';
import * as jsyaml from 'js-yaml';
import NavBar from '../../../components/NavBar.vue';
import { useCommonsCategoryImages } from '../../../composables/useCommonsCategoryImages'; // Import composable

// Define the structure matching the YAML items
interface DepictsItem {
  depictsId: string;
  name: string;
  limit?: number;
  category?: string; // Commons category
  exclude?: string[];
  excludeRegex?: string;
  imageUrl?: string;
}

// REMOVED: CommonsImage, CommonsMember interfaces
// REMOVED: IMAGE_FILE_EXTENSIONS constant

const route = useRoute<{ category: string; depictsId: string }>();
const router = useRouter();

// Use computed properties to safely access route params
const category = computed(() => route.params.category as string);
const depictsId = computed(() => route.params.depictsId as string);

// State for loading YAML data for the specific item
const loading = ref(false);
const error = ref<string | null>(null);
const itemData = ref<DepictsItem | null>(null);

const {
  commonsLoading,
  commonsError,
  commonsImages,
  isFetchingMore,
  canFetchMore,
  startInitialFetch,
  loadMoreImages,
  resetCommonsState,
} = useCommonsCategoryImages();

const selectedImageTitles = ref(new Set<string>());

// Function to fetch the category YAML and find the specific item's data
const fetchItemDataFromYaml = async () => {
  // ... (existing implementation - no changes needed)
  if (!category.value || !depictsId.value) {
    error.value = 'Category or Depicts ID missing.';
    return;
  }

  loading.value = true;
  error.value = null;
  itemData.value = null;
  const yamlUrl = `/depicts/${category.value}.yaml`;
  console.log(`Fetching YAML from: ${yamlUrl} to find item ${depictsId.value}`);

  try {
    const response = await fetch(yamlUrl);
    if (!response.ok) {
      throw new Error(`Failed to fetch ${yamlUrl}: ${response.status} ${response.statusText}`);
    }
    const yamlText = await response.text();
    const parsedData = jsyaml.load(yamlText);

    if (Array.isArray(parsedData)) {
      const foundItem = (parsedData as DepictsItem[]).find(item => item.depictsId === depictsId.value);
      if (foundItem) {
        itemData.value = foundItem;
        console.log(`Found item data for ${depictsId.value}:`, foundItem);
      } else {
        throw new Error(`Item with depictsId ${depictsId.value} not found in ${category.value}.yaml`);
      }
    } else {
      throw new Error('YAML data is not in the expected array format.');
    }
  } catch (e: any) {
    console.error('Failed to fetch or parse YAML for item data:', e);
    error.value = e.message || 'Unknown error loading item configuration.';
  } finally {
    loading.value = false;
  }
};

const handleScroll = () => {
  const nearBottom = window.innerHeight + window.scrollY >= document.body.offsetHeight - 200;
  if (nearBottom) {
    loadMoreImages(); // Call function from composable
  }
};

const selectImage = (title: string) => {
  if (selectedImageTitles.value.has(title)) {
    selectedImageTitles.value.delete(title);
  } else {
    selectedImageTitles.value.add(title);
  }
  console.log("TODO action selection", selectedImageTitles.value);
};

// Function to navigate back to the category page
const goBackToCategory = () => {
  // ... (existing implementation - no changes needed)
  if (category.value) {
    router.push({ name: '/depicts/[category]', params: { category: category.value } });
  } else {
    router.push('/depicts');
  }
};

// Fetch item data when the component mounts
onMounted(() => {
  fetchItemDataFromYaml();
  window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
});

// Watch for itemData to be loaded, then fetch Commons images if category exists
watch(itemData, (newItemData) => {
  if (newItemData?.category) {
    // Reset selection and start fetch using composable function
    selectedImageTitles.value.clear();
    startInitialFetch(newItemData.category);
  } else {
    // Clear all related state
    resetCommonsState(); // Use reset function from composable
    selectedImageTitles.value.clear();
  }
}, { immediate: false });

</script>

<style scoped>
.mb-4 {
  margin-bottom: 1rem;
}
.mt-4 {
  margin-top: 1rem;
}
.mt-6 {
  margin-top: 2rem; /* More space before the Commons section */
}
.v-card[hover]:hover {
  cursor: pointer;
  background-color: rgba(0, 0, 0, 0.05); /* Slight background change on hover */
}
.error-message {
  color: red;
  font-weight: bold;
}
.image-title {
  font-size: 0.8rem;
  padding: 8px;
  text-align: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.image-title a {
  color: inherit;
  text-decoration: none;
}
.image-title a:hover {
  text-decoration: underline;
}
.text-center {
  text-align: center;
}
.text-grey {
    color: #757575; /* Vuetify grey */
}
.image-card {
  cursor: pointer;
  border: 3px solid transparent; /* Reserve space for border */
  transition: border-color 0.2s ease-in-out;
}

.selected-image {
  border-color: #4CAF50; /* Green border */
  position: relative;
}

.selected-image::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(76, 175, 80, 0.3); /* Green overlay with transparency */
  pointer-events: none;
}
</style>
