<?php

declare(strict_types=1);

use Orchestra\Testbench\TestCase;
use paws1234\LaravelPostmanGenerator\Postman\NameHelper;

class NameHelperTest extends TestCase
{
    public function test_title_from_route(): void
    {
        $this->assertSame('GET users', NameHelper::titleFromRoute('GET', 'users'));
        $this->assertSame('POST users id', NameHelper::titleFromRoute('POST', 'users/{id}'));
    }

    public function test_slug(): void
    {
        $this->assertSame('get-users', NameHelper::slug('GET', 'users'));
        $this->assertSame('post-users-id', NameHelper::slug('POST', 'users/{id}'));
    }

    public function test_controller_base(): void
    {
        $this->assertSame('UserController', NameHelper::controllerBase('App\\Http\\Controllers\\UserController'));
        $this->assertNull(NameHelper::controllerBase(null));
    }

    public function test_phpdoc_summary(): void
    {
        /**
         * This is a summary.
         *
         * This is a description.
         */
        $class = new class {
            /**
             * Method summary.
             *
             * More details.
             */
            public function foo() {}
        };
        $this->assertSame('This is a summary.', NameHelper::phpDocSummary($class));
        $this->assertSame('Method summary.', NameHelper::phpDocSummary($class, 'foo'));
    }
}
