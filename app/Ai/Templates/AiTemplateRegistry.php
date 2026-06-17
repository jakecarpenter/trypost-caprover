<?php

declare(strict_types=1);

namespace App\Ai\Templates;

use InvalidArgumentException;

class AiTemplateRegistry
{
    /** @var array<int, class-string<AiContentTemplate>> */
    private const TEMPLATES = [
        ImageCardTemplate::class,
        TweetCardTemplate::class,
        TweetCardImageTemplate::class,
    ];

    /** @var array<int, AiContentTemplate>|null */
    private ?array $templates = null;

    /** @return array<int, AiContentTemplate> */
    public function all(): array
    {
        return $this->templates ??= array_map(fn (string $class) => app($class), self::TEMPLATES);
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_map(fn (AiContentTemplate $t) => $t->key(), $this->all());
    }

    public function find(string $key): AiContentTemplate
    {
        foreach ($this->all() as $template) {
            if ($template->key() === $key) {
                return $template;
            }
        }

        throw new InvalidArgumentException("Unknown AI content template: {$key}");
    }

    public function default(): AiContentTemplate
    {
        return app(ImageCardTemplate::class);
    }
}
