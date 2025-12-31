<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator;

use Illuminate\Support\ServiceProvider;
use paws1234\LaravelPostmanGenerator\Commands\ClearPostmanCommand;
use paws1234\LaravelPostmanGenerator\Commands\GeneratePostmanCommand;

final class PostmanGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/postman-generator.php', 'postman-generator');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/postman-generator.php' => config_path('postman-generator.php'),
        ], 'postman-generator-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GeneratePostmanCommand::class,
                ClearPostmanCommand::class,
            ]);
        }
    }
}
