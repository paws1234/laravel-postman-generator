<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator\Postman;

use paws1234\LaravelPostmanGenerator\FormRequest\FormRequestBodyInferer;

final class ItemBuilder
{
    public function __construct(
        private readonly AuthBuilder $authBuilder,
        private readonly FormRequestBodyInferer $bodyInferer
    ) {}

    public function buildItem(array $route, array $cfg, int $seq): array
    {
        $method = $route['method'];
        $uri = $route['uri'];

        $urlPath = $this->parameterizeRouteParams($uri, (bool)$cfg['request_generation']['parameterize_route_params']);
        $rawUrl = '{{baseUrl}}/' . ltrim($urlPath, '/');

        $headers = [
            ['key' => 'Accept', 'value' => 'application/json'],
        ];
        // Sort headers by key for determinism
        usort($headers, fn($a, $b) => strcmp($a['key'], $b['key']));

        $request = [
            'method' => $method,
            'header' => $headers,
            'url' => $rawUrl, // string form is valid and avoids baseUrl parsing issues
        ];

        // Query params (for GET/HEAD)
        if (in_array($method, ['GET', 'HEAD'], true)) {
            // Try to extract from route signature and FormRequest
            $scanner = new \paws1234\LaravelPostmanGenerator\Route\RouteScanner();
            $queryParams = $scanner->extractQueryParams($route['__route_obj'] ?? null, $route['form_request'] ?? null);
            if ($queryParams && is_array($queryParams)) {
                // Sort query params by key for determinism
                usort($queryParams, fn($a, $b) => strcmp($a['key'], $b['key']));
                $request['url'] = [
                    'raw' => $rawUrl,
                    'host' => ['{{baseUrl}}'],
                    'path' => explode('/', ltrim($urlPath, '/')),
                    'query' => array_map(fn($p) => [
                        'key' => $p['key'],
                        'value' => '',
                        'description' => $p['description'] ?? null,
                        'disabled' => !$p['required'],
                    ], $queryParams),
                ];
            }
        }

        // Body inference (JSON)
        $hasBody = in_array($method, ['POST', 'PUT', 'PATCH'], true);
        if ($hasBody && !empty($cfg['request_generation']['infer_body_from_form_request'])) {
            $body = $this->bodyInferer->infer(
                $route['form_request'],
                (bool)$cfg['request_generation']['generate_example_values']
            );

            if (is_array($body)) {
                $request['body'] = [
                    'mode' => 'raw',
                    'raw' => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    'options' => ['raw' => ['language' => 'json']],
                ];
                $request['header'][] = ['key' => 'Content-Type', 'value' => 'application/json'];
            }
        }

        $auth = $this->authBuilder->build($cfg, (bool)$route['has_auth']);
        $auth = $this->authBuilder->build($cfg, $route['auth_mode'] ?? null);
        if ($auth) {
            $request['auth'] = $auth;
        }

        $item = [
            'name' => $this->nameFor($route),
            'request' => $request,
        ];

        if (!empty($cfg['tests']['enabled'])) {
            $status = (int)($cfg['tests']['default_success_status'] ?? 200);
            $item['event'] = [[
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => [
                        'pm.test("Status is ' . $status . '", function () {',
                        '    pm.response.to.have.status(' . $status . ');',
                        '});',
                    ],
                ],
            ]];
        }

        return $item;
    }

    private function nameFor(array $route): string
    {
        if (!empty($route['name'])) {
            return (string)$route['name'];
        }
        return NameHelper::titleFromRoute($route['method'], $route['uri']);
    }

    private function parameterizeRouteParams(string $uri, bool $enabled): string
    {
        if (!$enabled) return $uri;

        // Replace {id} with {{id}} for Postman variables
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', fn($m) => '{{' . $m[1] . '}}', $uri) ?? $uri;
    }
}
