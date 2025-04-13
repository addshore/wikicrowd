<template>
  <div>
    <v-container v-if="route.path === '/depicts'">
      <NavBar />
      <h2>Depicts Categories</h2>
      <v-row>
        <v-col
          v-for="category in depictsCategories"
          :key="category.name"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <v-card
            class="mx-auto"
            max-width="400"
            hover
            @click="navigateToCategory(category.name)"
          >
            <v-card-item>
              <v-card-title>{{ category.name }}</v-card-title>
            </v-card-item>
            <v-card-text>
              <!-- Placeholder for description or image -->
              Explore items related to {{ category.name.toLowerCase() }}.
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
    <!-- Router view is always rendered for nested routes -->
    <router-view />
  </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router'; // Import useRoute
import { useTheme } from 'vuetify';
import { getUser, removeUser } from '../utils/storage';
import { generateCodeVerifier, generateCodeChallenge, redirectToAuth } from '../utils/oauth';
import { debounce } from 'lodash';
import NavBar from '../components/NavBar.vue';
import Wikibase from '../utils/wikibase';

const router = useRouter(); // Initialize router
const route = useRoute(); // Initialize route to access current route info

// Define depicts categories
const depictsCategories = ref([
  { name: 'People' },
  { name: 'Places' },
  { name: 'Animals' },
  { name: 'Sports' },
  { name: 'Transport' },
]);

// Placeholder function for navigation or action when a category is clicked
function navigateToCategory(categoryName: string) {
  const categoryParam = categoryName.toLowerCase();
  console.log(`Navigating to category route: /depicts/${categoryParam}`);
  // Use router to navigate using the path
  // NOTE: Since vue-router/auto generates routes based on file structure,
  // the path `/depicts/people` will match `/depicts/people.vue`
  // and `/depicts/artworks` will match `/depicts/[category].vue`
  // router.push({ path: `/depicts/${categoryParam}` });
  // Previous attempt using name:
  router.push({ name: '/depicts/[category]', params: { category: categoryParam } });
}

</script>

<style scoped>
.outlined-row {
  border: 1px solid #494949;
}

.no-underline {
  text-decoration: none;
}
</style>