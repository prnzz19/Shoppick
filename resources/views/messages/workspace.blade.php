@php
    $workspaceClass = $activeConversation ? 'has-active' : '';
    $basePath = rtrim(request()->getBaseUrl(), '/');
    $chatRoute = fn (string $name, $parameters = []) => $basePath.route($name, $parameters, false);
    $chatScriptUrl = $basePath.'/js/buyer-seller-messages.js?v='.filemtime(public_path('js/buyer-seller-messages.js'));
    $indexUrl = $sellerView ? route('seller.messages.index') : route('messages.index');
    $sendUrl = $activeConversation
        ? ($sellerView ? $chatRoute('seller.messages.send', $activeConversation) : $chatRoute('messages.send', $activeConversation))
        : '';
    $updatesUrl = $activeConversation
        ? ($sellerView ? $chatRoute('seller.messages.updates', $activeConversation) : $chatRoute('messages.updates', $activeConversation))
        : '';
    $olderUrl = $activeConversation
        ? ($sellerView ? $chatRoute('seller.messages.older', $activeConversation) : $chatRoute('messages.older', $activeConversation))
        : '';
    $peer = $activeConversation
        ? ($sellerView ? $activeConversation->buyer : $activeConversation->store)
        : null;
    $peerName = $peer?->name ?? 'Conversation';
    $peerImage = $peer
        ? ($sellerView ? $peer->avatar_url : ($peer->logo ? asset('storage/'.$peer->logo) : null))
        : null;
@endphp
<style>
.message-workspace{--msg-teal:#078f84;--msg-orange:#f2a33a;--msg-navy:#1d2e44;display:grid;grid-template-columns:minmax(250px,310px) minmax(0,1fr);height:min(760px,72vh);min-height:540px;overflow:hidden;border:1px solid #e5eaf0;border-radius:18px;background:#fff;box-shadow:0 10px 32px rgba(24,44,65,.07)}
.message-list-panel{display:flex;min-width:0;flex-direction:column;border-right:1px solid #e9edf1;background:#fff}
.message-list-heading{padding:20px 18px 14px}
.message-list-heading h1{margin:0;color:var(--msg-navy);font-size:22px;font-weight:800;letter-spacing:-.4px}
.message-list-heading p{margin-top:3px;color:#7c8999;font-size:12px}
.message-search{height:40px;margin:0 14px 12px;border:1px solid #e4eaf0;border-radius:11px;background:#f7f9fb;padding:0 12px;color:var(--msg-navy);font-size:13px;outline:none}
.message-search:focus{border-color:#75c4b9;box-shadow:0 0 0 3px rgba(7,143,132,.1)}
.message-conversation-list{flex:1;overflow-y:auto;padding:0 8px 10px}
.message-conversation{display:flex;align-items:center;gap:11px;min-width:0;margin:2px 0;border-radius:12px;padding:11px 10px;color:inherit;text-decoration:none;transition:background .15s}
.message-conversation:hover{background:#f5f8fa}
.message-conversation.active{background:#eaf7f5}
.message-avatar{display:flex;width:44px;height:44px;flex:none;align-items:center;justify-content:center;overflow:hidden;border-radius:50%;background:#e4f5f2;color:#087c73;font-size:15px;font-weight:800}
.message-avatar img{width:100%;height:100%;object-fit:cover}
.message-conversation-copy{min-width:0;flex:1}
.message-conversation-top{display:flex;align-items:center;justify-content:space-between;gap:8px}
.message-conversation-top strong{overflow:hidden;color:var(--msg-navy);font-size:13px;font-weight:750;text-overflow:ellipsis;white-space:nowrap}
.message-conversation time{flex:none;color:#96a0ab;font-size:10px}
.message-preview{display:block;overflow:hidden;margin-top:4px;color:#7c8999;font-size:12px;text-overflow:ellipsis;white-space:nowrap}
.message-unread{display:inline-flex;min-width:19px;height:19px;align-items:center;justify-content:center;margin-left:6px;border-radius:999px;background:var(--msg-orange);padding:0 5px;color:#fff;font-size:10px;font-weight:800}
.message-empty-list{margin:20px 12px;border:1px dashed #dfe7ec;border-radius:13px;padding:18px;text-align:center;color:#7c8999;font-size:12px;line-height:1.5}
.message-list-pagination{padding:4px 12px 12px;font-size:12px}
.message-list-pagination nav{display:flex;justify-content:center;gap:10px}
.message-thread-panel{display:flex;min-width:0;flex-direction:column;background:#fbfcfd}
.message-thread-header{display:flex;min-height:72px;align-items:center;gap:12px;border-bottom:1px solid #e9edf1;background:#fff;padding:12px 18px}
.message-peer-avatar{width:42px;height:42px}
.message-peer-name{color:var(--msg-navy);font-size:15px;font-weight:800}
.message-peer-caption{margin-top:2px;color:#8793a0;font-size:11px}
.message-back{display:none;border:0;background:none;color:var(--msg-teal);font-size:13px;font-weight:700}
.message-thread{display:flex;min-height:0;flex:1;flex-direction:column}
.message-scroll{flex:1;overflow-y:auto;padding:18px 22px 12px;scroll-behavior:smooth}
.message-load-older{display:block;margin:0 auto 15px;border:0;background:transparent;color:var(--msg-teal);font-size:12px;font-weight:700}
.message-load-older:disabled{opacity:.55}
.message-line{display:flex;margin:9px 0;outline:0}
.message-line:focus,.message-line:focus-within{outline:0}
.message-line.mine{justify-content:flex-end}
.message-bubble{max-width:min(76%,620px);border:1px solid #e6ebef;border-radius:16px 16px 16px 4px;background:#fff;padding:10px 13px;color:#344356;box-shadow:0 2px 5px rgba(24,44,65,.035)}
.message-line.mine .message-bubble{border-color:#078f84;border-radius:16px 16px 4px 16px;background:#078f84;color:#fff}
.message-text-bubble{width:max-content;max-width:min(76%,620px)}
.message-photo-group{width:min(76%,620px);max-width:100%;color:#344356}
.message-line.mine .message-photo-group{color:#344356}
.message-text{overflow-wrap:anywhere;white-space:pre-wrap;font-size:13px;line-height:1.45}
.message-time{display:block;margin-top:5px;color:#97a2ad;font-size:10px}
.message-line.mine .message-time{color:rgba(255,255,255,.72);text-align:right}
.message-context{display:flex;max-width:100%;align-items:center;gap:10px;margin-bottom:7px;border:1px solid #e6ebef;border-radius:10px;background:#fff;padding:8px;color:var(--msg-navy);text-decoration:none}
.message-line.mine .message-context{border-color:rgba(255,255,255,.35)}
.message-context-image{width:48px;height:48px;flex:none;border-radius:7px;background:#f1f4f6;object-fit:cover}
.message-context strong{display:block;font-size:12px}
.message-context small{display:block;margin-top:3px;color:#7e8b99;font-size:10px}
.message-line.mine .message-context small{color:#748b8b}
.message-composer{display:flex;align-items:flex-end;gap:10px;border-top:1px solid #e9edf1;background:#fff;padding:13px 16px}
.message-composer textarea{min-height:42px;max-height:130px;flex:1;resize:vertical;border:1px solid #e1e7ec;border-radius:12px;background:#fafbfc;padding:10px 12px;color:var(--msg-navy);font:inherit;font-size:13px;outline:none}
.message-composer textarea:focus{border-color:#75c4b9;box-shadow:0 0 0 3px rgba(7,143,132,.1)}
.message-send{height:42px;border:0;border-radius:11px;background:var(--msg-teal);padding:0 18px;color:white;font-size:13px;font-weight:800}
.message-send:hover{background:#06776e}
.message-send:disabled{cursor:wait;opacity:.6}
.message-send-error{display:none;padding:0 16px 8px;color:#b43631;font-size:12px}
.message-chat-empty{display:flex;flex:1;align-items:center;justify-content:center;padding:30px;text-align:center}
.message-chat-empty div{max-width:300px}
.message-chat-empty strong{display:block;color:var(--msg-navy);font-size:16px}
.message-chat-empty p{margin-top:7px;color:#8793a0;font-size:13px;line-height:1.5}
.message-empty-icon{display:flex;width:62px;height:62px;align-items:center;justify-content:center;margin:0 auto 14px;border-radius:20px;background:#eaf7f5;color:var(--msg-teal);font-size:27px}
@media(max-width:760px){.message-workspace{grid-template-columns:minmax(0,1fr);height:min(760px,calc(100dvh - 220px));min-height:470px}.message-thread-panel{display:none}.message-workspace.has-active .message-list-panel{display:none}.message-workspace.has-active .message-thread-panel{display:flex}.message-back{display:block}.message-list-panel{border-right:0}.message-list-heading{padding-top:16px}.message-scroll{padding:14px 12px}.message-bubble{max-width:88%}.message-composer{gap:7px;padding:10px}.message-send{padding:0 14px}}
.message-workspace{height:var(--chat-available-height,calc(100dvh - 112px));min-height:0}
.message-list-panel,.message-thread-panel,.message-conversation-list,.message-scroll{min-height:0}
.message-thread-header,.message-composer,.message-photo-preview{flex-shrink:0}
.message-composer textarea{min-width:0}
.message-photo-button{display:flex;align-items:center;justify-content:center;width:38px;height:42px;flex:none;border:0;border-radius:50%;background:transparent;color:#71808c;font-size:28px;font-weight:400;line-height:1;cursor:pointer}
.message-photo-button:hover{background:#f1f4f6;color:#52616d}.message-photo-button:focus-visible{outline:3px solid rgba(113,128,140,.25);outline-offset:2px}
.message-photo-preview{display:flex;align-items:center;gap:10px;padding:8px 16px;background:#fff;font-size:12px;color:#607382}
.message-photo-preview[hidden]{display:none}.message-photo-thumbnails{display:flex;flex:1;flex-wrap:wrap;gap:7px;min-width:0}.message-photo-preview-item{position:relative;width:58px;height:52px}.message-photo-preview-item img{width:100%;height:100%;object-fit:cover;border-radius:8px}.message-photo-preview-item button{position:absolute;top:-7px;right:-7px;width:21px;height:21px;border:1px solid #d7dfe4;border-radius:50%;background:#fff;color:#52616d;font-size:15px;line-height:16px;cursor:pointer}.message-photo-count{white-space:nowrap;font-size:11px;color:#70808d}
.message-photo-gallery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;width:min(100%,400px);max-width:100%}.message-photo-gallery.single{display:block;width:min(320px,100%)}
.message-photo{display:block;width:100%;min-width:0;max-width:100%;border:0;padding:0;background:transparent;cursor:zoom-in;outline:0}
.message-photo:focus-visible{outline:0!important;box-shadow:none!important}
.message-photo img{display:block;width:100%;height:170px;max-width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:12px}.message-photo-gallery.single .message-photo{width:fit-content}.message-photo-gallery.single .message-photo img{width:auto;height:auto;max-width:100%;max-height:400px;aspect-ratio:auto;object-fit:contain}
.message-photo-time{display:block;margin-top:5px;color:#97a2ad;font-size:10px}.message-line.mine .message-photo-time{text-align:right}
.message-bubble.has-photos{width:fit-content;max-width:min(420px,100%);border:0;background:transparent;padding:0;box-shadow:none;color:#344356}
.message-line.mine .message-bubble.has-photos{border:0;background:transparent;padding:0;box-shadow:none;color:#344356}
.message-line:not(.mine) .message-bubble.has-photos{margin-right:auto}.message-line.mine .message-bubble.has-photos{margin-left:auto}
.message-bubble.has-photos .message-text{width:fit-content;max-width:100%;margin-bottom:7px;border:1px solid #e6ebef;border-radius:16px 16px 16px 4px;background:#fff;padding:10px 13px;color:#344356}
.message-line.mine .message-bubble.has-photos .message-text{border-color:#078f84;border-radius:16px 16px 4px 16px;background:#078f84;color:#fff}
.message-bubble.has-photos .message-time{display:block;margin-top:5px;color:#97a2ad}
.message-line.mine .message-bubble.has-photos .message-time{color:#97a2ad;text-align:right}
.message-lightbox{position:fixed;inset:0;margin:auto;padding:42px 16px 16px;border:0;border-radius:12px;background:#17212b;max-width:94vw;max-height:94dvh;color:#fff}
.message-lightbox::backdrop{background:rgba(0,0,0,.85)}.message-lightbox img{display:block;max-width:85vw;max-height:80dvh;object-fit:contain}.message-lightbox button{position:absolute;right:12px;top:4px;font-size:28px;cursor:pointer}
@media(max-width:760px){.message-workspace{height:var(--chat-available-height,calc(100dvh - 112px));min-height:0}.message-photo-group,.message-text-bubble{width:88%;max-width:88%}.message-bubble.has-photos{max-width:min(420px,88%)}.message-photo-gallery{width:100%;max-width:100%}.message-photo-gallery.single{width:min(320px,100%)}.message-photo-gallery.single .message-photo img{max-height:360px}.message-photo img{max-width:100%;height:150px}}
</style>
<section data-message-shell class="message-workspace {{ $workspaceClass }}"
    @if($activeConversation)
        data-chat-workspace
        data-current-user="{{ auth()->id() }}"
        data-send-url="{{ $sendUrl }}"
        data-updates-url="{{ $updatesUrl }}"
        data-older-url="{{ $olderUrl }}"
        data-has-more="{{ $hasMoreMessages ? '1' : '0' }}"
    @endif>
    <aside class="message-list-panel" aria-label="Conversations">
        <div class="message-list-heading"><h1>Messages</h1><p>Buyer and shop conversations</p></div>
        <form method="GET" action="{{ $indexUrl }}">
            <input class="message-search" type="search" name="q" value="{{ $search }}" placeholder="Search chats or orders" aria-label="Search conversations">
        </form>
        <nav class="message-conversation-list">
            @forelse($conversations as $conversation)
                @php
                    $listPeer = $sellerView ? $conversation->buyer : $conversation->store;
                    $listName = $listPeer?->name ?? ($sellerView ? 'Buyer' : 'Shop unavailable');
                    $listImage = $sellerView
                        ? $listPeer?->avatar_url
                        : ($listPeer?->logo ? asset('storage/'.$listPeer->logo) : null);
                    $latest = $conversation->latestMessage;
                    $latestPhotoCount = $latest?->attachments?->count() ?? 0;
                    $preview = $latest?->body
                        ? \Illuminate\Support\Str::limit($latest->body, 54)
                        : ($latestPhotoCount > 0
                            ? ($latestPhotoCount === 1 ? 'Photo' : $latestPhotoCount.' photos')
                            : match($latest?->context_type) {
                                'product' => 'Shared a product',
                                'order' => 'Shared an order',
                                default => 'No messages yet',
                            });
                    $conversationUrl = $sellerView
                        ? route('seller.messages.show', $conversation)
                        : route('messages.show', $conversation);
                @endphp
                <a class="message-conversation {{ $activeConversation?->id === $conversation->id ? 'active' : '' }}" href="{{ $conversationUrl }}">
                    <span class="message-avatar">@if($listImage)<img src="{{ $listImage }}" alt="">@else{{ strtoupper(substr($listName,0,1)) }}@endif</span>
                    <span class="message-conversation-copy">
                        <span class="message-conversation-top"><strong>{{ $listName }}</strong>@if($conversation->last_message_at)<time>{{ $conversation->last_message_at->diffForHumans(null,true) }}</time>@endif</span>
                        <span class="message-preview">{{ $preview }}@if($conversation->unread_count)<span class="message-unread">{{ $conversation->unread_count }}</span>@endif</span>
                    </span>
                </a>
            @empty
                <p class="message-empty-list">{{ $sellerView ? 'No messages yet. Buyer conversations will appear here when customers contact your shop.' : 'No conversations yet. Message a Seller from a product or order page to get started.' }}</p>
            @endforelse
        </nav>
        @if($conversations->hasPages())<div class="message-list-pagination">{{ $conversations->links() }}</div>@endif
    </aside>

    <div class="message-thread-panel">
        @if($activeConversation)
            <header class="message-thread-header">
                <a class="message-back" href="{{ $indexUrl }}" aria-label="Back to conversations">‹ Back</a>
                <span class="message-avatar message-peer-avatar">@if($peerImage)<img src="{{ $peerImage }}" alt="">@else{{ strtoupper(substr($peerName,0,1)) }}@endif</span>
                <div><div class="message-peer-name">{{ $peerName }}</div><div class="message-peer-caption">{{ $sellerView ? 'Buyer conversation' : 'SHOPPICK shop' }}</div></div>
            </header>
            <div class="message-thread">
                <div class="message-scroll" data-message-scroll aria-live="polite">
                    @if($hasMoreMessages)<button class="message-load-older" type="button" data-load-older>Load older messages</button>@endif
                    @foreach($messages as $message)
                        @include('messages.message', ['payload' => $messagePayloads[$message->id]])
                    @endforeach
                </div>
                <div class="message-send-error" data-send-error role="status"></div>
                <div class="message-send-error" data-refresh-error role="status"></div>
                <div class="message-photo-preview" data-photo-preview hidden><div class="message-photo-thumbnails" data-photo-thumbnails></div><span class="message-photo-count" data-photo-count></span></div>
                <form class="message-composer" data-message-form enctype="multipart/form-data" method="POST" action="{{ $sendUrl }}">
                    @csrf
                    <input type="hidden" name="client_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" data-photo-input multiple hidden>
                    <button type="button" class="message-photo-button" data-choose-photo aria-label="Add photos" title="Add photos">+</button>
                    <textarea name="body" maxlength="2000" rows="1" placeholder="Write a message…" aria-label="Write a message"></textarea>
                    <button class="message-send" type="submit">Send</button>
                </form>
            </div>
        @else
            <div class="message-chat-empty"><div><span class="message-empty-icon" aria-hidden="true">✉</span><strong>Select a conversation</strong><p>Choose a chat to read and reply to messages. New conversations stay private between you and that shop.</p></div></div>
        @endif
    </div>
</section>
<dialog class="message-lightbox" data-photo-lightbox aria-label="Message photo"><button type="button" data-close-photo aria-label="Close photo">&times;</button><img alt="Message photo enlarged"></dialog>
@push('scripts')
        <script src="{{ $chatScriptUrl }}" defer></script>
@endpush
