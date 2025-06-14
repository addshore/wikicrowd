<template>
  <div
    v-if="isDragging && width > 0 && height > 0"
    class="drag-selection-rectangle"
    :style="rectangleStyle"
  ></div>
</template>

<script>
export default {
  name: 'DragSelectionOverlay',
  props: {
    isDragging: {
      type: Boolean,
      required: true
    },
    x: {
      type: Number,
      required: true
    },
    y: {
      type: Number,
      required: true
    },
    width: {
      type: Number,
      required: true
    },
    height: {
      type: Number,
      required: true
    },
    answerModeStyles: {
      type: Object,
      required: true
    },
    currentAnswerMode: {
      type: String,
      required: true
    }
  },
  computed: {
    rectangleStyle() {
      const baseStyle = {
        left: this.x + 'px',
        top: this.y + 'px',
        width: this.width + 'px',
        height: this.height + 'px',
      };
      
      // Default to 'skip' style (blue) if currentAnswerMode is not a key in answerModeStyles
      const currentStyleKey = this.currentAnswerMode && this.answerModeStyles[this.currentAnswerMode] 
        ? this.currentAnswerMode 
        : 'skip';
      const modeStyle = this.answerModeStyles[currentStyleKey];

      return {
        ...baseStyle,
        backgroundColor: modeStyle.lightColor,
        borderColor: modeStyle.color,
        borderWidth: '2px',
        borderStyle: 'dashed',
      };
    }
  }
};
</script>

<style scoped>
.drag-selection-rectangle {
  position: fixed;
  pointer-events: none;
  z-index: 1000;
  box-sizing: border-box;
}
</style>
