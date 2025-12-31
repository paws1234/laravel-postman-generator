<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator\Postman;

use paws1234\LaravelPostmanGenerator\FormRequest\FormRequestBodyInferer;
use paws1234\LaravelPostmanGenerator\Route\RouteScanner;
use Illuminate\Routing\Route;

final class ItemBuilder
{
    public function __construct(
        private readonly AuthBuilder $authBuilder,
        private readonly FormRequestBodyInferer $bodyInferer,
        private readonly RouteScanner $routeScanner,
    ) {}

    /**
     * Build a Postman request item from route data.
     *
     * @param array $route
     * @param array $cfg
     * @param int $seq
     * @return array Postman request item
     */
    public function buildItem(array $route, array $cfg, int $seq): array
    {
        $method = $route['method'];
        $uri = $route['uri'];

        $urlPath = $this->parameterizeRouteParams(
            $uri,
            (bool) ($cfg['request_generation']['parameterize_route_params'] ?? false)
        );

        $rawUrl = '{{baseUrl}}/' . ltrim($urlPath, '/');

        $headers = [
            ['key' => 'Accept', 'value' => 'application/json'],
        ];

        usort($headers, fn ($a, $b) => strcmp($a['key'], $b['key']));

        $request = [
            'method' => $method,
            'header' => $headers,
            'url' => $rawUrl,
        ];

        /**
         * -------------------------
         * Query params (GET / HEAD)
         * -------------------------
         */
        if (in_array($method, ['GET', 'HEAD'], true)
            && isset($route['__route_obj'])
            && $route['__route_obj'] instanceof Route
        ) {
            $queryParams = $this->routeScanner->extractQueryParams(
                $route['__route_obj'],
                $route['form_request'] ?? null
            );

            if ($queryParams !== []) {
                usort($queryParams, fn ($a, $b) => strcmp($a['key'], $b['key']));

                $request['url'] = [
                    'raw' => $rawUrl,
                    'host' => ['{{baseUrl}}'],
                    'path' => explode('/', ltrim($urlPath, '/')),
                    'query' => array_map(static fn ($p) => [
                        'key' => $p['key'],
                        'value' => '',
                        'description' => $p['description'] ?? null,
                        'disabled' => !$p['required'],
                    ], $queryParams),
                ];
            }
        }

        /**
         * -------------------------
         * Body (POST / PUT / PATCH)
         * -------------------------
         */
        if (
            in_array($method, ['POST', 'PUT', 'PATCH'], true)
            && !empty($cfg['request_generation']['infer_body_from_form_request'])
        ) {
            $body = $this->bodyInferer->infer(
                $route['form_request'] ?? null,
                (bool) ($cfg['request_generation']['generate_example_values'] ?? false)
            );

            if (is_array($body)) {
                $request['body'] = [
                    'mode' => 'raw',
                    'raw' => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    'options' => [
                        'raw' => ['language' => 'json'],
                    ],
                ];

                $request['header'][] = [
                    'key' => 'Content-Type',
                    'value' => 'application/json',
                ];
            }
        }

        /**
         * -----
         * Auth
         * -----
         */
        $auth = $this->authBuilder->build($cfg, $route['auth_mode'] ?? null);
        if ($auth !== null) {
            $request['auth'] = $auth;
        }

        $item = [
            'name' => $this->nameFor($route),
            'request' => $request,
        ];

        /**
         * -----
         * Tests
         * -----
         */
        if (!empty($cfg['tests']['enabled'])) {
            $status = (int) ($cfg['tests']['default_success_status'] ?? 200);

            $item['event'] = [[
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => [
                        "pm.test(\"Status is {$status}\", function () {",
                        "    pm.response.to.have.status({$status});",
                        "});",
                    ],
                ],
            ]];
        }

        return $item;
    }

    private function nameFor(array $route): string
    {
        if (!empty($route['name'])) {
            return (string) $route['name'];
        }

        return NameHelper::titleFromRoute($route['method'], $route['uri']);
    }

    private function parameterizeRouteParams(string $uri, bool $enabled): string
    {
        if (!$enabled) {
            return $uri;
        }

        // {id} or {id?} → {{id}}
        return preg_replace_callback(
            '/\{([a-zA-Z0-9_]+)\??\}/',
            static fn ($m) => '{{' . $m[1] . '}}',
            $uri
        ) ?? $uri;
    }
}
