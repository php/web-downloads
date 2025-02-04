<?php

namespace Actions;

use App\Actions\GetListing;
use App\Helpers\Helpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GetListingTest extends TestCase
{
    private string $tempDir;
    private GetListing $getListing;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/get_listing_test_' . uniqid();
        if (!mkdir($this->tempDir) && !is_dir($this->tempDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->tempDir));
        }
        $this->getListing = new GetListing();
    }

    protected function tearDown(): void
    {
        (new Helpers())->rmdirr($this->tempDir);
    }

    public static function bytes2StringProvider(): array
    {
        return [
            [100, "100B"],
            [1024, "1024B"],
            [1025, "1kB"],
            [5000, "4.88kB"],
            [1048576, "1024kB"],
            [1048577, "1MB"],
            [1073741824, "1024MB"],
            [1073741825, "1GB"],
            [1099511627776, "1024GB"],
            [1099511627777, "1TB"],
        ];
    }

    public static function parseFileNameProvider(): array
    {
        return [
            'with nts' => [
                'php-7.4.0-nts-Win32-VC15-x64-latest.zip',
                [
                    'version'       => '7.4.0',
                    'version_short' => '7.4',
                    'nts'           => 'nts',
                    'vc'            => 'VC15',
                    'arch'          => 'x64',
                    'ts'            => false,
                ],
            ],
            'without nts' => [
                'php-7.4.0-Win32-VC15-x64-latest.zip',
                [
                    'version'       => '7.4.0',
                    'version_short' => '7.4',
                    'nts'           => false,
                    'vc'            => 'VC15',
                    'arch'          => 'x64',
                    'ts'            => false,
                ],
            ],
            'with numeric vc' => [
                'php-5.6.0-nts-Win32-7-x86-latest.zip',
                [
                    'version'       => '5.6.0',
                    'version_short' => '5.6',
                    'nts'           => 'nts',
                    'vc'            => 'VC6',
                    'arch'          => 'x86',
                    'ts'            => false,
                ],
            ],
        ];
    }

    #[DataProvider('bytes2StringProvider')]
    public function testBytes2String(int $bytes, string $expected): void
    {
        $result = $this->getListing->bytes2string($bytes);
        $this->assertEquals($expected, $result);
    }
    #[DataProvider('parseFileNameProvider')]
    public function testParseFileName(string $fileName, array $expected): void
    {
        $parts = $this->getListing->parseFileName($fileName);
        $this->assertEquals($expected, $parts);
    }

    public function testGetSha256SumsCreatesFileAndReturnsHashes(): void
    {
        $dummyZip = $this->tempDir . '/dummy.zip';
        $content = "dummy content";
        file_put_contents($dummyZip, $content);

        $sums = $this->getListing->getSha256Sums($this->tempDir);

        $key = strtolower(basename($dummyZip));
        $expectedHash = hash_file('sha256', $dummyZip);

        $this->assertArrayHasKey($key, $sums);
        $this->assertEquals($expectedHash, $sums[$key]);

        $shaFile = $this->tempDir . '/sha256sum.txt';
        $this->assertFileExists($shaFile);
        $this->assertNotEmpty(file_get_contents($shaFile));
    }

    public function testHandleWithNoMatchingFiles(): void
    {
        $result = $this->getListing->handle($this->tempDir);
        $this->assertEmpty($result, "Expected an empty result when no build files are present.");
    }

    public function testHandleWithMatchingFiles(): void
    {
        $mainBuildFile = $this->tempDir . '/php-7.4.0-nts-Win32-VC15-x64-latest.zip';
        file_put_contents($mainBuildFile, 'build content');
        $fixedTime = 1609459200; // 2021-01-01 00:00:00
        touch($mainBuildFile, $fixedTime);

        $sourceFile      = $this->tempDir . '/php-7.4.0-src.zip';
        $debugPackFile   = $this->tempDir . '/php-debug-pack-7.4.0-nts-Win32-VC15-x64.zip';
        $develPackFile   = $this->tempDir . '/php-devel-pack-7.4.0-nts-Win32-VC15-x64.zip';
        $installerFile   = $this->tempDir . '/php-7.4.0-nts-Win32-VC15-x64.msi';
        $testPackFile    = $this->tempDir . '/php-test-pack-7.4.0.zip';

        file_put_contents($sourceFile, 'source content');
        file_put_contents($debugPackFile, 'debug content');
        file_put_contents($develPackFile, 'devel content');
        file_put_contents($installerFile, 'installer content');
        file_put_contents($testPackFile, 'test content');

        $result = $this->getListing->handle($this->tempDir);
        $versionShortKey = '7.4';
        $buildKey = 'nts-VC15-x64';

        $this->assertArrayHasKey($versionShortKey, $result);
        $versionData = $result[$versionShortKey];

        $this->assertArrayHasKey('version', $versionData);
        $this->assertEquals('7.4.0', $versionData['version']);

        $this->assertArrayHasKey($buildKey, $versionData);
        $buildDetails = $versionData[$buildKey];

        $expectedMtime = date('Y-M-d H:i:s', filemtime($mainBuildFile));
        $this->assertEquals($expectedMtime, $buildDetails['mtime']);

        $this->assertArrayHasKey('zip', $buildDetails);
        $zipInfo = $buildDetails['zip'];
        $this->assertEquals(basename($mainBuildFile), $zipInfo['path']);

        $expectedZipSize = $this->getListing->bytes2string(filesize($mainBuildFile));
        $this->assertEquals($expectedZipSize, $zipInfo['size']);

        $expectedSha256 = hash_file('sha256', $mainBuildFile);
        $this->assertEquals($expectedSha256, $zipInfo['sha256']);

        $this->assertArrayHasKey('source', $versionData);
        $sourceInfo = $versionData['source'];
        $this->assertEquals(basename($sourceFile), $sourceInfo['path']);
        $expectedSourceSize = $this->getListing->bytes2string(filesize($sourceFile));
        $this->assertEquals($expectedSourceSize, $sourceInfo['size']);
        $this->assertEquals($expectedSha256, $sourceInfo['sha256']);

        $this->assertArrayHasKey('test_pack', $versionData);
        $testPackInfo = $versionData['test_pack'];
        $this->assertEquals(basename($testPackFile), $testPackInfo['path']);
        $expectedTestPackSize = $this->getListing->bytes2string(filesize($testPackFile));
        $this->assertEquals($expectedTestPackSize, $testPackInfo['size']);
        $this->assertEquals($expectedSha256, $testPackInfo['sha256']);

        $this->assertArrayHasKey('debug_pack', $buildDetails);
        $debugInfo = $buildDetails['debug_pack'];
        $this->assertEquals(basename($debugPackFile), $debugInfo['path']);
        $expectedDebugSize = $this->getListing->bytes2string(filesize($debugPackFile));
        $this->assertEquals($expectedDebugSize, $debugInfo['size']);
        $this->assertEquals($expectedSha256, $debugInfo['sha256']);

        $this->assertArrayHasKey('devel_pack', $buildDetails);
        $develInfo = $buildDetails['devel_pack'];
        $this->assertEquals(basename($develPackFile), $develInfo['path']);
        $expectedDevelSize = $this->getListing->bytes2string(filesize($develPackFile));
        $this->assertEquals($expectedDevelSize, $develInfo['size']);
        $this->assertEquals($expectedSha256, $develInfo['sha256']);

        $this->assertArrayHasKey('installer', $buildDetails);
        $installerInfo = $buildDetails['installer'];
        $this->assertEquals(basename($installerFile), $installerInfo['path']);
        $expectedInstallerSize = $this->getListing->bytes2string(filesize($installerFile));
        $this->assertEquals($expectedInstallerSize, $installerInfo['size']);
        $this->assertEquals($expectedSha256, $installerInfo['sha256']);

        $this->assertFileExists($this->tempDir . '/sha256sum.txt');
    }
}
