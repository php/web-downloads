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
        (new Helpers)->rmdirr($this->baseDirectory);
        (new Helpers)->rmdirr($this->buildsDirectory);
        parent::tearDown();
    }

    public static function caseProvider(): array
    {
        return [
            ['8.3', 'vs17', 'vs17'],
            ['8.2', 'vs16', 'vs16'],
        ];
    }

    public function testFailsWhenNoSeriesDir(): void
    {
        $command = new SeriesInitCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result, 'Should fail when there is no builds/series directory.');
        $this->assertSame('Series directory does not exist', $output);
    }

    public function testMissingBaseDirectory(): void
    {
        $command = new SeriesInitCommand();
        $command->setOption('builds-directory', $this->buildsDirectory);

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Base directory is required', $output);
    }

    public function testMissingBuildsDirectory(): void
    {
        $command = new SeriesInitCommand();
        $command->setOption('base-directory', $this->baseDirectory);

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Build directory is required', $output);
    }

    #[DataProvider('caseProvider')]
    public function testProcessesJsonCopiesSeriesFilesAndCleansUp(string $series, string $series_vs, string $target_vs): void
    {
        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesBase, 0755, true);

        $sourceNames = [
            "packages-master-$series_vs-x86-stable.txt",
            "packages-master-$series_vs-x86-staging.txt",
            "packages-master-$series_vs-x64-stable.txt",
            "packages-master-$series_vs-x64-staging.txt",
        ];
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
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();
        $this->assertSame(0, $result, 'Command should return success.');

        $destinations = [
            "packages-$series-$target_vs-x86-stable.txt",
            "packages-$series-$target_vs-x86-staging.txt",
            "packages-$series-$target_vs-x64-stable.txt",
            "packages-$series-$target_vs-x64-staging.txt",
        ];

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
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();
        $this->assertSame(0, $result);

        $this->assertFileExists($jsonPath);
        $this->assertFileExists($jsonPath . '.lock');

        $this->assertFileDoesNotExist($seriesBase . "/packages-$series-$target_vs-x86-stable.txt");
        $this->assertFileDoesNotExist($seriesBase . "/packages-$series-$target_vs-x86-staging.txt");
        $this->assertFileDoesNotExist($seriesBase . "/packages-$series-$target_vs-x64-stable.txt");
        $this->assertFileDoesNotExist($seriesBase . "/packages-$series-$target_vs-x64-staging.txt");
    }

    public function testHandlesCorruptJson(): void
    {
        $seriesDir = $this->buildsDirectory . '/series';
        mkdir($seriesDir, 0755, true);
        $jsonPath = $seriesDir . '/series-init-bad.json';
        file_put_contents($jsonPath, '{corrupt json');

        $command = new SeriesInitCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

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
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

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
