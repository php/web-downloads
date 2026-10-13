<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\BaseController;
use App\Validator;

class SeriesInitController extends BaseController
{
    protected function validate(array $data): bool
    {
        $data += ['subject' => 'vs'];
        $rules = [
            'subject' => 'required|string|regex:/\A(?:vs|arch)\z/',
            'php_version' => $data['subject'] === 'arch'
                ? 'required|string|regex:/\A(?:\d+\.\d+|master)\z/'
                : 'required|string|regex:/^\d+\.\d+$/',
        ];
        if ($data['subject'] === 'arch') {
            $rules['source_arch'] = 'required|string|regex:/\A(?:x86|x64|arm64)\z/';
            $rules['target_arch'] = 'required|string|regex:/\A(?:x86|x64|arm64)\z/';
        } else {
            $rules['source_vs'] = 'required|string|regex:/^v[c|s]\d{2}$/';
            $rules['target_vs'] = 'required|string|regex:/^v[c|s]\d{2}$/';
        }
        $validator = new Validator($rules);

        $validator->validate($data);

        $valid = $validator->isValid;

        if (!$valid) {
            http_response_code(400);
            echo 'Invalid request: ' . $validator;
        }

        return $valid;
    }

    protected function execute(array $data): void
    {
        $directory = getenv('BUILDS_DIRECTORY') . '/series';
        $hash = hash('sha256', (string) $data['php_version']) . uniqid('', true);
        $file = $directory . '/series-init-' . $hash . '.json';
        file_put_contents($file, json_encode($data));
    }
}
