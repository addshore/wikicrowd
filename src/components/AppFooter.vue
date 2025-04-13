<template>
  <v-footer height="40" app>
    <a
      v-for="item in items"
      :key="item.title"
      :href="item.href"
      :title="item.title"
      class="d-inline-block mx-2 social-link"
      rel="noopener noreferrer"
      target="_blank"
    >
      <v-icon
        :icon="item.icon"
        :size="item.icon === '$vuetify' ? 24 : 16"
      />
    </a>

    <div
      class="text-caption text-disabled"
      style="position: absolute; right: 16px;"
    >
      &copy; 2025<span v-if="new Date().getFullYear() !== 2025">-{{ new Date().getFullYear() }}</span> <span class="d-none d-sm-inline-block">Addshore</span>
      —
      Some License
    </div>
  </v-footer>
</template>

<script setup lang="ts">
  const items = [
    {
      title: 'Website',
      icon: `mdi-wordpress`,
      href: 'https://addshore.com',
    },
    {
      title: 'GitHub',
      icon: `mdi-github`,
      href: 'https://github.com/addshore',
    },
    {
      title: 'Wikimedia',
      icon: `mdi-wikipedia`,
      href: 'https://meta.wikimedia.org/wiki/User:Addshore',
    },
    {
      title: 'Mastodon',
      icon: `mdi-mastodon`,
      href: 'https://mas.to/@addshore',
    },
  ]
</script>

<style scoped lang="sass">
  .social-link :deep(.v-icon)
    color: rgba(var(--v-theme-on-background), var(--v-disabled-opacity))
    text-decoration: none
    transition: .2s ease-in-out

    &:hover
      color: rgba(25, 118, 210, 1)
</style>
