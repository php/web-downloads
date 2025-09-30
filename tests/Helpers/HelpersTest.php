<?php
declare(strict_types=1);

namespace Helpers;

use PHPUnit\Framework\TestCase;
use App\Helpers\Helpers;

class HelpersTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/testDir';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Ensure all files and directories are cleaned up after each test
        if (file_exists($this->testDir)) {
            $helper = new Helpers();
            $helper->rmdirr($this->testDir);
        }
    }

    public function testRemoveNonExistentDirectory(): void
    {
        $helper = new Helpers();
        $this->assertFalse($helper->rmdirr($this->testDir . '/nonexistent'));
    }

    public function testRemoveDirectoryWithFiles(): void
    {
        mkdir($this->testDir, 0777, true);
        file_put_contents($this->testDir . '/file.txt', 'Hello World');

        $helper = new Helpers();
        $result = $helper->rmdirr($this->testDir);
        $this->assertTrue($result);
        $this->assertDirectoryDoesNotExist($this->testDir);
    }

    public function testRemoveDirectoryWithNestedDirectories(): void
    {
        mkdir($this->testDir . '/nested', 0777, true);
        file_put_contents($this->testDir . '/nested/file.txt', 'Hello World');

        $helper = new Helpers();
        $result = $helper->rmdirr($this->testDir);
        $this->assertTrue($result);
        $this->assertDirectoryDoesNotExist($this->testDir);
    }
}
