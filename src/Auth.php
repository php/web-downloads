<?php

namespace App;

class Auth
{
    public function authenticate(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $authToken = str_replace('Bearer ', '', $authHeader);

        return $authToken === getenv('AUTH_TOKEN');
    }
}
