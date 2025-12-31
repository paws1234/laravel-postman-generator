<?php

declare(strict_types=1);

use Orchestra\Testbench\TestCase;
use paws1234\LaravelPostmanGenerator\Postman\CollectionBuilder;
use paws1234\LaravelPostmanGenerator\Postman\ItemBuilder;
use paws1234\LaravelPostmanGenerator\Postman\AuthBuilder;
use paws1234\LaravelPostmanGenerator\FormRequest\FormRequestBodyInferer;

class CollectionBuilderTest extends TestCase
{
    public function test_build_returns_json(): void
    {
        $itemBuilder = new ItemBuilder(new AuthBuilder(), new FormRequestBodyInferer());
        $builder = new CollectionBuilder($itemBuilder);
        $routes = [
            [
                'method' => 'GET',
                'uri' => 'users',
                'name' => 'users.index',
                'action' => null,
                'controller' => null,
                'middleware' => [],
                'auth_mode' => null,
                'form_request' => null,
            ],
        ];
        $cfg = [
            'organization' => [
                'group_by' => 'none',
                'folder_depth' => 1,
            ],
            'request_generation' => [
                'parameterize_route_params' => false,
                'infer_body_from_form_request' => false,
                'generate_example_values' => false,
            ],
            'auth' => [
                'include_auth' => false,
            ],
            'tests' => [
                'enabled' => false,
            ],
        ];
        $json = $builder->build($routes, $cfg, 'Test Collection');
        $this->assertJson($json);
        $data = json_decode($json, true);
        $this->assertArrayHasKey('info', $data);
        $this->assertArrayHasKey('item', $data);
    }
}
