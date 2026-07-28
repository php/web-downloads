<?php
declare(strict_types=1);

namespace App\Http;

use Throwable;

final class DeferredCallbacks
{
    /**
     * @var list<callable>
     */
    private static array $callbacks = [];

    public static function add(callable $callback): void
    {
        self::$callbacks[] = $callback;
    }

    public static function hasCallbacks(): bool
    {
        return self::$callbacks !== [];
    }

    public static function invoke(): void
    {
        if (!self::hasCallbacks()) {
            return;
        }

        $callbacks = self::$callbacks;
        self::$callbacks = [];

        ignore_user_abort(true);
        set_time_limit(0);

        ResponseFinisher::finish();

        foreach ($callbacks as $callback) {
            try {
                $callback();
            } catch (Throwable $throwable) {
                error_log(sprintf(
                    'Deferred callback failed: %s: %s',
                    $throwable::class,
                    $throwable->getMessage(),
                ));
            }
        }
    }

    public static function clear(): void
    {
        self::$callbacks = [];
    }
}
