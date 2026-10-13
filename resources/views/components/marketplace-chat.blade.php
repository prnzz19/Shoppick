@props(['mode' => 'buyer', 'embedded' => false])
@php
    $config = match($mode) {
        'seller' => ['list'=>route('seller.chat.index'),'show'=>url('/seller/chat/{id}'),'send'=>url('/seller/chat/{id}/messages'),'support'=>route('seller.chat.support')],
        'admin' => ['list'=>route('admin.seller-support.conversations'),'show'=>url('/admin/seller-support/{id}'),'send'=>url('/admin/seller-support/{id}/messages')],
        default => ['list'=>route('chat.index'),'show'=>url('/chat/{id}'),'send'=>url('/chat/{id}/messages'),'start'=>route('chat.buyer.start')],
    };
@endphp
<section class="market-chat {{ $embedded ? 'market-chat-embedded' : '' }}" data-market-chat data-mode="{{ $mode }}" data-config='@json($config)' aria-label="SHOPPICK messages">
    @unless($embedded)
    <button type="button" class="market-chat-launcher" data-chat-launcher aria-expanded="false" aria-controls="market-chat-panel"><span aria-hidden="true">💬</span><span>Chat</span><b data-chat-total hidden></b></button>
    @endunless
    <div id="market-chat-panel" class="market-chat-panel {{ $embedded ? '' : 'is-closed' }}" data-chat-panel>
        <header class="market-chat-header">
            <button type="button" data-chat-back class="market-chat-icon is-hidden" aria-label="Back to conversations">←</button>
            <div class="min-w-0"><strong data-chat-title>{{ $mode === 'admin' ? 'Seller Support' : 'Messages' }}</strong><small data-chat-subtitle>{{ $mode === 'seller' ? 'Customers and SHOPPICK Support' : 'SHOPPICK marketplace chat' }}</small></div>
            @unless($embedded)<button type="button" data-chat-minimize class="market-chat-icon" aria-label="Minimize chat">−</button><button type="button" data-chat-expand class="market-chat-icon" aria-label="Expand chat">□</button><button type="button" data-chat-close class="market-chat-icon" aria-label="Close chat">×</button>@endunless
        </header>
        <div class="market-chat-status is-hidden" data-chat-status role="status"></div>
        <div class="market-chat-list" data-chat-list>
            @if($mode === 'seller')<div class="market-chat-tabs" role="tablist"><button type="button" data-chat-tab="customers" class="is-active">Customers</button><button type="button" data-chat-tab="support">SHOPPICK Support</button></div>@endif
            <label class="market-chat-search"><span class="sr-only">Search conversations</span><input type="search" data-chat-search placeholder="Search conversations"></label>
            @if($mode === 'seller')<button type="button" class="market-chat-support" data-chat-support>Contact SHOPPICK Support</button>@endif
            <div class="market-chat-conversations" data-chat-conversations></div>
            <p class="market-chat-empty" data-chat-empty>No conversations yet.</p>
        </div>
        <div class="market-chat-thread is-hidden" data-chat-thread>
            <a class="market-chat-context is-hidden" data-chat-context></a>
            <div class="market-chat-history" data-chat-history aria-live="polite"><button type="button" data-chat-older class="market-chat-older is-hidden">Load older messages</button></div>
            <form class="market-chat-composer" data-chat-form><label class="sr-only" for="market-chat-message-{{ $mode }}">Type a message</label><textarea id="market-chat-message-{{ $mode }}" data-chat-input rows="1" maxlength="2000" placeholder="Type a message here..."></textarea><button type="submit" data-chat-send>Send</button></form>
        </div>
    </div>
</section>
