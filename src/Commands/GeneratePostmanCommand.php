<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use paws1234\LaravelPostmanGenerator\Postman\CollectionBuilder;
use paws1234\LaravelPostmanGenerator\Postman\EnvironmentBuilder;
use paws1234\LaravelPostmanGenerator\Route\RouteScanner;

final class GeneratePostmanCommand extends Command
{
    protected $signature = 'postman:generate
        {--output= : Override base output path}
        {--name= : Override collection name}
        {--api-only : Include only API routes}
        {--prefix= : Include only routes under a prefix (e.g. api/v1)}
        {--middleware= : Include only routes that have a middleware (e.g. auth:sanctum)}
        {--group-by= : Group by prefix|controller|none}
        {--with-body-inference : Enable FormRequest body inference}
        {--with-tests : Generate Postman tests}
        {--force : Overwrite existing files}
        {--dry-run : Preview what would be generated (no files written)}';

    protected $description = 'Generate Postman collection + environments from Laravel routes';

    /**
     * Handle the command execution.
     *
     * @param RouteScanner $scanner
     * @param CollectionBuilder $collectionBuilder
     * @param EnvironmentBuilder $envBuilder
     * @return int
     */
    public function handle(
        RouteScanner $scanner,
        CollectionBuilder $collectionBuilder,
        EnvironmentBuilder $envBuilder
    ): int {
        try {
            $cfg = config('postman-generator');
            $basePath = (string)($this->option('output') ?: $cfg['output']['base_path']);
            $collectionName = (string)($this->option('name') ?: 'Laravel API');
            $overrides = [
                'routes' => [
                    'api_only' => (bool)($this->option('api-only') ?: $cfg['routes']['api_only']),
                    'include_prefixes' => $this->option('prefix') ? [(string)$this->option('prefix')] : $cfg['routes']['include_prefixes'],
                    'include_middleware' => $this->option('middleware') ? [(string)$this->option('middleware')] : $cfg['routes']['include_middleware'],
                ],
                'organization' => [
                    'group_by' => $this->option('group-by') ?: $cfg['organization']['group_by'],
                ],
                'request_generation' => [
                    'infer_body_from_form_request' => (bool)($this->option('with-body-inference') ?: $cfg['request_generation']['infer_body_from_form_request']),
                ],
                'tests' => [
                    'enabled' => (bool)($this->option('with-tests') ?: $cfg['tests']['enabled']),
                ],
            ];
            $routes = $scanner->scan($cfg, $overrides);
            $collectionJson = $collectionBuilder->build($routes, $cfg, $collectionName);
            $envJsonMap = $envBuilder->buildAll($cfg);
            $collectionsDir = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $cfg['output']['collections_dir'];
            $envDir = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $cfg['output']['environments_dir'];
            $collectionFile = $collectionsDir . DIRECTORY_SEPARATOR . $cfg['output']['collection_filename'];
            $dryRun = (bool)$this->option('dry-run');
            $force = (bool)$this->option('force');
            $this->info('Routes selected: ' . count($routes));
            $this->line('Collection: ' . $collectionFile);
            $this->line('Environments: ' . $envDir);
            if ($dryRun) {
                $this->comment('Dry run enabled. No files written.');
                return self::SUCCESS;
            }
            File::ensureDirectoryExists($collectionsDir);
            File::ensureDirectoryExists($envDir);
            if (!$force && File::exists($collectionFile)) {
                $this->error('Collection already exists. Use --force to overwrite.');
                return self::FAILURE;
            }
            File::put($collectionFile, $collectionJson);
            foreach ($envJsonMap as $envName => $json) {
                $envFile = $envDir . DIRECTORY_SEPARATOR . $envName . '.postman_environment.json';
                if (!$force && File::exists($envFile)) {
                    $this->warn("Environment exists, skipped (use --force): {$envFile}");
                    continue;
                }
                File::put($envFile, $json);
            }
            $this->info('Done.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
