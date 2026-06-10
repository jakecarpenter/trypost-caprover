<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationNodeState extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /**
     * Idempotent lookup for the state row of a given node within an automation,
     * creating an empty row on first access. Use this in poll/fire actions that
     * need to read or update an internal watermark.
     */
    public static function for(string $automationId, string $nodeId): self
    {
        return self::firstOrCreate(
            ['automation_id' => $automationId, 'node_id' => $nodeId],
            ['data' => []],
        );
    }
}
