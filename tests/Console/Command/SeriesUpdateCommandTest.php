<?php
declare(strict_types=1);

namespace Console\Command;

use App\Console\Command\SeriesUpdateCommand;
use App\Helpers\Helpers;
use PHPUnit\Framework\Attributes\DataProvider;
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
        Helpers::rmdirr($this->baseDirectory);
        Helpers::rmdirr($this->buildsDirectory);
        parent::tearDown();
    }

    public function testMissingBaseDirectory(): void
    {
        $command = new SeriesUpdateCommand();
        $command->options = ['builds-directory' => $this->buildsDirectory];

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Base directory is required', $output);
    }

    public function testMissingBuildsDirectory(): void
    {
        $command = new SeriesUpdateCommand();
        $command->options = ['base-directory' => $this->baseDirectory];

        ob_start();
        $result = $command->handle();
        $output = trim(ob_get_clean());

        $this->assertSame(1, $result);
        $this->assertSame('Build directory is required', $output);
    }

    public function testFailsWhenNoSeriesDirectory(): void
    {
        $command = new SeriesUpdateCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

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
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        $result = $command->handle();

        $this->assertSame(0, $result);

        $x64Lines = file($filePathX64, FILE_IGNORE_NEW_LINES);
        $this->assertSame([
            'libxml2-2.9.15-vs16-x64.zip',
            'openssl-1.1.1w-vs16-x64.zip',
        ], $x64Lines);

        $x86Lines = file($filePathX86, FILE_IGNORE_NEW_LINES);
        $this->assertSame([
            'libxml2-2.9.15-vs16-x86.zip',
            'zlib-1.2.13-vs16-x86.zip',
        ], $x86Lines);

        $this->assertEmpty(glob($this->buildsDirectory . '/series/series-update-*.json'));
    }

    public function testAddsLibraryInSortedOrder(): void
    {
        $seriesDirectory = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($seriesDirectory, 0755, true);

        $filePathX64 = $seriesDirectory . '/packages-8.4-vs17-x64-staging.txt';
        $filePathX86 = $seriesDirectory . '/packages-8.4-vs17-x86-staging.txt';

        file_put_contents($filePathX64, implode("\n", [
            'curl-8.8.0-vs17-x64.zip',
            'openssl-3.4.1-vs17-x64.zip',
        ]));

        file_put_contents($filePathX86, implode("\n", [
            'curl-8.8.0-vs17-x86.zip',
            'openssl-3.4.1-vs17-x86.zip',
        ]));

        $this->createTask([
            'php_version' => '8.4',
            'vs_version' => 'vs17',
            'stability' => 'staging',
            'library' => 'brotli',
            'ref' => '1.1.0',
        ]);

        $command = new SeriesUpdateCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        $result = $command->handle();

        $this->assertSame(0, $result);
        $this->assertSame([
            'brotli-1.1.0-vs17-x64.zip',
            'curl-8.8.0-vs17-x64.zip',
            'openssl-3.4.1-vs17-x64.zip',
        ], file($filePathX64, FILE_IGNORE_NEW_LINES));
        $this->assertSame([
            'brotli-1.1.0-vs17-x86.zip',
            'curl-8.8.0-vs17-x86.zip',
            'openssl-3.4.1-vs17-x86.zip',
        ], file($filePathX86, FILE_IGNORE_NEW_LINES));
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

        $depsX86 = $this->baseDirectory . '/php-sdk/deps/vs17/x86';
        $depsX64 = $this->baseDirectory . '/php-sdk/deps/vs17/x64';
        mkdir($depsX86, 0755, true);
        mkdir($depsX64, 0755, true);
        file_put_contents($depsX86 . '/libzip-1.9.1.zip', 'x86 artifact');
        file_put_contents($depsX64 . '/libzip-1.9.1.zip', 'x64 artifact');
        file_put_contents($depsX86 . '/curl-7.88.0.zip', 'keep x86');
        file_put_contents($depsX64 . '/curl-7.88.0.zip', 'keep x64');

        $this->createTask([
            'php_version' => '8.1',
            'vs_version' => 'vs17',
            'stability' => 'stable',
            'library' => 'libzip',
            'ref' => '',
        ]);

        $command = new SeriesUpdateCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        $result = $command->handle();

        $this->assertSame(0, $result);

        $x86Lines = file($filePathX86, FILE_IGNORE_NEW_LINES);
        $this->assertSame(['curl-7.88.0-vs17-x86.zip'], $x86Lines);

        $x64Lines = file($filePathX64, FILE_IGNORE_NEW_LINES);
        $this->assertSame(['curl-7.88.0-vs17-x64.zip'], $x64Lines);

        $this->assertFileDoesNotExist($depsX86 . '/libzip-1.9.1.zip');
        $this->assertFileDoesNotExist($depsX64 . '/libzip-1.9.1.zip');
        $this->assertFileExists($depsX86 . '/curl-7.88.0.zip');
        $this->assertFileExists($depsX64 . '/curl-7.88.0.zip');
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
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

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
        $this->assertFileDoesNotExist($this->baseDirectory . '/php-sdk/deps/series/packages-8.3-vs17-arm64-staging.txt');
    }

    public static function arm64UpdateProvider(): array
    {
        return [
            'replace x64 placeholder' => [true, 'libffi-3.7.0-vs16-x64.zip', true],
            'replace old ARM64 build' => [true, 'libffi-3.7.0-vs16-arm64.zip', true],
            'add available ARM64 library' => [true, null, true],
            'keep x64 placeholder until upload' => [true, 'libffi-3.7.0-vs16-x64.zip', false],
            'keep previous ARM64 build until upload' => [true, 'libffi-3.7.0-vs16-arm64.zip', false],
            'skip unavailable ARM64 library' => [true, null, false],
            'skip missing ARM64 series' => [false, null, true],
            'skip absent ARM64 support' => [false, null, false],
        ];
    }

    #[DataProvider('arm64UpdateProvider')]
    public function testUpdatesArm64OnlyWhenSeriesAndRequestedArtifactExist(bool $hasSeries, ?string $existingLibrary, bool $hasArtifact): void
    {
        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        $artifactBase = $this->baseDirectory . '/php-sdk/deps/vs16/arm64';
        mkdir($seriesBase, 0755, true);
        mkdir($artifactBase, 0755, true);
        $seriesFile = "$seriesBase/packages-8.2-vs16-arm64-staging.txt";
        $package = 'libffi-3.8.0-1-vs16-arm64.zip';
        $lines = ['zlib-1.3.2-vs16-x64.zip'];
        if ($existingLibrary !== null) {
            array_unshift($lines, $existingLibrary);
        }
        if ($hasSeries) {
            file_put_contents($seriesFile, implode("\n", $lines));
        }
        // An older artifact must not satisfy the check for the requested version.
        file_put_contents("$artifactBase/libffi-3.7.0-vs16-arm64.zip", 'old artifact');
        if ($hasArtifact) {
            file_put_contents("$artifactBase/$package", 'new artifact');
        }
        $taskFile = $this->createTask([
            'php_version' => '8.2',
            'vs_version' => 'vs16',
            'stability' => 'staging',
            'library' => 'libffi',
            'ref' => '3.8.0-1',
        ]);

        $command = new SeriesUpdateCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];
        $this->assertSame(0, $command->handle());

        if ($hasSeries) {
            $expected = $hasArtifact ? [$package, 'zlib-1.3.2-vs16-x64.zip'] : $lines;
            $this->assertSame($expected, file($seriesFile, FILE_IGNORE_NEW_LINES));
        } else {
            $this->assertFileDoesNotExist($seriesFile);
        }
        foreach (['x86', 'x64'] as $arch) {
            $this->assertStringEqualsFile("$seriesBase/packages-8.2-vs16-$arch-staging.txt", "libffi-3.8.0-1-vs16-$arch.zip");
        }
        $this->assertFileDoesNotExist($taskFile);
        $this->assertFileDoesNotExist($taskFile . '.lock');
    }

    public static function arm64RemovalProvider(): array
    {
        return [
            'mixed series' => [["libffi-3.8.0-1-vs16-arm64.zip", "zlib-1.3.2-vs16-x64.zip"], ['zlib-1.3.2-vs16-x64.zip']],
            'x64 placeholder' => [['libffi-3.7.0-vs16-x64.zip'], null],
            'missing series' => [null, null],
        ];
    }

    #[DataProvider('arm64RemovalProvider')]
    public function testRemovesArm64LibraryAndArtifacts(?array $lines, ?array $remaining): void
    {
        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        $artifactBase = $this->baseDirectory . '/php-sdk/deps/vs16/arm64';
        mkdir($seriesBase, 0755, true);
        mkdir($artifactBase, 0755, true);
        $seriesFile = "$seriesBase/packages-8.2-vs16-arm64-staging.txt";
        if ($lines !== null) {
            file_put_contents($seriesFile, implode("\n", $lines));
        }
        $artifact = "$artifactBase/libffi-3.8.0-1-vs16-arm64.zip";
        $unrelated = "$artifactBase/zlib-1.3.2-vs16-arm64.zip";
        file_put_contents($artifact, 'remove');
        file_put_contents($unrelated, 'keep');
        $taskFile = $this->createTask([
            'php_version' => '8.2',
            'vs_version' => 'vs16',
            'stability' => 'staging',
            'library' => 'libffi',
            'ref' => '',
        ]);

        $command = new SeriesUpdateCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];
        $this->assertSame(0, $command->handle());

        if ($remaining !== null) {
            $this->assertSame($remaining, file($seriesFile, FILE_IGNORE_NEW_LINES));
        } else {
            $this->assertFileDoesNotExist($seriesFile);
        }
        $this->assertFileDoesNotExist($artifact);
        $this->assertStringEqualsFile($unrelated, 'keep');
        $this->assertFileDoesNotExist($taskFile);
        $this->assertFileDoesNotExist($taskFile . '.lock');
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
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

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
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

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
