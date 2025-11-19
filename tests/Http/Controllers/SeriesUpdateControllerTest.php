<?php
declare(strict_types=1);

namespace Http\Controllers;

use App\Helpers\Helpers;
use App\Http\Controllers\SeriesUpdateController;
use PHPUnit\Framework\TestCase;

class SeriesUpdateControllerTest extends TestCase
{
    private string $buildsDirectory;
    private ?string $originalBuildsDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildsDirectory = sys_get_temp_dir() . '/series_update_controller_' . uniqid();
        mkdir($this->buildsDirectory, 0755, true);

        $this->originalBuildsDirectory = getenv('BUILDS_DIRECTORY') ?: null;
        putenv('BUILDS_DIRECTORY=' . $this->buildsDirectory);
    }

    protected function tearDown(): void
    {
        putenv($this->originalBuildsDirectory === null ? 'BUILDS_DIRECTORY' : 'BUILDS_DIRECTORY=' . $this->originalBuildsDirectory);
        (new Helpers)->rmdirr($this->buildsDirectory);
        parent::tearDown();
    }

    public function testEnqueuesUpdateTask(): void
    {
        $payload = [
            'php_version' => '8.2',
            'vs_version' => 'vs16',
            'stability' => 'stable',
            'library' => 'zlib',
            'ref' => '1.2.13',
        ];

        $inputPath = $this->createInputFile($payload);

        $controller = new SeriesUpdateController($inputPath);
        $controller->handle();
        unlink($inputPath);

        $taskFiles = glob($this->buildsDirectory . '/series/series-update-*.json');
        $this->assertNotEmpty($taskFiles);

        $taskData = json_decode(file_get_contents($taskFiles[0]), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($payload, $taskData);
    }

    public function testEnqueuesDeleteTaskWithoutPackage(): void
    {
        $payload = [
            'php_version' => '8.1',
            'vs_version' => 'vs17',
            'stability' => 'staging',
            'library' => 'libcurl',
            'ref' => '',
        ];

        $inputPath = $this->createInputFile($payload);

        $controller = new SeriesUpdateController($inputPath);
        $controller->handle();
        unlink($inputPath);

        $taskFiles = glob($this->buildsDirectory . '/series/series-update-*.json');
        $this->assertNotEmpty($taskFiles);
        $taskData = json_decode(file_get_contents($taskFiles[0]), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($payload, $taskData);
    }

    public function testRejectsInvalidPackageName(): void
    {
        $payload = [
            'php_version' => '8.0',
            'vs_version' => 'vs16',
            'stability' => 'stable',
            'library' => 'openssl',
            // ref missing entirely
        ];

        $inputPath = $this->createInputFile($payload);
        $controller = new SeriesUpdateController($inputPath);

        ob_start();
        $controller->handle();
        $output = ob_get_clean();
        unlink($inputPath);

        $this->assertStringContainsString('The ref field must be a string.', $output);
        $this->assertEmpty(glob($this->buildsDirectory . '/series/series-update-*.json'));
    }

    private function createInputFile(array $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'series-update-input-');
        file_put_contents($path, json_encode($data));
        return $path;
    }
}