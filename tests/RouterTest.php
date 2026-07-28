<?php
declare(strict_types=1);

use App\Http\Controllers\IndexController;
use App\Http\ControllerInterface;
use App\Http\DeferredCallbacks;
use PHPUnit\Framework\TestCase;
use App\Router;

class DeferredTestController implements ControllerInterface
{
    public function handle(): void
    {
        echo 'Response';
        DeferredCallbacks::add(static fn () => print 'Deferred');
    }
}

class RouterTest extends TestCase {
    protected function tearDown(): void
    {
        DeferredCallbacks::clear();
        parent::tearDown();
    }

    /**
     * @throws JsonException
     */
    public function testHandleIndexRequest() {
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_AUTHORIZATION'] = '';
        $router = new Router();
        $router->registerRoute('/', 'GET', IndexController::class
        );
        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertEquals('Welcome!', $output, 'Should respond with Welcome! for index route.');
    }

    /**
     * @throws JsonException
     */
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

    /**
     * @throws JsonException
     */
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

    /**
     * @throws JsonException
     */
    public function testHandleRequestNotFound() {
        $_SERVER['REQUEST_URI'] = '/nonexistent';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $router = new Router();
        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();
        $this->assertEquals('Not Found', $output, 'Should respond with Not Found for unregistered routes.');
    }

    public function testHandleRequestInvokesDeferredCallbacksAfterController(): void
    {
        $_SERVER['REQUEST_URI'] = '/deferred';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router();
        $router->registerRoute('/deferred', 'GET', DeferredTestController::class);

        ob_start();
        $router->handleRequest();
        $output = ob_get_clean();

        $this->assertSame('ResponseDeferred', $output);
    }
}
