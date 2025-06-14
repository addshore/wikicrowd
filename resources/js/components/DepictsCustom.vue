<template>
    <div class="mt-12 border-t pt-8">
        <h2 class="text-xl font-bold mb-2 text-gray-900 dark:text-gray-100">Custom Grid</h2>
        <div class="text-gray-600 dark:text-gray-400 text-sm">Create your own grids, based on categories and wikidata items.</div>
        <a href="/questions/depicts/custom" class="inline-block bg-blue-600 text-white px-4 py-2 rounded font-bold hover:bg-blue-700">
            Go to Custom Grid
        </a>
        <div class="mt-4">
            <div class="font-semibold mb-1 text-gray-800 dark:text-gray-200">Suggested examples:</div>
            <ul class="list-disc pl-5 space-y-1">
                <li>
                    <a
                        href="/questions/depicts/custom?category=Category%3AKitesurfing&auto=1&item=Q219554"
                        class="text-blue-600 hover:underline dark:text-blue-400"
                        >Kitesurfing</a
                    >
                </li>
                <li>
                    <a
                        href="/questions/depicts/custom?category=Category%3AWindsurfing&item=Q191051"
                        class="text-blue-600 hover:underline dark:text-blue-400"
                        >Windsurfing</a
                    >
                </li>
                <li>
                    <a
                        href="/questions/depicts/custom?category=Category%3ANational+flag+of+Ukraine&item=Q127974"
                        class="text-blue-600 hover:underline dark:text-blue-400"
                        >National flag of Ukraine</a
                    >
                </li>
                <!-- Add more suggested examples here as needed -->
            </ul>
        </div>
    </div>
</template>

<script setup>
// No props or logic needed for this simple component
</script>

<style scoped>
ul li::marker {
    color: #4b5563; /* Tailwind's text-gray-600 */
}
.dark ul li::marker {
    color: #9ca3af; /* Tailwind's text-gray-400 */
}

</style>
