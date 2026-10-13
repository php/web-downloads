<?php
declare(strict_types=1);

namespace Http\Controllers;

use App\Http\Controllers\WinlibsController;
use JsonException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MockWinlibsController extends WinlibsController {
    #[Override]
    protected function validate(array $data): bool {
        return isset($data['key']);
    }

    #[Override]
    protected function execute(array $data): void {
        echo "Executed";
    }

    #[Override]
    public function handle(): void
    {
        $data = json_decode(file_get_contents($this->inputPath), true);

        if ($this->validate($data)) {
            $this->execute($data);
        }
    }
}

class WinlibsControllerTest extends TestCase {
    #[DataProvider('architectureProvider')]
    public function testArchitectureValidation(array $override, bool $expected): void
    {
        $controller = new class extends WinlibsController {
            public function validateRequest(array $data): bool { return parent::validate($data); }
        };
        $data = $override + [
            'library' => 'zlib', 'ref' => '1.3.2', 'type' => 'php',
            'workflow_run_id' => '123', 'php_versions' => '8.4',
            'vs_version_targets' => 'vs17', 'stability' => 'staging',
            'update_series' => 'true', 'token' => 'test',
        ];
        ob_start();
        try {
            $this->assertSame($expected, $controller->validateRequest($data));
        } finally {
            ob_end_clean();
            http_response_code(200);
        }
    }

    public static function architectureProvider(): array
    {
        return [
            [[], true], [['arch' => 'all'], true], [['arch' => 'arm64'], true],
            [['arch' => 'x64'], true], [['arch' => 'x86'], true],
            [['arch' => ''], false], [['arch' => 'x64,arm64'], false],
            [['arch' => "arm64\n"], false], [['arch' => null], false],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testHandleWithValidData() {
        $data = json_encode(["key" => "value"]);
        $tempFile = tempnam(sys_get_temp_dir(), 'phpunit');
        file_put_contents($tempFile, $data);
        $controller = new MockWinlibsController($tempFile);
        $this->expectOutputString("Executed");
        $controller->handle();
        unlink($tempFile);
    }
}
