<?php


declare(strict_types=1);

namespace Tests\Feature;


use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Artisan;

class GeneratePostmanCommandTest extends TestCase
{
    public function test_command_runs_successfully(): void
    {
        $exitCode = Artisan::call('postman:generate', [
            '--dry-run' => true,
        ]);
        $this->assertSame(0, $exitCode);
    }
}
