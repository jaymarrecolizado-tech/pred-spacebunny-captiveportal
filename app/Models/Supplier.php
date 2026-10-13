<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property RecordStatus $status
 */
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use Auditable;

    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'status',
        'technical_contact_name',
        'technical_contact_email',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
        ];
    }

    /** @return HasMany<Site, $this> */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
