<?php
declare(strict_types=1);

namespace Console\Command;

use App\Actions\GetListing;
use App\Actions\UpdateReleasesJson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use App\Console\Command\PhpCommand;
use App\Helpers\Helpers;
use ZipArchive;

class PhpCommandTest extends TestCase
{
    private string $baseDirectory;
    private string $buildsDirectory;
    protected function setUp(): void
    {
        parent::setUp();

        // Set up temporary directories
        $this->baseDirectory = sys_get_temp_dir() . '/php_test_base';
        $this->buildsDirectory = sys_get_temp_dir() . '/builds';

        Helpers::rmdirr($this->baseDirectory);
        Helpers::rmdirr($this->buildsDirectory);

        mkdir($this->baseDirectory . '/releases/archives', 0755, true);
        mkdir($this->baseDirectory . '/qa/archives', 0755, true);
        mkdir($this->buildsDirectory . '/php', 0755, true);
        mkdir($this->baseDirectory . '/php-sdk/deps/series', 0755, true);
        file_put_contents($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x86-staging.txt', 'x86-staging');
        file_put_contents($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x64-staging.txt', 'x64-staging');
    }

    protected function tearDown(): void

    {
        parent::tearDown();
        // Clean up directories
        Helpers::rmdirr($this->baseDirectory);
        Helpers::rmdirr($this->buildsDirectory);
    }

    public static function buildsProvider(): array
    {
        $builds = [
            [[
                'php-8.4.1-Win32-vs17-x64.zip',
                'php-8.4.1-Win32-vs17-x64.zip.cdx.json',
                'php-8.4.1-Win32-vs17-x64.zip.spdx.json',
                'php-8.4.1-Win32-vs17-x64.zip.openvex.json',
                'php-8.4.1-Win32-vs17-x86.zip',
                'php-8.4.1-nts-Win32-vs17-x64.zip',
                'php-8.4.1-nts-Win32-vs17-x86.zip',
                'php-8.4.1-src.zip',
                'php-debug-pack-8.4.1-Win32-vs17-x64.zip',
                'php-debug-pack-8.4.1-Win32-vs17-x86.zip',
                'php-debug-pack-8.4.1-nts-Win32-vs17-x64.zip',
                'php-debug-pack-8.4.1-nts-Win32-vs17-x86.zip',
                'php-devel-pack-8.4.1-Win32-vs17-x64.zip',
                'php-devel-pack-8.4.1-Win32-vs17-x86.zip',
                'php-devel-pack-8.4.1-nts-Win32-vs17-x64.zip',
                'php-devel-pack-8.4.1-nts-Win32-vs17-x86.zip',
                'php-test-pack-8.4.1.zip',
            ]],[[
                'php-8.4.0-dev-Win32-vs17-x64.zip',
                'php-8.4.0-dev-Win32-vs17-x64.zip.cdx.json',
                'php-8.4.0-dev-Win32-vs17-x64.zip.spdx.json',
                'php-8.4.0-dev-Win32-vs17-x86.zip',
                'php-8.4.0-dev-nts-Win32-vs17-x64.zip',
                'php-8.4.0-dev-nts-Win32-vs17-x86.zip',
                'php-8.4.0-dev-src.zip',
                'php-debug-pack-8.4.0-dev-Win32-vs17-x64.zip',
                'php-debug-pack-8.4.0-dev-Win32-vs17-x86.zip',
                'php-debug-pack-8.4.0-dev-nts-Win32-vs17-x64.zip',
                'php-debug-pack-8.4.0-dev-nts-Win32-vs17-x86.zip',
                'php-devel-pack-8.4.0-dev-Win32-vs17-x64.zip',
                'php-devel-pack-8.4.0-dev-Win32-vs17-x86.zip',
                'php-devel-pack-8.4.0-dev-nts-Win32-vs17-x64.zip',
                'php-devel-pack-8.4.0-dev-nts-Win32-vs17-x86.zip',
                'php-test-pack-8.4.0-dev.zip',
            ]]
        ];
        foreach ($builds as [$files]) {
            $builds[] = [array_map(
                static fn (string $file): string => str_replace(['8.4.1', '8.4.0-dev'], ['8.4.1-1', '8.4.0-dev-2'], $file),
                $files
            )];
        }
        foreach ($builds as [$files]) {
            $builds[] = [array_map(
                static fn (string $file): string => str_replace('-x64', '-arm64', $file),
                array_filter($files, static fn (string $file): bool => !str_contains($file, '-x86'))
            )];
        }
        return $builds;
    }

    private function stageBuilds(array $phpZips, $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            foreach ($phpZips as $zipFileName) {
                $zipFilePath = $this->buildsDirectory . '/php/' . $zipFileName;
                if (str_ends_with($zipFileName, '.json')) {
                    file_put_contents($zipFilePath, '{}');
                } else {
                    $innerZip = new ZipArchive();
                    if ($innerZip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
                        $innerZip->addFromString('build.txt', $zipFileName);
                        $innerZip->close();
                    }
                }
                $zip->addFile($zipFilePath, $zipFileName);
            }
            $zip->close();
        }
        foreach ($phpZips as $zipFileName) {
            unlink($this->buildsDirectory . '/php/' . $zipFileName);
        }
    }

    #[DataProvider('buildsProvider')]
    public function testCommandHandlesSuccessfulExecution(array $phpZips): void
    {
        $command = new PhpCommand((new GetListing()), (new UpdateReleasesJson()));
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        $this->stageBuilds($phpZips, $this->buildsDirectory . '/php/test.zip');

        $result = $command->handle();

        $this->assertEquals(0, $result, "Command should return success.");

        $expectedDestination = $this->baseDirectory . (str_contains($phpZips[0], '-dev-') ? '/qa' : '/releases');
        $this->assertDirectoryExists($expectedDestination, "Destination directory should exist.");
        foreach ($phpZips as $file) {
            $this->assertFileExists($expectedDestination . '/' . $file);
        }
        foreach (array_filter($phpZips, static fn (string $file): bool => str_ends_with($file, '.json')) as $sidecar) {
            $this->assertFileExists($expectedDestination . '/' . $sidecar);
            $this->assertFileExists($expectedDestination . '/archives/' . $sidecar);
        }
        $this->assertStringNotContainsString('.json', file_get_contents($expectedDestination . '/sha256sum.txt'));
        $this->assertStringNotContainsString('.json', file_get_contents($expectedDestination . '/sha1sum.txt'));
        $this->assertStringNotContainsString('.json', file_get_contents($expectedDestination . '/releases.json'));
        if (str_contains($phpZips[0], '-arm64')) {
            $releases = json_decode(file_get_contents($expectedDestination . '/releases.json'), true);
            foreach (['ts', 'nts'] as $threadSafety) {
                $build = $releases['8.4']["$threadSafety-vs17-arm64"];
                foreach (['zip', 'debug_pack', 'devel_pack'] as $type) {
                    $path = $build[$type]['path'];
                    $this->assertSame(hash_file('sha256', "$expectedDestination/$path"), $build[$type]['sha256']);
                    $this->assertFileEquals("$expectedDestination/$path", "$expectedDestination/archives/$path");
                }
                if ($expectedDestination === $this->baseDirectory . '/releases') {
                    $latest = str_replace([$releases['8.4']['version'], '.zip'], ['8.4', '-latest.zip'], $build['zip']['path']);
                    $this->assertFileEquals("$expectedDestination/" . $build['zip']['path'], "$expectedDestination/latest/$latest");
                }
            }
        }
    }

    public function testCommandHandlerWithMissingTestPackZip(): void
    {
        $command = new PhpCommand(new GetListing(), new UpdateReleasesJson());
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        $this->stageBuilds(['php-8.4.0-dev-Win32-vs17-x64.zip'], $this->buildsDirectory . '/php/test.zip');
        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();
        $this->assertEquals('No test pack found in the artifact', $output);
        $this->assertEquals(1, $result, "Command should return failure.");
    }

    public function testCommandHandlesMissingBaseDirectory(): void
    {
        $command = new PhpCommand(new GetListing(), new UpdateReleasesJson());
        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();
        $this->assertEquals('Base directory is required', $output);
        $this->assertEquals(1, $result);
    }

    public function testFailsToOpenZip(): void
    {
        $zipPath = $this->buildsDirectory . '/php/broken.zip';
        file_put_contents($zipPath, "invalid zip content");
        $command = new PhpCommand(new GetListing(), new UpdateReleasesJson());
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];
        ob_start();
        $result = $command->handle();
        ob_get_clean();
        $this->assertEquals(1, $result, "Command should return failure on broken zip.");
    }

    public function testCleanupAfterCommand(): void
    {
        $command = new PhpCommand(new GetListing(), new UpdateReleasesJson());
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];
        $command->handle();
        $tempDirectory = sys_get_temp_dir() . '/php-web-downloads-*';
        $this->assertEmpty(glob($tempDirectory));
    }

    public static function seriesPromotionProvider(): array
    {
        return [
            'no ARM64 files' => [null, null],
            'ARM64 staging only' => ["libffi-3.8.0-1-vs17-arm64.zip\nzlib-1.3.2-vs17-x64.zip", null],
            'ARM64 stable only' => [null, 'existing-arm64-stable'],
            'both ARM64 files' => ['new-arm64-staging', 'existing-arm64-stable'],
        ];
    }

    #[DataProvider('seriesPromotionProvider')]
    public function testStableReleasePromotesSeriesFiles(?string $arm64Staging, ?string $arm64Stable): void
    {
        $seriesBase = $this->baseDirectory . '/php-sdk/deps/series';
        $dstArm64 = "$seriesBase/packages-8.4-vs17-arm64-stable.txt";
        if ($arm64Staging !== null) {
            file_put_contents("$seriesBase/packages-8.4-vs17-arm64-staging.txt", $arm64Staging);
        }
        if ($arm64Stable !== null) {
            file_put_contents($dstArm64, $arm64Stable);
        }
        $command = new PhpCommand(new GetListing(), new UpdateReleasesJson());
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        $this->stageBuilds(self::buildsProvider()[0][0], $this->buildsDirectory . '/php/test.zip');

        $result = $command->handle();

        $this->assertSame(0, $result);
        $this->assertFileExists($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x86-stable.txt');
        $this->assertFileExists($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x64-stable.txt');
        $this->assertSame('x86-staging', file_get_contents($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x86-stable.txt'));
        $this->assertSame('x64-staging', file_get_contents($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x64-stable.txt'));
        if ($arm64Staging !== null || $arm64Stable !== null) {
            $this->assertStringEqualsFile($dstArm64, $arm64Staging ?? $arm64Stable);
        } else {
            $this->assertFileDoesNotExist($dstArm64);
        }
    }

    public function testQaReleaseDoesNotPromoteSeriesFiles(): void
    {
        file_put_contents($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-arm64-staging.txt', 'arm64-staging');
        $command = new PhpCommand(new GetListing(), new UpdateReleasesJson());
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];

        $this->stageBuilds(self::buildsProvider()[1][0], $this->buildsDirectory . '/php/test.zip');

        $result = $command->handle();

        $this->assertSame(0, $result);
        $this->assertFileDoesNotExist($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x86-stable.txt');
        $this->assertFileDoesNotExist($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x64-stable.txt');
        $this->assertFileDoesNotExist($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-arm64-stable.txt');
    }

    public function testRebuildsArchiveOlderNumbersAndNeverDowngradeLatest(): void
    {
        $command = new PhpCommand(new GetListing(), new UpdateReleasesJson());
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];
        $files = self::buildsProvider()[0][0];
        $releaseDirectory = $this->baseDirectory . '/releases';
        $latestVersion = '8.4.1';
        foreach (['8.4.1', '8.4.1-1', '8.4.1-2', '8.4.1-10', '8.4.1-3', '8.4.2', '8.4.1-11'] as $version) {
            $builds = array_map(static fn (string $file): string => str_replace('8.4.1', $version, $file), $files);
            $this->stageBuilds($builds, $this->buildsDirectory . '/php/rebuild.zip');
            $this->assertSame(0, $command->handle());
            if (version_compare($version, $latestVersion) > 0) {
                $latestVersion = $version;
            }
            $releases = json_decode(file_get_contents($releaseDirectory . '/releases.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($latestVersion, $releases['8.4']['version']);
            foreach ($builds as $file) {
                $this->assertFileExists($releaseDirectory . '/archives/' . $file);
                if ($version !== $latestVersion) {
                    $this->assertFileDoesNotExist($releaseDirectory . '/' . $file);
                }
            }
            foreach ($files as $original) {
                $current = str_replace('8.4.1', $latestVersion, $original);
                $this->assertFileExists($releaseDirectory . '/' . $current);
                if (str_ends_with($original, '.zip')) {
                    $latest = str_replace(['8.4.1', '.zip'], ['8.4', '-latest.zip'], $original);
                    $this->assertFileEquals($releaseDirectory . '/' . $current, $releaseDirectory . '/latest/' . $latest);
                }
            }
            array_walk_recursive($releases, function ($value, $key) use ($latestVersion, $releaseDirectory): void {
                if ($key === 'path') {
                    $this->assertStringContainsString($latestVersion, $value);
                    $this->assertFileExists($releaseDirectory . '/' . $value);
                }
            });
            foreach (['nts', 'ts'] as $ts) {
                foreach (['x86', 'x64'] as $arch) {
                    foreach (['zip', 'debug_pack', 'devel_pack'] as $type) {
                        $entry = $releases['8.4']["$ts-vs17-$arch"][$type];
                        $this->assertSame(hash_file('sha256', $releaseDirectory . '/' . $entry['path']), $entry['sha256']);
                    }
                }
            }
        }
        foreach (['8.4.1', '8.4.1-1', '8.4.1-2', '8.4.1-10'] as $old) {
            foreach ($files as $file) {
                $this->assertFileDoesNotExist($releaseDirectory . '/' . str_replace('8.4.1', $old, $file));
            }
        }
    }
}
