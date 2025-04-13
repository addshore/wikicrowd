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
            <v-card>
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
        <div v-else class="mt-4">
          No images found in this category on Commons, or the category does not exist.
        </div>
      </div>

      <v-card v-if="itemData" class="mt-4" hover>
        <v-card-title>Debug Info</v-card-title>
        <v-card-text>
          <p v-if="itemData.exclude"><strong>Exclude Categories:</strong> {{ itemData.exclude.join(', ') }}</p>
          <p v-if="itemData.excludeRegex"><strong>Exclude Regex:</strong> {{ itemData.excludeRegex }}</p>
        </v-card-text>
      </v-card>
      <div v-else class="mt-4">
        Item configuration not found in {{ category }}.yaml for {{ depictsId }}.
      </div>

    </v-container>
  </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted, watch } from 'vue'; // Added watch
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
const commonsLoading = ref(false);
const commonsError = ref<string | null>(null);
const commonsImages = ref<CommonsImage[]>([]);

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

// Function to fetch images from Wikimedia Commons category, including subcategories recursively
const fetchCommonsCategoryImages = async () => {
  if (!itemData.value?.category) {
    console.log('No Commons category specified in item data.');
    commonsImages.value = []; // Clear any previous images
    return;
  }
  console.log(`Fetching Commons images for category: ${itemData.value.category}`);

  commonsLoading.value = true;
  commonsError.value = null;
  commonsImages.value = [];
  const visitedCategories = new Set<string>(); // Track visited categories to avoid loops
  const maxImagesToShowInitially = 100; // Define the limit

  const fetchCategory = async (categoryTitle: string) => {
    // PAUSE CHECK: Stop fetching if we already have enough images
    if (commonsImages.value.length >= maxImagesToShowInitially) {
      console.log(`Pausing fetch for ${categoryTitle}, already have ${commonsImages.value.length} images.`);
      return;
    }

    if (visitedCategories.has(categoryTitle)) {
      console.log(`Skipping already visited category: ${categoryTitle}`);
      return;
    }
    visitedCategories.add(categoryTitle);

    // Ensure the category title starts with "Category:" for the API call
    const apiCategoryTitle = categoryTitle.startsWith('Category:') ? categoryTitle : `Category:${categoryTitle}`;

    const commonsApiUrl = `https://commons.wikimedia.org/w/api.php?action=query&format=json&formatversion=2&list=categorymembers&cmtitle=${encodeURIComponent(apiCategoryTitle)}&cmtype=file|subcat&cmlimit=500&origin=*`; // Increased limit slightly

    console.log(`Fetching Commons category members from: ${commonsApiUrl}`);

    try {
      const response = await fetch(commonsApiUrl);
      if (!response.ok) {
        // Check for specific errors like missing category
        if (response.status === 404) {
           console.warn(`Category not found on Commons: ${apiCategoryTitle}`);
           return; // Stop recursion for this branch if category doesn't exist
        }
        throw new Error(`Commons API error! status: ${response.status}`);
      }
      const data = await response.json();

      // Check for API warnings (e.g., category redirected)
      if (data.warnings) {
          console.warn('Commons API warnings:', data.warnings);
      }

      if (data.query?.categorymembers) {
        const members = data.query.categorymembers as CommonsMember[];

        for (const member of members) {
           // PAUSE CHECK within loop: If we hit the limit while processing members, stop processing this category's members and prevent further recursion from here.
           if (commonsImages.value.length >= maxImagesToShowInitially) {
               console.log(`Reached image limit (${maxImagesToShowInitially}) while processing ${categoryTitle}.`);
               return; // Stop processing members and prevent further recursion from this call
           }

          // Check namespace to determine type
          if (member.ns === 6) { // ns: 6 is the File namespace
            const fileExtension = getFileExtension(member.title);
            // Check if the extension is allowed AND if not already added
            if (IMAGE_FILE_EXTENSIONS.includes(fileExtension) && !commonsImages.value.some(img => img.title === member.title)) {
                commonsImages.value.push({
                  title: member.title,
                  thumbnailUrl: `https://commons.wikimedia.org/wiki/Special:FilePath/${encodeURIComponent(member.title)}?width=200`
                });
            } else if (!IMAGE_FILE_EXTENSIONS.includes(fileExtension)) {
                console.log(`Skipping file with unsupported extension (${fileExtension}): ${member.title}`);
            }
          } else if (member.ns === 14) { // ns: 14 is the Category namespace
            // Introduce a small delay before fetching the next subcategory
            // This allows the UI to update with images found so far.
            await sleep(20); // Delay for 20 milliseconds

            // Recursively fetch subcategory (check pause condition again inside the recursive call)
            await fetchCategory(member.title);
          }
        }

        // Handle continuation if necessary (though less likely with increased limit and recursive calls)
        if (data.continue?.cmcontinue) {
            console.log("Need to handle continuation for category members - skipping for now in this recursive approach.");
            // Note: Proper continuation within recursion is complex.
            // A non-recursive, queue-based approach might be better for deep hierarchies.
        }

      } else if (data.error) {
        // Log specific API errors
        console.error(`Commons API error for ${apiCategoryTitle}: ${data.error.code} - ${data.error.info}`);
        // Optionally set commonsError, but maybe allow partial results
        // commonsError.value = `API Error: ${data.error.info}`;
      } else if (!data.query) {
          console.warn(`Category likely does not exist or is empty: ${apiCategoryTitle}`, data);
      } else {
        console.log(`No category members found for ${apiCategoryTitle} or unexpected API response:`, data);
      }
    } catch (e: any) {
      console.error(`Failed to fetch Commons category members for ${apiCategoryTitle}:`, e);
      // Set top-level error only if it hasn't been set yet
      if (!commonsError.value) {
          commonsError.value = e.message || 'Unknown error loading Commons images.';
      }
    }
  };

  try {
    // Start fetching with the category from itemData, ensuring it has the "Category:" prefix
    const initialCategory = itemData.value.category.startsWith('Category:')
        ? itemData.value.category
        : `Category:${itemData.value.category}`;
    await fetchCategory(initialCategory);
    // Log final status, including if paused
    if (commonsImages.value.length >= maxImagesToShowInitially) {
        console.log(`Finished initial fetch phase. Paused fetching more as limit of ${maxImagesToShowInitially} images was reached. Total found: ${commonsImages.value.length}`);
    } else {
        console.log(`Finished fetching Commons images. Total found: ${commonsImages.value.length}`);
    }
  } catch (e: any) {
    console.error('Error during recursive category fetching:', e);
    commonsError.value = e.message || 'Unknown error during recursive fetching.';
  } finally {
    commonsLoading.value = false; // Set loading to false after the initial fetch attempt completes or pauses
  }
};

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
});

// Watch for itemData to be loaded, then fetch Commons images if category exists
watch(itemData, (newItemData) => {
  if (newItemData?.category) {
    fetchCommonsCategoryImages();
  } else {
    // Clear commons data if itemData is cleared or lacks a category
    commonsImages.value = [];
    commonsError.value = null;
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
</style>
