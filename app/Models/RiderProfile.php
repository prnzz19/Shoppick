<?php
namespace App\Models;
use App\Traits\ScopesLogisticsProvider;
use Illuminate\Database\Eloquent\Model;
class RiderProfile extends Model
{
    protected $fillable=['user_id','logistics_user_id','logistics_provider_id','hub_id','requested_hub_id','vehicle_id','account_status','availability','rating','notes','profile_photo_path','requested_service_area','approved_service_area','requested_rider_type','rider_type','vehicle_type','vehicle_make_model','plate_number','vehicle_color','vehicle_registration_number','or_cr_path','proof_of_address_path','driver_license_path','emergency_contact_name','emergency_contact_relationship','emergency_contact_phone','notification_preferences','driver_license_number','driver_license_classification','driver_license_expires_at','driver_license_front_path','driver_license_back_path','driver_license_status','driver_license_rejection_reason','driver_license_reviewed_by','driver_license_reviewed_at'];
    protected $casts=['rating'=>'decimal:2','notification_preferences'=>'array','driver_license_expires_at'=>'date','driver_license_reviewed_at'=>'datetime'];

    public function user(){return $this->belongsTo(User::class);}
    public function logisticsOwner(){return $this->belongsTo(User::class,'logistics_user_id');}
    public function provider(){return $this->belongsTo(LogisticsProvider::class,'logistics_provider_id');}
    public function hub(){return $this->belongsTo(LogisticsHub::class);}
    public function requestedHub(){return $this->belongsTo(LogisticsHub::class,'requested_hub_id');}
    public function vehicle(){return $this->belongsTo(Vehicle::class);}
    public function deliveryAreas(){return $this->belongsToMany(DeliveryArea::class,'delivery_area_rider');}
    public function licenseReviewer(){return $this->belongsTo(User::class,'driver_license_reviewed_by');}
    public function licenseAudits(){return $this->hasMany(RiderLicenseAudit::class);}

    public function scopeForProvider($query,int $providerId){return $query->where('logistics_provider_id',$providerId);}

    public function scopeAssignmentEligible($query)
    {
        return $query->where('account_status','active')->where('availability','available')
            ->whereNotNull('driver_license_number')->where(fn($q)=>$q->where(fn($docs)=>$docs->whereNotNull('driver_license_front_path')->whereNotNull('driver_license_back_path'))->orWhereNotNull('driver_license_path'))
            ->whereDate('driver_license_expires_at','>=',today())
            ->when(config('riders.license_verification_required',true),fn($q)=>$q->where('driver_license_status','verified'));
    }

    public function getLicenseDisplayStatusAttribute(): string
    {
        if ($this->driver_license_expires_at?->lt(today())) return 'expired';
        if ($this->driver_license_status === 'verified' && $this->driver_license_expires_at?->lte(now()->addDays(config('riders.license_expiry_warning_days', 30)))) return 'expiring_soon';
        return $this->driver_license_status ?: 'pending_review';
    }

    public function getMaskedLicenseNumberAttribute(): string
    {
        $number = (string) $this->driver_license_number;
        return $number === '' ? 'Not recorded' : str_repeat('•', max(4, mb_strlen($number) - 4)).mb_substr($number, -4);
    }

    public function isAssignmentEligible(): bool
    {
        if ($this->account_status !== 'active' || $this->availability !== 'available') return false;
        return $this->hasValidLicense();
    }

    public function hasValidLicense(): bool
    {
        if (!$this->driver_license_expires_at || $this->driver_license_expires_at->lt(today())) return false;
        if (config('riders.license_verification_required', true) && $this->driver_license_status !== 'verified') return false;
        return filled($this->driver_license_number) && (filled($this->driver_license_path) || (filled($this->driver_license_front_path) && filled($this->driver_license_back_path)));
    }
}
