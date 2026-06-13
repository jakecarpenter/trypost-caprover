<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Ai\AiImageClient;
use App\Services\Image\BrandColorMapper;
use App\Services\Image\TemplateImageGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;

$minimalPng = fn () => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

beforeEach(function () {
    Storage::fake();
    Cache::flush();
    Image::fake();
});

test('returns null when AI client cannot generate (no keywords)', function () {
    $service = new TemplateImageGenerator(new BrandColorMapper, new AiImageClient);
    $result = $service->render(
        workspace: Workspace::factory()->make(),
        socialAccount: SocialAccount::factory()->make(['username' => 'testuser', 'display_name' => 'Test User']),
        title: 'Hello',
        body: 'World',
        imageKeywords: [],
    );

    expect($result)->toBeNull();
    Image::assertNothingGenerated();
});

test('returns null when AI generation throws', function () {
    Image::fake(fn () => throw new RuntimeException('upstream outage'));

    $service = new TemplateImageGenerator(new BrandColorMapper, new AiImageClient);
    $result = $service->render(
        workspace: Workspace::factory()->make(),
        socialAccount: SocialAccount::factory()->make(),
        title: 'Hello',
        body: 'World',
        imageKeywords: ['kitchen'],
    );

    expect($result)->toBeNull();
});

test('renders a slide and stores webp when AI returns bytes', function () use ($minimalPng) {
    Image::fake([base64_encode($minimalPng())]);

    if (! file_exists(base_path('resources/fonts/Inter-Bold.ttf'))) {
        $this->markTestSkipped('Inter fonts not available — skipping render-dependent test.');
    }

    $service = new TemplateImageGenerator(new BrandColorMapper, new AiImageClient);
    $result = $service->render(
        workspace: Workspace::factory()->make([
            'image_style' => 'illustration',
            'brand_color' => '#0000ff',
            'background_color' => '#ffffff',
            'text_color' => '#000000',
        ]),
        socialAccount: SocialAccount::factory()->make([
            'username' => 'testuser',
            'display_name' => 'Test User',
        ]),
        title: 'Hello World',
        body: 'This is a test slide body.',
        imageKeywords: ['kitchen', 'morning'],
    );

    if ($result !== null) {
        expect($result['path'])->toStartWith('ai-images/')->toEndWith('.webp');
        expect($result['source_meta'])
            ->toHaveKey('keywords')
            ->toHaveKey('style', 'illustration')
            ->toHaveKey('model', 'gpt-image-2')
            ->toHaveKey('title', 'Hello World')
            ->toHaveKey('brand_color', '#0000ff')
            ->toHaveKey('background_color', '#ffffff')
            ->toHaveKey('text_color', '#000000')
            ->toHaveKey('background_path');
    }

    Image::assertGenerated(fn ($prompt) => $prompt->contains('kitchen')
        && $prompt->contains('BRAND COLOR PALETTE')
        && $prompt->contains('blue'));
})->skip(fn () => ! extension_loaded('gd'), 'GD extension required');

test('omits the brand colour palette from the AI image prompt when brand visuals are off', function () use ($minimalPng) {
    Image::fake([base64_encode($minimalPng())]);

    if (! file_exists(base_path('resources/fonts/Inter-Bold.ttf'))) {
        $this->markTestSkipped('Inter fonts not available — skipping render-dependent test.');
    }

    $service = new TemplateImageGenerator(new BrandColorMapper, new AiImageClient);
    $service->render(
        workspace: Workspace::factory()->make([
            'brand_color' => '#0000ff',
            'background_color' => '#ffffff',
            'text_color' => '#000000',
        ]),
        socialAccount: SocialAccount::factory()->make(['username' => 'u', 'display_name' => 'U']),
        title: 'Hello',
        body: 'Body',
        imageKeywords: ['kitchen'],
        applyBrandVisuals: false,
    );

    // Despite the workspace having brand colours, the prompt must stay neutral.
    Image::assertGenerated(fn ($prompt) => $prompt->contains('kitchen')
        && ! $prompt->contains('BRAND COLOR PALETTE'));
})->skip(fn () => ! extension_loaded('gd'), 'GD extension required');

test('reuses existing background path when provided', function () use ($minimalPng) {
    if (! file_exists(base_path('resources/fonts/Inter-Bold.ttf'))) {
        $this->markTestSkipped('Inter fonts not available — skipping render-dependent test.');
    }

    $backgroundPath = 'ai-images/reuse-bg.webp';
    Storage::put($backgroundPath, $minimalPng());

    $service = new TemplateImageGenerator(new BrandColorMapper, new AiImageClient);
    $result = $service->render(
        workspace: Workspace::factory()->make(),
        socialAccount: SocialAccount::factory()->make(['username' => 'testuser', 'display_name' => 'Test User']),
        title: 'Updated title',
        body: 'Updated body',
        imageKeywords: [],
        backgroundPath: $backgroundPath,
    );

    expect($result)->not->toBeNull();
    expect(data_get($result, 'source_meta.background_path'))->toBe($backgroundPath);
    Image::assertNothingGenerated();
})->skip(fn () => ! extension_loaded('gd'), 'GD extension required');
