<?php
declare(strict_types=1);

namespace App\Console\Command;

use App\Console\Command;
use Exception;

class SeriesInitCommand extends Command
{
    public string $signature = 'series:init --base-directory= --builds-directory=';
    public string $description = 'Initialize series files for libraries';

    protected ?string $baseDirectory = null;

    public function handle(): int
    {
        try {
            $this->baseDirectory = $this->options['base-directory'] ?? null;
            if (!$this->baseDirectory) {
                throw new Exception('Base directory is required');
            }

            $buildsDirectory = $this->options['builds-directory'] ?? null;
            if (!$buildsDirectory) {
                throw new Exception('Build directory is required');
            }

            $series_directory = $buildsDirectory . '/series';
            if(!is_dir($series_directory)) {
                throw new Exception('Series directory does not exist');
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
                $subject = $data['subject'] ?? 'vs';
                if ($subject === 'vs') {
                    $this->initSeriesFiles(
                        $data['php_version'],
                        $data['source_vs'],
                        $data['target_vs']
                    );
                } elseif ($subject === 'arch') {
                    $this->initArchitectureSeriesFiles(
                        $data['php_version'],
                        $data['source_arch'] ?? '',
                        $data['target_arch'] ?? ''
                    );
                } else {
                    throw new Exception('Subject must be vs or arch');
                }
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
    private function initArchitectureSeriesFiles(
        string $phpVersion,
        string $sourceArch,
        string $targetArch
    ): void {
        $architectures = ['x86', 'x64', 'arm64'];
        if (!in_array($sourceArch, $architectures, true) || !in_array($targetArch, $architectures, true)) {
            throw new Exception('Source and target architectures must both be x86, x64, or arm64');
        }

        $vsVersions = json_decode(
            file_get_contents(dirname(__DIR__, 3) . '/config/vs.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        if (!isset($vsVersions[$phpVersion]) || !is_string($vsVersions[$phpVersion])) {
            throw new Exception('No VS version found for PHP ' . $phpVersion);
        }
        $vsVersion = $vsVersions[$phpVersion];

        $seriesDirectory = $this->baseDirectory . '/php-sdk/deps/series';
        $files = [];
        foreach (['stable', 'staging'] as $stability) {
            $sourceFile = "$seriesDirectory/packages-$phpVersion-$vsVersion-$sourceArch-$stability.txt";
            $targetFile = "$seriesDirectory/packages-$phpVersion-$vsVersion-$targetArch-$stability.txt";
            if (file_exists($targetFile)) {
                continue;
            }
            if (!is_file($sourceFile)) {
                throw new Exception("$sourceFile not found");
            }
            $contents = file_get_contents($sourceFile);
            if ($contents === false) {
                throw new Exception("Unable to read $sourceFile");
            }
            $files[$targetFile] = str_replace("-$sourceArch.zip", "-$targetArch.zip", $contents);
        }

        foreach ($files as $targetFile => $contents) {
            if (file_put_contents($targetFile, $contents) === false) {
                throw new Exception("Unable to write $targetFile");
            }
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
        foreach(['x86', 'x64', 'arm64'] as $arch) {
            foreach(['stable', 'staging'] as $stability) {
                $sourceSeries = 'packages-master-' . $source_vs . '-' . $arch . '-' . $stability . '.txt';
                if(!file_exists($baseDirectory . '/' . $sourceSeries)) {
                    if ($arch === 'arm64') {
                        continue;
                    }
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
