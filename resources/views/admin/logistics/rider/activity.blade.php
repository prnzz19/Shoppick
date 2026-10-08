<ol class="rd-timeline">
@forelse($events as $event)
<li class="rd-event"><span class="rd-dot" data-event-status="{{ $event->status }}" aria-hidden="true"></span><div class="rd-event-heading">@include('admin.logistics.rider.badge',['status'=>$event->status])<time datetime="{{ $event->created_at?->toIso8601String() }}" class="rd-muted">{{ $event->created_at?->format('M j, Y · g:i A') ?? 'Date not recorded' }}</time></div>
@if($event->shipment)<a class="rd-event-tracking" href="{{ route('admin.logistics.deliveries.show',$event->shipment) }}">{{ $event->shipment->shipment_number }}</a>@endif
@if($event->note)<p class="rd-event-note">{{ $event->note }}</p>@endif
@if($event->location)<p class="rd-muted mt-1">{{ $event->location }}</p>@endif
</li>
@empty<li class="rd-empty">No delivery activity has been recorded for this rider yet.</li>@endforelse
</ol>
@include('admin.logistics.rider.pagination',['paginator'=>$events,'unit'=>'events'])
