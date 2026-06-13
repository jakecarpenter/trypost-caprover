<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Automation;

use App\Enums\Automation\NodeRun\Status;
use DateTimeInterface;

class NodeRunResult
{
    public function __construct(
        public readonly Status $status,
        public readonly array $output = [],
        public readonly string $nextHandle = 'default',
        public readonly ?DateTimeInterface $sleepUntil = null,
        public readonly ?array $error = null,
    ) {}

    public static function completed(array $output = [], string $nextHandle = 'default'): self
    {
        return new self(Status::Completed, $output, $nextHandle);
    }

    public static function sleep(DateTimeInterface $until): self
    {
        return new self(Status::Completed, sleepUntil: $until);
    }

    public static function failed(string $message, ?array $extra = null): self
    {
        return new self(Status::Failed, error: array_merge(['message' => $message], $extra ?? []));
    }
}
