<?php

namespace App\Http\Controllers;

use App\Http\BaseController;
use App\Validator;

class SeriesDeleteController extends BaseController
{
    protected function validate(array $data): bool
    {
        $validator = new Validator([
            'php_version' => 'required|string:regex:/^(\d+\.\d+|master)$/',
            'vs_version' => 'required|string|regex:/^v[c|s]\d{2}$/',
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
        extract($data);
        $directory = getenv('BUILDS_DIRECTORY') . '/series';
        $hash = hash('sha256', $php_version) . strtotime('now');
        $file = $directory . '/series-delete-' . $hash . '.json';
        file_put_contents($file, json_encode($data));
    }
}