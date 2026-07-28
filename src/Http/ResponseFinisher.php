<?php
declare(strict_types=1);

namespace App\Http;

final class ResponseFinisher
{
    public static function finish(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }

        if (in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            return;
        }

        $serverProtocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        if (!headers_sent() && str_starts_with($serverProtocol, 'HTTP/1.')) {
            header('Connection: close');
        }

        while (ob_get_level() > 0) {
            if (!@ob_end_flush()) {
                break;
            }
        }

        flush();
    }
}
