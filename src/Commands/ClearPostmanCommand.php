<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class ClearPostmanCommand extends Command
{
    protected $signature = 'postman:clear {--output= : Override base output path}';

    protected $description = 'Delete generated Postman collections/environments';

    /**
     * Handle the clear command execution.
     */
    public function handle(): int
    {
        try {
            $cfg = config('postman-generator');
            $basePath = (string) ($this->option('output') ?: $cfg['output']['base_path']);
            if (! File::exists($basePath)) {
                $this->info('Nothing to clear.');

                return self::SUCCESS;
            }
            File::deleteDirectory($basePath);
            $this->info("Deleted: {$basePath}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
