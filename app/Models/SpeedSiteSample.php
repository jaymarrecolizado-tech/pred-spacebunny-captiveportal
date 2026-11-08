<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeedSiteSample extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'client_count' => 'integer',
            'wireless_count' => 'integer',
            'wired_count' => 'integer',
            'ap_to_client_max_kbps' => 'integer',
            'client_to_ap_max_kbps' => 'integer',
            'low_rate_wireless_count' => 'integer',
            'delta_down_bytes' => 'integer',
            'delta_up_bytes' => 'integer',
            'sample_interval_seconds' => 'integer',
            'matched_clients' => 'integer',
            'unmatched_clients' => 'integer',
            'down_mbps' => 'float',
            'up_mbps' => 'float',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
