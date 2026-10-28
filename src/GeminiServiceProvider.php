<?php

namespace AnmolMishra\Gemini;

use AnmolMishra\Gemini\Console\GenerateImageCommand;
use AnmolMishra\Gemini\Console\GenerateVideoCommand;
use Illuminate\Support\ServiceProvider;

class GeminiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/gemini.php', 'gemini');

        $this->app->singleton(GeminiClient::class, fn ($app) => new GeminiClient($app['config']['gemini']));

        $this->app->singleton(Gemini::class, fn ($app) => new Gemini(
            $app->make(GeminiClient::class),
            $app['config']['gemini'],
        ));

        $this->app->alias(Gemini::class, 'gemini');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/gemini.php' => config_path('gemini.php'),
            ], 'gemini-config');

            $this->commands([GenerateImageCommand::class, GenerateVideoCommand::class]);
        }
    }
}
