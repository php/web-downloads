<?php

namespace App\Console\Command;

use App\Console\Command;
use Exception;

class SeriesStabilityCommand extends Command
{
    protected string $signature = 'series:stability --base-directory= --builds-directory=';
    protected string $description = 'Promote staging series files to stable for libraries';

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

            $files = glob($series_directory . '/series-stability-*.json');

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
                extract($data);
                $this->promoteSeriesFiles($php_version, $vs_version);
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
    private function promoteSeriesFiles(
        string $php_version,
        string $vs_version,
    ): void
    {
        $baseDirectory = $this->baseDirectory . "/php-sdk/deps/series";

        if (!is_dir($baseDirectory)) {
            mkdir($baseDirectory, 0755, true);
        }
        foreach(['x86', 'x64'] as $arch) {
            $sourceFile = $baseDirectory . '/packages-' . $php_version . '-' . $vs_version . '-' . $arch . '-staging.txt';
            $destinationFile = $baseDirectory . '/packages-' . $php_version . '-' . $vs_version . '-' . $arch . '-stable.txt';
            if(!file_exists($sourceFile)) {
                throw new Exception($sourceFile . ' does not exist');
            }
            copy($sourceFile, $destinationFile);
        }
    }
}