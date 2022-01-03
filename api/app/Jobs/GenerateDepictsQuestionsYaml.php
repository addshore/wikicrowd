<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Yaml\Yaml;

class GenerateDepictsQuestionsYaml implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $yamlFile;
    private $limit;

    public function __construct(
        string $yamlFile,
        int $limit
        )
    {
        $this->yamlFile = $yamlFile;
        $this->limit = $limit;
    }

    public function handle()
    {
        $content = file_get_contents(__DIR__ . '/../../spec/' . $this->yamlFile);
        $value = Yaml::parse($content);
        ( new GenerateDepictsQuestions(
            $value['category'],
            implode($value['exclude'], '|'),
            $value['depictsId'],
            $value['name'],
            $this->limit
        ) )->handle();
    }
}
