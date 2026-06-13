<?php

declare(strict_types=1);

namespace App\Enums\Workspace;

/**
 * Discrete brand-voice traits a workspace can toggle, replacing the old
 * free-text `brand_voice_notes` AND the single `brand_tone` column. Stored as a
 * JSON array of these string values (e.g. ["third_person", "balanced", "no_hype"]).
 *
 * Traits are organized into groups so any selection composes coherently:
 *  - the spectrum groups (pov, formality, energy, humor, attitude, warmth,
 *    confidence) are SINGLE-SELECT in the UI — mutually exclusive opposites,
 *  - the `style` group stacks freely (additive traits with no opposite).
 *
 * The instruction each trait maps to lives in the prompt Blade
 * (`prompts/post_content/_voice`), never here — see the project AI conventions.
 */
enum BrandVoiceTrait: string
{
    // Point of view.
    case FirstPerson = 'first_person';
    case SecondPerson = 'second_person';
    case ThirdPerson = 'third_person';

    // Formality.
    case Formal = 'formal';
    case Balanced = 'balanced';
    case Casual = 'casual';

    // Energy.
    case Calm = 'calm';
    case Moderate = 'moderate';
    case Enthusiastic = 'enthusiastic';
    case Vibrant = 'vibrant';

    // Humor.
    case Serious = 'serious';
    case Dry = 'dry';
    case Witty = 'witty';
    case Playful = 'playful';

    // Attitude.
    case Respectful = 'respectful';
    case EvenHanded = 'even_handed';
    case Bold = 'bold';
    case Provocative = 'provocative';

    // Warmth.
    case Neutral = 'neutral';
    case Friendly = 'friendly';
    case Empathetic = 'empathetic';

    // Confidence.
    case Humble = 'humble';
    case Confident = 'confident';
    case Assertive = 'assertive';

    // Additive style traits (stack freely).
    case Direct = 'direct';
    case Concise = 'concise';
    case Transparent = 'transparent';
    case NoHype = 'no_hype';
    case Practical = 'practical';
    case DataDriven = 'data_driven';
    case Storytelling = 'storytelling';
    case Inspirational = 'inspirational';
    case Educational = 'educational';
    case Technical = 'technical';
    case Minimalist = 'minimalist';

    /**
     * UI grouping. Every group except `style` is single-select (mutually
     * exclusive opposites); `style` traits stack.
     */
    public function group(): string
    {
        return match ($this) {
            self::FirstPerson, self::SecondPerson, self::ThirdPerson => 'pov',
            self::Formal, self::Balanced, self::Casual => 'formality',
            self::Calm, self::Moderate, self::Enthusiastic, self::Vibrant => 'energy',
            self::Serious, self::Dry, self::Witty, self::Playful => 'humor',
            self::Respectful, self::EvenHanded, self::Bold, self::Provocative => 'attitude',
            self::Neutral, self::Friendly, self::Empathetic => 'warmth',
            self::Humble, self::Confident, self::Assertive => 'confidence',
            default => 'style',
        };
    }

    /**
     * Groups whose options are mutually exclusive (rendered single-select).
     *
     * @return array<int, string>
     */
    public static function singleSelectGroups(): array
    {
        return ['pov', 'formality', 'energy', 'humor', 'attitude', 'warmth', 'confidence'];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }

    /**
     * Reduces arbitrary values (e.g. an LLM's guess) to a coherent selection:
     * only valid traits, and at most one per single-select dimension. Style
     * traits stack. Order is preserved.
     *
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    public static function coerce(array $values): array
    {
        $single = self::singleSelectGroups();
        $usedGroups = [];
        $result = [];

        foreach ($values as $value) {
            $trait = is_string($value) ? self::tryFrom($value) : null;
            if ($trait === null) {
                continue;
            }

            $group = $trait->group();
            if (in_array($group, $single, true)) {
                if (isset($usedGroups[$group])) {
                    continue;
                }
                $usedGroups[$group] = true;
            }

            $result[] = $trait->value;
        }

        return $result;
    }

    /**
     * Trait values keyed by group, in declaration order — drives the brand
     * form's grouped pill picker.
     *
     * @return array<string, array<int, string>>
     */
    public static function grouped(): array
    {
        $grouped = [];
        foreach (self::cases() as $trait) {
            $grouped[$trait->group()][] = $trait->value;
        }

        return $grouped;
    }
}
