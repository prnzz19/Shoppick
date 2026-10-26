<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A row from the PSA Philippine Standard Geographic Code hierarchy.
 *
 * The catalog is reference data.  Operational coverage is stored separately
 * on DeliveryArea and is never inferred from the existence of a catalog row.
 */
class PsgcLocation extends Model
{
    protected $table = 'psgc_locations';

    protected $fillable = [
        'code', 'correspondence_code', 'level', 'name', 'parent_code',
        'region_code', 'province_code', 'municipality_code', 'source_version',
        'is_urban', 'population', 'is_active',
    ];

    protected $casts = [
        'is_urban' => 'boolean',
        'population' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }
}
