<?php
declare(strict_types=1);

namespace Console\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use App\Console\Command\WinlibsCommand;
use App\Helpers\Helpers;
use ZipArchive;

class WinlibsCommandTest extends TestCase
{
    private string $baseDirectory;
    
    private string $winlibsDirectory;

    private string $buildsDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDirectory = sys_get_temp_dir() . '/winlibs_test_base';
        $this->buildsDirectory = sys_get_temp_dir() . '/builds';
        mkdir($this->baseDirectory, 0755, true);

        $this->winlibsDirectory = $this->buildsDirectory . '/winlibs';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        (new Helpers)->rmdirr($this->baseDirectory);
        (new Helpers)->rmdirr($this->buildsDirectory);
    }

    #[DataProvider('versionProvider')]
    public function testSuccessfulFileOperations($phpVersion, $vsVersion, $arch, $stability): void
    {
        mkdir($this->winlibsDirectory . '/lib', 0755, true);

        $library = 'lib';
        $ref = '2.0.0';
        $seriesFilePath = $this->baseDirectory . "/php-sdk/deps/series/packages-$phpVersion-$vsVersion-$arch-$stability.txt";

        file_put_contents($this->winlibsDirectory . '/lib/data.json', json_encode([
            'type' => 'php',
            'library' => $library,
            'ref' => $ref,
            'vs_version_targets' => $vsVersion,
            'php_versions' => $phpVersion,
            'stability' => $stability
        ]));

        $zipPath = $this->winlibsDirectory . "/lib/lib-$ref-$vsVersion-$arch.zip";
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFromString("dummy_file.txt", "dummy content");
            $zip->close();
        }

        $command = new WinlibsCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();

        $this->assertEquals(0, $result, "Command should return success.");
        $this->assertStringEqualsFile($seriesFilePath, "lib-$ref-$vsVersion-$arch.zip", "Series file should be updated correctly.");
    }

    #[DataProvider('versionProvider')]
    public function testSuccessfulFileOperationsWithExistingSeriesFile($phpVersion, $vsVersion, $arch, $stability): void
    {
        mkdir($this->winlibsDirectory . '/lib', 0755, true);
        mkdir($this->baseDirectory . '/php-sdk/deps/series', 0755, true);

        $library = 'lib';
        $ref = '2.0.0';
        $seriesFilePath = $this->baseDirectory . "/php-sdk/deps/series/packages-$phpVersion-$vsVersion-$arch-$stability.txt";

        file_put_contents($this->winlibsDirectory . '/lib/data.json', json_encode([
            'type' => 'php',
            'library' => $library,
            'ref' => $ref,
            'vs_version_targets' => $vsVersion,
            'php_versions' => $phpVersion,
            'stability' => $stability
        ]));

        file_put_contents($seriesFilePath, "existing-$ref-$vsVersion-$arch.zip");

        $zipPath = $this->winlibsDirectory . "/lib/lib-$ref-$vsVersion-$arch.zip";
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFromString("dummy_file.txt", "dummy content");
            $zip->close();
        }

        $command = new WinlibsCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();

        $this->assertEquals(0, $result, "Command should return success.");
        $this->assertStringContainsString("lib-$ref-$vsVersion-$arch.zip", file_get_contents($seriesFilePath), "Series file should be updated correctly.");
    }

    #[DataProvider('versionProvider')]
    public function testSuccessfulFileOperationsWithExistingOldLibraryInSeriesFile($phpVersion, $vsVersion, $arch, $stability): void
    {
        mkdir($this->winlibsDirectory . '/lib', 0755, true);
        mkdir($this->baseDirectory . '/php-sdk/deps/series', 0755, true);

        $library = 'lib';
        $ref = '2.0.0';
        $seriesFilePath = $this->baseDirectory . "/php-sdk/deps/series/packages-$phpVersion-$vsVersion-$arch-$stability.txt";

        file_put_contents($this->winlibsDirectory . '/lib/data.json', json_encode([
            'type' => 'php',
            'library' => $library,
            'ref' => $ref,
            'vs_version_targets' => $vsVersion,
            'php_versions' => $phpVersion,
            'stability' => $stability
        ]));

        file_put_contents($seriesFilePath, "lib-1.0.0-$vsVersion-$arch.zip");

        $zipPath = $this->winlibsDirectory . "/lib/lib-$ref-$vsVersion-$arch.zip";
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFromString("dummy_file.txt", "dummy content");
            $zip->close();
        }

        $command = new WinlibsCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();

        $this->assertEquals(0, $result, "Command should return success.");
        $this->assertStringContainsString("lib-$ref-$vsVersion-$arch.zip", file_get_contents($seriesFilePath), "Series file should be updated correctly.");
        $this->assertStringNotContainsString("lib-1.0.0-$vsVersion-$arch.zip", file_get_contents($seriesFilePath), "Series file should be updated correctly.");
    }

    public function testSuccessfulPeclFileOperations(): void
    {
        mkdir($this->winlibsDirectory . '/redis', 0755, true);
        mkdir($this->baseDirectory . '/pecl/deps', 0755, true);

        $library = 'phpredis';
        $ref = '5.3.7';
        $vsVersion = 'vs16';
        $arch = 'x64';

        file_put_contents($this->winlibsDirectory . '/redis/data.json', json_encode([
            'type' => 'pecl',
            'library' => $library,
            'ref' => $ref,
            'vs_version_targets' => $vsVersion,
            'php_versions' => '8.2',
            'stability' => 'stable'
        ]));

        $zipPath = $this->winlibsDirectory . "/redis/redis-$ref-$vsVersion-$arch.zip";
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFromString('dummy_file.txt', 'dummy content');
            $zip->close();
        }

        $command = new WinlibsCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);

        $result = $command->handle();

        $this->assertEquals(0, $result, 'Command should return success.');
        $this->assertFileExists($this->baseDirectory . "/pecl/deps/$library-$ref-$vsVersion-$arch.zip");
    }

    public static function versionProvider(): array
    {
        return [
            ['7.4', 'vs15', 'x86', 'stable'],
            ['8.0', 'vs16', 'x64', 'staging'],
            ['8.1', 'vs17', 'x86', 'stable'],
        ];
    }

    public function testCommandHandlesMissingBaseDirectory(): void
    {
        $command = new WinlibsCommand();
        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();
        $this->assertEquals('Base directory is required', $output);
        $this->assertEquals(1, $result);
    }

    public function testHandlesCorruptDataFile(): void
    {
        mkdir($this->winlibsDirectory . '/lib', 0755, true);
        file_put_contents($this->winlibsDirectory . '/lib/data.json', '{corrupt json');

        $command = new WinlibsCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);
        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();
        $this->assertStringContainsString('Syntax error', $output);
        $this->assertEquals(1, $result);
    }


    #[DataProvider('fileProvider')]
    public function testParseFiles($file, $expected): void
    {
        $command = new WinlibsCommand();
        $result = $command->parseFiles([$file]);
        $this->assertEquals($expected, $result[0]);
    }

    public static function fileProvider(): array
    {
        return [
            ['/tmp/net-snmp-5.7.3-1-vs16-x86.zip', [
                'file_path'     => '/tmp/net-snmp-5.7.3-1-vs16-x86.zip',
                'file_name'     => 'net-snmp-5.7.3-1-vs16-x86.zip',
                'extension'     => 'zip',
                'artifact_name' => 'net-snmp',
                'vs_version'    => 'vs16',
                'arch'          => 'x86',
            ]],
            ['/tmp/libxml2-2.9.14-1-vs16-x86.zip', [
                'file_path'     => '/tmp/libxml2-2.9.14-1-vs16-x86.zip',
                'file_name'     => 'libxml2-2.9.14-1-vs16-x86.zip',
                'extension'     => 'zip',
                'artifact_name' => 'libxml2',
                'vs_version'    => 'vs16',
                'arch'          => 'x86',
            ]],
            ['/tmp/c-client-2007f-1-vs16-x86.zip', [
                'file_path'     => '/tmp/c-client-2007f-1-vs16-x86.zip',
                'file_name'     => 'c-client-2007f-1-vs16-x86.zip',
                'extension'     => 'zip',
                'artifact_name' => 'c-client',
                'vs_version'    => 'vs16',
                'arch'          => 'x86',
            ]],
            ['/tmp/nghttp2-1.57.0-vs16-x86.zip', [
                'file_path'     => '/tmp/nghttp2-1.57.0-vs16-x86.zip',
                'file_name'     => 'nghttp2-1.57.0-vs16-x86.zip',
                'extension'     => 'zip',
                'artifact_name' => 'nghttp2',
                'vs_version'    => 'vs16',
                'arch'          => 'x86',
            ]],
            ['/tmp/openssl-1.1.1w.pl1-vs16-x86.zip', [
                'file_path'     => '/tmp/openssl-1.1.1w.pl1-vs16-x86.zip',
                'file_name'     => 'openssl-1.1.1w.pl1-vs16-x86.zip',
                'extension'     => 'zip',
                'artifact_name' => 'openssl',
                'vs_version'    => 'vs16',
                'arch'          => 'x86',
            ]],
            ['/tmp/zlib-1.2.12-vs16-x86.zip', [
                'file_path'     => '/tmp/zlib-1.2.12-vs16-x86.zip',
                'file_name'     => 'zlib-1.2.12-vs16-x86.zip',
                'extension'     => 'zip',
                'artifact_name' => 'zlib',
                'vs_version'    => 'vs16',
                'arch'          => 'x86',
            ]],
        ];
    }
}
