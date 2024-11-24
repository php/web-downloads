<?php

namespace App;

use Exception;
use ZipArchive;

class PeclHandler extends BaseHandler
{
    protected function validate(mixed $data): bool
    {
        $validator = new Validator([
            'url' => 'required|url',
            'extension' => 'required|string',
            'ref' => 'required|string',
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
    private function fetchExtension(string $extension, string $ref, string $url, string $token): void
    {
        $filepath = getenv('BUILDS_DIRECTORY') . "/pecl/$extension-$ref-" . hash('sha256', $url) . strtotime('now') . ".zip";

        FetchArtifact::handle($url, $filepath, $token);

        if(!file_exists($filepath) || mime_content_type($filepath) !== 'application/zip') {
            throw new Exception('Failed to fetch the extension');
        }
    }
}