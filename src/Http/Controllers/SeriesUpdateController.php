<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\BaseController;
use App\Validator;

class SeriesUpdateController extends BaseController
{
    protected function validate(array $data): bool
    {
        $validator = new Validator([
            'php_version' => 'required|string|regex:/^(?:\d+\.\d+|master)$/',
            'vs_version' => 'required|string|regex:/^v[c|s]\d{2}$/',
            'stability' => 'required|string|regex:/^(stable|staging)$/',
            'library' => 'required|string',
            'ref' => 'string',
        ]);

        $validator->validate($data);

        if (!$validator->isValid()) {
            http_response_code(400);
            echo 'Invalid request: ' . $validator;
            return false;
        }

        return true;
    }

    protected function execute(array $data): void
    {
        $directory = rtrim((string) getenv('BUILDS_DIRECTORY'), '/');
        if ($directory === '') {
            http_response_code(500);
            echo 'Invalid server configuration: BUILDS_DIRECTORY is not set.';
            return;
        }

        $seriesDirectory = $directory . '/series';
        if (!is_dir($seriesDirectory)) {
            mkdir($seriesDirectory, 0755, true);
        }

        $payload = [
            'php_version' => $data['php_version'],
            'vs_version' => $data['vs_version'],
            'stability' => $data['stability'],
            'library' => $data['library'],
            'ref' => $data['ref'],
        ];

        $hash = hash('sha256', $data['php_version'] . $data['vs_version'] . $data['library']) . microtime(true);
        $file = $seriesDirectory . '/series-update-' . $hash . '.json';

        file_put_contents($file, json_encode($payload));
    }
}