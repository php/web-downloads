<?php
declare(strict_types=1);

namespace Http;

use App\Http\DeferredCallbacks;
use PHPUnit\Framework\TestCase;

class DeferredCallbacksTest extends TestCase
{
    protected function tearDown(): void
    {
        DeferredCallbacks::clear();
        parent::tearDown();
    }

    public function testCallbacksRunInRegistrationOrderAndOnlyOnce(): void
    {
        $invoked = [];

        DeferredCallbacks::add(static function () use (&$invoked): void {
            $invoked[] = 'first';
        });
        DeferredCallbacks::add(static function () use (&$invoked): void {
            $invoked[] = 'second';
        });

        DeferredCallbacks::invoke();
        DeferredCallbacks::invoke();

        $this->assertSame(['first', 'second'], $invoked);
    }

    public function testClearRemovesCallbacksWithoutInvokingThem(): void
    {
        $invoked = false;

        DeferredCallbacks::add(static function () use (&$invoked): void {
            $invoked = true;
        });

        DeferredCallbacks::clear();
        DeferredCallbacks::invoke();

        $this->assertFalse($invoked);
    }
}
