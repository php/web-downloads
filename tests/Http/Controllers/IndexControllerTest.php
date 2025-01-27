<?php

namespace Http\Controllers;

use App\Http\Controllers\IndexController;
use PHPUnit\Framework\TestCase;

class IndexControllerTest extends TestCase {
    public function testHandle() {
        $controller = new IndexController();
        $controller->handle();
        $this->expectOutputString('Welcome!');
        $this->assertTrue($controller->validate([]));
        ob_start();
        $controller->execute([]);
        $this->assertEmpty(ob_get_clean());
    }
}
