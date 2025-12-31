<?php

declare(strict_types=1);

namespace Tests\Feature;

use Orchestra\Testbench\TestCase;
use paws1234\LaravelPostmanGenerator\PostmanGeneratorServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Mockery;

final class EdgeCaseCollectionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PostmanGeneratorServiceProvider::class,
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test generating a collection with no routes.
     */
    public function test_generate_collection_with_no_routes(): void
    {
        // Dry run disables file writing; no need to mock File facade
        $exitCode = Artisan::call('postman:generate', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
    }

    /**
     * Test generating a collection with force overwrite.
     */
    public function test_generate_collection_force_overwrite(): void
{
    $exitCode = Artisan::call('postman:generate', [
        '--force' => true,
        '--dry-run' => true,
    ]);

    $this->assertSame(0, $exitCode);
}

}
