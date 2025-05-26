// Utility to fetch all subclasses/instances of a QID from Wikidata
export async function fetchSubclassesAndInstances(qid) {
  const endpoint = 'https://query.wikidata.org/sparql';
  const query = `SELECT DISTINCT ?item WHERE {
    ?item (wdt:P31/wdt:P279*|wdt:P279/wdt:P279*) wd:${qid} .
  }`;
  const url = endpoint + '?format=json&query=' + encodeURIComponent(query);
  const resp = await fetch(url, { headers: { 'Accept': 'application/sparql-results+json' } });
  const data = await resp.json();
  // Always include the original QID in the set, even if not in results
  const ids = new Set([qid]);
  if (data.results && data.results.bindings) {
    for (const b of data.results.bindings) {
      const uri = b.item.value;
      const id = uri.split('/').pop();
      ids.add(id);
    }
  }
  console.log('[depictsUtils] QID', qid, 'subclasses/instances set:', Array.from(ids));
  return ids;
}

// Utility to fetch depicts statements for a batch of mediainfo IDs from Commons
export async function fetchDepictsForMediaInfoIds(mids) {
  if (!mids.length) return {};
  const url = 'https://commons.wikimedia.org/w/api.php?action=wbgetentities&format=json&ids=' + mids.join('|') + '&props=claims&origin=*';
  const resp = await fetch(url);
  const data = await resp.json();
  const depicts = {};
  for (const mid of mids) {
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
  return depicts;
}
