<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\ControllerInterface;
use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ListBuildsController implements ControllerInterface
{
    public function __construct(private ?string $buildsDirectory = null)
    {
        if ($this->buildsDirectory === null) {
            $this->buildsDirectory = getenv('BUILDS_DIRECTORY') ?: '';
        }
    }

    public function handle(): void
    {
        if ($this->buildsDirectory === '' || !is_dir($this->buildsDirectory)) {
            http_response_code(500);
            $this->outputJson(['error' => 'Builds directory not configured or missing.']);
            return;
        }

        $builds = $this->collectBuilds($this->buildsDirectory);

        http_response_code(200);
        $this->outputJson(['builds' => $builds]);
    }

    private function collectBuilds(string $root): array
    {
        $entries = [];

            $normalizedRoot = rtrim($root, DIRECTORY_SEPARATOR);

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($normalizedRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

                $relativePath = substr($fileInfo->getPathname(), strlen($normalizedRoot) + 1);

            $entries[] = [
                'path' => $relativePath,
                'size' => $fileInfo->getSize(),
                'modified' => gmdate('c', $fileInfo->getMTime()),
            ];
        }

        usort($entries, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return $entries;
    }

    private function outputJson(array $payload): void
    {
        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (JsonException) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo '{"error":"Failed to encode response."}';
            return;
        }

        header('Content-Type: application/json');
        echo $json;
    }
}
