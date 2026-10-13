<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Context;

/**
 * Append-only administrative audit trail (FR-ADM-005, Spec 10.10).
 *
 * @property string $action
 * @property string $entity_type
 * @property int|null $entity_id
 * @property array<string, mixed>|null $summary
 */
class AuditLog extends Model
{
    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'action',
        'entity_type',
        'entity_id',
        'correlation_id',
        'summary',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Single write path for audit events. Never store secrets, raw tokens or full
     * free-text payloads in $summary.
     *
     * @param  array<string, mixed>|null  $summary
     */
    public static function record(string $action, Model $entity, ?array $summary = null, ?int $actorId = null): self
    {
        return static::create([
            'actor_user_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => (int) $entity->getKey(),
            'correlation_id' => Context::get('correlation_id'),
            'summary' => $summary,
            // Retained only while needed for security review; see retention policy.
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
