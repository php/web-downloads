<?php

namespace App;

class WinlibsHandler extends BaseHandler
{
    protected function validate(array $data): bool
    {
        $validator = new Validator([
            'library' => 'required|string',
            'ref' => 'required|string',
            'workflow_run_id' => 'required|string',
            'php_versions' => 'required|string|regex:/^\d+\.\d+$}|^master$/',
            'vs_version' => 'required|string|regex:/^(v[c|s]\d{2})(,v[c|s]\d{2})*$/',
            'vs_version_targets' => 'required|string|regex:/^v[c|s]\d{2}$/',
            'stability' => 'required|string|regex:/^(stable|staging)(,(stable|staging))?$/',
            'token' => 'required|string',
        ]);

        $validator->validate($data);

        $valid = $validator->isValid();

        if(!$valid) {
            http_response_code(400);
            echo 'Invalid request: ' . $validator;
        }

        return $valid;
    }

    protected function execute(array $data): void
    {
        extract($data);
        GetArtifacts::handle($workflow_run_id, $token);
        $directory = getenv('BUILDS_DIRECTORY') . '/winlibs/'. $workflow_run_id;
        file_put_contents( $directory . '/data.json', json_encode($data));
    }
}