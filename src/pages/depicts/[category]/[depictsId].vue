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
import { ref, computed, onMounted, watch, nextTick, onUnmounted } from 'vue'; // Added onUnmounted
import { useRoute, useRouter } from 'vue-router';
import * as jsyaml from 'js-yaml'; // Added js-yaml import
import NavBar from '../../../components/NavBar.vue'; // Adjust path as needed

// Define the structure matching the YAML items
interface DepictsItem {
  depictsId: string;
  name: string;
  limit?: number;
  category?: string; // Commons category
  exclude?: string[];
  excludeRegex?: string;
  imageUrl?: string; // Keep for consistency, though maybe not used directly here
}

interface CommonsImage {
  title: string;
  thumbnailUrl: string;
}

// Define the structure of a category member from the API response
interface CommonsMember {
  pageid: number;
  ns: number; // Namespace: 6 for File, 14 for Category
  title: string;
}

// Allowed image file extensions
const IMAGE_FILE_EXTENSIONS = [
  'jpg', 'jpeg', 'png', 'gif', 'svg', 'tiff',
];

const route = useRoute<{ category: string; depictsId: string }>();
const router = useRouter();

// Use computed properties to safely access route params
const category = computed(() => route.params.category as string);
const depictsId = computed(() => route.params.depictsId as string); // Assuming depictsId is the QID

// State for loading YAML data for the specific item
const loading = ref(false);
const error = ref<string | null>(null);
const itemData = ref<DepictsItem | null>(null); // Holds the config for the specific depictsId

// State for loading Commons category images
const commonsLoading = ref(false); // For the *initial* load
const commonsError = ref<string | null>(null);
const commonsImages = ref<CommonsImage[]>([]);

// --- State for Infinite Scrolling ---
const imageDisplayLimit = ref(100); // Initial limit, will be increased
const imagesToFetchIncrement = 100; // How many more to fetch each time
const isFetchingMore = ref(false); // True when fetching subsequent batches
const canFetchMore = ref(false); // True if the last fetch hit the limit, suggesting more might exist
const visitedCategories = ref(new Set<string>()); // Persist visited categories across fetch cycles
const initialCategoryTitle = ref<string | null>(null); // Store the starting category for resuming
// --- End Infinite Scrolling State ---

const selectedImageTitles = ref(new Set<string>()); // State for selected images (Set)

// Helper function to get lowercased file extension
const getFileExtension = (filename: string): string => {
  const parts = filename.split('.');
  if (parts.length > 1) {
    return parts[parts.length - 1].toLowerCase();
  }
  return '';
};

// Function to fetch the category YAML and find the specific item's data
const fetchItemDataFromYaml = async () => {
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

// Helper function to introduce a delay
const sleep = (ms: number) => new Promise(resolve => setTimeout(resolve, ms));

// --- Refactored Fetching Logic ---

// Recursive function to fetch category members
const fetchCategory = async (categoryTitle: string) => {
    // Check limit *before* fetching API
    if (commonsImages.value.length >= imageDisplayLimit.value) {
        console.log(`Pausing fetch for ${categoryTitle}, limit ${imageDisplayLimit.value} reached.`);
        canFetchMore.value = true; // Indicate more might be available
        return; // Pause
    }

    if (visitedCategories.value.has(categoryTitle)) {
        console.log(`Skipping already visited category: ${categoryTitle}`);
        return;
    }
    visitedCategories.value.add(categoryTitle);

    const apiCategoryTitle = categoryTitle.startsWith('Category:') ? categoryTitle : `Category:${categoryTitle}`;
    const commonsApiUrl = `https://commons.wikimedia.org/w/api.php?action=query&format=json&formatversion=2&list=categorymembers&cmtitle=${encodeURIComponent(apiCategoryTitle)}&cmtype=file|subcat&cmlimit=500&origin=*`;

    console.log(`Fetching Commons category members from: ${commonsApiUrl}`);

    try {
        const response = await fetch(commonsApiUrl);
        // ... existing response error handling (404 etc.) ...
        const data = await response.json();
        // ... existing warnings handling ...

        if (data.query?.categorymembers) {
            const members = data.query.categorymembers as CommonsMember[];
            for (const member of members) {
                // Check limit *inside* loop as well
                if (commonsImages.value.length >= imageDisplayLimit.value) {
                    console.log(`Reached image limit (${imageDisplayLimit.value}) while processing ${categoryTitle}.`);
                    canFetchMore.value = true; // Set flag before returning
                    return; // Stop processing members and prevent further recursion from this call
                }

                if (member.ns === 6) { // File
                    const fileExtension = getFileExtension(member.title);
                    if (IMAGE_FILE_EXTENSIONS.includes(fileExtension) && !commonsImages.value.some(img => img.title === member.title)) {
                        commonsImages.value.push({
                            title: member.title,
                            thumbnailUrl: `https://commons.wikimedia.org/wiki/Special:FilePath/${encodeURIComponent(member.title)}?width=200`
                        });
                        await nextTick(); // Update UI immediately
                    }
                    // ... existing logging for skipped files ...
                } else if (member.ns === 14) { // Category
                    // Recursively fetch subcategory (limit check happens at the start of the recursive call)
                    await fetchCategory(member.title);
                    // Check if the recursive call paused us
                    if (canFetchMore.value) {
                        return; // Propagate the pause signal up
                    }
                }
            }
             // ... existing continuation comment ...
        }
        // ... existing API error/no query/no members handling ...
    } catch (e: any) {
      console.error(`Failed to fetch Commons category members for ${apiCategoryTitle}:`, e);
      if (!commonsError.value) { // Set top-level error only once
          commonsError.value = e.message || 'Unknown error loading Commons images.';
      }
      canFetchMore.value = false; // Stop trying on error
      throw e; // Re-throw to be caught by runFetchCycle
    }
};

// Function to run a fetch cycle (initial or subsequent)
const runFetchCycle = async () => {
    if (!initialCategoryTitle.value) {
        console.error("Cannot run fetch cycle without an initial category title.");
        return;
    }

    isFetchingMore.value = true; // Indicate fetching is active
    canFetchMore.value = false; // Assume no more until fetchCategory pauses

    console.log(`Running fetch cycle, starting from ${initialCategoryTitle.value}, limit ${imageDisplayLimit.value}`);

    try {
        await fetchCategory(initialCategoryTitle.value);
        // If fetchCategory completed without throwing and without setting canFetchMore,
        // it means we explored everything reachable within the current limit.
        console.log(`Fetch cycle complete. Can fetch more: ${canFetchMore.value}. Total images: ${commonsImages.value.length}`);
    } catch (e: any) {
        console.error('Error during fetch cycle execution:', e);
        // commonsError should be set within fetchCategory's catch block
        canFetchMore.value = false; // Ensure we stop on error
    } finally {
        isFetchingMore.value = false; // Fetching attempt finished
    }
};

// Function to set up and start the *initial* fetch
const startInitialFetch = () => {
    if (!itemData.value?.category) {
        console.log('No Commons category specified in item data.');
        commonsImages.value = [];
        commonsError.value = null;
        canFetchMore.value = false;
        isFetchingMore.value = false;
        initialCategoryTitle.value = null;
        visitedCategories.value.clear();
        return;
    }
    console.log(`Starting initial fetch for category: ${itemData.value.category}`);

    commonsLoading.value = true; // Show initial loading indicator
    commonsError.value = null;
    commonsImages.value = [];
    visitedCategories.value.clear(); // Reset visited for a new item
    imageDisplayLimit.value = 100; // Reset limit
    canFetchMore.value = false;
    isFetchingMore.value = false;

    initialCategoryTitle.value = itemData.value.category.startsWith('Category:')
        ? itemData.value.category
        : `Category:${itemData.value.category}`;

    runFetchCycle().finally(() => {
        commonsLoading.value = false; // Hide initial loading indicator once first cycle finishes/pauses
    });
};

// --- End Refactored Fetching Logic ---

// --- Infinite Scroll Handling ---

const loadMoreImages = () => {
    if (!canFetchMore.value || isFetchingMore.value) {
        return; // Don't fetch if not allowed or already fetching
    }
    console.log('Scroll triggered: Loading more images...');
    imageDisplayLimit.value += imagesToFetchIncrement;
    runFetchCycle(); // Continue fetching with the new limit
};

const handleScroll = () => {
  // Check if near bottom (e.g., within 200px)
  const nearBottom = window.innerHeight + window.scrollY >= document.body.offsetHeight - 200;
  if (nearBottom) {
    loadMoreImages();
  }
};

// --- End Infinite Scroll Handling ---

// --- Image Selection ---
const selectImage = (title: string) => {
  if (selectedImageTitles.value.has(title)) {
    selectedImageTitles.value.delete(title); // Deselect
  } else {
    selectedImageTitles.value.add(title); // Select
  }
  console.log("TODO action selection", selectedImageTitles.value); // Log the set
};
// --- End Image Selection ---

// Function to navigate back to the category page
const goBackToCategory = () => {
  if (category.value) {
    router.push({ name: '/depicts/[category]', params: { category: category.value } });
  } else {
    // Fallback if category param is somehow missing
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
    startInitialFetch(); // Use the new setup function
  } else {
    // Clear all commons/fetch related state if itemData is cleared or lacks category
    commonsImages.value = [];
    commonsError.value = null;
    commonsLoading.value = false;
    canFetchMore.value = false;
    isFetchingMore.value = false;
    initialCategoryTitle.value = null;
    visitedCategories.value.clear();
    selectedImageTitles.value.clear(); // Clear selected images
  }
}, { immediate: false }); // Don't run immediately, wait for fetchItemDataFromYaml

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
}
</style>
