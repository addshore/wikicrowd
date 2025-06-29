<?php

namespace App\Http\Controllers\Api;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\JsonResponse;
use Symfony\Component\Yaml\Yaml;

class YamlSpecController extends BaseController
{
    /**
     * GET /api/yaml-spec
     * Loads the YAML spec from Commons and returns it as JSON.
     */
    public function index(): JsonResponse
    {
        \Log::info('Api/YamlSpecController@index called');
        $yamlUrl = 'https://commons.wikimedia.org/wiki/User:Addshore/wikicrowd.yaml?action=raw';
        $yamlContent = @file_get_contents($yamlUrl);
        if ($yamlContent === false) {
            return response()->json(['error' => 'Failed to load YAML from Commons'], 500);
        }
        try {
            $parsed = Yaml::parse($yamlContent, Yaml::PARSE_OBJECT_FOR_MAP);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to parse YAML', 'details' => $e->getMessage()], 500);
        }
        return response()->json($parsed);
    }
}
