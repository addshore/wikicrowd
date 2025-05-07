<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Symfony\Component\Yaml\Yaml;
use App\Jobs\GenerateDepictsQuestions;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        // TODO at some point refactor into a light job that checks if questions are needed
        // And a heavy job which actually generates them...

        // GenerateDepictsQuestions
        echo "Generating depicts questions for schedule jobs...\n";
        $depictsYamlDir = realpath(__DIR__ . '/../../spec/');
        echo "Looking for depicts yaml files in $depictsYamlDir\n";
        $depictsYamlFiles = $this->getRecursiveYamlFilesInDirectory( $depictsYamlDir );
        $depictsJobs = [];
        foreach( $depictsYamlFiles as $file ) {
            $file = Yaml::parse(file_get_contents($file), Yaml::PARSE_OBJECT_FOR_MAP);
            if( is_array( $file ) ) {
                $depictsJobs = array_merge( $depictsJobs, $file );
            } else {
                $depictsJobs[] = $file;
            }
        }
        foreach( $depictsJobs as $job ) {
            // Make sure that job is an object
            if( !is_object( $job ) ) {
                echo "Job is not an object, skipping\n";
                print_r($job);
                continue;
            }
            // Make sure it has the required fields
            if( !isset( $job->category ) || !isset( $job->depictsId ) || !isset( $job->name ) || !isset( $job->limit ) ) {
                echo "Job is missing required fields\n";
                continue;
            }
            $schedule->job(
                new GenerateDepictsQuestions(
                    $job->category,
                    implode('|||', isset($job->exclude) ? $job->exclude : []),
                    isset($job->excludeRegex) ? $job->excludeRegex : "",
                    $job->depictsId,
                    $job->name,
                    $job->limit
                ),
                "low"
            )->everySixHours();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * TODO move this function elsewhere?
     */
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
}
