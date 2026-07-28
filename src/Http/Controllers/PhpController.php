<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\FetchArtifact;
use App\Http\BaseController;
use App\Http\DeferredCallbacks;
use App\Validator;
use Exception;
use RuntimeException;
use SensitiveParameter;

class PhpController extends BaseController
{
    protected function validate(array $data): bool
    {
        $validator = new Validator([
            'url' => 'required|url',
            'token' => 'required|string',
        ]);

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
        try {
            $filepath = $this->prepareBuildFilepath($data['url']);
        } catch (Exception $exception) {
            http_response_code(500);
            echo 'Error: ' . $exception->getMessage();
            return;
        }

        http_response_code(200);
        header('Content-Length: 0');

        DeferredCallbacks::add(
            fn () => $this->fetchPhpBuild($data['url'], $data['token'], $filepath),
        );
    }

    /**
     * @throws Exception
     */
    protected function fetchPhpBuild(
        string $url,
        #[SensitiveParameter] string $token,
        string $filepath,
    ): void
    {
        $temporaryFilepath = $filepath . '.part';

        try {
            (new FetchArtifact(4))->handle($url, $temporaryFilepath, $token);

            if (
                !file_exists($temporaryFilepath)
                || mime_content_type($temporaryFilepath) !== 'application/zip'
            ) {
                throw new RuntimeException('Failed to fetch the PHP build');
            }

            if (!chmod($temporaryFilepath, 0777)) {
                throw new RuntimeException('Failed to set permissions on the PHP build');
            }

            if (!rename($temporaryFilepath, $filepath)) {
                throw new RuntimeException('Failed to publish the PHP build');
            }
        } finally {
            if (file_exists($temporaryFilepath)) {
                @unlink($temporaryFilepath);
            }
        }
    }

    private function prepareBuildFilepath(string $url): string
    {
        $buildsDirectory = getenv('BUILDS_DIRECTORY');
        if ($buildsDirectory === false || $buildsDirectory === '') {
            throw new RuntimeException('Builds directory is not configured');
        }

        $directory = $buildsDirectory . '/php';

        if (!is_dir($directory)) {
            $previousUmask = umask(0);

            try {
                if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                    throw new RuntimeException('Failed to create the PHP builds directory');
                }
            } finally {
                umask($previousUmask);
            }
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('PHP builds directory is not writable');
        }

        $hash = hash('sha256', $url) . uniqid('', true);

        return $directory . '/php-' . $hash . '.zip';
    }
}
