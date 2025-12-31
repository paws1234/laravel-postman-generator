<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use paws1234\LaravelPostmanGenerator\PostmanGeneratorServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class EdgeCaseCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [PostmanGeneratorServiceProvider::class];
    }

    /**
     * Test generating a collection with no routes.
     */
    public function test_generate_collection_with_no_routes(): void
    {
        // Simulate no routes
        File::shouldReceive('put')->once();
        $exitCode = Artisan::call('postman:generate', ['--dry-run' => true]);
        $this->assertEquals(0, $exitCode);
    }

    /**
     * Test generating a collection with force overwrite.
     */
    public function test_generate_collection_force_overwrite(): void
    {
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('put')->twice();
        $exitCode = Artisan::call('postman:generate', ['--force' => true]);
        $this->assertEquals(0, $exitCode);
    }
}
