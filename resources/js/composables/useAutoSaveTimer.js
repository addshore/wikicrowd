import { reactive, ref } from 'vue';

/**
 * Composable for managing auto-save timers and countdown functionality
 * @param {Object} config - Configuration object
 * @param {Object} config.autoSaveDelay - Reactive reference to auto-save delay in seconds
 * @param {Function} config.onTimerExpired - Callback when timer expires for an item
 * @returns {Object} Composable object with timer management methods
 */
export function useAutoSaveTimer({ autoSaveDelay, onTimerExpired }) {
  const timers = reactive(new Map()); // Main auto-save timers
  const countdownTimers = reactive(new Map()); // Visual countdown timers (seconds remaining)
  const countdownIntervals = reactive(new Map()); // Interval IDs for countdown updates

  /**
   * Start auto-save timer for a specific item
   * @param {string} itemId - Unique identifier for the item
   * @param {string} mode - The mode/action to be saved
   */
  const startTimer = (itemId, mode) => {
    // Clear any existing timers for this item
    clearTimer(itemId);

    // Set up the main auto-save timer
    const autoSaveTimer = setTimeout(() => {
      // Timer expired, execute callback and cleanup
      if (onTimerExpired) {
        onTimerExpired(itemId, mode);
      }
      
      // Clear from our tracking
      timers.delete(itemId);
      countdownTimers.delete(itemId);
    }, autoSaveDelay.value * 1000);

    timers.set(itemId, autoSaveTimer);

    // Start the visual countdown
    countdownTimers.set(itemId, autoSaveDelay.value);
    
    const intervalId = setInterval(() => {
      if (countdownTimers.has(itemId)) {
        const currentTime = countdownTimers.get(itemId);
        if (currentTime > 1) {
          countdownTimers.set(itemId, currentTime - 1);
        } else {
          // Countdown finished, cleanup interval
          clearInterval(intervalId);
          countdownIntervals.delete(itemId);
        }
      } else {
        // Item removed externally, cleanup interval
        clearInterval(intervalId);
        countdownIntervals.delete(itemId);
      }
    }, 1000);

    countdownIntervals.set(itemId, intervalId);
  };

  /**
   * Clear timer for a specific item
   * @param {string} itemId - Unique identifier for the item
   */
  const clearTimer = (itemId) => {
    // Clear main timer
    if (timers.has(itemId)) {
      clearTimeout(timers.get(itemId));
      timers.delete(itemId);
    }

    // Clear countdown interval
    if (countdownIntervals.has(itemId)) {
      clearInterval(countdownIntervals.get(itemId));
      countdownIntervals.delete(itemId);
    }

    // Clear countdown display
    countdownTimers.delete(itemId);
  };

  /**
   * Clear all active timers
   */
  const clearAllTimers = () => {
    // Clear all main timers
    for (const timer of timers.values()) {
      clearTimeout(timer);
    }
    timers.clear();

    // Clear all countdown intervals
    for (const intervalId of countdownIntervals.values()) {
      clearInterval(intervalId);
    }
    countdownIntervals.clear();

    // Clear all countdown displays
    countdownTimers.clear();
  };

  /**
   * Get the remaining countdown time for an item
   * @param {string} itemId - Unique identifier for the item
   * @returns {number|undefined} Remaining seconds or undefined if no timer
   */
  const getCountdownTime = (itemId) => {
    return countdownTimers.get(itemId);
  };

  /**
   * Check if an item has an active timer
   * @param {string} itemId - Unique identifier for the item
   * @returns {boolean} True if timer is active
   */
  const hasTimer = (itemId) => {
    return timers.has(itemId);
  };

  return {
    timers,
    countdownTimers,
    countdownIntervals,
    startTimer,
    clearTimer,
    clearAllTimers,
    getCountdownTime,
    hasTimer
  };
}
