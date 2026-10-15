<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // Suite-wide guardrails: no test may reach the network, sleep for real,
        // or report an exception to an external service. (No Sleep facade in
        // this framework version — the class itself is the fake entry point.)
        Http::preventStrayRequests();

        Sleep::fake(syncWithCarbon: true);

        Exceptions::fake();
    }
}
