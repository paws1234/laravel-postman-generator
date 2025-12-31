<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator\Postman;

final class AuthBuilder
{
    /**
     * Build Postman auth config for a route.
     *
     * @param array $cfg
     * @param string|null $authMode  e.g. 'bearer', 'basic', null
     * @return array|null
     */
    /**
     * Build Postman auth config for a route.
     *
     * @param array $cfg
     * @param string|null $authMode  e.g. 'bearer', 'basic', null
     * @return array|null
     */
    public function build(array $cfg, ?string $authMode): ?array
    {
        if (empty($cfg['auth']['include_auth'])) {
            return null;
        }

        if (!$authMode || $authMode === 'none') {
            return null;
        }

        return match ($authMode) {
            'bearer' => $this->buildBearer($cfg),
            'basic'  => $this->buildBasic($cfg),
            default  => null,
        };
    }

    private function buildBearer(array $cfg): array
    {
        $var = (string)($cfg['auth']['bearer_token_var'] ?? 'token');

        return [
            'type' => 'bearer',
            'bearer' => [
                [
                    'key' => 'token',
                    'value' => '{{' . $var . '}}',
                    'type' => 'string',
                ],
            ],
        ];
    }

    private function buildBasic(array $cfg): array
    {
        $userVar = (string)($cfg['auth']['basic_user_var'] ?? 'username');
        $passVar = (string)($cfg['auth']['basic_pass_var'] ?? 'password');

        return [
            'type' => 'basic',
            'basic' => [
                [
                    'key' => 'username',
                    'value' => '{{' . $userVar . '}}',
                    'type' => 'string',
                ],
                [
                    'key' => 'password',
                    'value' => '{{' . $passVar . '}}',
                    'type' => 'string',
                ],
            ],
        ];
    }
}
