<?php

declare(strict_types=1);

use App\Actions\Automation\Node\RunGenerateNode;
use App\Enums\UserWorkspace\Role;
use App\Models\Automation;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->user = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->refresh();
});

it('rejects saving a generate node whose slide count exceeds a content type limit', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->putJson(route('app.automations.update', $automation->id), [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
                ['id' => 'n2', 'type' => 'generate', 'position' => ['x' => 1, 'y' => 0], 'data' => [
                    'accounts' => [['social_account_id' => 'acc-1', 'content_type' => 'pinterest_carousel']],
                    'prompt_template' => 'hi',
                    'image_source' => 'none',
                    'target_slide_count' => 8,
                ]],
            ],
            'connections' => [['id' => 'e1', 'source' => 'n1', 'target' => 'n2']],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nodes.1.data.accounts']);
});

it('allows saving a generate node within the content type limit', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->put(route('app.automations.update', $automation->id), [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
                ['id' => 'n2', 'type' => 'generate', 'position' => ['x' => 1, 'y' => 0], 'data' => [
                    'accounts' => [['social_account_id' => 'acc-1', 'content_type' => 'pinterest_carousel']],
                    'prompt_template' => 'hi',
                    'image_source' => 'none',
                    'target_slide_count' => 4,
                ]],
            ],
            'connections' => [['id' => 'e1', 'source' => 'n1', 'target' => 'n2']],
        ])
        ->assertRedirect();

    expect($automation->fresh()->nodes)->toHaveCount(2);
});

it('saves a generate node using the exact frontend payload (no image_source field)', function () {
    $automation = Automation::factory()->for($this->workspace)->create();

    $this->actingAs($this->user)
        ->put(route('app.automations.update', $automation->id), [
            'nodes' => [
                ['id' => 'n1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'schedule', 'cron' => '0 9 * * *']],
                ['id' => 'n2', 'type' => 'generate', 'position' => ['x' => 1, 'y' => 0], 'data' => [
                    'accounts' => [['social_account_id' => 'acc-1', 'content_type' => 'instagram_feed', 'meta' => []]],
                    'prompt_template' => 'hi',
                    'include_image' => true,
                    'target_slide_count' => 5,
                ]],
            ],
            'connections' => [['id' => 'e1', 'source' => 'n1', 'target' => 'n2']],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($automation->fresh()->nodes)->toHaveCount(2);
});

it('refuses to activate an automation whose generate node requests images for an image-less format', function () {
    $automation = Automation::factory()->for($this->workspace)->withScheduleTrigger()->create();
    $automation->update([
        'nodes' => array_merge($automation->nodes, [
            ['id' => 'n2', 'type' => 'generate', 'position' => ['x' => 1, 'y' => 1], 'data' => [
                'accounts' => [['social_account_id' => 'acc-1', 'content_type' => 'tiktok_video']],
                'include_image' => true,
            ]],
        ]),
        'connections' => [['id' => 'e1', 'source' => 'trigger_1', 'target' => 'n2']],
    ]);

    $this->actingAs($this->user)
        ->postJson(route('app.automations.activate', $automation->id))
        ->assertStatus(422);

    expect($automation->fresh()->status->value)->not->toBe('active');
});

it('caps the carousel slide count at the global max and the per-content-type limit', function () {
    $node = app(RunGenerateNode::class);

    // tiktok_photo allows 35, but AI generation is capped at 10.
    expect($node->deriveFormat([['content_type' => 'tiktok_photo']], ['target_slide_count' => 15]))
        ->toBe(['format' => 'carousel', 'slide_count' => 10]);

    // pinterest_carousel allows only 5.
    expect($node->deriveFormat([['content_type' => 'pinterest_carousel']], ['target_slide_count' => 8]))
        ->toBe(['format' => 'carousel', 'slide_count' => 5]);
});

it('recognises facebook_post as multi-image capable (rules-derived, not a hardcoded list)', function () {
    $node = app(RunGenerateNode::class);

    expect($node->deriveFormat([['content_type' => 'facebook_post']], ['target_slide_count' => 6]))
        ->toBe(['format' => 'carousel', 'slide_count' => 6]);

    // A single-image (video) format never becomes a carousel.
    expect($node->deriveFormat([['content_type' => 'tiktok_video']], ['target_slide_count' => 5]))
        ->toBe(['format' => 'single', 'slide_count' => 1]);
});
