<?php

declare(strict_types=1);

namespace paws1234\LaravelPostmanGenerator\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

final class FormRequestBodyInferer
{
    public function infer(?string $formRequestClass, bool $withExamples = true): ?array
    {
        if (!$formRequestClass || !class_exists($formRequestClass)) {
            return null;
        }
        if (!is_subclass_of($formRequestClass, FormRequest::class)) {
            return null;
        }

        /** @var FormRequest $req */
        $req = app($formRequestClass);

        $rules = $req->rules();
        if (!is_array($rules)) {
            return null;
        }

        $body = [];

        foreach ($rules as $field => $ruleSpec) {
            $ruleList = $this->normalizeRules($ruleSpec);

            // Ignore wildcard-only root (e.g. tags.*) unless tags exists too.
            if (str_ends_with((string)$field, '.*')) {
                continue;
            }

            $example = $withExamples ? $this->exampleForRules($ruleList, (string)$field, $rules) : null;
            $this->setNested($body, (string)$field, $example ?? '');
        }

        // Handle array wildcard children e.g. tags.* => string
        foreach ($rules as $field => $ruleSpec) {
            $field = (string)$field;
            if (!str_ends_with($field, '.*')) continue;

            $parent = substr($field, 0, -2);
            $childRules = $this->normalizeRules($ruleSpec);

            $childExample = $withExamples ? $this->exampleForRules($childRules, $field, $rules) : '';

            // ensure parent exists as array
            $existing = Arr::get($body, $parent);
            if (!is_array($existing)) {
                $this->setNested($body, $parent, []);
            }

            $arr = Arr::get($body, $parent);
            if (is_array($arr)) {
                $arr = array_values($arr);
                $arr[] = $childExample;
                $this->setNested($body, $parent, $arr);
            }
        }

        return $body;
    }

    private function normalizeRules(mixed $ruleSpec): array
    {
        if (is_string($ruleSpec)) {
            return array_filter(explode('|', $ruleSpec));
        }
        if (is_array($ruleSpec)) {
            $out = [];
            foreach ($ruleSpec as $r) {
                if (is_string($r)) $out[] = $r;
                elseif (is_object($r) && method_exists($r, '__toString')) $out[] = (string)$r;
                elseif (is_object($r) && method_exists($r, 'toString')) $out[] = (string)$r->toString();
            }
            return $out;
        }
        return [];
    }

    private function exampleForRules(array $rules, string $field, array $allRules): mixed
    {
        $name = strtolower(trim(strrchr($field, '.') ?: $field, '.'));

        $isArray = $this->hasRule($rules, 'array');
        if ($isArray) return [];

        if ($this->hasRule($rules, 'boolean') || $this->hasRule($rules, 'bool')) return true;

        if ($this->hasRule($rules, 'integer') || $this->hasRule($rules, 'int')) return 1;
        if ($this->hasRule($rules, 'numeric') || $this->hasRule($rules, 'decimal')) return 1;

        if ($this->hasRule($rules, 'email')) return 'user@example.com';
        if ($this->hasRule($rules, 'url')) return 'https://example.com';
        if ($this->hasRule($rules, 'uuid')) return '00000000-0000-0000-0000-000000000000';

        if ($this->hasRule($rules, 'date') || $this->hasRule($rules, 'datetime')) return '2025-01-01';

        if ($this->hasRule($rules, 'file') || $this->hasRule($rules, 'image')) {
            // Postman usually sends file via form-data; we keep placeholder
            return null;
        }

        // Heuristic from field name
        if (str_contains($name, 'email')) return 'user@example.com';
        if (str_contains($name, 'name')) return 'Name';
        if (str_contains($name, 'title')) return 'Title';
        if (str_contains($name, 'password')) return 'password';
        if (str_contains($name, 'id')) return 1;

        return 'string';
    }

    private function hasRule(array $rules, string $needle): bool
    {
        foreach ($rules as $r) {
            $r = strtolower((string)$r);
            if ($r === $needle) return true;
            if (str_starts_with($r, $needle . ':')) return true;
        }
        return false;
    }

    private function setNested(array &$arr, string $path, mixed $value): void
    {
        // Convert dot notation to nested arrays
        $segments = explode('.', $path);
        $ref =& $arr;

        foreach ($segments as $i => $seg) {
            $last = $i === count($segments) - 1;
            if ($last) {
                $ref[$seg] = $value;
                return;
            }
            if (!isset($ref[$seg]) || !is_array($ref[$seg])) {
                $ref[$seg] = [];
            }
            $ref =& $ref[$seg];
        }
    }
}
