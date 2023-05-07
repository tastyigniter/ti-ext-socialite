<?php

namespace Igniter\Socialite\Tests;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \Igniter\Flame\ServiceProvider::class,
            \Igniter\Socialite\Extension::class,
        ];
    }
}
