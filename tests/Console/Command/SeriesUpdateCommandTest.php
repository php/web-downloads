<?php
declare(strict_types=1);

namespace Console\Command;

use App\Console\Command\SeriesUpdateCommand;
use App\Helpers\Helpers;
use PHPUnit\Framework\TestCase;

class SeriesUpdateCommandTest extends TestCase
{
    private string $baseDirectory;
    private string $buildsDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDirectory = sys_get_temp_dir() . '/series_update_base_' . uniqid();
        $this->buildsDirectory = sys_get_temp_dir() . '/series_update_builds_' . uniqid();

        mkdir($this->baseDirectory, 0755, true);
        mkdir($this->buildsDirectory, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Helpers)->rmdirr($this->baseDirectory);
        (new Helpers)->rmdirr($this->buildsDirectory);
        parent::tearDown();
    }

    public function testMissingBaseDirectory(): void
    {
        $command = new SeriesUpdateCommand();
        $command->setOption('builds-directory', $this->buildsDirectory);

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Base directory is required', $output);
    }

    public function testMissingBuildsDirectory(): void
    {
        $command = new SeriesUpdateCommand();
        $command->setOption('base-directory', $this->baseDirectory);

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Build directory is required', $output);
    }

    public function testFailsWhenNoSeriesDirectory(): void
    {
        $command = new SeriesUpdateCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Series directory does not exist', $output);
    }

    public function testUpdatesExistingLibraryEntry(): void
    {
        $seriesDirectory = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesDirectory, 0755, true);

        $filePathX64 = $seriesDirectory . '/packages-8.2-vs16-x64-stable.txt';
        $filePathX86 = $seriesDirectory . '/packages-8.2-vs16-x86-stable.txt';

        file_put_contents($filePathX64, implode("\n", [
            'libxml2-2.9.14-vs16-x64.zip',
            'openssl-1.1.1w-vs16-x64.zip',
        ]));

        file_put_contents($filePathX86, implode("\n", [
            'zlib-1.2.13-vs16-x86.zip',
            'libxml2-2.9.14-vs16-x86.zip',
        ]));

        $this->createTask([
            'php_version' => '8.2',
            'vs_version' => 'vs16',
            'stability' => 'stable',
            'library' => 'libxml2',
            'ref' => '2.9.15',
        ]);

        $command = new SeriesUpdateCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();

        $this->assertSame(0, $result);

        $x64Lines = file($filePathX64, FILE_IGNORE_NEW_LINES);
        $this->assertSame([
            'libxml2-2.9.15-vs16-x64.zip',
            'openssl-1.1.1w-vs16-x64.zip',
        ], $x64Lines);

        $x86Lines = file($filePathX86, FILE_IGNORE_NEW_LINES);
        $this->assertSame([
            'zlib-1.2.13-vs16-x86.zip',
            'libxml2-2.9.15-vs16-x86.zip',
        ], $x86Lines);

        $this->assertEmpty(glob($this->buildsDirectory . '/series/series-update-*.json'));
    }

    public function testRemovesLibraryWhenNoPackageProvided(): void
    {
        $seriesDirectory = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesDirectory, 0755, true);

        $filePathX86 = $seriesDirectory . '/packages-8.1-vs17-x86-stable.txt';
        $filePathX64 = $seriesDirectory . '/packages-8.1-vs17-x64-stable.txt';

        file_put_contents($filePathX86, implode("\n", [
            'curl-7.88.0-vs17-x86.zip',
            'libzip-1.9.1-vs17-x86.zip',
        ]));

        file_put_contents($filePathX64, implode("\n", [
            'curl-7.88.0-vs17-x64.zip',
            'libzip-1.9.1-vs17-x64.zip',
        ]));

        $this->createTask([
            'php_version' => '8.1',
            'vs_version' => 'vs17',
            'stability' => 'stable',
            'library' => 'libzip',
            'ref' => '',
        ]);

        $command = new SeriesUpdateCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();

        $this->assertSame(0, $result);

        $x86Lines = file($filePathX86, FILE_IGNORE_NEW_LINES);
        $this->assertSame(['curl-7.88.0-vs17-x86.zip'], $x86Lines);

        $x64Lines = file($filePathX64, FILE_IGNORE_NEW_LINES);
        $this->assertSame(['curl-7.88.0-vs17-x64.zip'], $x64Lines);
    }

    public function testCreatesSeriesFileWhenMissing(): void
    {
        $this->createTask([
            'php_version' => '8.3',
            'vs_version' => 'vs17',
            'stability' => 'staging',
            'library' => 'libpq',
            'ref' => '16.0.0',
        ]);

        $command = new SeriesUpdateCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();

        $this->assertSame(0, $result);

        $filePathX64 = $this->baseDirectory . '/php-sdk/deps/series/packages-8.3-vs17-x64-staging.txt';
        $filePathX86 = $this->baseDirectory . '/php-sdk/deps/series/packages-8.3-vs17-x86-staging.txt';

        $this->assertFileExists($filePathX64);
        $this->assertFileExists($filePathX86);

        $this->assertSame([
            'libpq-16.0.0-vs17-x64.zip',
        ], file($filePathX64, FILE_IGNORE_NEW_LINES));

        $this->assertSame([
            'libpq-16.0.0-vs17-x86.zip',
        ], file($filePathX86, FILE_IGNORE_NEW_LINES));
    }

    public function testSkipsLockedTask(): void
    {
        $seriesDirectory = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesDirectory, 0755, true);

        $filePathX64 = $seriesDirectory . '/packages-8.0-vs16-x64-stable.txt';
        $filePathX86 = $seriesDirectory . '/packages-8.0-vs16-x86-stable.txt';
        file_put_contents($filePathX64, 'sqlite-3.45.0-vs16-x64.zip');
        file_put_contents($filePathX86, 'sqlite-3.45.0-vs16-x86.zip');

        $taskFile = $this->createTask([
            'php_version' => '8.0',
            'vs_version' => 'vs16',
            'stability' => 'stable',
            'library' => 'sqlite',
            'ref' => '3.46.0',
        ]);
        touch($taskFile . '.lock');

        $command = new SeriesUpdateCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();

        $this->assertSame(0, $result);

        $this->assertSame('sqlite-3.45.0-vs16-x64.zip', trim(file_get_contents($filePathX64)));
        $this->assertSame('sqlite-3.45.0-vs16-x86.zip', trim(file_get_contents($filePathX86)));
        $this->assertFileExists($taskFile);
    }

    public function testHandlesCorruptJson(): void
    {
        $seriesDir = $this->buildsDirectory . '/series';
        mkdir($seriesDir, 0755, true);

        $taskFile = $seriesDir . '/series-update-corrupt.json';
        file_put_contents($taskFile, '{corrupt json');

        $command = new SeriesUpdateCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();

        $this->assertSame(1, $result);
        $this->assertStringContainsString('Syntax error', $output);
        $this->assertFileExists($taskFile);
        $this->assertFileExists($taskFile . '.lock');
    }

    private function createTask(array $data): string
    {
        $seriesDir = $this->buildsDirectory . '/series';
        if (!is_dir($seriesDir)) {
            mkdir($seriesDir, 0755, true);
        }

        $taskFile = $seriesDir . '/series-update-' . uniqid() . '.json';
        file_put_contents($taskFile, json_encode($data));

        return $taskFile;
    }
}