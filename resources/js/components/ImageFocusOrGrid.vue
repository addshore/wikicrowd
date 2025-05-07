<template>
  <div>
    <ImageFocus v-if="!gridMode" @open-grid="gridMode = true" />
    <GridMode v-else @disable-grid="gridMode = false" />
  </div>
</template>

<script>
import ImageFocus from './ImageFocus.vue';
import GridMode from './GridMode.vue';

export default {
  name: 'ImageFocusOrGrid',
  components: { ImageFocus, GridMode },
  data() {
    return {
      gridMode: false
    };
  }
};
</script>
