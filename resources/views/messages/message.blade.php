@php($attachments = $payload['attachments'] ?? [])
<article class="message-line {{ $payload['mine'] ? 'mine' : '' }}" data-chat-message data-message-id="{{ $payload['id'] }}">
    <div class="message-bubble {{ $attachments !== [] ? 'has-photos' : '' }}">
        @if($payload['context'])
            @if($payload['context']['url'])<a class="message-context" href="{{ $payload['context']['url'] }}">@else<div class="message-context">@endif
                @if(!empty($payload['context']['image']))<img class="message-context-image" src="{{ $payload['context']['image'] }}" alt="">@endif
                <span>
                    <strong>{{ $payload['context']['type'] === 'product' ? 'Product' : 'Order' }}: {{ $payload['context']['title'] }}</strong>
                    @if($payload['context']['type'] === 'product' && $payload['context']['price'])<small>₱{{ $payload['context']['price'] }} · View Product</small>
                    @elseif($payload['context']['type'] === 'order' && $payload['context']['status'])<small>{{ $payload['context']['status'] }} · View Order</small>
                    @else<small>{{ $payload['context']['type'] === 'product' ? 'Product unavailable' : 'Order unavailable' }}</small>@endif
                </span>
            @if($payload['context']['url'])</a>@else</div>@endif
        @endif
        @if($attachments !== [])
            <div class="message-photo-gallery {{ count($attachments) === 1 ? 'single' : '' }}">
                @foreach($attachments as $attachment)
                    <button type="button" class="message-photo" data-message-photo="{{ $attachment['url'] }}" aria-label="Open message photo"><img src="{{ $attachment['url'] }}" alt="Photo attachment" loading="lazy"></button>
                @endforeach
            </div>
        @endif
        @if($payload['body'] !== null)<div class="message-text">{{ $payload['body'] }}</div>@endif
        <time class="message-time" datetime="{{ $payload['created_at'] }}">{{ $payload['time'] }}</time>
    </div>
</article>
