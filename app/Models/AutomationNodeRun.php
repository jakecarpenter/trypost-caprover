<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Automation\Node\Type as NodeType;
use App\Enums\Automation\NodeRun\Status;
use App\Observers\AutomationNodeRunObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([AutomationNodeRunObserver::class])]
class AutomationNodeRun extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'status' => Status::class,
        'node_type' => NodeType::class,
        'input' => 'array',
        'output' => 'array',
        'error' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'run_id');
    }
}
