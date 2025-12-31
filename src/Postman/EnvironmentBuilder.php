<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator\Postman;

final class EnvironmentBuilder
{
    /**
     * @return array<string, string> envName => json
     */
    /**
     * Build all Postman environments from config.
     *
     * @param array $cfg
     * @return array<string, string> envName => json
     */
    public function buildAll(array $cfg): array
    {
        $out = [];
        foreach ((array)$cfg['environments'] as $name => $vars) {
            $out[(string)$name] = $this->buildOne((string)$name, (array)$vars);
        }
        return $out;
    }

    private function buildOne(string $envName, array $vars): string
    {
        $values = [];
        ksort($vars);

        foreach ($vars as $k => $v) {
            $values[] = [
                'key' => (string)$k,
                'value' => is_scalar($v) || $v === null ? (string)$v : json_encode($v),
                'enabled' => true,
            ];
        }

        $env = [
            'name' => $envName,
            'values' => $values,
            '_postman_variable_scope' => 'environment',
            '_postman_exported_at' => gmdate('c'),
            '_postman_exported_using' => 'laravel-postman-generator',
        ];

        return json_encode($env, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
