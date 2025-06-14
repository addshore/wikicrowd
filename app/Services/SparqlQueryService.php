<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Addwiki\Wikibase\Query\WikibaseQueryFactory;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Addwiki\Wikibase\Api\WikibaseApi;
use Addwiki\Wikibase\Query\PrefixSets;

/**
 * Centralized SPARQL query management service
 * All SPARQL queries used across the application should be defined here
 */
class SparqlQueryService
{
    public const WIKIDATA_SPARQL_ENDPOINT = 'https://query.wikidata.org/sparql';
    public const WIKIDATA_EMBED_URL = 'https://query.wikidata.org/embed.html';

    /**
     * SPARQL query templates
     */
    public const QUERIES = [
        /**
         * Get all parent classes of a given item (upward hierarchy) with labels
         * Used for generating "up" query links and understanding broader categories
         */
        'PARENT_CLASSES_WITH_LABELS' => 'SELECT DISTINCT ?item ?itemLabel WHERE { 
            { wd:%s (wdt:P31/wdt:P279)+ ?item. } 
            UNION 
            { wd:%s (wdt:P31/wdt:P279|wdt:P279)+ ?item . } 
            SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],mul,en". } 
        }',

        /**
         * Get all subclasses and instances of a given item (downward hierarchy)
         * Used for checking if items are more specific than a given depicts value
         * Also used for generating "down" query links in embedded URLs
         */
        'DOWNWARD_HIERARCHY' => 'SELECT ?item ?itemLabel
            WHERE {
                ?item wdt:P31/wdt:P279*|wdt:P279/wdt:P279* wd:%s.
                SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],mul,en". }
            }'
    ];

    private $queryService;

    public function __construct()
    {
        $this->queryService = (new WikibaseQueryFactory(
            self::WIKIDATA_SPARQL_ENDPOINT,
            PrefixSets::WIKIDATA
        ))->newWikibaseQueryService();
    }

    /**
     * Execute a SPARQL query
     *
     * @param string $queryKey The query template key from self::QUERIES
     * @param string|array $parameters Parameters to substitute in the query
     * @param int $cacheMinutes Cache duration in minutes (0 to disable caching)
     * @return array Query results
     */
    public function executeQuery(string $queryKey, $parameters, int $cacheMinutes = 2): array
    {
        if (!isset(self::QUERIES[$queryKey])) {
            throw new \InvalidArgumentException("Unknown query key: {$queryKey}");
        }

        $template = self::QUERIES[$queryKey];
        
        // Handle both single parameter and array of parameters
        if (is_array($parameters)) {
            $query = sprintf($template, ...$parameters);
        } else {
            $query = sprintf($template, $parameters);
        }

        if ($cacheMinutes > 0) {
            $cacheKey = 'sparql_query:' . md5($query);
            return Cache::remember($cacheKey, $cacheMinutes, function () use ($query) {
                return $this->queryService->query($query);
            });
        }

        return $this->queryService->query($query);
    }

    /**
     * Get all subclasses and instances of an item
     *
     * @param string $itemId The Wikidata item ID (Q123)
     * @param int $cacheMinutes Cache duration in minutes
     * @return array Array of QIDs
     */
    public function getSubclassesAndInstances(string $itemId, int $cacheMinutes = 2): array
    {
        $result = $this->executeQuery('DOWNWARD_HIERARCHY', $itemId, $cacheMinutes);
        
        $ids = [];
        foreach ($result['results']['bindings'] as $binding) {
            $ids[] = $this->extractQidFromUri($binding['item']['value']);
        }
        
        return $ids;
    }

    /**
     * Get parent classes with labels
     *
     * @param string $itemId The Wikidata item ID (Q123)
     * @param int $cacheMinutes Cache duration in minutes
     * @return array Array of items with QIDs and labels
     */
    public function getParentClassesWithLabels(string $itemId, int $cacheMinutes = 2): array
    {
        $result = $this->executeQuery('PARENT_CLASSES_WITH_LABELS', [$itemId, $itemId], $cacheMinutes);
        
        $items = [];
        foreach ($result['results']['bindings'] as $binding) {
            $items[] = [
                'qid' => $this->extractQidFromUri($binding['item']['value']),
                'label' => $binding['itemLabel']['value'] ?? null
            ];
        }
        
        return $items;
    }

    /**
     * Generate a URL for the Wikidata Query Service embed view
     *
     * @param string $queryKey The query template key
     * @param string|array $parameters Parameters for the query
     * @return string The embed URL
     */
    public function generateEmbedUrl(string $queryKey, $parameters): string
    {
        if (!isset(self::QUERIES[$queryKey])) {
            throw new \InvalidArgumentException("Unknown query key: {$queryKey}");
        }

        $template = self::QUERIES[$queryKey];
        
        if (is_array($parameters)) {
            $query = sprintf($template, ...$parameters);
        } else {
            $query = sprintf($template, $parameters);
        }

        return self::WIKIDATA_EMBED_URL . '#' . urlencode($query);
    }

    /**
     * Generate URL for depicts "up" query link
     *
     * @param string $depictsId The depicts QID
     * @return string Query service embed URL
     */
    public function generateDepictsUpQueryUrl(string $depictsId): string
    {
        if (empty($depictsId)) {
            return '';
        }
        
        return $this->generateEmbedUrl('PARENT_CLASSES_WITH_LABELS', [$depictsId, $depictsId]);
    }

    /**
     * Generate URL for depicts "down" query link
     *
     * @param string $depictsId The depicts QID
     * @return string Query service embed URL
     */
    public function generateDepictsDownQueryUrl(string $depictsId): string
    {
        if (empty($depictsId)) {
            return '';
        }
        
        return $this->generateEmbedUrl('DOWNWARD_HIERARCHY', $depictsId);
    }

    /**
     * Extract QID from Wikidata entity URI
     *
     * @param string $uri Wikidata entity URI
     * @return string QID (e.g., "Q123")
     */
    public function extractQidFromUri(string $uri): string
    {
        $parts = explode('/', $uri);
        return end($parts);
    }

    /**
     * Backward compatibility method - matches the old instancesOfAndSubclassesOf pattern
     *
     * @param string $itemId The Wikidata item ID
     * @return array Array of QIDs
     */
    public function instancesOfAndSubclassesOf(string $itemId): array
    {
        return $this->getSubclassesAndInstances($itemId);
    }
}
