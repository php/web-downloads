<?php
declare(strict_types=1);

namespace Actions;

use App\Actions\UpdateReleasesJson;
use App\Helpers\Helpers;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;

class UpdateReleasesJsonTest extends TestCase
{
    private string $tempDir;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        date_default_timezone_set('UTC');
        $this->tempDir = sys_get_temp_dir() . '/update_releases_test_' . uniqid();
        if (!mkdir($this->tempDir, 0777, true) && !is_dir($this->tempDir)) {
            throw new Exception(sprintf('Directory "%s" was not created', $this->tempDir));
        }
    }

    protected function tearDown(): void
    {
        (new Helpers())->rmdirr($this->tempDir);
    }

    /**
     * @throws Exception
     */
    public function testHandleValidReleases(): void
    {
        $releases = [
            '7.4' => [
                'version' => '7.4.0',
                'nts-VC15-x64' => [
                    'mtime'      => "2023-01-01 10:00:00",
                    'zip'        => [
                        'path'   => 'php-7.4.0-nts-Win32-VC15-x64-latest.zip',
                        'size'   => '12kB',
                        'sha256' => 'abcdef'
                    ],
                    'debug_pack' => [
                        'mtime'      => "2023-01-01 11:00:00",
                        'path'       => 'php-debug-pack-7.4.0-nts-Win32-VC15-x64.zip',
                        'size'       => '3kB',
                        'sha256'     => '123456'
                    ],
                ],
                'source' => [
                    'path'   => 'php-7.4.0-src.zip',
                    'size'   => '5MB',
                    'sha256' => '987654'
                ],
            ],
        ];

        $updater = new UpdateReleasesJson();
        $updater->handle($releases, $this->tempDir);

        $jsonFile = $this->tempDir . '/releases.json';
        $this->assertFileExists($jsonFile);

        $jsonData = json_decode(file_get_contents($jsonFile), true);
        $this->assertNotNull($jsonData, 'Decoded JSON should not be null.');

        $expectedDate = (new DateTimeImmutable("2023-01-01 10:00:00"))->format('c');

        $this->assertEquals(
            $expectedDate,
            $jsonData['7.4']['nts-VC15-x64']['mtime'],
            'Main build mtime should be in ISO 8601 format.'
        );

        $this->assertArrayHasKey('source', $jsonData['7.4']);
        $this->assertArrayNotHasKey('mtime', $jsonData['7.4']['source']);
    }

    public function testHandleWithInvalidMtimeThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to generate releases.json:');

        $releases = [
            '7.4' => [
                'nts-VC15-x64' => [
                    'mtime' => "invalid date string",
                    'zip'   => [
                        'path'   => 'php-7.4.0-nts-Win32-VC15-x64-latest.zip',
                        'size'   => '12kB',
                        'sha256' => 'abcdef'
                    ],
                ],
            ],
        ];

        (new UpdateReleasesJson())->handle($releases, $this->tempDir);
    }

    /**
     * @throws Exception
     */
    public function testHandleWithNoMtime(): void
    {
        $releases = [
            '7.4' => [
                'nts-VC15-x64' => [
                    'zip' => [
                        'path'   => 'php-7.4.0-nts-Win32-VC15-x64-latest.zip',
                        'size'   => '12kB',
                        'sha256' => 'abcdef'
                    ],
                ],
            ],
        ];

        $updater = new UpdateReleasesJson();
        $updater->handle($releases, $this->tempDir);

        $jsonFile = $this->tempDir . '/releases.json';
        $this->assertFileExists($jsonFile);

        $jsonData = json_decode(file_get_contents($jsonFile), true);
        $this->assertNotNull($jsonData, 'Decoded JSON should not be null.');

        $this->assertArrayHasKey('zip', $jsonData['7.4']['nts-VC15-x64']);
        $this->assertEquals(
            'php-7.4.0-nts-Win32-VC15-x64-latest.zip',
            $jsonData['7.4']['nts-VC15-x64']['zip']['path']
        );
        $this->assertArrayNotHasKey('mtime', $jsonData['7.4']['nts-VC15-x64']);
    }
}
