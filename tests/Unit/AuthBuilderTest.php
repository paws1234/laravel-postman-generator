<?php

declare(strict_types=1);

use Orchestra\Testbench\TestCase;
use paws1234\LaravelPostmanGenerator\Postman\AuthBuilder;

class AuthBuilderTest extends TestCase
{
    public function test_build_returns_null_for_none(): void
    {
        $builder = new AuthBuilder();
        $cfg = ['auth' => ['include_auth' => true, 'mode' => 'none']];
        $this->assertNull($builder->build($cfg, 'none'));
    }

    public function test_build_bearer(): void
    {
        $builder = new AuthBuilder();
        $cfg = ['auth' => ['include_auth' => true, 'mode' => 'bearer', 'bearer_token_var' => 'TOKEN']];
        $auth = $builder->build($cfg, 'bearer');
        $this->assertIsArray($auth);
        $this->assertSame('bearer', $auth['type']);
    }

    public function test_build_basic(): void
    {
        $builder = new AuthBuilder();
        $cfg = ['auth' => ['include_auth' => true, 'mode' => 'basic', 'basic_user_var' => 'USER', 'basic_pass_var' => 'PASS']];
        $auth = $builder->build($cfg, 'basic');
        $this->assertIsArray($auth);
        $this->assertSame('basic', $auth['type']);
    }
}
