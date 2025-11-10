<?php
declare(strict_types=1);

namespace Http\Controllers;

use App\Http\Controllers\ListBuildsController;
use JsonException;
use PHPUnit\Framework\TestCase;

class ListBuildsControllerTest extends TestCase
{
    public function testHandleOutputsBuildListing(): void
    {
        $tempDir = $this->createTempBuildDirectory();

        $controller = new ListBuildsController($tempDir);

        http_response_code(200);
        ob_start();
        $controller->handle();
        $output = ob_get_clean();

        static::assertNotFalse($output);

        $data = $this->decodeJson($output);

        static::assertSame(200, http_response_code());
        static::assertArrayHasKey('builds', $data);
        static::assertCount(2, $data['builds']);
        static::assertSame('php/build-one.zip', $data['builds'][0]['path']);
        static::assertSame('winlibs/run/info.json', $data['builds'][1]['path']);

        $this->removeDirectory($tempDir);
    }

    public function testHandleReturnsErrorWhenDirectoryMissing(): void
    {
        $controller = new ListBuildsController('/path/to/missing/builds');

        http_response_code(200);
        ob_start();
        $controller->handle();
        $output = ob_get_clean();

        $data = $this->decodeJson($output);

        static::assertSame(500, http_response_code());
        static::assertSame('Builds directory not configured or missing.', $data['error']);
    }

    private function decodeJson(string $json): array
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            static::fail('Response is not valid JSON: ' . $exception->getMessage());
        }

        return [];
    }

    private function createTempBuildDirectory(): string
    {
        $base = sys_get_temp_dir() . '/list-builds-' . uniqid();
        mkdir($base . '/php', 0755, true);
        mkdir($base . '/winlibs/run', 0755, true);

        file_put_contents($base . '/php/build-one.zip', 'fake-zip-data');
        touch($base . '/php/build-one.zip', 1730000000);

        file_put_contents($base . '/winlibs/run/info.json', '{}');
        touch($base . '/winlibs/run/info.json', 1730003600);

        return $base;
    }

    private function removeDirectory(string $path): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
