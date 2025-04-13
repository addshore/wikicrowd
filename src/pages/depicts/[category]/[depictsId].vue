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
const imageDisplayLimit = ref(100);
const imagesToFetchIncrement = 100;
const isFetchingMore = ref(false);
const canFetchMore = ref(false);
const visitedCategories = ref(new Set<string>());
const initialCategoryTitle = ref<string | null>(null);
const categoryQueue = ref<string[]>([]); // Queue for categories to process
// --- End Infinite Scrolling State ---

const selectedImageTitles = ref(new Set<string>());

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

// --- Refactored Fetching Logic (Iterative Queue-Based) ---

// REMOVED: Recursive fetchCategory function

// Function to run a fetch cycle (initial or subsequent) using a queue
const runFetchCycle = async () => {
    if (categoryQueue.value.length === 0 && !initialCategoryTitle.value) {
        console.error("Cannot run fetch cycle without an initial category or items in the queue.");
        isFetchingMore.value = false; // Ensure fetching stops
        canFetchMore.value = false;
        return;
    }
     // If queue is empty but we have an initial title (first run), add it.
    if (categoryQueue.value.length === 0 && initialCategoryTitle.value) {
         if (!visitedCategories.value.has(initialCategoryTitle.value)) {
             categoryQueue.value.push(initialCategoryTitle.value);
             console.log(`Initialized queue with: ${initialCategoryTitle.value}`);
         } else {
             console.log(`Initial category ${initialCategoryTitle.value} already visited, skipping queue init.`);
         }
    }


    isFetchingMore.value = true;
    // We assume we *might* be able to fetch more until the queue is exhausted *without* hitting the limit.
    // This will be set correctly at the end of the loop.
    canFetchMore.value = false;

    console.log(`Running fetch cycle. Queue size: ${categoryQueue.value.length}, Limit: ${imageDisplayLimit.value}`);

    try {
        while (categoryQueue.value.length > 0 && commonsImages.value.length < imageDisplayLimit.value) {
            const currentCategoryTitle = categoryQueue.value.shift()!; // Dequeue

            if (visitedCategories.value.has(currentCategoryTitle)) {
                console.log(`Skipping already visited category from queue: ${currentCategoryTitle}`);
                continue;
            }
            visitedCategories.value.add(currentCategoryTitle);
            console.log(`Processing category: ${currentCategoryTitle}`);


            const apiCategoryTitle = currentCategoryTitle.startsWith('Category:') ? currentCategoryTitle : `Category:${currentCategoryTitle}`;
            const commonsApiUrl = `https://commons.wikimedia.org/w/api.php?action=query&format=json&formatversion=2&list=categorymembers&cmtitle=${encodeURIComponent(apiCategoryTitle)}&cmtype=file|subcat&cmlimit=500&origin=*`;

            // Fetch members for the current category
            try {
                const response = await fetch(commonsApiUrl);
                // Basic error check
                 if (!response.ok) {
                    if (response.status === 404) {
                       console.warn(`Category not found on Commons: ${apiCategoryTitle}`);
                       continue; // Skip this category
                    }
                    throw new Error(`Commons API error! status: ${response.status} for ${apiCategoryTitle}`);
                 }
                const data = await response.json();

                // Warnings check
                if (data.warnings) {
                    console.warn('Commons API warnings:', data.warnings);
                }

                // Process members
                if (data.query?.categorymembers) {
                    const members = data.query.categorymembers as CommonsMember[];
                    for (const member of members) {
                        // Check limit *before* processing member
                        if (commonsImages.value.length >= imageDisplayLimit.value) {
                            console.log(`Limit (${imageDisplayLimit.value}) reached while processing members of ${currentCategoryTitle}.`);
                            // Re-add the current category to the front if it wasn't fully processed? No, visitedCategories handles this.
                            // The loop condition will break us out.
                            break; // Stop processing members for this category
                        }

                        if (member.ns === 6) { // File
                            const fileExtension = getFileExtension(member.title);
                            if (IMAGE_FILE_EXTENSIONS.includes(fileExtension) && !commonsImages.value.some(img => img.title === member.title)) {
                                commonsImages.value.push({
                                    title: member.title,
                                    thumbnailUrl: `https://commons.wikimedia.org/wiki/Special:FilePath/${encodeURIComponent(member.title)}?width=200`
                                });
                                await nextTick();
                            }
                        } else if (member.ns === 14) { // Subcategory
                            // Enqueue only if not already visited (minor optimization)
                            if (!visitedCategories.value.has(member.title)) {
                                categoryQueue.value.push(member.title);
                            }
                        }
                    } // End member loop
                } else if (data.error) {
                     console.error(`Commons API error for ${apiCategoryTitle}: ${data.error.code} - ${data.error.info}`);
                     // Decide if we should stop everything or just skip this category
                     continue; // Skip this category on API error
                } else {
                     console.log(`No members found or unexpected response for ${apiCategoryTitle}`);
                }

            } catch (fetchErr: any) {
                console.error(`Failed to fetch or process members for ${currentCategoryTitle}:`, fetchErr);
                // Decide if we should stop everything or just skip this category
                commonsError.value = commonsError.value || fetchErr.message || 'Unknown error fetching category members.';
                continue; // Skip this category on fetch error
            }

            // Check limit again after processing all members of a category
             if (commonsImages.value.length >= imageDisplayLimit.value) {
                 console.log(`Limit (${imageDisplayLimit.value}) reached after processing ${currentCategoryTitle}.`);
                 break; // Stop processing queue
             }

        } // End while loop (queue processing)

        // Determine if more can be fetched
        if (commonsImages.value.length >= imageDisplayLimit.value && categoryQueue.value.length > 0) {
            // We hit the limit, and there are still categories waiting in the queue
            canFetchMore.value = true;
            console.log(`Fetch cycle paused: Limit (${imageDisplayLimit.value}) reached. Queue size: ${categoryQueue.value.length}. Total images: ${commonsImages.value.length}`);
        } else if (categoryQueue.value.length === 0) {
             // Queue is empty, we've explored everything reachable
             canFetchMore.value = false;
             console.log(`Fetch cycle completed: Queue empty. Total images: ${commonsImages.value.length}`);
        } else {
             // Queue has items, but we didn't hit the limit (shouldn't happen with current logic, but safe fallback)
             canFetchMore.value = false;
             console.log(`Fetch cycle completed: Queue has items (${categoryQueue.value.length}) but limit (${imageDisplayLimit.value}) not reached. Total images: ${commonsImages.value.length}`);
        }

    } catch (e: any) {
        console.error('Error during iterative fetch cycle execution:', e);
        commonsError.value = commonsError.value || e.message || 'Unknown error during fetch cycle.';
        canFetchMore.value = false; // Stop on error
    } finally {
        isFetchingMore.value = false;
        console.log(`Fetch cycle finally block. isFetchingMore: ${isFetchingMore.value}, canFetchMore: ${canFetchMore.value}`);
    }
};


// Function to set up and start the *initial* fetch
const startInitialFetch = () => {
    if (!itemData.value?.category) {
        // ... (reset state as before) ...
        commonsImages.value = [];
        commonsError.value = null;
        canFetchMore.value = false;
        isFetchingMore.value = false;
        initialCategoryTitle.value = null;
        visitedCategories.value.clear();
        categoryQueue.value = []; // Clear queue
        selectedImageTitles.value.clear();
        return;
    }
    console.log(`Starting initial fetch for category: ${itemData.value.category}`);

    commonsLoading.value = true;
    commonsError.value = null;
    commonsImages.value = [];
    visitedCategories.value.clear();
    categoryQueue.value = []; // Clear queue
    imageDisplayLimit.value = 100;
    canFetchMore.value = false;
    isFetchingMore.value = false;
    selectedImageTitles.value.clear();

    initialCategoryTitle.value = itemData.value.category.startsWith('Category:')
        ? itemData.value.category
        : `Category:${itemData.value.category}`;

    // No need to add to queue here, runFetchCycle will do it if queue is empty

    runFetchCycle().finally(() => {
        commonsLoading.value = false;
    });
};

// --- End Refactored Fetching Logic ---

// --- Infinite Scroll Handling ---
const loadMoreImages = () => {
    if (!canFetchMore.value || isFetchingMore.value) {
        return;
    }
    console.log('Scroll triggered: Loading more images...');
    imageDisplayLimit.value += imagesToFetchIncrement;
    runFetchCycle(); // Continue fetching with the new limit (will process remaining queue)
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
    categoryQueue.value = []; // Clear queue
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
