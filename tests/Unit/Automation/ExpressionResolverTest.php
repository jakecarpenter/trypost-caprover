<?php

declare(strict_types=1);

use App\Services\Automation\ExpressionResolver;

beforeEach(function () {
    $this->resolver = new ExpressionResolver;
});

it('substitutes trigger and generated variables', function () {
    $template = 'Title: {{ trigger.title }} | Post: {{ generated.post_url }}';
    $context = [
        'trigger' => ['title' => 'Hello World'],
        'generated' => ['post_url' => 'https://example.com/p/1'],
    ];

    expect($this->resolver->resolve($template, $context))
        ->toBe('Title: Hello World | Post: https://example.com/p/1');
});

it('supports nested paths', function () {
    $template = 'Author: {{ trigger.author.name }}';
    $context = ['trigger' => ['author' => ['name' => 'Paulo']]];

    expect($this->resolver->resolve($template, $context))->toBe('Author: Paulo');
});

it('returns empty string for missing variables', function () {
    $template = 'Missing: {{ trigger.missing }}';

    expect($this->resolver->resolve($template, []))->toBe('Missing: ');
});

it('supports now and today helpers', function () {
    $now = $this->resolver->resolve('{{ now }}', []);
    $today = $this->resolver->resolve('{{ today }}', []);

    expect($now)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    expect($today)->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});

it('handles non-string values by casting', function () {
    $template = 'Count: {{ trigger.count }}';
    $context = ['trigger' => ['count' => 42]];

    expect($this->resolver->resolve($template, $context))->toBe('Count: 42');
});

it('passes through templates with no variables', function () {
    expect($this->resolver->resolve('plain text', []))->toBe('plain text');
});

it('json-encodes array values', function () {
    $context = ['fetched' => ['tags' => ['a', 'b']]];

    expect($this->resolver->resolve('{{ fetched.tags }}', $context))->toBe('["a","b"]');
});

it('does not throw when an array value contains malformed UTF-8', function () {
    // Scraped feed/HTTP payloads can carry invalid UTF-8; json_encode returns
    // false for it, but the resolver must still yield a string.
    $context = ['fetched' => ['body' => ["bad\xB1utf8"]]];

    expect($this->resolver->resolve('{{ fetched.body }}', $context))->toBeString();
});
