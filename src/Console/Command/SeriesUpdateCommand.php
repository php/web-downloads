<?php
declare(strict_types=1);

namespace App\Console\Command;

use App\Console\Command;
use Exception;

class SeriesUpdateCommand extends Command
{
    protected string $signature = 'series:update --base-directory= --builds-directory=';
    protected string $description = 'Update or remove libraries in series package files';

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

            $seriesDirectory = $buildsDirectory . '/series';
            if (!is_dir($seriesDirectory)) {
                return Command::SUCCESS;
            }

            $tasks = glob($seriesDirectory . '/series-update-*.json');
            $pendingTasks = [];

            foreach ($tasks as $taskFile) {
                $lockFile = $taskFile . '.lock';
                if (!file_exists($lockFile)) {
                    touch($lockFile);
                    $pendingTasks[] = $taskFile;
                }
            }

            foreach ($pendingTasks as $taskFile) {
                $data = $this->decodeTask($taskFile);

                $this->updateSeriesFiles(
                    $data['php_version'],
                    $data['vs_version'],
                    $data['stability'],
                    $data['library'],
                    $data['ref']
                );

                unlink($taskFile);
                unlink($taskFile . '.lock');
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            echo $e->getMessage();
            return Command::FAILURE;
        }
    }

    private function decodeTask(string $taskFile): array
    {
        $data = json_decode(file_get_contents($taskFile), true, 512, JSON_THROW_ON_ERROR);

        $required = ['php_version', 'vs_version', 'stability', 'library', 'ref'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new Exception("Missing field: $field");
            }
        }

        if (!is_string($data['ref'])) {
            throw new Exception('Invalid field: ref');
        }

        return $data;
    }

    private function updateSeriesFiles(
        string $phpVersion,
        string $vsVersion,
        string $stability,
        string $library,
        string $ref
    ): void {
        $seriesDirectory = $this->baseDirectory . '/php-sdk/deps/series';
        if (!is_dir($seriesDirectory)) {
            mkdir($seriesDirectory, 0755, true);
        }

        $arches = ['x86', 'x64'];

        foreach ($arches as $arch) {
            $filePath = $seriesDirectory . "/packages-$phpVersion-$vsVersion-$arch-$stability.txt";

            $lines = [];
            if (file_exists($filePath)) {
                $lines = file($filePath, FILE_IGNORE_NEW_LINES);
            }

            $refValue = trim($ref);
            $package = $refValue === '' ? null : sprintf('%s-%s-%s-%s.zip', $library, $refValue, $vsVersion, $arch);

            $replaced = false;
            foreach ($lines as $index => $line) {
                if (str_starts_with($line, $library . '-')) {
                    if ($package === null) {
                        unset($lines[$index]);
                    } elseif (!$replaced) {
                        $lines[$index] = $package;
                        $replaced = true;
                    } else {
                        unset($lines[$index]);
                    }
                }
            }

            $lines = array_values($lines);

            if ($package !== null && !$replaced) {
                $lines[] = $package;
            }

            if (empty($lines)) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                continue;
            }

            $tmpFile = $filePath . '.tmp';
            file_put_contents($tmpFile, implode("\n", $lines), LOCK_EX);
            rename($tmpFile, $filePath);
        }
    }
}