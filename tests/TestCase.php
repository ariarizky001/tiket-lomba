<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\RateLimiter;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limiter for the IP-based key used by throttle middleware
        // throttle middleware uses key format: api:127.0.0.1 (prefix:ip)
        RateLimiter::clear('api:127.0.0.1');
        RateLimiter::clear('api:localhost');
    }
}
