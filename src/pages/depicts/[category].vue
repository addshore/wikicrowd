<template>
  <div>
    <!-- Always render the container if the path matches -->
    <v-container v-if="route.path.startsWith('/depicts/') && !route.path.slice(9).includes('/')" class="mt-4">
      <NavBar />
      <h1>Depicts: {{ category }}</h1>
      <v-btn @click="goBackToCategories" prepend-icon="mdi-arrow-left" class="mb-4">
        Back to Categories
      </v-btn>

      <!-- Move conditional logic inside the container -->
      <div v-if="loading" class="mt-4">
        Loading {{ category }} data...
      </div>
      <div v-else-if="error" class="mt-4 error-message">
        Error loading data: {{ error }}
      </div>
      <v-row v-else-if="Array.isArray(yamlData.value) && yamlData.value.length > 0">
        <v-col
          v-for="item in yamlData.value"
          :key="item.depictsId || item.name"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <v-card
            class="mx-auto d-flex flex-column"
            max-width="400"
            min-height="200"
            hover
            @click="navigateToItem(item)"
          >
            <!-- Image Display -->
            <v-img
              v-if="item.imageUrl"
              :src="item.imageUrl"
              height="300px"
              cover
              class="text-white"
            >
              <template v-slot:placeholder>
                <v-row class="fill-height ma-0" align="center" justify="center">
                  <v-progress-circular indeterminate color="grey-lighten-5"></v-progress-circular>
                </v-row>
              </template>
              <template v-slot:error>
                 <v-row class="fill-height ma-0" align="center" justify="center" style="background-color: rgba(0,0,0,0.1);">
                   <small>No image</small>
                 </v-row>
              </template>
            </v-img>
             <div v-else class="d-flex align-center justify-center" style="height: 150px; background-color: #eee;">
                <small>No P18 image</small>
             </div>
            <v-card-item>
              <v-card-title>{{ item.name }}</v-card-title>
              <v-card-subtitle v-if="item.depictsId">
                ID:
                <a :href="`https://www.wikidata.org/wiki/${item.depictsId}`" target="_blank" rel="noopener noreferrer" @click.stop>
                  {{ item.depictsId }}
                </a>
              </v-card-subtitle>
            </v-card-item>
            <v-card-text class="flex-grow-1"> <!-- Allow text to grow -->
              Click to start tagging images of {{ item.name }} with depicts (or not).
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
      <div v-else class="mt-4">
        <!-- Display this only if not loading, no error, and data is empty or invalid -->
        No items found for this category or data is not in the expected format.
        <pre v-if="yamlData.value">{{ JSON.stringify(yamlData.value, null, 2) }}</pre>
      </div>
    </v-container>
    <!-- Keep router-view outside the conditional container -->
    <router-view />
  </div>
</template>

<script lang="ts" setup>
import { ref, watch, onMounted, reactive } from 'vue'; // Import reactive
import { useRoute, useRouter } from 'vue-router';
import * as jsyaml from 'js-yaml';
import NavBar from '../../components/NavBar.vue'; // Adjust path as needed
import Wikibase from '../../utils/wikibase'; // Import Wikibase

// Define the structure of an item in the YAML data, adding imageUrl
interface DepictsItem {
  depictsId: string;
  name: string;
  limit?: number;
  category?: string;
  exclude?: string[];
  excludeRegex?: string;
  imageUrl?: string; // Add optional imageUrl property
}

const route = useRoute();
const router = useRouter(); // Initialize router
const category = ref(route.params.category as string);
// Use reactive for yamlData to better handle updates to nested properties like imageUrl
const yamlData = reactive<{ value: DepictsItem[] | null }>({ value: null });
const loading = ref(false);
const error = ref<string | null>(null);

// Instantiate Wikibase - assuming Wikidata.org
const wikibase = new Wikibase('https://www.wikidata.org/w/');

const fetchAndParseYaml = async (categoryName: string) => {
  loading.value = true;
  error.value = null;
  yamlData.value = null; // Reset reactive data
  // Construct URL relative to the public folder
  const yamlUrl = `/depicts/${categoryName}.yaml`;
  console.log(`Fetching YAML from: ${yamlUrl}`);

  try {
    const response = await fetch(yamlUrl);
    if (!response.ok) {
      // Handle 404 specifically
      if (response.status === 404) {
        throw new Error(`YAML file not found at ${yamlUrl}`);
      }
      throw new Error(`HTTP error! status: ${response.status} ${response.statusText}`);
    }
    const yamlText = await response.text();
    const parsedData = jsyaml.load(yamlText);

    // Validate that the parsed data is an array
    if (Array.isArray(parsedData)) {
      // Assign initial data
      yamlData.value = parsedData as DepictsItem[];
      console.log('YAML data loaded:', yamlData.value);

      // Asynchronously fetch image URLs for each item
      yamlData.value.forEach(async (item, index) => {
        if (item.depictsId) {
          try {
            const itemData = await wikibase.loadItemData(item.depictsId);

            // Check if itemData.statements.P18 is set
            if (!itemData.statements || !itemData.statements.P18) {
              console.log(`No P18 image found for ${item.depictsId}`);
              return;
            }
            let imageStatements = itemData.statements.P18;
            let chosenImage = null;
            // First loop through the statements and look for the first preferred image (rank preferred)
            for (const statement of imageStatements) {
              if (statement.rank === 'preferred') {
                chosenImage = statement.value.content;
                break;
              }
            }
            // If no preferred image is found, fall back to the first image
            if (!chosenImage) {
              chosenImage = imageStatements[0].value.content;
            }
            // If still no image found, log and return
            if (!chosenImage) {
              console.log(`No P18 image found for ${item.depictsId}`);
              return;
            }
            // If we have a chosen image, set the imageUrl
            console.log(`Found image for ${item.depictsId}:`, chosenImage);
            // convert from a file name to a full URL
            chosenImage = `https://commons.wikimedia.org/wiki/Special:FilePath/${encodeURIComponent(chosenImage)}`;
            console.log(`Setting imageUrl for ${item.depictsId}:`, chosenImage);
            yamlData.value[index].imageUrl = chosenImage;
          } catch (e) {
            console.error(`Failed to load item data or find image for ${item.depictsId}:`, e);
            // Optionally set a flag or default image URL on error for this item
             if (yamlData.value && yamlData.value[index]) {
                 yamlData.value[index].imageUrl = undefined; // Ensure it's undefined on error
             }
          }
        }
      });

    } else {
      // Handle cases where YAML is valid but not an array
      console.error('Parsed YAML data is not an array:', parsedData);
      throw new Error('YAML data is not in the expected array format.');
    }
  } catch (e: any) {
    console.error('Failed to fetch or parse YAML:', e);
    error.value = e.message || 'Unknown error';
  } finally {
    loading.value = false;
  }
};

// Fetch data when component mounts
onMounted(() => {
  fetchAndParseYaml(category.value);
});

// Watch for changes in route parameter and refetch data
watch(
  () => route.params.category,
  (newCategory) => {
    if (newCategory && typeof newCategory === 'string' && newCategory !== category.value) {
      category.value = newCategory;
      fetchAndParseYaml(newCategory);
    }
  },
  { immediate: true } // Fetch data immediately when the component is created
);

// Function to navigate back to the main depicts page
const goBackToCategories = () => {
  router.push('/depicts');
};

// Function to navigate to the specific item page
const navigateToItem = (item: DepictsItem) => {
  if (!item.depictsId) {
    console.error('Item does not have a depictsId:', item);
    // Optionally show an error to the user
    return;
  }
  console.log(`Navigating to item: ${item.name} (ID: ${item.depictsId})`);
  router.push({
    name: '/depicts/[category]/[depictsId]', // Route name generated by unplugin-vue-router
    params: { category: category.value, depictsId: item.depictsId }
  });
};

</script>

<style scoped>
.yaml-output {
  background-color: #f5f5f5;
  border: 1px solid #ccc;
  padding: 10px;
  white-space: pre-wrap; /* Ensure long lines wrap */
  word-wrap: break-word; /* Break words if necessary */
  max-height: 500px; /* Optional: limit height */
  overflow-y: auto; /* Optional: add scrollbar if content exceeds max height */
  color: #333; /* Ensure text is readable */
}

.error-message {
  color: red;
  font-weight: bold;
}

.mb-4 {
  margin-bottom: 1rem; /* Add some space below the back button */
}

a {
  text-decoration: none;
  color: inherit; /* Optional: Make link color same as surrounding text */
}
a:hover {
  text-decoration: underline;
}

/* Ensure cards have consistent height and content alignment */
.v-card {
  display: flex;
  flex-direction: column;
  height: 100%; /* Make card take full height of its grid cell */
}

.v-card-text {
  flex-grow: 1; /* Allow text area to fill remaining space */
}
</style>
