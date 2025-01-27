<?php
use PHPUnit\Framework\TestCase;

class ControllerInterfaceTest extends TestCase {
    public function testInterfaceExists() {
        $this->assertTrue(interface_exists(App\Http\ControllerInterface::class), "ControllerInterface should exist.");
    }
}
