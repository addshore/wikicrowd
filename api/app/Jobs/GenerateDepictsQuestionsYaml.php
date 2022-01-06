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

    private const YAML_SPEC_DIR = __DIR__ . '/../../spec/';

    public function __construct(
        string $yamlFile = null
        )
    {
        $this->yamlFile = $yamlFile;
    }

    public function handle()
    {
        if ( $this->yamlFile === null ) {
            echo "No YAML file specified. Will run for ALL files.\n";
            $files = $this->getRecursiveYamlFilesInDirectory( self::YAML_SPEC_DIR );
            echo implode( "\n", $files ) . "\n";
        } else {
            $files = [ $this->yamlFile ];
        }

        foreach( $files as $file ) {
            $this->processYamlFile( $file );
        }

    }
    private function processYamlFile( string $yamlFile ){
        echo "Running for ${yamlFile}\n";

        $content = file_get_contents($yamlFile);
        $value = Yaml::parse($content);
        ( new GenerateDepictsQuestions(
            $value['category'],
            implode($value['exclude'] ?: [], '|'),
            $value['depictsId'],
            $value['name'],
            $value['limit']
        ) )->handle();
    }

    private function getRecursiveYamlFilesInDirectory(string $directory){
        $files = [];
        $dir = new \RecursiveDirectoryIterator($directory);
        $iterator = new \RecursiveIteratorIterator($dir);
        foreach ($iterator as $file) {
            if ( $file->isFile() ) {
                $files[] = realpath($file->getPathname());
            }
        }
        return $files;
    }

    private function isYamlFile(string $file){
        return substr($file, -5) === '.yaml' || substr($file, -4) === '.yml';
    }
}
