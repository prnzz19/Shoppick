<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A normalized Philippine Standard Geographic Code (PSGC) barangay.
 *
 * The catalog is reference data only; shipping snapshots remain immutable.
 */
class PsgcBarangay extends Model
{
    protected $table = 'psgc_barangays';

    protected $fillable = [
        'code', 'correspondence_code', 'province_code', 'province',
        'municipality_code', 'municipality', 'name', 'is_urban', 'population',
        'source_version', 'is_active',
    ];

    protected $casts = ['is_urban' => 'boolean', 'is_active' => 'boolean', 'population' => 'integer'];

    public function deliveryAreas()
    {
        return $this->belongsToMany(DeliveryArea::class, 'delivery_area_barangay');
    }
}
