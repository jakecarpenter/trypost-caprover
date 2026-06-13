<?php

declare(strict_types=1);

namespace App\Services\Automation;

use Illuminate\Support\Carbon;

class ExpressionResolver
{
    public function resolve(string $template, array $context): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            fn ($matches) => $this->resolveVariable($matches[1], $context),
            $template,
        ) ?? $template;
    }

    /**
     * Resolves `{{ ... }}` placeholders inside an already-decoded JSON structure
     * (arrays/strings), resolving only string leaves. The caller json_encodes the
     * result, so values are never string-interpolated into raw JSON — quotes,
     * `&`, newlines etc. in the data can't corrupt the payload.
     *
     * @param  array<string, mixed>  $context
     */
    public function resolveStructured(mixed $value, array $context): mixed
    {
        if (is_string($value)) {
            return $this->resolve($value, $context);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->resolveStructured($item, $context), $value);
        }

        return $value;
    }

    private function resolveVariable(string $path, array $context): string
    {
        if ($path === 'now') {
            return Carbon::now()->toIso8601String();
        }

        if ($path === 'today') {
            return Carbon::today()->toDateString();
        }

        $value = data_get($context, $path);

        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        // json_encode returns false on malformed UTF-8 (plausible for scraped
        // feed/HTTP payloads); the method must still return a string.
        return json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '';
    }
}
