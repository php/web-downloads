<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\Helpers;
use App\Http\BaseController;
use App\Validator;
use Throwable;

class WinlibsDeleteController extends BaseController
{
    protected function validate(array $data): bool
    {
        $validator = new Validator([
            'type' => 'required|string|regex:/\A(php|pecl)\z/',
            'filename' => 'required|string|regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\.zip\z/',
        ]);

        $validator->validate($data);
        if (!$validator->isValid) {
            http_response_code(400);
            echo 'Invalid request: ' . $validator;
            return false;
        }

        return true;
    }

    protected function execute(array $data): void
    {
        $buildsDirectory = rtrim((string) getenv('BUILDS_DIRECTORY'), '/');
        if ($buildsDirectory === '') {
            http_response_code(500);
            echo 'Invalid server configuration: BUILDS_DIRECTORY is not set.';
            return;
        }

        $queueDirectory = $buildsDirectory . '/winlibs';
        if (!is_dir($queueDirectory) || !is_writable($queueDirectory)) {
            http_response_code(500);
            echo 'Winlibs build queue is not writable.';
            return;
        }

        try {
            $this->queueJob($queueDirectory, $data['type'], $data['filename']);
        } catch (Throwable $error) {
            http_response_code(500);
            echo 'Unable to queue Winlibs deletion: ' . $error->getMessage();
        }
    }

    private function queueJob(string $queueDirectory, string $type, string $filename): void
    {
        $id = bin2hex(random_bytes(12));
        $stagingDirectory = $queueDirectory . '/.delete-' . $id;
        $jobDirectory = $queueDirectory . '/delete-' . $id;
        $previousUmask = umask(0);
        try {
            if (!@mkdir($stagingDirectory, 0777)) {
                throw new \RuntimeException('Unable to create deletion job directory.');
            }
        } finally {
            umask($previousUmask);
        }

        try {
            $payload = json_encode([
                'delete' => true,
                'type' => $type,
                'filename' => $filename,
            ], JSON_THROW_ON_ERROR);
            $dataFile = $stagingDirectory . '/data.json';
            if (file_put_contents($dataFile, $payload, LOCK_EX) === false
                || !chmod($dataFile, 0644)
                || !rename($stagingDirectory, $jobDirectory)) {
                throw new \RuntimeException('Unable to publish deletion job.');
            }
        } finally {
            if (is_dir($stagingDirectory)) {
                Helpers::rmdirr($stagingDirectory);
            }
        }
    }
}
