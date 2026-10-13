<?php
declare(strict_types=1);

namespace Http\Controllers;

use App\Console\Command\SeriesInitCommand;
use App\Helpers\Helpers;
use App\Http\Controllers\SeriesInitController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SeriesInitControllerTest extends TestCase
{
    private string $directory;
    private string|false $originalBuildsDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/series_init_controller_' . uniqid();
        mkdir($this->directory . '/builds/series', 0755, true);
        $this->originalBuildsDirectory = getenv('BUILDS_DIRECTORY');
        putenv('BUILDS_DIRECTORY=' . $this->directory . '/builds');
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        putenv($this->originalBuildsDirectory === false ? 'BUILDS_DIRECTORY' : 'BUILDS_DIRECTORY=' . $this->originalBuildsDirectory);
        http_response_code(200);
        Helpers::rmdirr($this->directory);
        parent::tearDown();
    }

    public static function validPayloadProvider(): array
    {
        return [
            'legacy request' => [[]],
            'VS initialization' => [['subject' => 'vs']],
            'VS initialization ignores architecture inputs' => [['subject' => 'vs', 'source_arch' => 'x64', 'target_arch' => 'arm64']],
            'missing subject defaults to VS initialization' => [['source_arch' => 'x64', 'target_arch' => 'arm64']],
            'ARM64 for existing PHP series' => [['subject' => 'arch', 'source_arch' => 'x64', 'target_arch' => 'arm64']],
            'ARM64 with blank workflow VS inputs' => [['subject' => 'arch', 'source_arch' => 'x64', 'target_arch' => 'arm64', 'source_vs' => '', 'target_vs' => '']],
            'ARM64 ignores VS inputs' => [['subject' => 'arch', 'source_arch' => 'x64', 'target_arch' => 'arm64', 'source_vs' => 'vs17', 'target_vs' => 'vs18']],
            'ARM64 for PHP 8.4' => [['subject' => 'arch', 'php_version' => '8.4', 'source_arch' => 'x64', 'target_arch' => 'arm64'], 'vs17'],
            'ARM64 for master' => [['subject' => 'arch', 'php_version' => 'master', 'source_arch' => 'x64', 'target_arch' => 'arm64'], 'vs18'],
        ];
    }

    #[DataProvider('validPayloadProvider')]
    public function testRequestCreatesExpectedSeries(array $fields, string $vsVersion = 'vs16'): void
    {
        $payload = $fields + ['php_version' => '8.2'];
        $architectureCopy = ($payload['subject'] ?? 'vs') === 'arch';
        if (!$architectureCopy) {
            $payload += ['source_vs' => $vsVersion, 'target_vs' => $vsVersion];
        }
        $input = $this->directory . '/input.json';
        file_put_contents($input, json_encode($payload));
        (new SeriesInitController($input))->handle();
        $this->assertSame(200, http_response_code());
        $jobs = glob($this->directory . '/builds/series/series-init-*.json');
        $this->assertCount(1, $jobs);
        $this->assertSame($payload, json_decode(file_get_contents($jobs[0]), true));

        $seriesDirectory = $this->directory . '/base/php-sdk/deps/series';
        mkdir($seriesDirectory, 0755, true);
        $phpVersion = $payload['php_version'];
        $sourceVersion = $architectureCopy ? $phpVersion : 'master';
        $sourceArchitectures = $architectureCopy ? ['x64'] : ['x86', 'x64'];
        foreach ($sourceArchitectures as $arch) {
            foreach (['stable', 'staging'] as $stability) {
                file_put_contents("$seriesDirectory/packages-$sourceVersion-$vsVersion-$arch-$stability.txt", "libffi-3.8.0-1-$vsVersion-$arch.zip\nzlib-1.3.2-$vsVersion-$arch.zip\n");
            }
        }

        $command = new SeriesInitCommand();
        $command->options = ['base-directory' => $this->directory . '/base', 'builds-directory' => $this->directory . '/builds'];
        $this->assertSame(0, $command->handle());

        foreach ($architectureCopy ? ['arm64'] : ['x86', 'x64'] as $arch) {
            foreach (['stable', 'staging'] as $stability) {
                $this->assertStringEqualsFile("$seriesDirectory/packages-$phpVersion-$vsVersion-$arch-$stability.txt", "libffi-3.8.0-1-$vsVersion-$arch.zip\nzlib-1.3.2-$vsVersion-$arch.zip\n");
            }
        }
        $this->assertFileDoesNotExist($jobs[0]);
        $this->assertFileDoesNotExist($jobs[0] . '.lock');
    }

    public static function invalidPayloadProvider(): array
    {
        return [
            'missing target architecture' => [['source_arch' => 'x64']],
            'missing source architecture' => [['target_arch' => 'arm64']],
            'all to specific architecture' => [['source_arch' => 'all', 'target_arch' => 'arm64']],
            'specific architecture to all' => [['source_arch' => 'x64', 'target_arch' => 'all']],
            'invalid architecture' => [['source_arch' => 'x64', 'target_arch' => 'aarch64']],
            'architecture path' => [['source_arch' => '../x64', 'target_arch' => 'arm64']],
            'architecture trailing newline' => [['source_arch' => 'x64', 'target_arch' => "arm64\n"]],
            'null architecture' => [['source_arch' => null, 'target_arch' => 'arm64']],
            'empty architecture' => [['source_arch' => '', 'target_arch' => 'arm64']],
            'array architecture' => [['source_arch' => ['x64'], 'target_arch' => 'arm64']],
            'invalid PHP series' => [['php_version' => '../8.2', 'source_arch' => 'x64', 'target_arch' => 'arm64']],
            'VS master request' => [['subject' => 'vs', 'php_version' => 'master', 'source_vs' => 'vs16', 'target_vs' => 'vs16']],
            'VS missing source version' => [['subject' => 'vs', 'target_vs' => 'vs16']],
            'VS missing target version' => [['subject' => 'vs', 'source_vs' => 'vs16']],
            'VS blank versions' => [['subject' => 'vs', 'source_vs' => '', 'target_vs' => '']],
            'VS invalid source version' => [['subject' => 'vs', 'source_vs' => '../vs16', 'target_vs' => 'vs16']],
            'VS invalid target version' => [['subject' => 'vs', 'source_vs' => 'vs16', 'target_vs' => '../vs16']],
            'invalid subject' => [['subject' => 'all']],
            'null subject' => [['subject' => null]],
            'empty subject' => [['subject' => '']],
            'array subject' => [['subject' => ['arch']]],
            'subject trailing newline' => [['subject' => "arch\n"]],
        ];
    }

    #[DataProvider('invalidPayloadProvider')]
    public function testRejectsInvalidRequest(array $fields): void
    {
        $input = $this->directory . '/input.json';
        file_put_contents($input, json_encode($fields + ['subject' => 'arch', 'php_version' => '8.2']));
        ob_start();
        (new SeriesInitController($input))->handle();
        $output = ob_get_clean();

        $this->assertSame(400, http_response_code());
        $this->assertStringStartsWith('Invalid request:', $output);
        $this->assertEmpty(glob($this->directory . '/builds/series/series-init-*.json'));
    }
}
