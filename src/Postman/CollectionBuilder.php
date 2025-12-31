<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator\Postman;

final class CollectionBuilder
{
    public function __construct(private readonly ItemBuilder $itemBuilder) {}

    public function build(array $routes, array $cfg, string $collectionName): string
    {
        $groupBy = (string)$cfg['organization']['group_by'];
        $folderDepth = (int)($cfg['organization']['folder_depth'] ?? 2);

        $root = [
            'info' => [
                'name' => $collectionName,
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => [],
        ];

        if ($groupBy === 'none') {
            $seq = 1;
            foreach ($routes as $r) {
                $root['item'][] = $this->itemBuilder->buildItem($r, $cfg, $seq++);
            }
            return json_encode($root, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        // Build folder map: folderName => items
        $folders = [];

        foreach ($routes as $r) {
            $folderName = $this->folderNameFor($r, $groupBy, $folderDepth);
            $folders[$folderName] ??= [];
            $folders[$folderName][] = $r;
        }

        ksort($folders, SORT_STRING);

        $seq = 1;
        foreach ($folders as $folderName => $folderRoutes) {
            $folder = [
                'name' => $folderName,
                'item' => [],
            ];
            foreach ($folderRoutes as $r) {
                $folder['item'][] = $this->itemBuilder->buildItem($r, $cfg, $seq++);
            }
            $root['item'][] = $folder;
        }

        return json_encode($root, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function folderNameFor(array $route, string $groupBy, int $depth): string
    {
        if ($groupBy === 'controller') {
            return NameHelper::controllerBase($route['controller']) ?? 'Routes';
        }

        // prefix grouping
        $uri = trim((string)$route['uri'], '/');
        if ($uri === '') return 'Root';

        $parts = explode('/', $uri);
        $parts = array_slice($parts, 0, max(1, $depth));
        return implode('/', $parts);
    }
}
