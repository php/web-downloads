<?php

namespace Console\Command;

use App\Console\Command\SeriesStabilityCommand;
use App\Helpers\Helpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SeriesStabilityCommandTest extends TestCase
{
    private string $baseDirectory;
    private string $buildsDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDirectory   = sys_get_temp_dir() . '/series_stability_base_' . uniqid();
        $this->buildsDirectory = sys_get_temp_dir() . '/series_stability_builds_' . uniqid();

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
        $command = new SeriesStabilityCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();

        $this->assertSame(0, $result);
    }

    public function testMissingBaseDirectory(): void
    {
        $command = new SeriesStabilityCommand();
        $command->setOption('builds-directory', $this->buildsDirectory);

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Base directory is required', $output);
    }

    public function testMissingBuildsDirectory(): void
    {
        $command = new SeriesStabilityCommand();
        $command->setOption('base-directory', $this->baseDirectory);

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Build directory is required', $output);
    }

    #[DataProvider('versionProvider')]
    public function testPromotesStagingToStableAndCleansTask(string $phpVersion, string $vsVersion): void
    {
        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesBase, 0755, true);

        $srcX86 = "$seriesBase/packages-$phpVersion-$vsVersion-x86-staging.txt";
        $srcX64 = "$seriesBase/packages-$phpVersion-$vsVersion-x64-staging.txt";
        file_put_contents($srcX86, "x86-content");
        file_put_contents($srcX64, "x64-content");

        $seriesDir = $this->buildsDirectory . '/series';
        mkdir($seriesDir, 0755, true);
        $jsonPath = $seriesDir . '/series-stability-task1.json';
        file_put_contents($jsonPath, json_encode([
            'php_version' => $phpVersion,
            'vs_version'  => $vsVersion,
        ]));

        $command = new SeriesStabilityCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();
        $this->assertSame(0, $result);

        $dstX86 = "$seriesBase/packages-$phpVersion-$vsVersion-x86-stable.txt";
        $dstX64 = "$seriesBase/packages-$phpVersion-$vsVersion-x64-stable.txt";
        $this->assertFileExists($dstX86);
        $this->assertFileExists($dstX64);
        $this->assertSame("x86-content", file_get_contents($dstX86));
        $this->assertSame("x64-content", file_get_contents($dstX64));

        $this->assertFileDoesNotExist($jsonPath);
        $this->assertFileDoesNotExist($jsonPath . '.lock');
    }

    public function testSkipsLockedJsonFile(): void
    {
        $phpVersion = '8.3';
        $vsVersion  = 'vs17';

        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesBase, 0755, true);
        file_put_contents("$seriesBase/packages-$phpVersion-$vsVersion-x86-staging.txt", "x86");
        file_put_contents("$seriesBase/packages-$phpVersion-$vsVersion-x64-staging.txt", "x64");

        $seriesDir = $this->buildsDirectory . '/series';
        mkdir($seriesDir, 0755, true);
        $jsonPath = $seriesDir . '/series-stability-locked.json';
        file_put_contents($jsonPath, json_encode([
            'php_version' => $phpVersion,
            'vs_version'  => $vsVersion,
        ]));
        touch($jsonPath . '.lock');

        $command = new SeriesStabilityCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();
        $this->assertSame(0, $result);

        $this->assertFileExists($jsonPath);
        $this->assertFileExists($jsonPath . '.lock');
        $this->assertFileDoesNotExist("$seriesBase/packages-$phpVersion-$vsVersion-x86-stable.txt");
        $this->assertFileDoesNotExist("$seriesBase/packages-$phpVersion-$vsVersion-x64-stable.txt");
    }

    public function testHandlesCorruptJson(): void
    {
        $seriesDir = $this->buildsDirectory . '/series';
        mkdir($seriesDir, 0755, true);
        $jsonPath = $seriesDir . '/series-stability-bad.json';
        file_put_contents($jsonPath, '{corrupt json');

        $command = new SeriesStabilityCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();

        $this->assertSame(1, $result);
        $this->assertStringContainsString('Syntax error', $output);
        $this->assertFileExists($jsonPath);
        $this->assertFileExists($jsonPath . '.lock');
    }

    public function testFailsWhenAnySourceStagingFileIsMissing(): void
    {
        $phpVersion = '8.3';
        $vsVersion  = 'vs17';

        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesBase, 0755, true);
        file_put_contents("$seriesBase/packages-$phpVersion-$vsVersion-x86-staging.txt", "x86");

        $seriesDir = $this->buildsDirectory . '/series';
        mkdir($seriesDir, 0755, true);
        $jsonPath = $seriesDir . '/series-stability-task.json';
        file_put_contents($jsonPath, json_encode([
            'php_version' => $phpVersion,
            'vs_version'  => $vsVersion,
        ]));

        $command = new SeriesStabilityCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertStringContainsString("$seriesBase/packages-$phpVersion-$vsVersion-x64-staging.txt does not exist", $output);

        $this->assertFileExists($jsonPath);
        $this->assertFileExists($jsonPath . '.lock');
        $this->assertFileExists("$seriesBase/packages-$phpVersion-$vsVersion-x86-staging.txt");
        $this->assertFileExists("$seriesBase/packages-$phpVersion-$vsVersion-x86-stable.txt");
        $this->assertFileDoesNotExist("$seriesBase/packages-$phpVersion-$vsVersion-x64-stable.txt");
    }
}
