<?php

declare(strict_types=1);

use App\Actions\Automation\Node\RunConditionNode;
use App\Models\AutomationRun;

it('routes to yes when contains matches', function () {
    $run = AutomationRun::factory()->create([
        'context' => ['trigger' => ['title' => 'Stripe Radar update']],
    ]);

    $result = app(RunConditionNode::class)($run, [
        'field' => '{{ trigger.title }}',
        'operator' => 'contains',
        'value' => 'Stripe',
    ]);

    expect($result->nextHandle)->toBe('yes');
});

it('routes to no when contains does not match', function () {
    $run = AutomationRun::factory()->create([
        'context' => ['trigger' => ['title' => 'Facebook update']],
    ]);

    $result = app(RunConditionNode::class)($run, [
        'field' => '{{ trigger.title }}',
        'operator' => 'contains',
        'value' => 'Stripe',
    ]);

    expect($result->nextHandle)->toBe('no');
});

it('supports equals and regex match operators', function () {
    $run = AutomationRun::factory()->create([
        'context' => ['trigger' => ['kind' => 'post']],
    ]);

    expect(app(RunConditionNode::class)($run, [
        'field' => '{{ trigger.kind }}', 'operator' => 'equals', 'value' => 'post',
    ])->nextHandle)->toBe('yes');

    expect(app(RunConditionNode::class)($run, [
        'field' => '{{ trigger.kind }}', 'operator' => 'matches', 'value' => '^p.*t$',
    ])->nextHandle)->toBe('yes');
});

it('supports greater_than for numerics', function () {
    $run = AutomationRun::factory()->create([
        'context' => ['trigger' => ['score' => 80]],
    ]);

    expect(app(RunConditionNode::class)($run, [
        'field' => '{{ trigger.score }}', 'operator' => 'greater_than', 'value' => '50',
    ])->nextHandle)->toBe('yes');
});

it('supports not_contains', function () {
    $run = AutomationRun::factory()->create([
        'context' => ['trigger' => ['title' => 'Facebook update']],
    ]);

    expect(app(RunConditionNode::class)($run, [
        'field' => '{{ trigger.title }}', 'operator' => 'not_contains', 'value' => 'Stripe',
    ])->nextHandle)->toBe('yes');
});

it('supports not_equals', function () {
    $run = AutomationRun::factory()->create([
        'context' => ['trigger' => ['kind' => 'post']],
    ]);

    expect(app(RunConditionNode::class)($run, [
        'field' => '{{ trigger.kind }}', 'operator' => 'not_equals', 'value' => 'media',
    ])->nextHandle)->toBe('yes');
});

it('supports less_than for numerics', function () {
    $run = AutomationRun::factory()->create([
        'context' => ['trigger' => ['score' => 20]],
    ]);

    expect(app(RunConditionNode::class)($run, [
        'field' => '{{ trigger.score }}', 'operator' => 'less_than', 'value' => '50',
    ])->nextHandle)->toBe('yes');
});
