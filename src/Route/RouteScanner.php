    /**
     * Extract query parameters from route signature and FormRequest rules.
     * @param \Illuminate\Routing\Route $route
     * @param string|null $formRequestClass
     * @return array<int, array{key: string, required: bool, type: string|null, description: string|null}>
     */
    public function extractQueryParams(\Illuminate\Routing\Route $route, ?string $formRequestClass = null): array
    {
        $params = [];
        // From route signature (e.g. /users/{id})
        preg_match_all('/\{(\w+)\??\}/', $route->uri(), $matches);
        foreach ($matches[1] as $param) {
            $params[] = [
                'key' => $param,
                'required' => !str_ends_with($param, '?'),
                'type' => 'string',
                'description' => null,
            ];
        }
        // From FormRequest rules (for GET/HEAD)
        if ($formRequestClass && class_exists($formRequestClass)) {
            $req = app($formRequestClass);
            if (method_exists($req, 'rules')) {
                $rules = $req->rules();
                foreach ($rules as $field => $ruleSpec) {
                    if (is_string($field) && !str_contains($field, '.')) {
                        $params[] = [
                            'key' => $field,
                            'required' => !str_contains(implode('|', (array)$ruleSpec), 'nullable'),
                            'type' => null,
                            'description' => null,
                        ];
                    }
                }
            }
        }
        return $params;
    }
<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator\Route;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;

final class RouteScanner
{
    /**
     * @return array<int, array{
     *   method: string,
     *   uri: string,
     *   name: string|null,
     *   action: string|null,
     *   controller: string|null,
     *   middleware: array<int, string>,
     *   has_auth: bool,
     *   form_request: string|null
     * }>
     */
    public function scan(array $cfg, array $overrides = []): array
    {
        $merged = $this->deepMerge($cfg, $overrides);

        /** @var Router $router */
        $router = app('router');
        $routes = $router->getRoutes();

        $out = [];

        /** @var Route $r */
        foreach ($routes as $r) {
            if ($merged['routes']['exclude_fallback'] && method_exists($r, 'isFallback') && $r->isFallback()) {
                continue;
            }

            $methods = array_values(array_diff($r->methods(), ['HEAD']));
            if (count($methods) === 0) {
                continue;
            }

            // Only include one method per item for determinism (Laravel routes can have multiple)
            // In practice, most are single-method; for multi-method routes, we split.
            foreach ($methods as $method) {
                $uri = ltrim($r->uri(), '/');

                $middleware = array_values(array_unique($r->gatherMiddleware()));
                if (!$this->passesFilters($merged, $uri, (string)$r->getName(), $middleware)) {
                    continue;
                }

                $action = $this->actionString($r);
                [$controller, $formRequest] = $this->inferControllerAndFormRequest($r);

                $hasAuth = $this->detectAuth($merged, $middleware);

                $out[] = [
                    'method' => strtoupper($method),
                    'uri' => $uri,
                    'name' => $r->getName(),
                    'action' => $action,
                    'controller' => $controller,
                    'middleware' => $middleware,
                    'has_auth' => $hasAuth,
                    'form_request' => $formRequest,
                ];
            }
        }

        $out = $this->sortRoutes($out, $merged['organization']['sort_by'], $merged['organization']['sort_direction']);

        return $out;
    }

    private function passesFilters(array $cfg, string $uri, ?string $name, array $middleware): bool
    {
        // api_only: best effort; either /api prefix or contains "api" middleware
        if (!empty($cfg['routes']['api_only'])) {
            $isApiPrefix = str_starts_with($uri, 'api/');
            $hasApiMiddleware = in_array('api', $middleware, true) || $this->containsMiddleware($middleware, 'api');
            if (!$isApiPrefix && !$hasApiMiddleware) {
                return false;
            }
        }

        foreach ((array)$cfg['routes']['exclude_middleware'] as $mw) {
            if ($this->containsMiddleware($middleware, (string)$mw)) {
                return false;
            }
        }

        $includeMw = (array)$cfg['routes']['include_middleware'];
        if (!empty($includeMw)) {
            $ok = false;
            foreach ($includeMw as $mw) {
                if ($this->containsMiddleware($middleware, (string)$mw)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) return false;
        }

        $excludePrefixes = (array)$cfg['routes']['exclude_prefixes'];
        foreach ($excludePrefixes as $p) {
            $p = trim((string)$p, '/');
            if ($p !== '' && (str_starts_with($uri, $p . '/') || $uri === $p)) {
                return false;
            }
        }

        $includePrefixes = (array)$cfg['routes']['include_prefixes'];
        if (!empty($includePrefixes)) {
            $ok = false;
            foreach ($includePrefixes as $p) {
                $p = trim((string)$p, '/');
                if ($p !== '' && (str_starts_with($uri, $p . '/') || $uri === $p)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) return false;
        }

        $excludeNames = (array)$cfg['routes']['exclude_names'];
        if ($name) {
            foreach ($excludeNames as $pattern) {
                $pattern = (string)$pattern;
                if (@preg_match($pattern, '') !== false && preg_match($pattern, $name)) {
                    return false;
                }
            }
        }

        $includeNames = (array)$cfg['routes']['include_names'];
        if (!empty($includeNames)) {
            if (!$name) return false;
            $ok = false;
            foreach ($includeNames as $pattern) {
                $pattern = (string)$pattern;
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
            // exact or prefix match (auth:sanctum should match auth)
            if ($mw === $needle) return true;
            if (str_starts_with($mw, $needle . ':')) return true;
        }
        return false;
    }

    private function detectAuth(array $cfg, array $middleware): bool
    {
        if (empty($cfg['auth']['include_auth'])) {
            return false;
        }
        if (empty($cfg['auth']['detect_from_middleware'])) {
            return $cfg['auth']['mode'] !== 'none';
        }

        foreach ((array)$cfg['auth']['auth_middleware'] as $mw) {
            if ($this->containsMiddleware($middleware, (string)$mw)) {
                return true;
            }
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
            // multi: [ ["middleware" => ["auth:sanctum"], "mode" => "bearer"], ... ]
            foreach ((array)$cfg['auth']['multi'] as $authGroup) {
                $groupMw = (array)($authGroup['middleware'] ?? []);
                foreach ($groupMw as $mw) {
                    if ($this->containsMiddleware($middleware, (string)$mw)) {
                        return $authGroup['mode'] ?? null;
                    }
                }
            }
        }
        if (empty($cfg['auth']['detect_from_middleware'])) {
            return $cfg['auth']['mode'] ?? null;
        }
        foreach ((array)$cfg['auth']['auth_middleware'] as $mw) {
            if ($this->containsMiddleware($middleware, (string)$mw)) {
                return $cfg['auth']['mode'] ?? 'bearer';
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
     * @return array{0: string|null, 1: string|null} controller, formRequest
     */
    private function inferControllerAndFormRequest(Route $route): array
    {
        $action = $route->getAction();
        $uses = Arr::get($action, 'controller') ?? Arr::get($action, 'uses');
        $controller = null;
                        'auth_mode' => $authMode,

        // Handle invokable controllers (single __invoke method)
                    $authMode = $this->detectAuth($merged, $middleware);
        if (is_string($uses) && class_exists($uses) && method_exists($uses, '__invoke')) {
            $controller = $uses;
            $method = '__invoke';
        }
        // Handle [Class, method] or 'Class@method' or 'Class::method'
        elseif (is_array($uses) && count($uses) === 2 && is_string($uses[0]) && is_string($uses[1])) {
            $controller = $uses[0];
            $method = $uses[1];
        } elseif (is_string($uses) && str_contains($uses, '@')) {
            [$controller, $method] = explode('@', $uses) + [null, null];
        } elseif (is_string($uses) && str_contains($uses, '::')) {
            [$controller, $method] = explode('::', $uses) + [null, null];
        } else {
            $method = null;
        }

        // Handle closures (route actions as closures)
        if ($uses instanceof \Closure) {
            $ref = new \ReflectionFunction($uses);
            foreach ($ref->getParameters() as $p) {
                $t = $p->getType();
                if ($t instanceof \ReflectionNamedType && !$t->isBuiltin()) {
                    $paramClass = $t->getName();
                    if (is_subclass_of($paramClass, \Illuminate\Foundation\Http\FormRequest::class)) {
                        $formRequest = $paramClass;
                        break;
                    }
                }
            }
            return [null, $formRequest];
        }

        // Reflect method params for FormRequest
        if ($controller && $method && class_exists($controller) && method_exists($controller, $method)) {
            try {
                $ref = new \ReflectionMethod($controller, $method);
                foreach ($ref->getParameters() as $p) {
                    $t = $p->getType();
                    if ($t instanceof \ReflectionNamedType && !$t->isBuiltin()) {
                        $paramClass = $t->getName();
                        if (is_subclass_of($paramClass, \Illuminate\Foundation\Http\FormRequest::class)) {
                            $formRequest = $paramClass;
                            break;
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        // Route model binding pattern: {user} or {user:id}
        // Not a FormRequest, but could be used for doc generation if needed

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
