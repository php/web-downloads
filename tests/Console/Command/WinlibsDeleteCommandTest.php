<?php
declare(strict_types=1);

namespace Console\Command;

use App\Console\Command\WinlibsCommand;
use App\Console\Command\WinlibsDeleteCommand;
use App\Helpers\Helpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WinlibsDeleteCommandTest extends TestCase
{
    private string $baseDirectory;
    private string $buildsDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDirectory = sys_get_temp_dir() . '/winlibs_delete_base_' . uniqid();
        $this->buildsDirectory = sys_get_temp_dir() . '/winlibs_delete_builds_' . uniqid();
        mkdir($this->baseDirectory, 0755, true);
        mkdir($this->buildsDirectory, 0755, true);
    }

    protected function tearDown(): void
    {
        Helpers::rmdirr($this->baseDirectory);
        Helpers::rmdirr($this->buildsDirectory);
        parent::tearDown();
    }

    public function testPhpDeletionRemovesExactBuildFromEverySeriesAndArtifactDirectory(): void
    {
        $target = 'libcurl-8.22.0-1-vs18-x64.zip';
        $older = 'libcurl-8.22.0-vs18-x64.zip';
        $other = 'libcurl-ng-8.22.0-1-vs18-x64.zip';
        $series = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($series, 0755, true);
        $stable = "$series/packages-8.6-vs18-x64-stable.txt";
        $staging = "$series/packages-8.7-vs18-x64-staging.txt";
        $master = "$series/packages-master-vs18-x64-stable.txt";
        $unrelated = "$series/packages-8.6-vs18-x86-stable.txt";
        file_put_contents($stable, "$older\n$target\n$other\n");
        file_put_contents($staging, "$target\n$older");
        file_put_contents($master, "$target");
        file_put_contents($unrelated, 'libcurl-8.22.0-1-vs18-x86.zip');
        chmod($stable, 0644);

        foreach (['vs18/x64', 'vs17/x64'] as $targetDirectory) {
            $directory = $this->baseDirectory . '/php-sdk/deps/' . $targetDirectory;
            mkdir($directory, 0755, true);
            file_put_contents($directory . '/' . $target, 'target');
            file_put_contents($directory . '/' . $older, 'older');
        }
        $this->queue('php', $target);

        $this->assertSame(0, $this->runCommand());
        $this->assertSame("$older\n$other\n", file_get_contents($stable));
        $this->assertSame($older, file_get_contents($staging));
        $this->assertFileDoesNotExist($master);
        $this->assertSame('libcurl-8.22.0-1-vs18-x86.zip', file_get_contents($unrelated));
        $this->assertSame(0644, fileperms($stable) & 0777);
        foreach (['vs18/x64', 'vs17/x64'] as $targetDirectory) {
            $directory = $this->baseDirectory . '/php-sdk/deps/' . $targetDirectory;
            $this->assertFileDoesNotExist($directory . '/' . $target);
            $this->assertFileExists($directory . '/' . $older);
        }
        $this->assertSame([], $this->queuedTasks());
    }

    public function testPeclDeletionOnlyRemovesExactPackageLineAndZip(): void
    {
        $directory = $this->baseDirectory . '/pecl/deps';
        mkdir($directory, 0755, true);
        $target = 'OpenBLAS-0.3.34-vs18-x64.zip';
        $listed = 'OpenBLAS-0.3.18-vs16-x64.zip';
        $unlisted = 'OpenBLAS-0.3.34-vs18-x86.zip';
        foreach ([$target, $listed, $unlisted] as $filename) {
            file_put_contents($directory . '/' . $filename, 'zip');
        }
        file_put_contents($directory . '/packages.txt', "$listed\r\n$target\r\n");
        $this->queue('pecl', $target);

        $this->assertSame(0, $this->runCommand());
        $this->assertFileDoesNotExist($directory . '/' . $target);
        $this->assertFileExists($directory . '/' . $listed);
        $this->assertFileExists($directory . '/' . $unlisted);
        $this->assertSame("$listed\r\n", file_get_contents($directory . '/packages.txt'));
        $this->assertSame([], $this->queuedTasks());
    }

    public function testPeclDeletionDoesNotAddIntentionallyUnlistedZip(): void
    {
        $directory = $this->baseDirectory . '/pecl/deps';
        mkdir($directory, 0755, true);
        $target = 'OpenBLAS-0.3.34-vs18-x64.zip';
        file_put_contents($directory . '/' . $target, 'zip');
        file_put_contents($directory . '/packages.txt', "listed-1.0-vs18-x64.zip\n");
        $this->queue('pecl', $target);

        $this->assertSame(0, $this->runCommand());
        $this->assertFileDoesNotExist($directory . '/' . $target);
        $this->assertSame("listed-1.0-vs18-x64.zip\n", file_get_contents($directory . '/packages.txt'));
    }

    public function testPeclDeletionWorksWithoutPackagesIndex(): void
    {
        $directory = $this->baseDirectory . '/pecl/deps';
        mkdir($directory, 0755, true);
        $target = 'libfoo-1.0-vs18-x64.zip';
        file_put_contents($directory . '/' . $target, 'zip');
        $this->queue('pecl', $target);

        $this->assertSame(0, $this->runCommand());
        $this->assertFileDoesNotExist($directory . '/' . $target);
        $this->assertFileDoesNotExist($directory . '/packages.txt');
    }

    public function testPhpDeletionCleansStaleSeriesEntryWithoutZip(): void
    {
        $directory = $this->baseDirectory . '/php-sdk/deps/series';
        mkdir($directory, 0755, true);
        $target = 'libfoo-1.0-vs18-x64.zip';
        $index = $directory . '/packages-8.6-vs18-x64-stable.txt';
        file_put_contents($index, "other-1.0-vs18-x64.zip\n$target");
        $this->queue('php', $target);

        $this->assertSame(0, $this->runCommand());
        $this->assertSame('other-1.0-vs18-x64.zip', file_get_contents($index));
    }

    public function testMissingBuildAndIndexEntriesAreIdempotent(): void
    {
        $this->queue('php', 'missing-1.0-vs18-x64.zip');
        $this->queue('pecl', 'missing-1.0-vs18-x64.zip');
        $this->assertSame(0, $this->runCommand());
        $this->assertSame([], $this->queuedTasks());
    }

    #[DataProvider('invalidTasks')]
    public function testInvalidTaskFailsAndRemainsQueued(string $type, string $filename): void
    {
        $task = $this->queue($type, $filename);
        ob_start();
        $result = $this->runCommand();
        $output = (string) ob_get_clean();
        $this->assertSame(1, $result);
        $this->assertStringContainsString('Invalid Winlibs deletion job', $output);
        $this->assertFileExists($task);
    }

    public static function invalidTasks(): array
    {
        return [
            'path traversal' => ['php', '../outside.zip'],
            'php type with trailing newline' => ["php\n", 'zlib-1.3.2-vs18-x64.zip'],
            'pecl type with trailing newline' => ["pecl\n", 'zlib-1.3.2-vs18-x64.zip'],
            'php filename with trailing newline' => ['php', "zlib-1.3.2-vs18-x64.zip\n"],
            'pecl filename with trailing newline' => ['pecl', "zlib-1.3.2-vs18-x64.zip\n"],
        ];
    }

    public function testInvalidDeleteMarkerDoesNotBlockOtherJobsAndCanBeRetried(): void
    {
        $directory = $this->baseDirectory . '/pecl/deps';
        mkdir($directory, 0755, true);
        $invalidTarget = 'libfoo-1.0-vs18-x64.zip';
        $validTarget = 'libbar-1.0-vs18-x64.zip';
        file_put_contents($directory . '/' . $invalidTarget, 'zip');
        file_put_contents($directory . '/' . $validTarget, 'zip');
        file_put_contents($directory . '/packages.txt', "$invalidTarget\n$validTarget");

        $invalidTask = $this->queue('pecl', $invalidTarget);
        file_put_contents($invalidTask, json_encode([
            'delete' => false,
            'type' => 'pecl',
            'filename' => $invalidTarget,
        ], JSON_THROW_ON_ERROR));
        $this->queue('pecl', $validTarget);

        ob_start();
        $result = $this->runCommand();
        $output = (string) ob_get_clean();
        $this->assertSame(1, $result);
        $this->assertStringContainsString('Invalid Winlibs deletion job', $output);
        $this->assertFileExists($invalidTask);
        $this->assertFileExists($directory . '/' . $invalidTarget);
        $this->assertFileDoesNotExist($directory . '/' . $validTarget);
        $this->assertSame([$invalidTask], $this->queuedTasks());

        file_put_contents($invalidTask, json_encode([
            'delete' => true,
            'type' => 'pecl',
            'filename' => $invalidTarget,
        ], JSON_THROW_ON_ERROR));
        $this->assertSame(0, $this->runCommand());
        $this->assertFileDoesNotExist($directory . '/' . $invalidTarget);
        $this->assertSame([], $this->queuedTasks());
    }

    public function testIndexFailureRetainsTaskAndZipForRetry(): void
    {
        $directory = $this->baseDirectory . '/pecl/deps';
        mkdir($directory . '/packages.txt', 0755, true);
        $target = 'libfoo-1.0-vs18-x64.zip';
        file_put_contents($directory . '/' . $target, 'zip');
        $task = $this->queue('pecl', $target);

        ob_start();
        $result = $this->runCommand();
        $output = (string) ob_get_clean();
        $this->assertSame(1, $result);
        $this->assertStringContainsString('Invalid package index', $output);
        $this->assertFileExists($task);
        $this->assertFileExists($directory . '/' . $target);

        rmdir($directory . '/packages.txt');
        $this->assertSame(0, $this->runCommand());
        $this->assertFileDoesNotExist($directory . '/' . $target);
        $this->assertSame([], $this->queuedTasks());
    }

    public function testPartialSeriesUpdateCanBeRetriedWithoutDeletingZipEarly(): void
    {
        $series = $this->baseDirectory . '/php-sdk/deps/series';
        $zipDirectory = $this->baseDirectory . '/php-sdk/deps/vs18/x64';
        mkdir($series, 0755, true);
        mkdir($zipDirectory, 0755, true);
        $target = 'libfoo-1.0-vs18-x64.zip';
        $first = $series . '/packages-8.6-vs18-x64-stable.txt';
        $second = $series . '/packages-8.7-vs18-x64-stable.txt';
        file_put_contents($first, "other-1.0-vs18-x64.zip\n$target");
        mkdir($second, 0755, true);
        file_put_contents($zipDirectory . '/' . $target, 'zip');
        $task = $this->queue('php', $target);

        ob_start();
        $this->assertSame(1, $this->runCommand());
        ob_end_clean();
        $this->assertSame('other-1.0-vs18-x64.zip', file_get_contents($first));
        $this->assertFileExists($zipDirectory . '/' . $target);
        $this->assertFileExists($task);

        rmdir($second);
        file_put_contents($second, $target);
        $this->assertSame(0, $this->runCommand());
        $this->assertFileDoesNotExist($second);
        $this->assertFileDoesNotExist($zipDirectory . '/' . $target);
        $this->assertSame([], $this->queuedTasks());
    }

    public function testLockedJobIsProcessedOnNextRun(): void
    {
        $directory = $this->baseDirectory . '/pecl/deps';
        mkdir($directory, 0755, true);
        $target = 'libfoo-1.0-vs18-x64.zip';
        file_put_contents($directory . '/' . $target, 'zip');
        $task = $this->queue('pecl', $target);
        $lock = fopen(dirname($task) . '.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $this->assertSame(0, $this->runCommand());
            $this->assertFileExists($task);
            $this->assertFileExists($directory . '/' . $target);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $this->assertSame(0, $this->runCommand());
        $this->assertFileDoesNotExist($task);
        $this->assertFileDoesNotExist($directory . '/' . $target);
    }

    public function testAddCommandLeavesDeletionQueued(): void
    {
        $directory = $this->baseDirectory . '/pecl/deps';
        mkdir($directory, 0755, true);
        $target = 'libfoo-1.0-vs18-x64.zip';
        file_put_contents($directory . '/' . $target, 'zip');
        file_put_contents($directory . '/packages.txt', $target);
        $this->queue('pecl', $target);

        $command = new WinlibsCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];
        $this->assertSame(0, $command->handle());
        $this->assertFileExists($directory . '/' . $target);
        $this->assertSame($target, file_get_contents($directory . '/packages.txt'));
        $this->assertCount(1, $this->queuedTasks());

        $this->assertSame(0, $this->runCommand());
        $this->assertFileDoesNotExist($directory . '/' . $target);
        $this->assertSame('', file_get_contents($directory . '/packages.txt'));
        $this->assertSame([], $this->queuedTasks());
    }

    public function testRemovedDeleteOptionDoesNotProcessJobs(): void
    {
        $directory = $this->baseDirectory . '/pecl/deps';
        mkdir($directory, 0755, true);
        $target = 'libfoo-1.0-vs18-x64.zip';
        file_put_contents($directory . '/' . $target, 'zip');
        $task = $this->queue('pecl', $target);

        $command = new WinlibsCommand();
        $command->cliArguments = [
            'runner.php',
            'winlibs:add',
            '--base-directory=' . $this->baseDirectory,
            '--builds-directory=' . $this->buildsDirectory,
            '--delete',
        ];
        ob_start();
        $result = $command->handle();
        $output = (string) ob_get_clean();

        $this->assertSame(1, $result);
        $this->assertSame('Unsupported option for winlibs:add', $output);
        $this->assertFileExists($directory . '/' . $target);
        $this->assertFileExists($task);
    }

    public function testDeleteCommandSkipsPendingUploads(): void
    {
        $directory = $this->baseDirectory . '/pecl/deps';
        mkdir($directory, 0755, true);
        $target = 'libfoo-1.0-vs18-x64.zip';
        file_put_contents($directory . '/' . $target, 'zip');
        $this->queue('pecl', $target);
        $pendingUpload = $this->buildsDirectory . '/winlibs/pending';
        mkdir($pendingUpload, 0755, true);

        $command = new WinlibsDeleteCommand();
        $command->cliArguments = [
            'runner.php',
            'winlibs:delete',
            '--base-directory=' . $this->baseDirectory,
            '--builds-directory=' . $this->buildsDirectory,
        ];
        $this->assertSame(0, $command->handle());
        $this->assertFileDoesNotExist($directory . '/' . $target);
        $this->assertDirectoryExists($pendingUpload);
    }

    public function testSeparateCommandsProcessUploadedBuildThenItsDeletion(): void
    {
        $target = 'libfoo-1.0-vs18-x64.zip';
        $pendingUpload = $this->buildsDirectory . '/winlibs/1234';
        mkdir($pendingUpload, 0755, true);
        file_put_contents($pendingUpload . '/data.json', json_encode([
            'type' => 'pecl',
            'library' => 'libfoo',
        ], JSON_THROW_ON_ERROR));
        file_put_contents($pendingUpload . '/' . $target, 'zip');
        $this->queue('pecl', $target);

        $command = new WinlibsCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];
        $this->assertSame(0, $command->handle());
        $this->assertFileExists($this->baseDirectory . '/pecl/deps/' . $target);
        $this->assertSame($target, file_get_contents($this->baseDirectory . '/pecl/deps/packages.txt'));
        $this->assertDirectoryDoesNotExist($pendingUpload);
        $this->assertCount(1, $this->queuedTasks());

        $this->assertSame(0, $this->runCommand());
        $this->assertFileDoesNotExist($this->baseDirectory . '/pecl/deps/' . $target);
        $this->assertSame('', file_get_contents($this->baseDirectory . '/pecl/deps/packages.txt'));
        $this->assertSame([], $this->queuedTasks());
    }

    public function testFailedUploadDoesNotBlockSeparateDeletionCommand(): void
    {
        $directory = $this->baseDirectory . '/pecl/deps';
        mkdir($directory, 0755, true);
        $target = 'libfoo-1.0-vs18-x64.zip';
        file_put_contents($directory . '/' . $target, 'zip');
        file_put_contents($directory . '/packages.txt', $target);
        $this->queue('pecl', $target);

        $failedUpload = $this->buildsDirectory . '/winlibs/invalid';
        mkdir($failedUpload, 0755, true);
        file_put_contents($failedUpload . '/data.json', '{"type":"pecl","library":"libfoo"}');

        $command = new WinlibsCommand();
        $command->options = ['base-directory' => $this->baseDirectory, 'builds-directory' => $this->buildsDirectory];
        ob_start();
        $result = $command->handle();
        $output = (string) ob_get_clean();

        $this->assertSame(1, $result);
        $this->assertStringContainsString('No valid files found in invalid', $output);
        $this->assertFileExists($directory . '/' . $target);
        $this->assertCount(1, $this->queuedTasks());

        $this->assertSame(0, $this->runCommand());
        $this->assertFileDoesNotExist($directory . '/' . $target);
        $this->assertSame('', file_get_contents($directory . '/packages.txt'));
        $this->assertSame([], $this->queuedTasks());
    }

    public function testRequiresBothDirectories(): void
    {
        $command = new WinlibsDeleteCommand();
        $command->options = ['builds-directory' => $this->buildsDirectory];
        ob_start();
        $this->assertSame(1, $command->handle());
        $this->assertSame('Base directory is required', ob_get_clean());

        $command->options = ['base-directory' => $this->baseDirectory];
        ob_start();
        $this->assertSame(1, $command->handle());
        $this->assertSame('Build directory is required', ob_get_clean());
    }

    private function queue(string $type, string $filename): string
    {
        $queue = $this->buildsDirectory . '/winlibs';
        if (!is_dir($queue)) {
            mkdir($queue, 0755, true);
        }
        $jobDirectory = $queue . '/delete-' . uniqid();
        mkdir($jobDirectory, 0755, true);
        $path = $jobDirectory . '/data.json';
        file_put_contents($path, json_encode([
            'delete' => true,
            'type' => $type,
            'filename' => $filename,
        ], JSON_THROW_ON_ERROR));
        return $path;
    }

    private function queuedTasks(): array
    {
        return glob($this->buildsDirectory . '/winlibs/delete-*/data.json') ?: [];
    }

    private function runCommand(): int
    {
        $command = new WinlibsDeleteCommand();
        $command->options = [
            'base-directory' => $this->baseDirectory,
            'builds-directory' => $this->buildsDirectory,
        ];
        return $command->handle();
    }
}
