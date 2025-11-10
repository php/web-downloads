<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\Helpers;
use App\Http\BaseController;
use App\Validator;
use JsonException;
use RuntimeException;

class DeletePendingJobController extends BaseController
{
    private string $buildsDirectory;

    public function __construct(string $inputPath = 'php://input', ?string $buildsDirectory = null)
    {
        parent::__construct($inputPath);

        $this->buildsDirectory = $buildsDirectory ?? (getenv('BUILDS_DIRECTORY') ?: '');
    }

    protected function validate(array $data): bool
    {
        $validator = new Validator([
            'type' => 'required|string|regex:/^(php|pecl|winlibs)$/i',
            'job' => 'required|string|regex:/^[A-Za-z0-9._-]+$/',
        ]);

        $validator->validate($data);

        $valid = $validator->isValid();

        if (!$valid) {
            http_response_code(400);
            echo 'Invalid request: ' . $validator;
        }

        return $valid;
    }

    protected function execute(array $data): void
    {
        if ($this->buildsDirectory === '') {
            http_response_code(500);
            echo 'Error: Builds directory is not configured.';
            return;
        }

        $type = strtolower($data['type']);
        $jobName = $data['job'];

        try {
            $this->deleteJob($type, $jobName);
            http_response_code(200);
            $this->outputJson(['status' => 'deleted']);
        } catch (RuntimeException $runtimeException) {
            $status = $runtimeException->getCode() ?: 500;
            http_response_code($status);
            echo 'Error: ' . $runtimeException->getMessage();
        } catch (JsonException) {
            http_response_code(500);
            echo 'Error: Failed to encode response.';
        }
    }

    private function deleteJob(string $type, string $jobName): void
    {
        $path = $this->resolvePath($type, $jobName);

        if ($type === 'winlibs') {
            $this->deleteDirectoryJob($path);
        } else {
            $this->deleteFileJob($path);
        }
    }

    private function resolvePath(string $type, string $jobName): string
    {
        return match ($type) {
            'php', 'pecl' => $this->buildsDirectory . '/' . $type . '/' . $jobName,
            'winlibs' => $this->buildsDirectory . '/winlibs/' . $jobName,
            default => $this->buildsDirectory,
        };
    }

    private function deleteFileJob(string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('Job not found.', 404);
        }

        if (!@unlink($filePath)) {
            throw new RuntimeException('Unable to delete job file.', 500);
        }

        $lockFile = $filePath . '.lock';
        if (is_file($lockFile)) {
            @unlink($lockFile);
        }
    }

    private function deleteDirectoryJob(string $directoryPath): void
    {
        if (!is_dir($directoryPath)) {
            throw new RuntimeException('Job not found.', 404);
        }

        $helper = new Helpers();
        if (!$helper->rmdirr($directoryPath)) {
            throw new RuntimeException('Unable to delete job directory.', 500);
        }

        $lockFile = $directoryPath . '.lock';
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    }

    /**
     * @throws JsonException
     */
    private function outputJson(array $payload): void
    {
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}