<?php
declare(strict_types=1);

use App\Auth;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase {
    public function testAuthenticateWithValidToken() {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid_token';
        putenv('AUTH_TOKEN=valid_token');
        $auth = new Auth();
        $this->assertTrue($auth->authenticate(), 'Authentication should succeed with valid token.');
    }

    public function testAuthenticateWithInvalidToken() {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid_token';
        putenv('AUTH_TOKEN=valid_token');
        $auth = new Auth();
        $this->assertFalse($auth->authenticate(), 'Authentication should fail with invalid token.');
    }

    public function testAuthenticateWithNoToken() {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        putenv('AUTH_TOKEN=valid_token');
        $auth = new Auth();
        $this->assertFalse($auth->authenticate(), 'Authentication should fail with no token provided.');
    }

    public function testAuthenticateFailsWhenAuthTokenUnset() {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        putenv('AUTH_TOKEN');
        $auth = new Auth();
        $this->assertFalse($auth->authenticate(), 'Authentication should fail when AUTH_TOKEN is unset.');
    }

    public function testAuthenticateFailsWithEmptyAuthTokenAndEmptyHeader() {
        $_SERVER['HTTP_AUTHORIZATION'] = '';
        putenv('AUTH_TOKEN');
        $auth = new Auth();
        $this->assertFalse($auth->authenticate(), 'Authentication should fail when both AUTH_TOKEN and header are empty.');
    }
}
