<template>
  <v-app>
    <!-- You might have other layout components like v-app-bar, v-navigation-drawer, etc. -->
    <v-main>
      <!-- Ensure router-view is present to render the page content -->
      <router-view />
    </v-main>
    <!-- You might have other layout components like v-footer -->
  </v-app>
</template>

<script setup lang="ts">
  // Layout-specific script setup if needed
</script>

<style scoped>
  /* Layout-specific styles */
</style>
