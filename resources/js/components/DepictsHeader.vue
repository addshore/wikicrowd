<template>
  <h2 class="text-xl font-bold mb-2 flex flex-col items-center">
    <p class="text-lg leading-7 text-gray-500 mb-1">
      Does this image clearly <a :href="depictsLinkHref" target="_blank" class="text-blue-600 hover:underline">depict</a>
    </p>
    <div v-if="depictsId" class="text-lg font-semibold flex items-center mb-1">
      <a
        :href="'https://www.wikidata.org/wiki/' + depictsId"
        target="_blank"
        class="mr-2 text-blue-600 hover:underline"
      >
        {{ depictsId }}
      </a>
      <span class="ml-1">(<WikidataLabel :qid="depictsId" :fallback="depictsName" />)</span>
      <span class="ml-2 text-sm">
        <a
          :href="depictsUpQueryUrl"
          target="_blank"
          class="text-blue-600 hover:underline"
        >(up)</a>
        <a
          :href="`https://query.wikidata.org/embed.html#SELECT%20%3Fitem%20%3FitemLabel%0AWHERE%0A%7B%0A%20%20%3Fitem%20wdt%3AP31%2Fwdt%3AP279*|wdt%3AP279%2Fwdt%3AP279*%20wd%3A${depictsId}.%0A%20%20SERVICE%20wikibase%3Alabel%20%7B%20bd%3AserviceParam%20wikibase%3Alanguage%20%22%5BAUTO_LANGUAGE%5D%2Cmul%2Cen%22.%20%7D%0A%7D`"
          target="_blank"
          class="ml-1 text-blue-600 hover:underline"
        >(down)</a>
      </span>
    </div>
    <div v-if="depictsId" class="text-gray-600 text-sm mt-1">
      <WikidataDescription :qid="depictsId" />
    </div>
  </h2>
</template>

<script>
import WikidataLabel from './WikidataLabel.vue';
import WikidataDescription from './WikidataDescription.vue';

export default {
  name: 'DepictsHeader',
  components: { WikidataLabel, WikidataDescription },
  props: {
    depictsId: {
      type: String,
      default: null
    },
    depictsName: {
      type: String,
      default: null
    },
    depictsUpQueryUrl: {
      type: String,
      required: true
    },
    depictsLinkHref: {
      type: String,
      required: true
    }
  }
};
</script>
