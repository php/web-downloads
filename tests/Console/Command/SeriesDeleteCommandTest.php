<?php
declare(strict_types=1);

namespace Console\Command;

use App\Console\Command\SeriesDeleteCommand;
use App\Helpers\Helpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SeriesDeleteCommandTest extends TestCase
{
    private string $baseDirectory;
    private string $buildsDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDirectory   = sys_get_temp_dir() . '/series_delete_base_' . uniqid();
        $this->buildsDirectory = sys_get_temp_dir() . '/series_delete_builds_' . uniqid();

        mkdir($this->baseDirectory, 0755, true);
        mkdir($this->buildsDirectory, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Helpers)->rmdirr($this->baseDirectory);
        (new Helpers)->rmdirr($this->buildsDirectory);
        parent::tearDown();
    }

    public static function versionProvider(): array
    {
        return [
            ['8.3', 'vs17'],
            ['8.2', 'vs16'],
        ];
    }

    public function testReturnsSuccessWhenNoSeriesDir(): void
    {
        $command = new SeriesDeleteCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();

        $this->assertSame(0, $result, 'Should return success when there is no builds/series directory.');
    }

    public function testMissingBaseDirectory(): void
    {
        $command = new SeriesDeleteCommand();
        $command->setOption('builds-directory', $this->buildsDirectory);

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Base directory is required', $output);
    }

    public function testMissingBuildsDirectory(): void
    {
        $command = new SeriesDeleteCommand();
        $command->setOption('base-directory', $this->baseDirectory);

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Build directory is required', $output);
    }

    #[DataProvider('versionProvider')]
    public function testDeletesAllSeriesFilesAndCleansUpTask(string $phpVersion, string $vsVersion): void
    {
        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesBase, 0755, true);

        $paths = [
            "$seriesBase/packages-$phpVersion-$vsVersion-x86-stable.txt",
            "$seriesBase/packages-$phpVersion-$vsVersion-x86-staging.txt",
            "$seriesBase/packages-$phpVersion-$vsVersion-x64-stable.txt",
            "$seriesBase/packages-$phpVersion-$vsVersion-x64-staging.txt",
        ];
        foreach ($paths as $p) {
            file_put_contents($p, "dummy");
        }

        $seriesDir = $this->buildsDirectory . '/series';
        mkdir($seriesDir, 0755, true);
        $jsonPath = $seriesDir . '/series-delete-task1.json';
        file_put_contents($jsonPath, json_encode([
            'php_version' => $phpVersion,
            'vs_version'  => $vsVersion,
        ]));

        clearstatcache(true);

        $command = new SeriesDeleteCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();
        $this->assertSame(0, $result, 'Command should return success.');

        foreach ($paths as $p) {
            $this->assertFileDoesNotExist($p);
        }

        $this->assertFileDoesNotExist($jsonPath);
        $this->assertFileDoesNotExist($jsonPath . '.lock');
    }

    #[DataProvider('versionProvider')]
    public function testIdempotentWhenSomeFilesMissing(string $phpVersion, string $vsVersion): void
    {
        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesBase, 0755, true);

        $existing = [
            "$seriesBase/packages-$phpVersion-$vsVersion-x86-stable.txt",
            "$seriesBase/packages-$phpVersion-$vsVersion-x64-staging.txt",
        ];
        foreach ($existing as $p) {
            file_put_contents($p, "exists");
        }
        $missing = [
            "$seriesBase/packages-$phpVersion-$vsVersion-x86-staging.txt",
            "$seriesBase/packages-$phpVersion-$vsVersion-x64-stable.txt",
        ];

        $seriesDir = $this->buildsDirectory . '/series';
        mkdir($seriesDir, 0755, true);
        $jsonPath = $seriesDir . '/series-delete-task2.json';
        file_put_contents($jsonPath, json_encode([
            'php_version' => $phpVersion,
            'vs_version'  => $vsVersion,
        ]));

        clearstatcache(true);

        $command = new SeriesDeleteCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();
        $this->assertSame(0, $result);

        foreach ($existing as $p) {
            $this->assertFileDoesNotExist($p);
        }
        foreach ($missing as $p) {
            $this->assertFileDoesNotExist($p);
        }

        $this->assertFileDoesNotExist($jsonPath);
        $this->assertFileDoesNotExist($jsonPath . '.lock');
    }

    public function testSkipsLockedJsonFile(): void
    {
        $phpVersion = '8.3';
        $vsVersion  = 'vs17';

        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesBase, 0755, true);

        $target = "$seriesBase/packages-$phpVersion-$vsVersion-x86-stable.txt";
        file_put_contents($target, 'keep');

        $seriesDir = $this->buildsDirectory . '/series';
        mkdir($seriesDir, 0755, true);
        $jsonPath = $seriesDir . '/series-delete-locked.json';
        file_put_contents($jsonPath, json_encode([
            'php_version' => $phpVersion,
            'vs_version'  => $vsVersion,
        ]));
        touch($jsonPath . '.lock');

        clearstatcache(true);

        $command = new SeriesDeleteCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();
        $this->assertSame(0, $result);

        $this->assertFileExists($jsonPath);
        
        $this->assertFileExists($target);
    }

    public function testHandlesCorruptJson(): void
    {
        $phpVersion = '8.3';
        $vsVersion  = 'vs17';

        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesBase, 0755, true);

        $target = "$seriesBase/packages-$phpVersion-$vsVersion-x86-stable.txt";
        file_put_contents($target, 'data');

        $seriesDir = $this->buildsDirectory . '/series';
        mkdir($seriesDir, 0755, true);
        $jsonPath = $seriesDir . '/series-delete-bad.json';
        file_put_contents($jsonPath, '{corrupt json');

        $command = new SeriesDeleteCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();

        $this->assertSame(1, $result, 'Corrupt JSON should return FAILURE.');
        $this->assertStringContainsString('Syntax error', $output);

        $this->assertFileExists($jsonPath);
        $this->assertFileExists($jsonPath . '.lock');

        $this->assertFileExists($target);
    }
}
