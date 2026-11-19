<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\BaseController;
use App\Validator;
use RuntimeException;

class PhpReleaseController extends BaseController
{
    private const string BUILD_FILENAME_PATTERN = 'php-[a-f0-9]{32}\.zip';

    protected function validate(array $data): bool
    {
        $validator = new Validator([
            'build' => 'required|string|regex:/\A(?:all|' . self::BUILD_FILENAME_PATTERN . ')\z/',
        ]);
        $validator->validate($data);

        if (!$validator->isValid) {
            http_response_code(400);
            echo 'Invalid request: ' . $validator;
        }

        return $validator->isValid;
    }

    protected function execute(array $data): void
    {
        $buildsDirectory = getenv('BUILDS_DIRECTORY');
        if ($buildsDirectory === false || $buildsDirectory === '') {
            http_response_code(500);
            echo 'Error: Builds directory is not configured.';
            return;
        }

        $queue = $buildsDirectory . '/php';
        $staging = $queue . '/staging';

        try {
            $builds = $data['build'] === 'all' ? $this->listCompletedBuilds($staging) : [$data['build']];

            foreach ($builds as $build) {
                $source = $staging . '/' . $build;
                $destination = $queue . '/' . $build;
                if (!is_file($source) || is_link($source)) {
                    throw new RuntimeException('Completed staged PHP build not found.', 404);
                }
                if (file_exists($destination) || is_link($destination)) {
                    throw new RuntimeException('A PHP build with this filename is already queued: ' . $build, 409);
                }
            }

            foreach ($builds as $build) {
                if (!@rename($staging . '/' . $build, $queue . '/' . $build)) {
                    throw new RuntimeException('Failed to queue staged PHP build ' . $build, 500);
                }
            }

            http_response_code(200);
        } catch (RuntimeException $error) {
            http_response_code($error->getCode());
            echo 'Error: ' . $error->getMessage();
        }
    }

    private function listCompletedBuilds(string $staging): array
    {
        if (!is_dir($staging)) {
            return [];
        }

        $entries = @scandir($staging);
        if ($entries === false) {
            throw new RuntimeException('Failed to list staged PHP builds.', 500);
        }

        return array_filter($entries, static function (string $build) use ($staging): bool {
            $path = $staging . '/' . $build;
            return preg_match('/\A' . self::BUILD_FILENAME_PATTERN . '\z/', $build) === 1
                && is_file($path) && !is_link($path);
        });
    }
}
