<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Automation\Run\Status;
use App\Observers\AutomationRunObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([AutomationRunObserver::class])]
class AutomationRun extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'status' => Status::class,
        'context' => 'array',
        'error' => 'array',
        'is_manual' => 'boolean',
        'is_dry_run' => 'boolean',
        'next_action_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /**
     * Context for template (`{{ ... }}`) resolution: the run context plus the
     * automation's workflow variables, merged in-memory. Variables are NEVER
     * persisted into the run context (they're encrypted at rest and would
     * otherwise leak in plaintext via the run/node-run API), so we compute this
     * on demand at resolve time only.
     *
     * @return array<string, mixed>
     */
    public function resolverContext(): array
    {
        return array_merge(
            $this->context ?? [],
            ['variables' => $this->automation->resolvedVariables()],
        );
    }

    public function triggerItem(): BelongsTo
    {
        return $this->belongsTo(AutomationTriggerItem::class, 'trigger_item_id');
    }

    public function generatedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'generated_post_id');
    }

    public function nodeRuns(): HasMany
    {
        return $this->hasMany(AutomationNodeRun::class, 'run_id');
    }

    /**
     * Hides dry-run rows from user-facing history queries. Internal/analytics
     * queries can ignore the scope to see every row.
     */
    public function scopeExcludingDryRuns(Builder $query): Builder
    {
        return $query->where('is_dry_run', false);
    }
}
