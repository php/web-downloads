<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\FetchArtifact;
use App\Http\BaseController;
use App\Validator;
use Exception;

class PhpController extends BaseController
{
    protected function validate(array $data): bool
    {
        $validator = new Validator([
            'url' => 'required|url',
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
        try {
            $this->fetchPhpBuild($data['url'], $data['token']);
        } catch (Exception $exception) {
            http_response_code(500);
            echo 'Error: ' . $exception->getMessage();
        }
    }

    /**
     * @throws Exception
     */
    private function fetchPhpBuild(string $url, #[\SensitiveParameter] string $token): void
    {
        $hash = hash('sha256', $url) . time();

        $directory = getenv('BUILDS_DIRECTORY') . "/php";

        $filepath = $directory . "/php-" . $hash . ".zip";

        if(!is_dir($directory)) {
            umask(0);
            mkdir($directory, 0777, true);
        }

        (new FetchArtifact)->handle($url, $filepath, $token);

        if (!file_exists($filepath) || mime_content_type($filepath) !== 'application/zip') {
            throw new Exception('Failed to fetch the PHP build');
        }

        chmod($filepath, 0777);
    }
}