<?php
declare(strict_types=1);

namespace Http\Controllers;

use App\Http\BaseController;
use PHPUnit\Framework\TestCase;

class MockPeclController extends BaseController {
    protected function validate(array $data): bool {
        return isset($data['key']);
    }

    protected function execute(array $data): void {
        echo "Executed";
    }

    public function handle(): void
    {
        $data = json_decode(file_get_contents($this->inputPath), true);

        if ($this->validate($data)) {
            $this->execute($data);
        }
    }
}

class PeclControllerTest extends TestCase {
    public function testHandleWithValidData() {
        $data = json_encode(["key" => "value"]);
        $tempFile = tempnam(sys_get_temp_dir(), 'phpunit');
        file_put_contents($tempFile, $data);
        $controller = new MockPeclController($tempFile);
        $this->expectOutputString("Executed");
        $controller->handle();
        unlink($tempFile);
    }
}
