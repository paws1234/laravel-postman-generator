<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator\Route;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;

final class RouteScanner
{
    /**
     * Extract path params from route signature and query params from FormRequest rules (GET/HEAD only).
     *
     * @param  Route       $route
     * @param  class-string<FormRequest>|null $formRequestClass
     * @return array<int, array{key: string, in: 'path'|'query', required: bool, type: string|null, description: string|null}>
     */
    /**
     * Extract path params from route signature and query params from FormRequest rules (GET/HEAD only).
     *
     * @param  Route       $route
     * @param  class-string<FormRequest>|null $formRequestClass
     * @return array<int, array{key: string, in: 'path'|'query', required: bool, type: string|null, description: string|null}>
     */
    public function extractQueryParams(Route $route, ?string $formRequestClass = null): array
    {
        $params = [];

        // ---- PATH params from route signature (e.g. /users/{id} or /users/{id?})
        // Capture both name + optional marker
        preg_match_all('/\{(\w+)(\?)?\}/', $route->uri(), $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $key = (string) $m[1];
            $optional = isset($m[2]) && $m[2] === '?';

            $params[] = [
                'key' => $key,
                'in' => 'path',
                'required' => !$optional,
                'type' => 'string',
                'description' => null,
            ];
        }

        // ---- QUERY params from FormRequest rules (only for GET/HEAD)
        $methods = $route->methods();
        $isRead = in_array('GET', $methods, true) || in_array('HEAD', $methods, true);

        if ($isRead && $formRequestClass && class_exists($formRequestClass)) {
            /** @var FormRequest $req */
            $req = app($formRequestClass);

            if (method_exists($req, 'rules')) {
                $rules = (array) $req->rules();

                foreach ($rules as $field => $ruleSpec) {
                    if (!is_string($field) || $field === '' || str_contains($field, '.')) {
                        continue; // skip nested keys (foo.bar) for now
                    }

                    // Normalize rules into a single string for simple checks
                    $ruleStr = is_array($ruleSpec) ? implode('|', array_map('strval', $ruleSpec)) : (string) $ruleSpec;

                    // Required logic: if it contains "required" => required, if nullable/sometimes => not required (best-effort)
                    $required =
                        str_contains($ruleStr, 'required') &&
                        !str_contains($ruleStr, 'nullable') &&
                        !str_contains($ruleStr, 'sometimes');

                    $params[] = [
                        'key' => $field,
                        'in' => 'query',
                        'required' => $required,
                        'type' => null,
                        'description' => null,
                    ];
                }
            }
        }

        return $params;
    }

    public function scan(array $cfg, array $overrides = []): array
    {
        $merged = $this->deepMerge($cfg, $overrides);

        /** @var Router $router */
        $router = app('router');
        $routes = $router->getRoutes();

        $out = [];

        /** @var Route $r */
        foreach ($routes as $r) {
            if (($merged['routes']['exclude_fallback'] ?? false) && method_exists($r, 'isFallback') && $r->isFallback()) {
                continue;
            }

            $methods = array_values(array_diff($r->methods(), ['HEAD']));
            if ($methods === []) {
                continue;
            }

            foreach ($methods as $method) {
                $uri = ltrim($r->uri(), '/');

                $middleware = array_values(array_unique($r->gatherMiddleware()));
                if (!$this->passesFilters($merged, $uri, is_string($r->getName()) ? $r->getName() : null, $middleware)) {
                    continue;
                }

                $action = $this->actionString($r);
                [$controller, $formRequest] = $this->inferControllerAndFormRequest($r);

                $authMode = $this->detectAuth($merged, $middleware);

                $out[] = [
                    'method' => strtoupper($method),
                    'uri' => $uri,
                    'name' => $r->getName(),
                    'action' => $action,
                    'controller' => $controller,
                    'middleware' => $middleware,
                    'auth_mode' => $authMode, // renamed from has_auth (it’s actually string|null)
                    'form_request' => $formRequest,
                ];
            }
        }

        return $this->sortRoutes(
            $out,
            (string)($merged['organization']['sort_by'] ?? 'uri'),
            (string)($merged['organization']['sort_direction'] ?? 'asc'),
        );
    }

    private function passesFilters(array $cfg, string $uri, ?string $name, array $middleware): bool
    {
        if (!empty($cfg['routes']['api_only'])) {
            $isApiPrefix = str_starts_with($uri, 'api/');
            $hasApiMiddleware = in_array('api', $middleware, true) || $this->containsMiddleware($middleware, 'api');

            if (!$isApiPrefix && !$hasApiMiddleware) {
                return false;
            }
        }

        foreach ((array)($cfg['routes']['exclude_middleware'] ?? []) as $mw) {
            if ($this->containsMiddleware($middleware, (string) $mw)) {
                return false;
            }
        }

        $includeMw = (array)($cfg['routes']['include_middleware'] ?? []);
        if ($includeMw !== []) {
            $ok = false;
            foreach ($includeMw as $mw) {
                if ($this->containsMiddleware($middleware, (string) $mw)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) return false;
        }

        foreach ((array)($cfg['routes']['exclude_prefixes'] ?? []) as $p) {
            $p = trim((string) $p, '/');
            if ($p !== '' && (str_starts_with($uri, $p . '/') || $uri === $p)) {
                return false;
            }
        }

        $includePrefixes = (array)($cfg['routes']['include_prefixes'] ?? []);
        if ($includePrefixes !== []) {
            $ok = false;
            foreach ($includePrefixes as $p) {
                $p = trim((string) $p, '/');
                if ($p !== '' && (str_starts_with($uri, $p . '/') || $uri === $p)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) return false;
        }

        if ($name) {
            foreach ((array)($cfg['routes']['exclude_names'] ?? []) as $pattern) {
                $pattern = (string) $pattern;
                if (@preg_match($pattern, '') !== false && preg_match($pattern, $name)) {
                    return false;
                }
            }
        }

        $includeNames = (array)($cfg['routes']['include_names'] ?? []);
        if ($includeNames !== []) {
            if (!$name) return false;

            $ok = false;
            foreach ($includeNames as $pattern) {
                $pattern = (string) $pattern;
                if (@preg_match($pattern, '') !== false && preg_match($pattern, $name)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) return false;
        }

        return true;
    }

    private function containsMiddleware(array $middleware, string $needle): bool
    {
        foreach ($middleware as $mw) {
            if ($mw === $needle) return true;
            if (str_starts_with($mw, $needle . ':')) return true;
        }
        return false;
    }

    /**
     * Detect auth strategy for a route based on middleware and config.
     * Returns string|null: 'bearer', 'basic', etc., or null if no auth.
     */
    private function detectAuth(array $cfg, array $middleware): ?string
    {
        if (empty($cfg['auth']['include_auth'])) {
            return null;
        }

        if (!empty($cfg['auth']['multi'])) {
            foreach ((array)$cfg['auth']['multi'] as $authGroup) {
                $groupMw = (array)($authGroup['middleware'] ?? []);
                foreach ($groupMw as $mw) {
                    if ($this->containsMiddleware($middleware, (string)$mw)) {
                        return is_string($authGroup['mode'] ?? null) ? $authGroup['mode'] : null;
                    }
                }
            }
        }

        if (empty($cfg['auth']['detect_from_middleware'])) {
            return is_string($cfg['auth']['mode'] ?? null) ? $cfg['auth']['mode'] : null;
        }

        foreach ((array)($cfg['auth']['auth_middleware'] ?? []) as $mw) {
            if ($this->containsMiddleware($middleware, (string)$mw)) {
                return is_string($cfg['auth']['mode'] ?? null) ? $cfg['auth']['mode'] : 'bearer';
            }
        }

        return null;
    }

    private function actionString(Route $r): ?string
    {
        $a = $r->getActionName();
        return is_string($a) ? $a : null;
    }

    /**
     * @return array{0: string|null, 1: class-string<FormRequest>|null} controller, formRequest
     */
    private function inferControllerAndFormRequest(Route $route): array
    {
        $action = $route->getAction();
        $uses = Arr::get($action, 'controller') ?? Arr::get($action, 'uses');

        $controller = null;
        $method = null;
        /** @var class-string<FormRequest>|null $formRequest */
        $formRequest = null;

        // Invokable controller class string
        if (is_string($uses) && class_exists($uses) && method_exists($uses, '__invoke')) {
            $controller = $uses;
            $method = '__invoke';
        }
        // [Class, method]
        elseif (is_array($uses) && count($uses) === 2 && is_string($uses[0]) && is_string($uses[1])) {
            $controller = $uses[0];
            $method = $uses[1];
        }
        // Class@method
        elseif (is_string($uses) && str_contains($uses, '@')) {
            [$controller, $method] = explode('@', $uses) + [null, null];
        }
        // Class::method
        elseif (is_string($uses) && str_contains($uses, '::')) {
            [$controller, $method] = explode('::', $uses) + [null, null];
        }

        // Closure action (Laravel stores closure in 'uses')
        if ($uses instanceof \Closure) {
            $ref = new \ReflectionFunction($uses);
            foreach ($ref->getParameters() as $p) {
                $t = $p->getType();
                if ($t instanceof \ReflectionNamedType && !$t->isBuiltin()) {
                    $paramClass = $t->getName();
                    if (is_subclass_of($paramClass, FormRequest::class)) {
                        /** @var class-string<FormRequest> $paramClass */
                        $formRequest = $paramClass;
                        break;
                    }
                }
            }
            return [null, $formRequest];
        }

        // Reflect controller method params for FormRequest
        if ($controller && $method && class_exists($controller) && method_exists($controller, $method)) {
            try {
                $ref = new \ReflectionMethod($controller, $method);
                foreach ($ref->getParameters() as $p) {
                    $t = $p->getType();
                    if ($t instanceof \ReflectionNamedType && !$t->isBuiltin()) {
                        $paramClass = $t->getName();
                        if (is_subclass_of($paramClass, FormRequest::class)) {
                            /** @var class-string<FormRequest> $paramClass */
                            $formRequest = $paramClass;
                            break;
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return [$controller, $formRequest];
    }

    private function sortRoutes(array $routes, string $by, string $dir): array
    {
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

        usort($routes, function ($a, $b) use ($by, $dir) {
            $ka = $a[$by] ?? '';
            $kb = $b[$by] ?? '';
            $cmp = strcmp((string)$ka, (string)$kb);
            return $dir === 'desc' ? -$cmp : $cmp;
        });

        return $routes;
    }

    private function deepMerge(array $base, array $overrides): array
    {
        foreach ($overrides as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                $base[$k] = $this->deepMerge($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }
}
