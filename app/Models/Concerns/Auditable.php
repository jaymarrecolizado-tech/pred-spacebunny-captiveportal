<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Records administrative changes of a model in the audit trail.
 *
 * One shared implementation for every audited entity, so a new registry model
 * cannot quietly skip auditing.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn (self $model) => AuditLog::record('created', $model, $model->auditSummary()));

        static::updated(function (self $model): void {
            $changes = $model->auditChanges();

            if ($changes !== []) {
                AuditLog::record('updated', $model, ['changed' => $changes]);
            }
        });

        static::deleted(fn (self $model) => AuditLog::record('deleted', $model));
    }

    /**
     * Field-level before/after pairs for the audit summary (Spec 10.10
     * `safe_before_after_summary`). `getChanges()` alone only reports the new
     * value, which makes an audit trail much less useful.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    protected function auditChanges(): array
    {
        $changes = [];

        foreach ($this->getDirty() as $key => $value) {
            $changes[$key] = [
                'from' => $this->normalizeForAudit($this->getOriginal($key)),
                'to' => $this->normalizeForAudit($value),
            ];
        }

        return $changes;
    }

    /**
     * Enums and objects are reduced to storable scalars.
     */
    protected function normalizeForAudit(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditSummary(): array
    {
        return [];
    }
}
