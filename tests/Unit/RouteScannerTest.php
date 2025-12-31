<?php

declare(strict_types=1);

use Orchestra\Testbench\TestCase;
use paws1234\LaravelPostmanGenerator\Route\RouteScanner;

class RouteScannerTest extends TestCase
{
    public function test_scan_returns_array(): void
    {
        $scanner = new RouteScanner();
        $result = $scanner->scan([
            'routes' => [
                'exclude_fallback' => false,
                'api_only' => false,
                'exclude_middleware' => [],
                'include_middleware' => [],
                'exclude_prefixes' => [],
                'include_prefixes' => [],
                'exclude_names' => [],
                'include_names' => [],
            ],
            'organization' => [
                'sort_by' => 'uri',
                'sort_direction' => 'asc',
                'group_by' => 'none',
                'folder_depth' => 1,
            ],
            'auth' => [
                'include_auth' => false,
                'detect_from_middleware' => false,
                'mode' => 'none',
                'auth_middleware' => [],
            ],
            'request_generation' => [
                'parameterize_route_params' => false,
                'infer_body_from_form_request' => false,
                'generate_example_values' => false,
            ],
            'tests' => [
                'enabled' => false,
            ],
            'output' => [
                'base_path' => 'output',
                'collections_dir' => 'collections',
                'environments_dir' => 'environments',
                'collection_filename' => 'collection.json',
            ],
            'environments' => [],
        ]);
        $this->assertIsArray($result);
    }
}
