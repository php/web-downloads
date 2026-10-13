<?php
declare(strict_types=1);

namespace Console\Command;

use App\Console\Command\SeriesInitCommand;
use App\Helpers\Helpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SeriesInitCommandTest extends TestCase
{
    private string $baseDirectory;
    private string $buildsDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDirectory   = sys_get_temp_dir() . '/series_init_base_' . uniqid();
        $this->buildsDirectory = sys_get_temp_dir() . '/series_init_builds_' . uniqid();

        mkdir($this->baseDirectory, 0755, true);
        mkdir($this->buildsDirectory, 0755, true);
    }

    protected function tearDown(): void
    {
        Helpers::rmdirr($this->baseDirectory);
        Helpers::rmdirr($this->buildsDirectory);
        parent::tearDown();
    }

    public static function caseProvider(): array
    {
        return [
            ['8.3', 'vs17', 'vs17'],
            ['8.2', 'vs16', 'vs16'],
            ['8.7', 'vs18', 'vs18'],
            ['8.7', 'vs18', 'vs18', ['stable', 'staging']],
            ['8.7', 'vs18', 'vs18', ['stable']],
            ['8.7', 'vs18', 'vs18', ['staging']],
        ];
    }

    public function testFailsWhenNoSeriesDir(): void
    {
        $command = new SeriesInitCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result, 'Should fail when there is no builds/series directory.');
        $this->assertSame('Series directory does not exist', $output);
    }

    public function testMissingBaseDirectory(): void
    {
        $command = new SeriesInitCommand();
        $command->options = ['builds-directory' => $this->buildsDirectory];

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Base directory is required', $output);
    }

    public function testMissingBuildsDirectory(): void
    {
        $command = new SeriesInitCommand();
        $command->options = ['base-directory' => $this->baseDirectory];

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Build directory is required', $output);
    }

    #[DataProvider('caseProvider')]
    public function testProcessesJsonCopiesSeriesFilesAndCleansUp(string $series, string $series_vs, string $target_vs, array $arm64Stabilities = []): void
    {
        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesBase, 0755, true);

        $sourceNames = [
            "packages-master-$series_vs-x86-stable.txt",
            "packages-master-$series_vs-x86-staging.txt",
            "packages-master-$series_vs-x64-stable.txt",
            "packages-master-$series_vs-x64-staging.txt",
        ];
        foreach ($arm64Stabilities as $stability) {
            $sourceNames[] = "packages-master-$series_vs-arm64-$stability.txt";
        }
        foreach ($sourceNames as $name) {
            file_put_contents($seriesBase . '/' . $name, "content-of-$name");
        }

        $buildSeriesDir = $this->buildsDirectory . '/series';
        mkdir($buildSeriesDir, 0755, true);
        $jsonPath = $buildSeriesDir . '/series-init-task1.json';
        file_put_contents($jsonPath, json_encode([
            'php_version'    => $series,
            'source_vs' => $series_vs,
            'target_vs' => $target_vs,
        ]));

        $command = new SeriesInitCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        $result = $command->handle();
        $this->assertSame(0, $result, 'Command should return success.');

        $destinations = [
            "packages-$series-$target_vs-x86-stable.txt",
            "packages-$series-$target_vs-x86-staging.txt",
            "packages-$series-$target_vs-x64-stable.txt",
            "packages-$series-$target_vs-x64-staging.txt",
        ];
        foreach ($arm64Stabilities as $stability) {
            $destinations[] = "packages-$series-$target_vs-arm64-$stability.txt";
        }

        foreach ($destinations as $dest) {
            $destPath = $seriesBase . '/' . $dest;
            $this->assertFileExists($destPath);
            $srcName = str_replace("packages-$series-$target_vs", "packages-master-$series_vs", $dest);
            $this->assertSame(
                "content-of-$srcName",
                file_get_contents($destPath),
                "Destination $dest should have the same content as $srcName"
            );
        }

        $this->assertFileDoesNotExist($jsonPath);
        $this->assertFileDoesNotExist($jsonPath . '.lock');
        foreach (array_diff(['stable', 'staging'], $arm64Stabilities) as $stability) {
            $this->assertFileDoesNotExist("$seriesBase/packages-$series-$target_vs-arm64-$stability.txt");
        }
    }

    public function testSkipsLockedJsonFile(): void
    {
        $series = '8.3';
        $series_vs = 'vs17';
        $target_vs = 'vs17';

        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesBase, 0755, true);
        foreach ([
                     "packages-master-$series_vs-x86-stable.txt",
                     "packages-master-$series_vs-x86-staging.txt",
                     "packages-master-$series_vs-x64-stable.txt",
                     "packages-master-$series_vs-x64-staging.txt",
                 ] as $name) {
            file_put_contents($seriesBase . '/' . $name, "ok");
        }

        $buildSeriesDir = $this->buildsDirectory . '/series';
        mkdir($buildSeriesDir, 0755, true);
        $jsonPath = $buildSeriesDir . '/series-init-locked.json';
        file_put_contents($jsonPath, json_encode([
            'php_version'    => $series,
            'source_vs' => $series_vs,
            'target_vs' => $target_vs,
        ]));
        touch($jsonPath . '.lock');

        $command = new SeriesInitCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        $result = $command->handle();
        $this->assertSame(0, $result);

        $this->assertFileExists($jsonPath);
        $this->assertFileExists($jsonPath . '.lock');

        $this->assertFileDoesNotExist($seriesBase . "/packages-$series-$target_vs-x86-stable.txt");
        $this->assertFileDoesNotExist($seriesBase . "/packages-$series-$target_vs-x86-staging.txt");
        $this->assertFileDoesNotExist($seriesBase . "/packages-$series-$target_vs-x64-stable.txt");
        $this->assertFileDoesNotExist($seriesBase . "/packages-$series-$target_vs-x64-staging.txt");
    }

    public static function architectureProvider(): array
    {
        return [
            'x64 to ARM64' => ['8.2', 'vs16', 'x64', 'arm64', []],
            'master' => ['master', 'vs18', 'x64', 'arm64', []],
            'PHP 8.4 uses VS17' => ['8.4', 'vs17', 'x64', 'arm64', []],
            'x86 source' => ['8.2', 'vs16', 'x86', 'arm64', []],
            'preserve stable' => ['8.2', 'vs16', 'x64', 'arm64', ['stable']],
            'preserve staging' => ['8.2', 'vs16', 'x64', 'arm64', ['staging']],
            'preserve both' => ['8.2', 'vs16', 'x64', 'arm64', ['stable', 'staging']],
        ];
    }

    #[DataProvider('architectureProvider')]
    public function testInitializesArchitectureWithinSelectedSeries(
        string $phpVersion,
        string $vsVersion,
        string $sourceArch,
        string $targetArch,
        array $existingStabilities
    ): void {
        $seriesDirectory = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesDirectory, 0755, true);
        $sourceContents = "libffi-3.8.0-1-vs16-$sourceArch.zip\r\nx64-helper-1.0-$vsVersion-$sourceArch.zip\r\n";
        $expectedContents = "libffi-3.8.0-1-vs16-$targetArch.zip\r\nx64-helper-1.0-$vsVersion-$targetArch.zip\r\n";
        foreach (['stable', 'staging'] as $stability) {
            file_put_contents("$seriesDirectory/packages-$phpVersion-$vsVersion-$sourceArch-$stability.txt", $sourceContents);
            if (in_array($stability, $existingStabilities, true)) {
                file_put_contents("$seriesDirectory/packages-$phpVersion-$vsVersion-$targetArch-$stability.txt", "existing-$stability");
            }
        }
        $task = [
            'subject' => 'arch',
            'php_version' => $phpVersion,
            'source_arch' => $sourceArch,
            'target_arch' => $targetArch,
        ];
        $jsonPath = $this->createTask($task);
        $command = new SeriesInitCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        $this->assertSame(0, $command->handle());

        foreach (['stable', 'staging'] as $stability) {
            $expected = in_array($stability, $existingStabilities, true) ? "existing-$stability" : $expectedContents;
            $this->assertStringEqualsFile("$seriesDirectory/packages-$phpVersion-$vsVersion-$targetArch-$stability.txt", $expected);
            $this->assertStringEqualsFile("$seriesDirectory/packages-$phpVersion-$vsVersion-$sourceArch-$stability.txt", $sourceContents);
        }
        $this->assertFileDoesNotExist($jsonPath);
        $this->assertFileDoesNotExist($jsonPath . '.lock');

        // Repeating initialization must preserve series that have since been updated.
        $targetStable = "$seriesDirectory/packages-$phpVersion-$vsVersion-$targetArch-stable.txt";
        file_put_contents($targetStable, 'updated-after-init');
        $jsonPath = $this->createTask($task);
        $this->assertSame(0, $command->handle());
        $this->assertStringEqualsFile($targetStable, 'updated-after-init');
        $this->assertFileDoesNotExist($jsonPath);
        $this->assertFileDoesNotExist($jsonPath . '.lock');
    }

    public function testMissingArchitectureSourceDoesNotCreatePartialSeries(): void
    {
        $seriesDirectory = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesDirectory, 0755, true);
        file_put_contents("$seriesDirectory/packages-8.2-vs16-x64-stable.txt", 'libffi-3.8.0-1-vs16-x64.zip');
        $jsonPath = $this->createTask([
            'subject' => 'arch',
            'php_version' => '8.2',
            'source_arch' => 'x64',
            'target_arch' => 'arm64',
        ]);
        $command = new SeriesInitCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();

        $this->assertSame(1, $result);
        $this->assertStringContainsString('packages-8.2-vs16-x64-staging.txt not found', $output);
        $this->assertFileDoesNotExist("$seriesDirectory/packages-8.2-vs16-arm64-stable.txt");
        $this->assertFileDoesNotExist("$seriesDirectory/packages-8.2-vs16-arm64-staging.txt");
        $this->assertFileExists($jsonPath);
        $this->assertFileExists($jsonPath . '.lock');
    }

    public function testUnknownArchitectureSeriesDoesNotCreateFiles(): void
    {
        $seriesDirectory = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesDirectory, 0755, true);
        $jsonPath = $this->createTask([
            'subject' => 'arch',
            'php_version' => '9.9',
            'source_arch' => 'x64',
            'target_arch' => 'arm64',
        ]);
        $command = new SeriesInitCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();

        $this->assertSame(1, $result);
        $this->assertSame('No VS version found for PHP 9.9', $output);
        $this->assertEmpty(glob($seriesDirectory . '/*'));
        $this->assertFileExists($jsonPath);
    }

    private function createTask(array $data): string
    {
        $seriesDirectory = $this->buildsDirectory . '/series';
        if (!is_dir($seriesDirectory)) {
            mkdir($seriesDirectory, 0755, true);
        }
        $file = $seriesDirectory . '/series-init-' . uniqid() . '.json';
        file_put_contents($file, json_encode($data));
        return $file;
    }

    public function testHandlesCorruptJson(): void
    {
        $seriesDir = $this->buildsDirectory . '/series';
        mkdir($seriesDir, 0755, true);
        $jsonPath = $seriesDir . '/series-init-bad.json';
        file_put_contents($jsonPath, '{corrupt json');

        $command = new SeriesInitCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();

        $this->assertSame(1, $result);
        $this->assertStringContainsString('Syntax error', $output);
        $this->assertFileExists($jsonPath);
        $this->assertFileExists($jsonPath . '.lock', 'Lock is created before processing and should remain after failure.');
    }

    public function testFailsWhenAnySourceSeriesFileIsMissing(): void
    {
        $series = '8.3';
        $series_vs = 'vs17';
        $target_vs = 'vs17';

        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesBase, 0755, true);

        file_put_contents($seriesBase . "/packages-master-$series_vs-x86-stable.txt", 'ok');

        $seriesDir = $this->buildsDirectory . '/series';
        mkdir($seriesDir, 0755, true);
        $jsonPath = $seriesDir . '/series-init-task.json';
        file_put_contents($jsonPath, json_encode([
            'php_version'    => $series,
            'source_vs' => $series_vs,
            'target_vs' => $target_vs,
        ]));

        $command = new SeriesInitCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertStringContainsString(
            "$seriesBase/packages-master-$series_vs-x86-staging.txt not found",
            $output
        );

        $this->assertFileExists($seriesBase . "/packages-$series-$target_vs-x86-stable.txt");

        $this->assertFileDoesNotExist($seriesBase . "/packages-$series-$target_vs-x86-staging.txt");
        $this->assertFileDoesNotExist($seriesBase . "/packages-$series-$target_vs-x64-stable.txt");
        $this->assertFileDoesNotExist($seriesBase . "/packages-$series-$target_vs-x64-staging.txt");

        $this->assertFileExists($jsonPath);
        $this->assertFileExists($jsonPath . '.lock');
    }
}
