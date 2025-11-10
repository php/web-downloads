<?php
declare(strict_types=1);

namespace Http\Controllers;

use App\Helpers\Helpers;
use App\Http\Controllers\DeletePendingJobController;
use PHPUnit\Framework\TestCase;

class DeletePendingJobControllerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/delete-pending-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Helpers())->rmdirr($this->tempDir);
        parent::tearDown();
    }

    public function testDeletesPhpJobAndLock(): void
    {
        $phpDir = $this->tempDir . '/php';
        mkdir($phpDir, 0755, true);
        $jobFile = $phpDir . '/php-job.zip';
        file_put_contents($jobFile, 'artifact');
        file_put_contents($jobFile . '.lock', '');

        $payload = json_encode(['type' => 'php', 'job' => 'php-job.zip'], JSON_THROW_ON_ERROR);
        $inputFile = $this->createInputFile($payload);

        http_response_code(200);
        $controller = new DeletePendingJobController($inputFile, $this->tempDir);
        ob_start();
        $controller->handle();
        $output = ob_get_clean();

        static::assertSame(200, http_response_code());
        static::assertFalse(file_exists($jobFile));
        static::assertFalse(file_exists($jobFile . '.lock'));
        static::assertJsonStringEqualsJsonString('{"status":"deleted"}', $output);

        unlink($inputFile);
    }

    public function testDeletesWinlibsJobDirectory(): void
    {
        $winlibsDir = $this->tempDir . '/winlibs';
        mkdir($winlibsDir, 0755, true);
        $jobDir = $winlibsDir . '/12345';
        mkdir($jobDir, 0755, true);
        file_put_contents($jobDir . '/data.json', '{}');
        file_put_contents($jobDir . '.lock', '');

        $payload = json_encode(['type' => 'winlibs', 'job' => '12345'], JSON_THROW_ON_ERROR);
        $inputFile = $this->createInputFile($payload);

        http_response_code(200);
        $controller = new DeletePendingJobController($inputFile, $this->tempDir);
        ob_start();
        $controller->handle();
        ob_end_clean();

        static::assertSame(200, http_response_code());
        static::assertFalse(is_dir($jobDir));
        static::assertFalse(file_exists($jobDir . '.lock'));

        unlink($inputFile);
    }

    public function testReturns404WhenJobMissing(): void
    {
        $payload = json_encode(['type' => 'pecl', 'job' => 'missing.zip'], JSON_THROW_ON_ERROR);
        $inputFile = $this->createInputFile($payload);

        http_response_code(200);
        $controller = new DeletePendingJobController($inputFile, $this->tempDir);
        ob_start();
        $controller->handle();
        $output = ob_get_clean();

        static::assertSame(404, http_response_code());
        static::assertStringContainsString('Job not found', $output);

        unlink($inputFile);
    }

    private function createInputFile(string $json): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'delete-pending-input-');
        file_put_contents($tempFile, $json);

        return $tempFile;
    }
}