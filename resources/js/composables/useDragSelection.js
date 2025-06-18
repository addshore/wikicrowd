import { ref, reactive } from 'vue';

/**
 * Composable for handling drag selection functionality (both mouse and touch)
 * @param {Object} config - Configuration object
 * @param {Object} config.images - Reactive reference to images array
 * @param {Object} config.answered - Reactive reference to answered items set
 * @param {Object} config.answerModeStyles - Styles for different answer modes
 * @param {Function} config.onSelectionComplete - Callback when selection is complete
 * @returns {Object} Composable object with drag selection methods and state
 */
export function useDragSelection({ images, answered, answerModeStyles, onSelectionComplete }) {
  // Drag selection state
  const isDragging = ref(false);
  const dragStartCoordinates = ref({ x: 0, y: 0 });
  const multiSelectedImageIds = ref(new Set());
  const dragSelectionRect = ref({ x: 0, y: 0, width: 0, height: 0 });

  // Mobile long-press drag state
  const longPressTimer = ref(null);
  const touchStartCoordinates = ref({ x: 0, y: 0 });
  const isLongPressActive = ref(false);
  const maxTouchMoveThreshold = ref(10); // Pixels

  /**
   * Apply drag highlight styling to an image element
   * @param {string} imageId - The image ID to highlight
   */
  const applyDragHighlight = (imageId) => {
    const element = document.querySelector(`[data-image-id="${imageId}"]`);
    if (element) {
      // Remove any existing drag highlight classes
      Object.values(answerModeStyles).forEach(style => {
        element.classList.remove(`is-drag-highlighted-${style.classSuffix}`);
      });
      
      // Add the new highlight class based on current answer mode
      const currentStyle = answerModeStyles.yes; // Default to 'yes' style for highlighting
      element.classList.add('is-drag-highlighted');
      if (currentStyle?.classSuffix) {
        element.classList.add(`is-drag-highlighted-${currentStyle.classSuffix}`);
      }
    }
  };

  /**
   * Remove drag highlight styling from an image element
   * @param {string} imageId - The image ID to remove highlight from
   */
  const removeDragHighlight = (imageId) => {
    const element = document.querySelector(`[data-image-id="${imageId}"]`);
    if (element) {
      element.classList.remove('is-drag-highlighted');
      Object.values(answerModeStyles).forEach(style => {
        element.classList.remove(`is-drag-highlighted-${style.classSuffix}`);
      });
    }
  };

  /**
   * Handle mouse down event to start drag selection
   * @param {Object} image - The image object
   * @param {MouseEvent} event - The mouse event
   */
  const handleImageMouseDown = (image, event) => {
    // Only proceed if Shift key is pressed
    if (!event.shiftKey) return;

    isDragging.value = true;
    dragStartCoordinates.value = { x: event.clientX, y: event.clientY };

    // Clear any existing multi-selection and highlights
    multiSelectedImageIds.value.clear();
    images.value.forEach(img => removeDragHighlight(img.id));

    // If the clicked image is not already answered, add it to selection
    if (!answered.value.has(image.id)) {
      multiSelectedImageIds.value.add(image.id);
      applyDragHighlight(image.id);
    }

    // Initialize drag selection rectangle
    dragSelectionRect.value = {
      x: event.clientX,
      y: event.clientY,
      width: 0,
      height: 0,
    };
  };

  /**
   * Handle mouse move event during drag selection
   * @param {MouseEvent} event - The mouse event
   */
  const handleMouseMove = (event) => {
    if (!isDragging.value) return;

    const currentX = event.clientX;
    const currentY = event.clientY;

    // Update selection rectangle
    dragSelectionRect.value = {
      x: Math.min(dragStartCoordinates.value.x, currentX),
      y: Math.min(dragStartCoordinates.value.y, currentY),
      width: Math.abs(currentX - dragStartCoordinates.value.x),
      height: Math.abs(currentY - dragStartCoordinates.value.y),
    };

    // Check for intersection with images
    images.value.forEach(img => {
      const element = document.querySelector(`[data-image-id="${img.id}"]`);
      if (!element) return;

      const rect = element.getBoundingClientRect();
      const selectionRect = dragSelectionRect.value;
      
      const intersects = rect.left < selectionRect.x + selectionRect.width &&
                         rect.left + rect.width > selectionRect.x &&
                         rect.top < selectionRect.y + selectionRect.height &&
                         rect.top + rect.height > selectionRect.y;

      if (intersects && !answered.value.has(img.id)) {
        if (!multiSelectedImageIds.value.has(img.id)) {
          multiSelectedImageIds.value.add(img.id);
          applyDragHighlight(img.id);
        }
      } else {
        if (multiSelectedImageIds.value.has(img.id)) {
          multiSelectedImageIds.value.delete(img.id);
          removeDragHighlight(img.id);
        }
      }
    });
  };

  /**
   * Handle mouse up event to complete drag selection
   * @param {MouseEvent} event - The mouse event
   */
  const handleMouseUp = (event) => {
    if (!isDragging.value) return;

    const selectedItems = Array.from(multiSelectedImageIds.value);
    
    // Call the selection complete callback with selected items
    if (onSelectionComplete && selectedItems.length > 0) {
      onSelectionComplete(selectedItems);
    }

    // Cleanup highlights
    selectedItems.forEach(imageId => {
      removeDragHighlight(imageId);
    });

    // Reset drag state
    resetDragState();
  };

  /**
   * Handle touch start event for mobile drag selection
   * @param {Object} image - The image object
   * @param {TouchEvent} event - The touch event
   */
  const handleTouchStart = (image, event) => {
    // Ignore multi-touch gestures
    if (event.touches.length > 1) {
      clearTimeout(longPressTimer.value);
      return;
    }

    clearTimeout(longPressTimer.value);

    // Store initial touch coordinates
    touchStartCoordinates.value = { 
      x: event.touches[0].clientX, 
      y: event.touches[0].clientY 
    };
    isLongPressActive.value = false;

    // Start long press timer
    longPressTimer.value = setTimeout(() => {
      isLongPressActive.value = true;
      // Only set isDragging to true after long press timer completes
      isDragging.value = true;

      dragStartCoordinates.value = { 
        x: touchStartCoordinates.value.x, 
        y: touchStartCoordinates.value.y 
      };

      // Clear previous selection
      multiSelectedImageIds.value.clear();
      images.value.forEach(img => removeDragHighlight(img.id));

      // Add touched image to selection if not answered
      if (!answered.value.has(image.id)) {
        multiSelectedImageIds.value.add(image.id);
        applyDragHighlight(image.id);
      }

      // Initialize selection rectangle
      dragSelectionRect.value = {
        x: dragStartCoordinates.value.x,
        y: dragStartCoordinates.value.y,
        width: 0,
        height: 0,
      };

      // Optional haptic feedback
      if (navigator.vibrate) {
        navigator.vibrate(50);
      }
    }, 500); // 500ms for long press
  };

  /**
   * Handle touch move event during drag selection
   * @param {TouchEvent} event - The touch event
   */
  const handleTouchMove = (event) => {
    if (event.touches.length === 0) return;
    const touch = event.touches[0];

    // If long press hasn't been activated, check for scroll cancellation
    if (!isLongPressActive.value) {
      const deltaX = Math.abs(touch.clientX - touchStartCoordinates.value.x);
      const deltaY = Math.abs(touch.clientY - touchStartCoordinates.value.y);
      
      if (deltaX > maxTouchMoveThreshold.value || deltaY > maxTouchMoveThreshold.value) {
        clearTimeout(longPressTimer.value);
      }
      // If long press is not active (either timer running or cleared by movement),
      // do not proceed with drag logic. This allows scrolling.
      return;
    }

    // If dragging hasn't started (e.g. long press timer didn't fire yet, though isLongPressActive might be true if timer fired but isDragging was deferred)
    // This check is important.
    if (!isDragging.value) return;

    // Update selection rectangle (similar to mouse move)
    dragSelectionRect.value = {
      x: Math.min(dragStartCoordinates.value.x, touch.clientX),
      y: Math.min(dragStartCoordinates.value.y, touch.clientY),
      width: Math.abs(touch.clientX - dragStartCoordinates.value.x),
      height: Math.abs(touch.clientY - dragStartCoordinates.value.y),
    };

    // Check for intersections with images (same logic as mouse move)
    images.value.forEach(img => {
      const element = document.querySelector(`[data-image-id="${img.id}"]`);
      if (!element) return;

      const rect = element.getBoundingClientRect();
      const selectionRect = dragSelectionRect.value;
      
      const intersects = rect.left < selectionRect.x + selectionRect.width &&
                         rect.left + rect.width > selectionRect.x &&
                         rect.top < selectionRect.y + selectionRect.height &&
                         rect.top + rect.height > selectionRect.y;

      if (intersects && !answered.value.has(img.id)) {
        if (!multiSelectedImageIds.value.has(img.id)) {
          multiSelectedImageIds.value.add(img.id);
          applyDragHighlight(img.id);
        }
      } else {
        if (multiSelectedImageIds.value.has(img.id)) {
          multiSelectedImageIds.value.delete(img.id);
          removeDragHighlight(img.id);
        }
      }
    });
  };

  /**
   * Handle touch end event to complete touch drag selection
   * @param {TouchEvent} event - The touch event
   */
  const handleTouchEnd = (event) => {
    clearTimeout(longPressTimer.value);

    // If drag was active due to long press
    if (isDragging.value && isLongPressActive.value) {
      event.preventDefault(); // Prevent click event after drag

      const selectedItems = Array.from(multiSelectedImageIds.value);
      
      // Call selection complete callback
      if (onSelectionComplete && selectedItems.length > 0) {
        onSelectionComplete(selectedItems);
      }

      // Cleanup highlights
      selectedItems.forEach(imageId => {
        removeDragHighlight(imageId);
      });
    }

    // Reset all drag states
    resetDragState();
  };

  /**
   * Reset all drag-related state
   */
  const resetDragState = () => {
    isDragging.value = false;
    isLongPressActive.value = false;
    multiSelectedImageIds.value.clear();
    dragSelectionRect.value = { x: 0, y: 0, width: 0, height: 0 };
    
    // Ensure any remaining highlights are cleared
    images.value.forEach(img => {
      const elem = document.querySelector(`[data-image-id="${img.id}"]`);
      if (elem?.classList.contains('is-drag-highlighted')) {
        removeDragHighlight(img.id);
      }
    });
  };

  return {
    // State
    isDragging,
    dragSelectionRect,
    multiSelectedImageIds,
    isLongPressActive,
    
    // Methods
    handleImageMouseDown,
    handleMouseMove,
    handleMouseUp,
    handleTouchStart,
    handleTouchMove,
    handleTouchEnd,
    applyDragHighlight,
    removeDragHighlight,
    resetDragState
  };
}
