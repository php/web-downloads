<?php
declare(strict_types=1);

namespace App\Console\Command;

use App\Actions\DeleteWinlibsBuild;
use App\Console\Command;
use App\Helpers\Helpers;
use Exception;
use Throwable;

class WinlibsDeleteCommand extends Command
{
    public string $signature = 'winlibs:delete --base-directory= --builds-directory=';
    public string $description = 'Process queued Winlibs deletions';

    public function handle(): int
    {
        try {
            $baseDirectory = $this->options['base-directory'] ?? null;
            if (!$baseDirectory) {
                throw new Exception('Base directory is required');
            }

            $buildsDirectory = $this->options['builds-directory'] ?? null;
            if (!$buildsDirectory) {
                throw new Exception('Build directory is required');
            }

            $jobDirectories = glob($buildsDirectory . '/winlibs/delete-*', GLOB_ONLYDIR);
            if ($jobDirectories === false) {
                throw new Exception('Unable to list Winlibs deletion jobs');
            }

            $deletions = new DeleteWinlibsBuild($baseDirectory);
            $errors = [];
            foreach ($jobDirectories as $directoryPath) {
                $lockFile = $directoryPath . '.lock';
                $lock = @fopen($lockFile, 'c');
                if ($lock === false) {
                    $errors[] = 'Unable to lock Winlibs job: ' . basename($directoryPath);
                    continue;
                }

                if (!flock($lock, LOCK_EX | LOCK_NB)) {
                    fclose($lock);
                    continue;
                }

                try {
                    $data = json_decode((string) file_get_contents($directoryPath . '/data.json'), true, 512, JSON_THROW_ON_ERROR);
                    if (($data['delete'] ?? false) !== true
                        || !is_string($data['type'] ?? null)
                        || !is_string($data['filename'] ?? null)) {
                        throw new Exception('Invalid Winlibs deletion job: ' . basename($directoryPath));
                    }

                    $deletions->handle($data['type'], $data['filename']);
                    if (!Helpers::rmdirr($directoryPath)) {
                        throw new Exception('Unable to remove Winlibs job: ' . basename($directoryPath));
                    }
                    unlink($lockFile);
                } catch (Throwable $error) {
                    $errors[] = $error->getMessage();
                } finally {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
            }

            if ($errors !== []) {
                throw new Exception(implode("\n", $errors));
            }
            return Command::SUCCESS;
        } catch (Throwable $error) {
            echo $error->getMessage();
            return Command::FAILURE;
        }
    }
}
