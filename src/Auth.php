<?php
declare(strict_types=1);

namespace App;

class Auth
{
    public function authenticate(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $authToken = str_replace('Bearer ', '', $authHeader);

        return hash_equals((string) getenv('AUTH_TOKEN'), $authToken);
    }
}
