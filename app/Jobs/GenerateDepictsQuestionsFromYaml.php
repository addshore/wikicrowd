<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\Yaml\Yaml;
use App\Jobs\GenerateDepictsQuestions;

class GenerateDepictsQuestionsFromYaml implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $depictItemId;
    private $yamlUrl;
    private $jobLimit;
    private $runSync;

    /**
     * @param string $depictItemId Optional QID to filter by
     * @param string $yamlUrl Optional override YAML URL
     * @param int $jobLimit Optional limit for how many jobs to process
     * @param bool $runSync If true, run dispatched jobs synchronously
     */
    public function __construct(string $depictItemId = '', string $yamlUrl = null, int $jobLimit = 0, bool $runSync = false)
    {
        $this->depictItemId = $depictItemId;
        $this->yamlUrl = $yamlUrl;
        $this->jobLimit = $jobLimit;
        $this->runSync = $runSync;
    }

    public function handle()
    {
        \Log::info("Starting GenerateDepictsQuestionsFromYaml job with depictItemId: {$this->depictItemId} and extra yamlUrl: {$this->yamlUrl}");

        $defaultYamlUrl = 'https://commons.wikimedia.org/wiki/User:Addshore/wikicrowd.yaml?action=raw';
        $yamlContent = @file_get_contents($defaultYamlUrl);
        if ($yamlContent === false) {
            \Log::error("Failed to download YAML from $defaultYamlUrl");
            return;
        }
        $parsed = Yaml::parse($yamlContent, Yaml::PARSE_OBJECT_FOR_MAP);
        if (!is_object($parsed) || !isset($parsed->questions) || !isset($parsed->global)) {
            \Log::error("YAML structure invalid or missing required keys");
            return;
        }
        // If an override YAML URL is provided, fetch and merge (override) it
        if ($this->yamlUrl !== null && $this->yamlUrl !== '') {
            $overrideContent = @file_get_contents($this->yamlUrl);
            if ($overrideContent === false) {
                \Log::error("Failed to download override YAML from {$this->yamlUrl}");
                return;
            }
            $overrideParsed = Yaml::parse($overrideContent, Yaml::PARSE_OBJECT_FOR_MAP);
            if (is_object($overrideParsed)) {
                if (isset($overrideParsed->global)) {
                    $parsed->global = (object)array_merge((array)$parsed->global, (array)$overrideParsed->global);
                }
                if (isset($overrideParsed->questions)) {
                    $existing = [];
                    foreach ($parsed->questions as $q) {
                        $key = (isset($q->name) ? $q->name : '') . '|' . (isset($q->depictsId) ? $q->depictsId : '');
                        $existing[$key] = $q;
                    }
                    foreach ($overrideParsed->questions as $oq) {
                        $key = (isset($oq->name) ? $oq->name : '') . '|' . (isset($oq->depictsId) ? $oq->depictsId : '');
                        $existing[$key] = $oq;
                    }
                    $parsed->questions = array_values($existing);
                }
            }
        }
        $global = $parsed->global;
        $questions = $parsed->questions;
        $defaultLimit = isset($global->limit) ? (int)$global->limit : 1000;
        $excludeRegexes = isset($global->excludeRegexes) ? (array)$global->excludeRegexes : [];

        \Log::info("Parsed YAML with " . count($questions) . " questions and global limit: $defaultLimit");

        $depictsJobs = [];
        foreach ($questions as $q) {
            $depictsId = '';
            if (isset($q->depictsId)) {
                if (preg_match('/\{\{Q\|([^}]+)\}\}/', $q->depictsId, $m)) {
                    $depictsId = $m[1];
                } else {
                    $depictsId = $q->depictsId;
                }
            }
            if ($this->depictItemId !== '' && $depictsId !== $this->depictItemId) {
                continue;
            }
            $categories = [];
            if (isset($q->category)) {
                if (is_array($q->category)) {
                    $categories = $q->category;
                } else {
                    $categories = [$q->category];
                }
            }
            $categories = array_map(function($cat) {
                if (preg_match('/\[\[:Category:([^\]]+)\]\]/', $cat, $m)) {
                    return trim($m[1]);
                }
                return trim($cat);
            }, $categories);
            $excludeRegex = '';
            if (isset($q->excludeRegex)) {
                $val = $q->excludeRegex;
                if (is_string($val) && isset($excludeRegexes[$val])) {
                    $excludeRegex = $excludeRegexes[$val];
                    \Log::info("Using named excludeRegex '{$val}' resolved to '{$excludeRegex}' for question: " . (isset($q->name) ? $q->name : ''));
                } else {
                    $excludeRegex = $val;
                    \Log::info("Using direct excludeRegex '{$excludeRegex}' for question: " . (isset($q->name) ? $q->name : ''));
                }
            } elseif (isset($q->excludeRegexes)) {
                $excludeRegex = $q->excludeRegexes;
                \Log::info("Using excludeRegexes array for question: " . (isset($q->name) ? $q->name : ''));
            } else {
                \Log::info("No excludeRegex set for question: " . (isset($q->name) ? $q->name : ''));
            }
            $exclude = isset($q->exclude) ? (is_array($q->exclude) ? $q->exclude : [$q->exclude]) : [];
            // Normalize and validate exclude categories
            $exclude = array_filter(array_map(function($e) {
                $norm = GenerateDepictsQuestions::normalizeCategoryName($e);
                if ($norm === null) {
                    \Log::warning("Invalid exclude category in YAML: '" . print_r($e, true) . "'");
                }
                return $norm;
            }, $exclude));
            $limit = isset($q->limit) ? (int)$q->limit : $defaultLimit;
            foreach ($categories as $cat) {
                $depictsJobs[] = [
                    'category' => $cat,
                    'exclude' => $exclude,
                    'excludeRegex' => $excludeRegex,
                    'depictsId' => $depictsId,
                    'name' => isset($q->name) ? $q->name : '',
                    'limit' => $limit,
                    'difficulty' => isset($q->difficulty) ? $q->difficulty : null,
                ];
            }
        }
        shuffle($depictsJobs);
        $dispatchedCount = 0;
        if ($this->jobLimit > 0) {
            \Log::info("Job limit set to {$this->jobLimit}, will dispatch up to this many jobs");
        } else {
            \Log::info("No job limit set, will dispatch all jobs");
        }

        $jobInstances = [];
        foreach ($depictsJobs as $job) {
            if (!isset($job['category']) || !isset($job['depictsId']) || !isset($job['name']) || !isset($job['limit'])) {
                \Log::info("Job is missing required fields");
                continue;
            }
            $jobInstance = new GenerateDepictsQuestions(
                $job['category'],
                $job['exclude'],
                $job['excludeRegex'],
                $job['depictsId'],
                $job['name'],
                $job['limit']
            );

            if ($this->runSync) {
                \Log::info("Dispatching job synchronously for category: {$job['category']}, depictsId: {$job['depictsId']}, name: {$job['name']}");
                dispatch_sync($jobInstance);
            } else {
                $jobInstances[] = $jobInstance;
            }
            $dispatchedCount++;
            if ($this->jobLimit > 0 && $dispatchedCount >= $this->jobLimit) {
                \Log::info("Reached job limit of {$this->jobLimit}, stopping dispatching");
                break;
            }
        }

        // Dispatch all jobs as a batch if running asynchronously
        if (!$this->runSync && !empty($jobInstances)) {
            $batch = Bus::batch($jobInstances)
                ->name('depicts_yaml:' . $this->depictItemId)
                ->onQueue('low')
                ->then(function() {
                    \Log::info("All GenerateDepictsQuestions jobs from YAML completed successfully");
                })
                ->catch(function($batch, $e) {
                    \Log::error("GenerateDepictsQuestions batch from YAML failed: " . $e->getMessage());
                })
                ->dispatch();

            \Log::info("Dispatched batch with " . count($jobInstances) . " GenerateDepictsQuestions jobs, batch ID: " . $batch->id);
        }
    }
}
