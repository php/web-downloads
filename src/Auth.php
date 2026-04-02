<?php
declare(strict_types=1);

namespace App;

class Auth
{
    public function authenticate(): bool
    {
        $expectedToken = (string) getenv('AUTH_TOKEN');
        if ($expectedToken === '') {
            return false;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $authToken = str_replace('Bearer ', '', $authHeader);

        return hash_equals($expectedToken, $authToken);
    }
}
