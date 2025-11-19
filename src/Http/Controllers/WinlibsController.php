<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GetArtifacts;
use App\Http\BaseController;
use App\Validator;

class WinlibsController extends BaseController
{
    protected function validate(array $data): bool
    {
        $validator = new Validator([
            'library' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/',
            'ref' => 'required|string|regex:/^[a-zA-Z0-9\.-]+$/',
            'type' => 'required|string|regex:/^(php|pecl)$/',
            'workflow_run_id' => 'required|string',
            'php_versions' => 'required|string|regex:/^(?:\d+\.\d+|master)(?:,\s*(?:\d+\.\d+|master))*$/',
            'vs_version_targets' => 'required|string|regex:/^(v[c|s]\d{2})(,v[c|s]\d{2})*$/',
            'stability' => 'required|string|regex:/^(stable|staging)(,(stable|staging))?$/',
            'token' => 'required|string',
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
        (new GetArtifacts)->handle($data['workflow_run_id'], $data['token']);
        $directory = getenv('BUILDS_DIRECTORY') . '/winlibs/' . $data['workflow_run_id'];
        file_put_contents($directory . '/data.json', json_encode($data));
    }
}