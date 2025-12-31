<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator\Postman;

final class AuthBuilder
{
    public function build(array $cfg, bool $routeHasAuth): ?array
    {
        if (empty($cfg['auth']['include_auth'])) return null;
        if (!$routeHasAuth) return null;

        $mode = (string)($cfg['auth']['mode'] ?? 'none');
        if ($mode === 'none') return null;

        if ($mode === 'bearer') {
            $var = (string)$cfg['auth']['bearer_token_var'];
            return [
                'type' => 'bearer',
                'bearer' => [
                    ['key' => 'token', 'value' => '{{' . $var . '}}', 'type' => 'string'],
                ],
            ];
        }

        if ($mode === 'basic') {
            $u = (string)$cfg['auth']['basic_user_var'];
            $p = (string)$cfg['auth']['basic_pass_var'];
            return [
                'type' => 'basic',
                'basic' => [
                    ['key' => 'username', 'value' => '{{' . $u . '}}', 'type' => 'string'],
                    ['key' => 'password', 'value' => '{{' . $p . '}}', 'type' => 'string'],
                ],
            ];
        }

        return null;
    }
    /**
     * Build Postman auth config for a route, supporting multi-auth.
     * @param array $cfg
     * @param string|null $authMode
     * @return array|null
     */
    public function build(array $cfg, ?string $authMode): ?array
    {
        if (empty($cfg['auth']['include_auth']) || !$authMode || $authMode === 'none') return null;

        if ($authMode === 'bearer') {
            $var = (string)($cfg['auth']['bearer_token_var'] ?? 'token');
            return [
                'type' => 'bearer',
                'bearer' => [
                    ['key' => 'token', 'value' => '{{' . $var . '}}', 'type' => 'string'],
                ],
            ];
        }
        if ($authMode === 'basic') {
            $u = (string)($cfg['auth']['basic_user_var'] ?? 'user');
            $p = (string)($cfg['auth']['basic_pass_var'] ?? 'pass');
            return [
                'type' => 'basic',
                'basic' => [
                    ['key' => 'username', 'value' => '{{' . $u . '}}', 'type' => 'string'],
                    ['key' => 'password', 'value' => '{{' . $p . '}}', 'type' => 'string'],
                ],
            ];
        }
        // Extend here for other auth types if needed
        return null;
    }
}
