<?php

namespace App\Console\Command;

use App\Console\Command;
use App\Helpers\Helpers;
use Exception;

class WinlibsCommand extends Command
{
    protected string $signature = 'winlibs:add --base-directory=';
    protected string $description = 'Add winlibs dependencies';

    protected ?string $baseDirectory = null;

    public function handle(): int
    {
        try {
            $this->baseDirectory = $this->getOption('base-directory');
            if (!$this->baseDirectory) {
                throw new Exception('Base directory is required');
            }

            $buildDirectories = glob($this->baseDirectory . '/winlibs/*', GLOB_ONLYDIR);

            // We lock the Directories we are working on
            // so that we don't process them again if the command is run again
            $filteredDirectories = [];
            foreach ($buildDirectories as $directoryPath) {
                $lockFile = $directoryPath . '.lock';
                if (!file_exists($lockFile)) {
                    touch($lockFile);
                    $filteredDirectories[] = $directoryPath;
                }
            }

            foreach ($filteredDirectories as $directoryPath) {
                $data = json_decode(file_get_contents($directoryPath . '/data.json'), true, 512, JSON_THROW_ON_ERROR);
                extract($data);
                $files = glob($directoryPath . '/*.zip');
                $files = $this->parseFiles($files);
                if ($files) {
                    $this->copyFiles($files, $library, $vs_version_targets);
                    $this->updateSeriesFiles($files, $library, $php_versions, $vs_version_targets, $stability);
                }

                (new Helpers)->rmdirr($directoryPath);

                unlink($directoryPath . '.lock');
            }
            return Command::SUCCESS;
        } catch (Exception $e) {
            echo $e->getMessage();
            return Command::FAILURE;
        }
    }

    private function parseFiles(array $files): array
    {
        $data = [];
        foreach ($files as $file) {
            $fileName = basename($file);
            $fileNameParts = explode('.', $fileName);
            $parsedFileNameParts = explode('-', $fileName);
            $archParts = explode('.', $parsedFileNameParts[3]);
            $data[] = [
                'file_path' => $file,
                'file_name' => $fileName,
                'extension' => $fileNameParts[count($fileNameParts)-1],
                'artifact_name' => $parsedFileNameParts[0],
                'vs_version' => $parsedFileNameParts[2],
                'arch' => $archParts[0],
            ];
        }
        return $data;
    }

    private function copyFiles(array $files, string $library, string $vs_version_targets): void
    {
        $baseDirectory = $this->baseDirectory . "/php-sdk/deps";
        if (!is_dir($baseDirectory)) {
            mkdir($baseDirectory, 0755, true);
        }
        $vs_version_targets = explode(',', $vs_version_targets);
        foreach ($files as $file) {
            foreach ($vs_version_targets as $vs_version_target) {
                $destinationDirectory = $baseDirectory . '/' . $vs_version_target . '/' . $file['arch'];
                if (!is_dir($destinationDirectory)) {
                    mkdir($destinationDirectory, 0755, true);
                }
                $destinationFileName = str_replace($file['artifact_name'], $library, $file['file_name']);
                copy($file['file_path'], $destinationDirectory . '/' . $destinationFileName);
            }
        }
    }

    private function updateSeriesFiles(
        array  $files,
        string $library,
        string $php_versions,
        string $vs_version_targets,
        string $stability
    ): void
    {
        $php_versions = explode(',', $php_versions);
        $vs_version_targets = explode(',', $vs_version_targets);
        $stability_values = explode(',', $stability);

        $baseDirectory = $this->baseDirectory . "/php-sdk/deps/series";

        if (!is_dir($baseDirectory)) {
            mkdir($baseDirectory, 0755, true);
        }

        foreach ($php_versions as $php_version) {
            foreach ($vs_version_targets as $vs_version_target) {
                foreach ($stability_values as $stability_value) {
                    foreach ($files as $file) {
                        $fileName = str_replace($file['artifact_name'], $library, $file['file_name']);
                        $arch = $file['arch'];
                        $seriesFile = $baseDirectory . "/packages-$php_version-$vs_version_target-$arch-$stability_value.txt";
                        if (!file_exists($seriesFile)) {
                            $file_lines = [$fileName];
                        } else {
                            $file_lines = file($seriesFile, FILE_IGNORE_NEW_LINES);
                            $found = false;
                            foreach ($file_lines as $no => $line) {
                                if (str_starts_with($line, $library)) {
                                    $file_lines[$no] = $fileName;
                                    $found = true;
                                }
                            }
                            if (!$found) {
                                $file_lines[] = $fileName;
                            }
                        }
                        file_put_contents($seriesFile, implode("\n", $file_lines));
                    }
                }
            }
        }
    }
}