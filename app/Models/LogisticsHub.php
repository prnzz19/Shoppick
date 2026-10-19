<?php
namespace App\Models;
use App\Traits\ScopesLogisticsProvider;
use Illuminate\Database\Eloquent\Model;
class LogisticsHub extends Model {
    use ScopesLogisticsProvider;

    protected $fillable=['logistics_provider_id','code','name','address','street','barangay','municipality','province','postal_code','latitude','longitude','contact_phone','operating_hours','capacity','status','notes'];
    protected $casts=['latitude'=>'decimal:7','longitude'=>'decimal:7'];
    public function provider(){return $this->belongsTo(LogisticsProvider::class,'logistics_provider_id');}
    public function scopeForProvider($q,int $id){return $q->where('logistics_provider_id',$id);}
    public function shipments(){return $this->hasMany(Shipment::class,'current_hub_id');}
    public function originShipments(){return $this->hasMany(Shipment::class,'origin_hub_id');}
    public function nextShipments(){return $this->hasMany(Shipment::class,'next_hub_id');}
    public function destinationShipments(){return $this->hasMany(Shipment::class,'destination_hub_id');}
    public function riders(){return $this->hasMany(RiderProfile::class,'hub_id');}
    public function vehicles(){return $this->hasMany(Vehicle::class,'hub_id');}
    public function scannerUsers(){return $this->hasMany(User::class,'sorting_center_id');}
    public function coverageAreas(){return $this->belongsToMany(DeliveryArea::class,'delivery_area_hub')->withTimestamps();}
}
