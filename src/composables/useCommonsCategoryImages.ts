import { ref, nextTick } from 'vue';

// --- Interfaces & Constants ---
export interface CommonsImage {
  title: string;
  thumbnailUrl: string;
}

interface CommonsMember {
  pageid: number;
  ns: number; // Namespace: 6 for File, 14 for Category
  title: string;
}

const IMAGE_FILE_EXTENSIONS = [
  'jpg', 'jpeg', 'png', 'gif', 'svg', 'tiff',
];

// --- Helper ---
const getFileExtension = (filename: string): string => {
  const parts = filename.split('.');
  if (parts.length > 1) {
    return parts[parts.length - 1].toLowerCase();
  }
  return '';
};


export function useCommonsCategoryImages() {
  // --- State ---
  const commonsLoading = ref(false); // For the *initial* load
  const commonsError = ref<string | null>(null);
  const commonsImages = ref<CommonsImage[]>([]);
  const imageDisplayLimit = ref(100);
  const imagesToFetchIncrement = 100;
  const isFetchingMore = ref(false); // True when fetching subsequent batches
  const canFetchMore = ref(false); // True if the last fetch hit the limit, suggesting more might exist
  const visitedCategories = ref(new Set<string>());
  const categoryQueue = ref<string[]>([]); // Queue for categories to process

  // --- Fetching Logic (Iterative Queue-Based) ---
  const runFetchCycle = async () => {
    // Initial queue population is handled by startInitialFetch

    if (categoryQueue.value.length === 0) {
        console.log("Fetch cycle called but queue is empty.");
        isFetchingMore.value = false;
        canFetchMore.value = false; // Nothing left to fetch
        return;
    }

    isFetchingMore.value = true;
    canFetchMore.value = false; // Assume no more until proven otherwise

    console.log(`Running fetch cycle. Queue size: ${categoryQueue.value.length}, Limit: ${imageDisplayLimit.value}`);

    try {
        while (categoryQueue.value.length > 0 && commonsImages.value.length < imageDisplayLimit.value) {
            const currentCategoryTitle = categoryQueue.value.shift()!;

            if (visitedCategories.value.has(currentCategoryTitle)) {
                console.log(`Skipping already visited category from queue: ${currentCategoryTitle}`);
                continue;
            }
            visitedCategories.value.add(currentCategoryTitle);
            console.log(`Processing category: ${currentCategoryTitle}`);

            const apiCategoryTitle = currentCategoryTitle.startsWith('Category:') ? currentCategoryTitle : `Category:${currentCategoryTitle}`;
            const commonsApiUrl = `https://commons.wikimedia.org/w/api.php?action=query&format=json&formatversion=2&list=categorymembers&cmtitle=${encodeURIComponent(apiCategoryTitle)}&cmtype=file|subcat&cmlimit=500&origin=*`;

            try {
                const response = await fetch(commonsApiUrl);
                 if (!response.ok) {
                    if (response.status === 404) {
                       console.warn(`Category not found on Commons: ${apiCategoryTitle}`);
                       continue;
                    }
                    throw new Error(`Commons API error! status: ${response.status} for ${apiCategoryTitle}`);
                 }
                const data = await response.json();

                if (data.warnings) {
                    console.warn('Commons API warnings:', data.warnings);
                }

                if (data.query?.categorymembers) {
                    const members = data.query.categorymembers as CommonsMember[];
                    for (const member of members) {
                        if (commonsImages.value.length >= imageDisplayLimit.value) {
                            console.log(`Limit (${imageDisplayLimit.value}) reached while processing members of ${currentCategoryTitle}.`);
                            break;
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
                            if (!visitedCategories.value.has(member.title)) {
                                categoryQueue.value.push(member.title);
                            }
                        }
                    }
                } else if (data.error) {
                     console.error(`Commons API error for ${apiCategoryTitle}: ${data.error.code} - ${data.error.info}`);
                     continue;
                } else {
                     console.log(`No members found or unexpected response for ${apiCategoryTitle}`);
                }

            } catch (fetchErr: any) {
                console.error(`Failed to fetch or process members for ${currentCategoryTitle}:`, fetchErr);
                commonsError.value = commonsError.value || fetchErr.message || 'Unknown error fetching category members.';
                continue;
            }

             if (commonsImages.value.length >= imageDisplayLimit.value) {
                 console.log(`Limit (${imageDisplayLimit.value}) reached after processing ${currentCategoryTitle}.`);
                 break;
             }

        } // End while loop

        // Determine if more can be fetched
        if (commonsImages.value.length >= imageDisplayLimit.value && categoryQueue.value.length > 0) {
            canFetchMore.value = true;
            console.log(`Fetch cycle paused: Limit (${imageDisplayLimit.value}) reached. Queue size: ${categoryQueue.value.length}. Total images: ${commonsImages.value.length}`);
        } else if (categoryQueue.value.length === 0) {
             canFetchMore.value = false;
             console.log(`Fetch cycle completed: Queue empty. Total images: ${commonsImages.value.length}`);
        } else {
             canFetchMore.value = false;
             console.log(`Fetch cycle completed: Queue has items (${categoryQueue.value.length}) but limit (${imageDisplayLimit.value}) not reached. Total images: ${commonsImages.value.length}`);
        }

    } catch (e: any) {
        console.error('Error during iterative fetch cycle execution:', e);
        commonsError.value = commonsError.value || e.message || 'Unknown error during fetch cycle.';
        canFetchMore.value = false;
    } finally {
        isFetchingMore.value = false;
        console.log(`Fetch cycle finally block. isFetchingMore: ${isFetchingMore.value}, canFetchMore: ${canFetchMore.value}`);
    }
  };

  // --- Control Functions ---
  const startInitialFetch = (initialCategory: string) => {
    console.log(`Starting initial fetch for category: ${initialCategory}`);

    commonsLoading.value = true;
    commonsError.value = null;
    commonsImages.value = [];
    visitedCategories.value.clear();
    categoryQueue.value = []; // Clear queue
    imageDisplayLimit.value = 100;
    canFetchMore.value = false;
    isFetchingMore.value = false;

    const initialCategoryTitle = initialCategory.startsWith('Category:')
        ? initialCategory
        : `Category:${initialCategory}`;

    // Initialize queue if not visited
    if (!visitedCategories.value.has(initialCategoryTitle)) {
        categoryQueue.value.push(initialCategoryTitle);
        console.log(`Initialized queue with: ${initialCategoryTitle}`);
    } else {
         console.log(`Initial category ${initialCategoryTitle} already visited, skipping queue init.`);
    }


    runFetchCycle().finally(() => {
        commonsLoading.value = false;
    });
  };

  const loadMoreImages = () => {
    if (!canFetchMore.value || isFetchingMore.value) {
        return;
    }
    console.log('Scroll triggered: Loading more images...');
    imageDisplayLimit.value += imagesToFetchIncrement;
    runFetchCycle(); // Continue fetching with the new limit (will process remaining queue)
  };

  const resetCommonsState = () => {
      commonsLoading.value = false;
      commonsError.value = null;
      commonsImages.value = [];
      imageDisplayLimit.value = 100;
      isFetchingMore.value = false;
      canFetchMore.value = false;
      visitedCategories.value.clear();
      categoryQueue.value = [];
  }

  return {
    commonsLoading,
    commonsError,
    commonsImages,
    isFetchingMore,
    canFetchMore,
    startInitialFetch,
    loadMoreImages,
    resetCommonsState,
  };
}
