<?php

namespace App\Console\Command;

use App\Console\Command;
use Exception;

class SeriesInitCommand extends Command
{
    protected string $signature = 'series:init --base-directory= --builds-directory=';
    protected string $description = 'Initialize series files for libraries';

    protected ?string $baseDirectory = null;

    public function handle(): int
    {
        try {
            $this->baseDirectory = $this->getOption('base-directory');
            if (!$this->baseDirectory) {
                throw new Exception('Base directory is required');
            }

            $buildsDirectory = $this->getOption('builds-directory');
            if (!$buildsDirectory) {
                throw new Exception('Build directory is required');
            }

            $series_directory = $buildsDirectory . '/series';
            if(!is_dir($series_directory)) {
                return Command::SUCCESS;
            }

            $files = glob($series_directory . '/series-init-*.json');

            // We lock the files we are working on
            // so that we don't process them again if the command is run again
            $filteredFiles = [];
            foreach ($files as $filepath) {
                $lockFile = $filepath . '.lock';
                if (!file_exists($lockFile)) {
                    touch($lockFile);
                    $filteredFiles[] = $filepath;
                }
            }

            foreach ($filteredFiles as $filepath) {
                $data = json_decode(file_get_contents($filepath), true, 512, JSON_THROW_ON_ERROR);
                $this->initSeriesFiles(
                    $data['php_version'],
                    $data['source_vs'],
                    $data['target_vs']
                );
                unlink($filepath);
                unlink($filepath . '.lock');
            }
            return Command::SUCCESS;
        } catch (Exception $e) {
            echo $e->getMessage();
            return Command::FAILURE;
        }
    }

    /**
     * @throws Exception
     */
    private function initSeriesFiles(
        string $php_version,
        string $source_vs,
        string $target_vs
    ): void
    {
        $baseDirectory = $this->baseDirectory . "/php-sdk/deps/series";

        if (!is_dir($baseDirectory)) {
            mkdir($baseDirectory, 0755, true);
        }
        foreach(['x86', 'x64'] as $arch) {
            foreach(['stable', 'staging'] as $stability) {
                $sourceSeries = 'packages-master-' . $source_vs . '-' . $arch . '-' . $stability . '.txt';
                if(!file_exists($baseDirectory . '/' . $sourceSeries)) {
                    throw new Exception("$baseDirectory/$sourceSeries not found");
                }
                $destinationFileName = 'packages-' . $php_version . '-' . $target_vs . '-' . $arch . '-' . $stability . '.txt';
                if(file_exists($baseDirectory . '/' . $destinationFileName)) {
                    throw new Exception("$baseDirectory/$destinationFileName already exists");
                }
                copy($baseDirectory . '/' . $sourceSeries, $baseDirectory . '/' . $destinationFileName);
            }
        }
    }
}