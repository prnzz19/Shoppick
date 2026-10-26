<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LogisticsProvider extends Model
{
    protected $fillable = ['owner_user_id','company_name','trade_name','logo_path','contact_name','email','phone','address','representative_position','representative_email','representative_phone','representative_id_path','street','barangay','city','province','postal_code','registration_number','sec_registration_number','tin','region','region_code','province_code','city_code','barangay_code','service_regions','business_registration_path','permit_path','tax_registration_info','tax_registration_path','areas_served','pickup_coverage_areas','delivery_coverage_areas','operating_hours','number_of_riders','number_of_vehicles','supported_vehicle_types','operations_contact','customer_support_contact','emergency_contact','status','is_active','review_notes','reviewed_by','reviewed_at'];
    protected $casts = ['is_active'=>'boolean','reviewed_at'=>'datetime','areas_served'=>'array','service_regions'=>'array','pickup_coverage_areas'=>'array','delivery_coverage_areas'=>'array','supported_vehicle_types'=>'array','operations_contact'=>'array','customer_support_contact'=>'array','emergency_contact'=>'array'];
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function users() { return $this->belongsToMany(User::class, 'logistics_provider_user')->withPivot(['membership_role','is_active'])->withTimestamps(); }
    public function riders() { return $this->hasMany(RiderProfile::class, 'logistics_provider_id'); }
    public function hubs() { return $this->hasMany(LogisticsHub::class, 'logistics_provider_id'); }
    public function vehicles() { return $this->hasMany(Vehicle::class, 'logistics_provider_id'); }
    public function shipments() { return $this->hasMany(Shipment::class, 'logistics_provider_id'); }
    public function scopeApprovedActive(Builder $query): Builder { return $query->where('status', 'approved')->where('is_active', true); }
    public function isOperational(): bool { return $this->status === 'approved' && (bool) $this->is_active; }
    public function platformStatus(): string { return $this->isOperational() ? 'active' : (string) $this->status; }
}
