import { fetchSubclassesAndInstances as sparqlFetchSubclassesAndInstances } from '../sparqlQueries.js';

// Utility to fetch all subclasses/instances of a QID from Wikidata
export async function fetchSubclassesAndInstances(qid) {
  return await sparqlFetchSubclassesAndInstances(qid);
}

// Utility to fetch depicts statements for a batch of mediainfo IDs from Commons
export async function fetchDepictsForMediaInfoIds(mids) {
  if (!mids.length) return {};
  const MAX_IDS = 50;
  const depicts = {};
  for (let i = 0; i < mids.length; i += MAX_IDS) {
    const batch = mids.slice(i, i + MAX_IDS);
    const url = 'https://commons.wikimedia.org/w/api.php?action=wbgetentities&format=json&ids=' + batch.join('|') + '&props=claims&origin=*';
    const resp = await fetch(url);
    const data = await resp.json();
    for (const mid of batch) {
      // check the mid is in the response
      if (!data.entities || !data.entities[mid]) {
        console.warn('[depictsUtils] MediaInfo', mid, 'not found in response');
        depicts[mid] = []; // Return empty array if not found
        continue;
      }
      const entity = data.entities[mid];
      depicts[mid] = [];
      // Support both .claims (old API) and .statements (newer API)
      const claims = entity && (entity.claims || entity.statements);
      if (claims && claims.P180) {
        for (const claim of claims.P180) {
          // DEBUG: log the full claim
          console.log('[depictsUtils] MediaInfo', mid, 'P180 claim:', claim);
          if (
            claim.mainsnak &&
            claim.mainsnak.snaktype === 'value' &&
            claim.mainsnak.datavalue &&
            claim.mainsnak.datavalue.value &&
            (claim.mainsnak.datavalue.value['id'] || claim.mainsnak.datavalue.value['numeric-id'])
          ) {
            // Prefer .id, fallback to Q + numeric-id
            let qid = claim.mainsnak.datavalue.value['id'];
            if (!qid && claim.mainsnak.datavalue.value['numeric-id']) {
              qid = 'Q' + claim.mainsnak.datavalue.value['numeric-id'];
            }
            if (qid) depicts[mid].push(qid);
          }
        }
      }
      console.log('[depictsUtils] MediaInfo', mid, 'depicts:', depicts[mid]);
    }
  }
  console.log('[depictsUtils] Final depicts map:', depicts);
  return depicts;
}
