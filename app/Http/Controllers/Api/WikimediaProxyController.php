<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;
use Addwiki\Mediawiki\Api\Client\Auth\OAuthOwnerConsumer;
use Addwiki\Wikimedia\Api\WikimediaFactory;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WikimediaProxyController extends Controller
{
    private const SITE_TO_DOMAIN = [
        'commons' => 'commons.wikimedia.org',
        'wikidata' => 'www.wikidata.org',
    ];

    private const ALLOWED_ACTIONS = [
        'commons' => ['query', 'parse', 'wbgetentities'],
        'wikidata' => ['wbsearchentities', 'wbgetentities'],
    ];

    private const ALLOWED_PARAMS_BY_ACTION = [
        'query' => [
            'list', 'srsearch', 'srnamespace', 'srlimit',
            'generator', 'gcmtitle', 'gcmtype', 'gcmlimit', 'gcmcontinue',
            'meta', 'uiprop',
            'prop', 'titles', 'pageids',
            'iiprop', 'iiurlwidth', 'iiurlheight',
        ],
        'parse' => [
            'page', 'prop',
        ],
        'wbgetentities' => [
            'ids', 'props', 'sites', 'titles', 'languages', 'languagefallback',
        ],
        'wbsearchentities' => [
            'search', 'language', 'uselang', 'type', 'limit', 'continue', 'strictlanguage',
        ],
    ];

    private const ALLOWED_REST_ENDPOINTS = [
        'labels_with_language_fallback',
        'descriptions_with_language_fallback',
    ];

    public function action(Request $request)
    {
        $request->validate([
            'site' => ['required', Rule::in(array_keys(self::SITE_TO_DOMAIN))],
            'action' => ['required', 'string'],
        ]);

        $site = (string)$request->query('site');
        $action = (string)$request->query('action');

        if (!in_array($action, self::ALLOWED_ACTIONS[$site], true)) {
            return response()->json([
                'error' => 'Unsupported action for selected site.',
            ], 422);
        }

        $user = $request->user('sanctum');
        if ($user === null || empty($user->token) || empty($user->token_secret)) {
            return response()->json([
                'error' => 'Authenticated Wikimedia credentials are not available for this user.',
            ], 403);
        }

        $params = $this->filteredActionParams($request, $action);
        $params['format'] = 'json';
        $params['assert'] = 'user';

        try {
            $mwAuth = $this->buildOAuthOwnerConsumer($user->token, $user->token_secret);
            $wmFactory = new WikimediaFactory();
            $mwApi = $wmFactory->newMediawikiApiForDomain(self::SITE_TO_DOMAIN[$site], $mwAuth);
            $response = $mwApi->request(ActionRequest::simpleGet($action, $params));
            return response()->json($response);
        } catch (RequestException $e) {
            return $this->proxyErrorResponse($e, 'Wikimedia API request failed.');
        } catch (\Throwable $e) {
            \Log::warning('Authenticated Wikimedia proxy failed', [
                'site' => $site,
                'action' => $action,
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Wikimedia API request failed.',
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    public function rest(Request $request)
    {
        $request->validate([
            'site' => ['required', Rule::in(array_keys(self::SITE_TO_DOMAIN))],
            'endpoint' => ['required', Rule::in(self::ALLOWED_REST_ENDPOINTS)],
            'qid' => ['required', 'regex:/^Q\\d+$/'],
            'lang' => ['nullable', 'regex:/^[a-z-]{2,12}$/i'],
        ]);

        $site = (string)$request->query('site');
        $endpoint = (string)$request->query('endpoint');
        $qid = (string)$request->query('qid');
        $lang = (string)$request->query('lang', 'en');

        $user = $request->user('sanctum');
        if ($user === null || empty($user->token) || empty($user->token_secret)) {
            return response()->json([
                'error' => 'Authenticated Wikimedia credentials are not available for this user.',
            ], 403);
        }

        try {
            $mwAuth = $this->buildOAuthOwnerConsumer($user->token, $user->token_secret);
            $wmFactory = new WikimediaFactory();
            $mwApi = $wmFactory->newMediawikiApiForDomain(self::SITE_TO_DOMAIN[$site], $mwAuth);

            $props = $endpoint === 'labels_with_language_fallback' ? 'labels' : 'descriptions';
            $actionResponse = $mwApi->request(ActionRequest::simpleGet('wbgetentities', [
                'ids' => $qid,
                'props' => $props,
                'languages' => $lang,
                'languagefallback' => 1,
                'assert' => 'user',
                'format' => 'json',
            ]));

            $entity = $actionResponse['entities'][$qid] ?? null;
            $value = $this->extractWbgetentitiesLocalizedValue($entity, $props, $lang);
            if ($value === null) {
                return response()->json([
                    'code' => 'not-found',
                    'message' => "No {$props} found for {$qid} in {$lang}.",
                ], 404);
            }

            return response()->json([
                'value' => $value,
            ]);
        } catch (RequestException $e) {
            return $this->proxyErrorResponse($e, 'Wikimedia REST API request failed.');
        } catch (\Throwable $e) {
            \Log::warning('Authenticated Wikimedia REST proxy failed', [
                'site' => $site,
                'endpoint' => $endpoint,
                'qid' => $qid,
                'lang' => $lang,
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Wikimedia REST API request failed.',
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    private function filteredActionParams(Request $request, string $action): array
    {
        $allowed = self::ALLOWED_PARAMS_BY_ACTION[$action] ?? [];
        $raw = $request->query();
        $params = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $raw)) {
                $params[$key] = $raw[$key];
            }
        }

        return $params;
    }

    private function buildOAuthOwnerConsumer(string $accessToken, string $accessSecret): OAuthOwnerConsumer
    {
        return new OAuthOwnerConsumer(
            config('services.mediawiki.identifier'),
            config('services.mediawiki.secret'),
            $accessToken,
            $accessSecret
        );
    }

    private function extractWbgetentitiesLocalizedValue(?array $entity, string $props, string $lang): ?string
    {
        if (!is_array($entity) || !isset($entity[$props]) || !is_array($entity[$props])) {
            return null;
        }

        $bucket = $entity[$props];
        $requestedValue = $bucket[$lang]['value'] ?? null;
        if (is_string($requestedValue) && $requestedValue !== '') {
            return $requestedValue;
        }

        foreach ($bucket as $entry) {
            if (is_array($entry) && isset($entry['value']) && is_string($entry['value']) && $entry['value'] !== '') {
                return $entry['value'];
            }
        }

        return null;
    }

    private function redactSensitiveHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'set-cookie'];
        $redacted = [];

        foreach ($headers as $name => $values) {
            $normalized = strtolower((string)$name);
            $redacted[$name] = in_array($normalized, $sensitive, true) ? ['[REDACTED]'] : $values;
        }

        return $redacted;
    }

    private function proxyErrorResponse(RequestException $e, string $defaultError)
    {
        $status = $e->getResponse()?->getStatusCode() ?? 502;
        $rawBody = $e->getResponse() ? (string)$e->getResponse()->getBody() : '';
        $decoded = $rawBody !== '' ? json_decode($rawBody, true) : null;

        if (is_array($decoded)) {
            return response()->json($decoded, $status);
        }

        return response()->json([
            'error' => $defaultError,
            'status' => $status,
            'upstream_body' => $rawBody !== '' ? $rawBody : null,
            'message' => $e->getMessage(),
            'request' => [
                'method' => $e->getRequest()->getMethod(),
                'url' => (string)$e->getRequest()->getUri(),
                'headers' => $this->redactSensitiveHeaders($e->getRequest()->getHeaders()),
                'body' => (string)$e->getRequest()->getBody(),
            ],
        ], $status);
    }
}
