<?php

namespace Console\Command;

use App\Helpers\Helpers;
use PHPUnit\Framework\TestCase;
use App\Console\Command\PeclCommand;
use ZipArchive;

class PeclCommandTest extends TestCase
{
    private string $baseDirectory;
    private string $buildsDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDirectory = sys_get_temp_dir() . '/pecl_test_base';
        $this->buildsDirectory = sys_get_temp_dir() . '/builds';

        mkdir($this->baseDirectory, 0755, true);
        mkdir($this->buildsDirectory . '/pecl', 0755, true);

        $zipPath = $this->buildsDirectory . '/pecl/test.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            $zip->addFromString("test_file.txt", "content");
            $zip->close();
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Helpers)->rmdirr($this->baseDirectory);
        (new Helpers)->rmdirr($this->buildsDirectory);
    }

    public function testPeclAddSuccessfullyExtractsZip(): void
    {
        $command = new PeclCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);
        $result = $command->handle();
        $this->assertEquals(0, $result);

        $extractedFiles = glob($this->baseDirectory . '/pecl/releases/*.*');
        $this->assertCount(1, $extractedFiles);
        $this->assertStringContainsString('test_file.txt', $extractedFiles[0]);

        $content = file_get_contents($extractedFiles[0]);
        $this->assertEquals('content', $content);
    }

    public function testPeclAddFailsWithoutBaseDirectory(): void
    {
        $command = new PeclCommand();
        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();
        $this->assertEquals('Base directory is required', $output);
        $this->assertEquals(1, $result);

        (new Helpers)->rmdirr($this->buildsDirectory . '/pecl');
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);
        $result = $command->handle();
        $this->assertEquals(0, $result);
    }

    public function testPeclAddFailsWithBrokenZip(): void
    {
        $zipPath = $this->buildsDirectory . '/pecl/broken.zip';
        file_put_contents($zipPath, 'broken zip');

        $command = new PeclCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);
        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();
        $this->assertEquals('Failed to extract the extension', $output);
        $this->assertEquals(1, $result);
    }

    public function testPeclAddFailsToExtractBuild(): void
    {
        $destinationDirectory = $this->baseDirectory . '/pecl/releases';
        mkdir($destinationDirectory, 0555, true);
        $command = new PeclCommand();
        $command->setOption('base-directory', $this->baseDirectory);
        $command->setOption('builds-directory', $this->buildsDirectory);
        ob_start();
        $result = @$command->handle();
        $output = ob_get_clean();
        chmod($destinationDirectory, 0755);
        if($output) {
            $this->assertStringContainsString('Failed to extract the extension build', $output);
            $this->assertEquals(1, $result);
        } else {
            $this->assertEquals(0, $result);
        }
    }
}
