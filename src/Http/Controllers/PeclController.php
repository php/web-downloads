<?php

namespace App\Http\Controllers;

use App\Actions\FetchArtifact;
use App\Http\BaseController;
use App\Validator;
use Exception;

class PeclController extends BaseController
{
    public function validate(array $data): bool
    {
        $validator = new Validator([
            'url' => 'required|url',
            'extension' => 'required|string',
            'ref' => 'required|string',
        ]);

        $validator->validate($data);

        $valid = $validator->isValid();

        if (!$valid) {
            http_response_code(400);
            echo 'Invalid request: ' . $validator;
        }

        return $valid;
    }

    public function execute(array $data): void
    {
        try {
            extract($data);
            $this->fetchExtension($extension, $ref, $url, $token ?? '');
        } catch (Exception $exception) {
            http_response_code(500);
            echo 'Error: ' . $exception->getMessage();
        }
    }

    /**
     * @throws Exception
     */
    protected function fetchExtension(string $extension, string $ref, string $url, string $token): void
    {
        $directory = getenv('BUILDS_DIRECTORY') . "/pecl";
        $filepath = $directory . "/$extension-$ref-" . hash('sha256', $url) . strtotime('now') . ".zip";

        if(!is_dir($directory)) {
            umask(0);
            mkdir($directory, 0777, true);
        }

        (new FetchArtifact)->handle($url, $filepath, $token);

        if (!file_exists($filepath) || mime_content_type($filepath) !== 'application/zip') {
            throw new Exception('Failed to fetch the extension');
        }
    }
}