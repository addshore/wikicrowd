/**
 * Centralized SPARQL query templates and utilities
 * All SPARQL queries used across the application should be defined here
 */

export const SPARQL_ENDPOINTS = {
  WIKIDATA: 'https://query.wikidata.org/sparql',
  WIKIDATA_EMBED: 'https://query.wikidata.org/embed.html'
};

/**
 * SPARQL query templates
 */
export const SPARQL_QUERIES = {
  /**
   * Get all parent classes of a given item (upward hierarchy) with labels
   * Used for generating "up" query links and understanding broader categories
   */
  PARENT_CLASSES_WITH_LABELS: (itemId) => 
    `SELECT DISTINCT ?item ?itemLabel WHERE {
  {
    wd:${itemId} (wdt:P31/wdt:P279)+ ?item.
  }
  UNION {
    wd:${itemId} (wdt:P31/wdt:P279|wdt:P279)+ ?item.
  }
  UNION {
    wd:${itemId} wdt:P31 ?item.
  }
  UNION {
    wd:${itemId} wdt:P31 ?i1 .
    ?i1 wdt:P13359 ?item .
  }
  UNION {
    wd:${itemId} wdt:P31 ?i1 .
    ?i1 wdt:P13359 ?i2 .
    ?i2 (wdt:P31/wdt:P279|wdt:P279)+ ?item .
  }
  FILTER NOT EXISTS { ?item wdt:P31 wd:Q96251598. }
  FILTER NOT EXISTS { ?item wdt:P31 wd:Q19478619. }
  FILTER NOT EXISTS { ?item wdt:P31 wd:Q104054982. }
  FILTER NOT EXISTS { ?item wdt:P31 wd:Q1786828. }
  FILTER NOT EXISTS { ?item wdt:P31 wd:Q23958852. }
  FILTER NOT EXISTS { ?item wdt:P31 wd:Q103997133. }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],mul,en". }
}`,

  /**
   * Get all subclasses and instances of a given item (downward hierarchy)
   * Used for checking if items are more specific than a given depicts value
   * Also used for generating "down" query links in embedded URLs
   */
  DOWNWARD_HIERARCHY: (itemId) =>
    `SELECT DISTINCT ?item ?itemLabel WHERE {
  {
    ?item (wdt:P31/wdt:P279* | wdt:P279/wdt:P279*) wd:${itemId}.
  }
  UNION {
    ?item wdt:P31 wd:${itemId}.
  }
  UNION {
    ?item (wdt:P31/wdt:P279* | wdt:P279/wdt:P279*) ?x .
    ?x wdt:P13359 wd:${itemId}.
  }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],mul,en". }
}`
};

/**
 * Execute a SPARQL query against Wikidata
 * @param {string} query - The SPARQL query to execute
 * @param {string} format - Response format (default: 'json')
 * @returns {Promise<Object>} - Query results
 */
export async function executeSparqlQuery(query, format = 'json') {
  const url = `${SPARQL_ENDPOINTS.WIKIDATA}?format=${format}&query=${encodeURIComponent(query)}`;
  const response = await fetch(url, {
    headers: { 'Accept': 'application/sparql-results+json' },
    redirect: 'follow'
  });
  
  if (!response.ok) {
    throw new Error(`SPARQL query failed: ${response.status} ${response.statusText}`);
  }
  
  return await response.json();
}

/**
 * Generate a URL for the Wikidata Query Service embed view
 * @param {string} query - The SPARQL query
 * @returns {string} - The embed URL
 */
export function generateEmbedUrl(query) {
  return `${SPARQL_ENDPOINTS.WIKIDATA_EMBED}#${encodeURIComponent(query)}`;
}

/**
 * Extract QID from Wikidata entity URI
 * @param {string} uri - Wikidata entity URI
 * @returns {string} - QID (e.g., "Q123")
 */
export function extractQidFromUri(uri) {
  const parts = uri.split('/');
  return parts[parts.length - 1];
}

/**
 * Fetch all subclasses and instances of a QID from Wikidata
 * This is a high-level utility function that handles the query execution
 * @param {string} qid - The Wikidata QID
 * @returns {Promise<Set<string>>} - Set of QIDs including the original
 */
export async function fetchSubclassesAndInstances(qid) {
  const query = SPARQL_QUERIES.DOWNWARD_HIERARCHY(qid);
  const data = await executeSparqlQuery(query);
  
  // Always include the original QID in the set
  const ids = new Set([qid]);
  
  if (data.results && data.results.bindings) {
    for (const binding of data.results.bindings) {
      const uri = binding.item.value;
      const id = extractQidFromUri(uri);
      ids.add(id);
    }
  }
  
  console.log('[sparqlQueries] QID', qid, 'subclasses/instances set:', Array.from(ids));
  return ids;
}

/**
 * Generate URL for upward hierarchy query (depicts "up" link)
 * @param {string} depictsId - The depicts QID
 * @returns {string} - Query service embed URL
 */
export function generateDepictsUpQueryUrl(depictsId) {
  if (!depictsId) return '';
  const query = SPARQL_QUERIES.PARENT_CLASSES_WITH_LABELS(depictsId);
  return generateEmbedUrl(query);
}

/**
 * Generate URL for downward hierarchy query (depicts "down" link)
 * @param {string} depictsId - The depicts QID
 * @returns {string} - Query service embed URL
 */
export function generateDepictsDownQueryUrl(depictsId) {
  if (!depictsId) return '';
  const query = SPARQL_QUERIES.DOWNWARD_HIERARCHY(depictsId);
  return generateEmbedUrl(query);
}
