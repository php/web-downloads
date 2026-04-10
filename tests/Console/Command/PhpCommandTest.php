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
        return [
            [[
                'php-8.4.1-Win32-vs17-x64.zip',
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
    }

    private function stageBuilds(array $phpZips, $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            foreach ($phpZips as $zipFileName) {
                $zipFilePath = $this->buildsDirectory . '/php/' . $zipFileName;
                $innerZip = new ZipArchive();
                if ($innerZip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
                    $innerZip->addFromString("test_file.php", "<?php echo 'Hello, world!'; ?>");
                    $innerZip->close();
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
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $this->stageBuilds($phpZips, $this->buildsDirectory . '/php/test.zip');

        $result = $command->handle();

        $this->assertEquals(0, $result, "Command should return success.");

        $expectedDestination = $this->baseDirectory . '/releases';
        $this->assertDirectoryExists($expectedDestination, "Destination directory should exist.");
    }

    public function testCommandHandlerWithMissingTestPackZip(): void
    {
        $command = new PhpCommand(new GetListing(), new UpdateReleasesJson());
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

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
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);
        ob_start();
        $result = $command->handle();
        ob_get_clean();
        $this->assertEquals(1, $result, "Command should return failure on broken zip.");
    }

    public function testCleanupAfterCommand(): void
    {
        $command = new PhpCommand(new GetListing(), new UpdateReleasesJson());
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);
        $command->handle();
        $tempDirectory = "/tmp/php-*";
        $this->assertEmpty(glob($tempDirectory));
    }

    public function testStableReleasePromotesSeriesFiles(): void
    {
        $command = new PhpCommand(new GetListing(), new UpdateReleasesJson());
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $this->stageBuilds(self::buildsProvider()[0][0], $this->buildsDirectory . '/php/test.zip');

        $result = $command->handle();

        $this->assertSame(0, $result);
        $this->assertFileExists($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x86-stable.txt');
        $this->assertFileExists($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x64-stable.txt');
        $this->assertSame('x86-staging', file_get_contents($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x86-stable.txt'));
        $this->assertSame('x64-staging', file_get_contents($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x64-stable.txt'));
    }

    public function testQaReleaseDoesNotPromoteSeriesFiles(): void
    {
        $command = new PhpCommand(new GetListing(), new UpdateReleasesJson());
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $this->stageBuilds(self::buildsProvider()[1][0], $this->buildsDirectory . '/php/test.zip');

        $result = $command->handle();

        $this->assertSame(0, $result);
        $this->assertFileDoesNotExist($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x86-stable.txt');
        $this->assertFileDoesNotExist($this->baseDirectory . '/php-sdk/deps/series/packages-8.4-vs17-x64-stable.txt');
    }
}
