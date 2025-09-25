<?php

namespace Http\Controllers;

use App\Http\Controllers\SeriesInitController;
use App\Http\Controllers\SeriesStabilityController;
use PHPUnit\Framework\TestCase;

class MockSeriesStabilityController extends SeriesStabilityController {
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

class SeriesStabilityControllerTest extends TestCase {
    public function testHandleWithValidData() {
        $data = json_encode(["key" => "value"]);
        $tempFile = tempnam(sys_get_temp_dir(), 'phpunit');
        file_put_contents($tempFile, $data);
        $controller = new MockSeriesStabilityController($tempFile);
        $this->expectOutputString("Executed");
        $controller->handle();
        unlink($tempFile);
    }
}
