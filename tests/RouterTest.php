<?php
use PHPUnit\Framework\TestCase;
use App\Router;

class RouterTest extends TestCase {
    public function testHandleIndexRequest() {
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = '';
        $router = new Router();
        $router->registerRoute('/', 'GET', 'App\Http\Controllers\IndexController'
        );
        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertEquals('Welcome!', $output, 'Should respond with Welcome! for index route.');
    }

    public function testHandleRequestUnauthorized() {
        $_SERVER['REQUEST_URI'] = '/protected';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = '';
        $router = new Router();
        $router->registerRoute('/protected', 'GET', 'TestHandler', true);
        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertEquals('Unauthorized', $output, 'Should respond with Unauthorized for protected routes.');
    }

    public function testHandleRequestMethodNotAllowed() {
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $router = new Router();
        $router->registerRoute('/test', 'GET', 'TestHandler');
        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertStringContainsString('Method Not Allowed', $output, 'Should respond with Method Not Allowed.');
    }

    public function testHandleRequestNotFound() {
        $_SERVER['REQUEST_URI'] = '/nonexistent';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $router = new Router();
        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertEquals('Not Found', $output, 'Should respond with Not Found for unregistered routes.');
    }
}
