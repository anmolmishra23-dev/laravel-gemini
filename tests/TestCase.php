<?php

namespace AnmolMishra\Gemini\Tests;

use AnmolMishra\Gemini\GeminiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [GeminiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('gemini.api_key', 'test-key');
    }
}
