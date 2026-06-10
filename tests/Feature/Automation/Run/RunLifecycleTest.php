<?php

declare(strict_types=1);

use App\Actions\Automation\TriggerItem\EnrollTriggerItem;
use App\Ai\Agents\PostContentGenerator;
use App\Ai\Agents\PostContentHumanizer;
use App\Enums\Automation\Run\Status as RunStatus;
use App\Enums\Automation\Status as AutomationStatus;
use App\Models\Automation;
use App\Models\SocialAccount;
use App\Models\Workspace;

it('runs a 3-node automation (Trigger → Generate → Publish) end to end', function () {
    PostContentGenerator::fake([
        ['content' => 'A great post about Hello', 'image_title' => 'Hello', 'image_body' => 'Hello world', 'image_keywords' => ['hello']],
    ]);

    PostContentHumanizer::fake([
        ['content' => 'A great post about Hello (humanized)', 'image_title' => 'Hello', 'image_body' => 'Hello world'],
    ]);

    $workspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->for($workspace)->create();
    $automation = Automation::factory()->for($workspace)->create([
        'status' => AutomationStatus::Active,
        'nodes' => [
            ['id' => 't', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => ['trigger_type' => 'rss', 'feed_url' => 'https://x']],
            ['id' => 'g', 'type' => 'generate', 'position' => ['x' => 1, 'y' => 0], 'data' => [
                'accounts' => [
                    ['social_account_id' => $account->id, 'content_type' => 'instagram_feed', 'meta' => []],
                ],
                'prompt_template' => 'about {{ trigger.title }}',
                'image_source' => 'none',
            ]],
            ['id' => 'p', 'type' => 'publish', 'position' => ['x' => 2, 'y' => 0], 'data' => ['mode' => 'draft']],
        ],
        'connections' => [
            ['id' => 'e1', 'source' => 't', 'target' => 'g'],
            ['id' => 'e2', 'source' => 'g', 'target' => 'p'],
        ],
    ]);

    app(EnrollTriggerItem::class)($automation, 'item-1', ['title' => 'Hello']);

    $run = $automation->runs()->latest()->first();
    expect($run->status)->toBe(RunStatus::Completed);
    expect($run->generated_post_id)->not->toBeNull();
});
