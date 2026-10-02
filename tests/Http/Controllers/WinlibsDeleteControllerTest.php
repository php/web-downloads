<?php
declare(strict_types=1);

namespace Http\Controllers;

use App\Console\Command\WinlibsDeleteCommand;
use App\Helpers\Helpers;
use App\Http\Controllers\WinlibsDeleteController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WinlibsDeleteControllerTest extends TestCase
{
    private string $buildsDirectory;
    private string|false $originalBuildsDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildsDirectory = sys_get_temp_dir() . '/winlibs_delete_controller_' . uniqid();
        mkdir($this->buildsDirectory, 0755, true);
        mkdir($this->buildsDirectory . '/winlibs', 0777);
        $this->originalBuildsDirectory = getenv('BUILDS_DIRECTORY');
        putenv('BUILDS_DIRECTORY=' . $this->buildsDirectory);
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        putenv($this->originalBuildsDirectory === false
            ? 'BUILDS_DIRECTORY'
            : 'BUILDS_DIRECTORY=' . $this->originalBuildsDirectory);
        Helpers::rmdirr($this->buildsDirectory);
        http_response_code(200);
        parent::tearDown();
    }

    public function testQueuesExactBuildForBothTypes(): void
    {
        $this->request(['type' => 'php', 'filename' => 'libcurl-8.22.0-1-vs18-x64.zip']);
        $this->request(['type' => 'pecl', 'filename' => 'OpenBLAS-0.3.34-vs18-x86.zip']);

        $tasks = $this->queuedTasks();
        $this->assertCount(2, $tasks);
        $payloads = array_map(static fn (string $path): array => json_decode(
            (string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR
        ), $tasks);
        $this->assertContains(['delete' => true, 'type' => 'php', 'filename' => 'libcurl-8.22.0-1-vs18-x64.zip'], $payloads);
        $this->assertContains(['delete' => true, 'type' => 'pecl', 'filename' => 'OpenBLAS-0.3.34-vs18-x86.zip'], $payloads);
        foreach ($tasks as $task) {
            $this->assertSame(0777, fileperms(dirname($task)) & 0777);
        }
        $this->assertSame(200, http_response_code());
    }

    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidPayload(array $payload): void
    {
        $output = $this->request($payload);
        $this->assertSame(400, http_response_code());
        $this->assertStringContainsString('Invalid request:', $output);
        $this->assertSame([], $this->queuedTasks());
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing type' => [['filename' => 'zlib-1.3.2-vs18-x64.zip']],
            'bad type' => [['type' => 'other', 'filename' => 'zlib-1.3.2-vs18-x64.zip']],
            'php type with trailing newline' => [['type' => "php\n", 'filename' => 'zlib-1.3.2-vs18-x64.zip']],
            'pecl type with trailing newline' => [['type' => "pecl\n", 'filename' => 'zlib-1.3.2-vs18-x64.zip']],
            'missing filename' => [['type' => 'php']],
            'php filename with trailing newline' => [['type' => 'php', 'filename' => "zlib-1.3.2-vs18-x64.zip\n"]],
            'pecl filename with trailing newline' => [['type' => 'pecl', 'filename' => "zlib-1.3.2-vs18-x64.zip\n"]],
            'path traversal' => [['type' => 'php', 'filename' => '../zlib.zip']],
            'nested path' => [['type' => 'php', 'filename' => 'vs18/x64/zlib.zip']],
            'non zip' => [['type' => 'pecl', 'filename' => 'packages.txt']],
            'empty filename' => [['type' => 'pecl', 'filename' => '']],
        ];
    }

    public function testRejectsMissingBuildsDirectoryConfiguration(): void
    {
        putenv('BUILDS_DIRECTORY');
        $output = $this->request(['type' => 'php', 'filename' => 'zlib-1.3.2-vs18-x64.zip']);
        $this->assertSame(500, http_response_code());
        $this->assertStringContainsString('BUILDS_DIRECTORY is not set', $output);
    }

    public function testRequiresExistingWinlibsQueue(): void
    {
        rmdir($this->buildsDirectory . '/winlibs');
        $output = $this->request(['type' => 'php', 'filename' => 'zlib-1.3.2-vs18-x64.zip']);
        $this->assertSame(500, http_response_code());
        $this->assertStringContainsString('Winlibs build queue is not writable', $output);
        $this->assertSame([], $this->queuedTasks());
    }

    public function testQueuedRequestIsAppliedByWinlibsDeleteCommand(): void
    {
        $baseDirectory = sys_get_temp_dir() . '/winlibs_delete_api_base_' . uniqid();
        $depsDirectory = $baseDirectory . '/php-sdk/deps';
        mkdir($depsDirectory . '/series', 0755, true);
        mkdir($depsDirectory . '/vs18/x64', 0755, true);
        $target = 'libcurl-8.22.0-1-vs18-x64.zip';
        $older = 'libcurl-8.22.0-vs18-x64.zip';
        $index = $depsDirectory . '/series/packages-8.6-vs18-x64-stable.txt';
        file_put_contents($index, "$older\n$target");
        file_put_contents($depsDirectory . '/vs18/x64/' . $target, 'zip');

        try {
            $this->request(['type' => 'php', 'filename' => $target]);
            $command = new WinlibsDeleteCommand();
            $command->options = [
                'base-directory' => $baseDirectory,
                'builds-directory' => $this->buildsDirectory,
            ];
            $this->assertSame(0, $command->handle());
            $this->assertSame($older, file_get_contents($index));
            $this->assertFileDoesNotExist($depsDirectory . '/vs18/x64/' . $target);
            $this->assertSame([], $this->queuedTasks());
        } finally {
            Helpers::rmdirr($baseDirectory);
        }
    }

    private function queuedTasks(): array
    {
        return glob($this->buildsDirectory . '/winlibs/delete-*/data.json') ?: [];
    }

    private function request(array $payload): string
    {
        $input = tempnam(sys_get_temp_dir(), 'winlibs-delete-input-');
        file_put_contents($input, json_encode($payload, JSON_THROW_ON_ERROR));
        try {
            ob_start();
            (new WinlibsDeleteController($input))->handle();
            return (string) ob_get_clean();
        } finally {
            unlink($input);
        }
    }
}
