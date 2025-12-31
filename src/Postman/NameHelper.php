<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator\Postman;

final class NameHelper
{
    /**
     * Generate a human-readable title for a route.
     */
    public static function titleFromRoute(string $method, string $uri): string
    {
        $uri = trim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        $nice = str_replace(['{', '}', '-', '_'], ['', '', ' ', ' '], $uri);
        $nice = preg_replace('/\s+/', ' ', $nice) ?: $nice;

        return strtoupper($method).' '.$nice;
    }

    /**
     * Generate a slug for a route.
     */
    public static function slug(string $method, string $uri): string
    {
        $uri = trim($uri, '/');
        $s = strtolower($method.'-'.$uri);
        $s = preg_replace('/[{}]/', '', $s) ?: $s;
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?: $s;

        return trim($s, '-');
    }

    /**
     * Get the base name of a controller FQCN.
     */
    public static function controllerBase(?string $controllerFqcn): ?string
    {
        if (! $controllerFqcn) {
            return null;
        }
        $parts = explode('\\', $controllerFqcn);

        return end($parts) ?: $controllerFqcn;
    }

    /**
     * Extract the PHPDoc summary (first line) from a class or method.
     *
     * @param  class-string|object  $classOrObject
     * @param  string|null  $method  Optional method name
     */
    public static function phpDocSummary($classOrObject, ?string $method = null): ?string
    {
        try {
            if ($method) {
                $ref = new \ReflectionMethod($classOrObject, $method);
            } else {
                $ref = new \ReflectionClass($classOrObject);
            }
            $doc = $ref->getDocComment();
            if (! $doc) {
                return null;
            }
            // Remove comment markers
            $doc = preg_replace('/^\s*\/\*\*?|\*\/|^\s*\* ?/m', '', $doc);
            $lines = preg_split('/\r?\n/', trim($doc));
            // Find first non-empty line
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    return $line;
                }
            }
        } catch (\ReflectionException) {
            return null;
        }

        return null;
    }
}
