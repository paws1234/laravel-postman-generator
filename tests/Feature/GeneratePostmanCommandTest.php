<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;

class GeneratePostmanCommandTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\paws1234\LaravelPostmanGenerator\PostmanGeneratorServiceProvider::class];
    }

    public function test_command_runs_successfully(): void
    {
        $exitCode = Artisan::call('postman:generate', [
            '--dry-run' => true,
        ]);
        $this->assertSame(0, $exitCode);
    }
}
