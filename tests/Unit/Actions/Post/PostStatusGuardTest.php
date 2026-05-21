<?php

declare(strict_types=1);

use App\Actions\Post\PostStatusGuard;
use App\Enums\Post\Status as PostStatus;
use App\Models\Post;

test('blocks editing for terminal statuses', function (PostStatus $status) {
    $post = Post::factory()->make(['status' => $status]);

    expect(PostStatusGuard::blocksEditing($post))->toBeTrue();
})->with([
    PostStatus::Publishing,
    PostStatus::Published,
    PostStatus::PartiallyPublished,
    PostStatus::Failed,
]);

test('allows editing for non terminal statuses', function (PostStatus $status) {
    $post = Post::factory()->make(['status' => $status]);

    expect(PostStatusGuard::blocksEditing($post))->toBeFalse();
})->with([
    PostStatus::Draft,
    PostStatus::Scheduled,
]);

test('blocks deletion for published statuses', function (PostStatus $status) {
    $post = Post::factory()->make(['status' => $status]);

    expect(PostStatusGuard::blocksDeletion($post))->toBeTrue();
})->with([
    PostStatus::Publishing,
    PostStatus::Published,
    PostStatus::PartiallyPublished,
]);

test('allows deletion for draft, scheduled and failed statuses', function (PostStatus $status) {
    $post = Post::factory()->make(['status' => $status]);

    expect(PostStatusGuard::blocksDeletion($post))->toBeFalse();
})->with([
    PostStatus::Draft,
    PostStatus::Scheduled,
    PostStatus::Failed,
]);
