<?php

namespace App\Models;

use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property UserRole $role
 * @property ScopeType $scope_type
 * @property int|null $scope_supplier_id
 * @property string|null $scope_provinces
 * @property bool $is_active
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable;

    use HasFactory;
    use Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'scope_type',
        'scope_supplier_id',
        'scope_provinces',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'scope_type' => ScopeType::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function scopeSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'scope_supplier_id');
    }

    public function hasFullScope(): bool
    {
        return $this->scope_type === ScopeType::All;
    }

    /**
     * @return array<int, string>
     */
    public function provinceList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->scope_provinces))));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    /**
     * FR-ADM-007: a deactivated account keeps its history but loses access.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
