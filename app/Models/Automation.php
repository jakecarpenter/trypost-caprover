<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Automation\Node\Type as NodeType;
use App\Enums\Automation\Status;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class Automation extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * Fields inside `nodes[].data` that hold sensitive credentials. Stored
     * encrypted on save and never returned to the frontend in plain text —
     * AutomationResource masks them with PLACEHOLDER on output.
     */
    public const SENSITIVE_NODE_FIELDS = ['auth_token', 'auth_password'];

    public const SENSITIVE_PLACEHOLDER = '••••••••';

    protected $guarded = [];

    protected $casts = [
        'status' => Status::class,
        'nodes' => 'array',
        'connections' => 'array',
        'variables' => 'array',
        'activated_at' => 'datetime',
        'paused_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $automation): void {
            $automation->nodes = self::encryptSensitiveFields(
                $automation->nodes ?? [],
                $automation->getOriginal('nodes') ?? [],
            );
            $automation->variables = self::encryptVariables(
                $automation->variables ?? [],
                $automation->getOriginal('variables') ?? [],
            );
            $automation->trigger_type = self::deriveTriggerType($automation->nodes ?? []);
        });
    }

    /**
     * Workflow variables decrypted into a `key => value` map for use during a
     * run (e.g. `{{ variables.API_KEY }}` resolution). Encrypted at rest and
     * never returned to the frontend in plain text.
     *
     * @return array<string, string>
     */
    public function resolvedVariables(): array
    {
        $resolved = [];

        foreach ($this->variables ?? [] as $variable) {
            $key = data_get($variable, 'key');
            if (! is_string($key) || $key === '') {
                continue;
            }
            $resolved[$key] = self::decryptValue((string) data_get($variable, 'value', ''));
        }

        return $resolved;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function triggerItems(): HasMany
    {
        return $this->hasMany(AutomationTriggerItem::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    /**
     * Walks both the incoming and stored node lists and reconciles sensitive
     * fields: a PLACEHOLDER value means "user didn't change it" (frontend
     * never received the real value) so we keep the existing ciphertext. Plain
     * text values get encrypted; already-encrypted strings pass through.
     *
     * Denormalize the trigger node's type into an indexed column so the
     * scheduler can filter by it in SQL instead of decoding every automation's
     * `nodes` JSON each minute. Recomputed on every save so it cannot drift.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private static function deriveTriggerType(array $nodes): ?string
    {
        $triggerNode = collect($nodes)->firstWhere('type', NodeType::Trigger->value);

        return data_get($triggerNode, 'data.trigger_type');
    }

    /**
     * @param  array<int, array<string, mixed>>  $incoming
     * @param  array<int, array<string, mixed>>|string  $original
     * @return array<int, array<string, mixed>>
     */
    private static function encryptSensitiveFields(array $incoming, array|string $original): array
    {
        $original = is_array($original) ? $original : (json_decode($original, true) ?: []);
        $originalById = collect($original)->keyBy('id');

        foreach ($incoming as &$node) {
            $originalNode = $originalById->get($node['id'] ?? null);
            foreach (self::SENSITIVE_NODE_FIELDS as $field) {
                $value = data_get($node, "data.{$field}");
                if (! is_string($value) || $value === '') {
                    continue;
                }
                if ($value === self::SENSITIVE_PLACEHOLDER) {
                    data_set($node, "data.{$field}", data_get($originalNode, "data.{$field}", ''));

                    continue;
                }
                if (self::looksEncrypted($value)) {
                    continue;
                }
                data_set($node, "data.{$field}", Crypt::encryptString($value));
            }
        }

        return $incoming;
    }

    /**
     * Reconciles workflow variable values exactly like node credentials, matched
     * by variable `key`: a PLACEHOLDER value keeps the existing ciphertext,
     * plaintext gets encrypted, already-encrypted strings pass through.
     *
     * @param  array<int, array<string, mixed>>  $incoming
     * @param  array<int, array<string, mixed>>|string  $original
     * @return array<int, array<string, mixed>>
     */
    private static function encryptVariables(array $incoming, array|string $original): array
    {
        $original = is_array($original) ? $original : (json_decode($original, true) ?: []);
        $originalByKey = collect($original)->keyBy('key');

        foreach ($incoming as &$variable) {
            $value = data_get($variable, 'value');
            if (! is_string($value) || $value === '') {
                continue;
            }
            if ($value === self::SENSITIVE_PLACEHOLDER) {
                $variable['value'] = (string) data_get($originalByKey->get($variable['key'] ?? null), 'value', '');

                continue;
            }
            if (self::looksEncrypted($value)) {
                continue;
            }
            $variable['value'] = Crypt::encryptString($value);
        }

        return $incoming;
    }

    private static function decryptValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }

    /**
     * Quick check for Laravel's `Crypt::encryptString` output without paying
     * the cost of a full decrypt attempt. Laravel wraps payloads as base64
     * JSON beginning with the canonical `eyJpdiI` ("{"iv":"...) prefix.
     */
    private static function looksEncrypted(string $value): bool
    {
        if (! str_starts_with($value, 'eyJ')) {
            return false;
        }
        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
