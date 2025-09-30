<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Http\BaseController;

class MockBaseController extends BaseController {
    protected function validate(array $data): bool {
        return isset($data['key']);
    }

    protected function execute(array $data): void {
        echo "Executed";
    }
}

class BaseControllerTest extends TestCase {
    private string $tempFile;

    protected function setUp(): void {
        parent::setUp();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'phpunit');
    }

    protected function tearDown(): void {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    /**
     * @throws JsonException
     */
    public function testHandleWithValidData() {
        $data = json_encode(["key" => "value"]);
        file_put_contents($this->tempFile, $data);
        $controller = new MockBaseController($this->tempFile);
        $this->expectOutputString("Executed");
        $controller->handle();
    }

    /**
     * @throws JsonException
     */
    public function testHandleWithInvalidData() {
        $data = json_encode([]);
        file_put_contents($this->tempFile, $data);
        $controller = new MockBaseController($this->tempFile);
        $this->expectOutputString('');
        $controller->handle();
    }

    public function testHandleWithMalformedJson() {
        $data = "{key: 'value'}";
        file_put_contents($this->tempFile, $data);
        $controller = new MockBaseController($this->tempFile);
        $this->expectException(JsonException::class);
        $controller->handle();
    }
}
