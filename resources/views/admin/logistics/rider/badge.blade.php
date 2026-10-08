@php($tone = match($status) {'active','delivered','completed','picked_up','pickup_accepted'=>'teal','in_transit','out_for_delivery','assigned_to_rider','hub_transfer'=>'blue','exception','delivery_failed'=>'orange','returned','suspended'=>'red',default=>'gray'})
<span class="rd-badge rd-{{ $tone }}" data-status="{{ $status }}">{{ ucwords(str_replace('_',' ',$status)) }}</span>
