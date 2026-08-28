@extends('layouts.account')

@section('title', 'Notifications')

@section('account-content')
<div class="mb-5 flex items-center justify-between">
    <h1 class="text-xl font-bold text-navy-800">Notifications</h1>
    <form method="POST" action="{{ route('notifications.read-all') }}">
        @csrf
        <button type="submit" class="btn-outline btn-sm">Mark all as read</button>
    </form>
</div>

@if($notifications->isEmpty())
    <div class="card flex flex-col items-center justify-center p-12 text-center text-slate-400">
        <svg class="h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <p class="mt-4 text-sm">No notifications yet.</p>
    </div>
@else
<div class="space-y-3">
    @foreach($notifications as $notification)
        <div class="card p-4 {{ $notification->read_at ? '' : 'border-brand-200 bg-brand-50/40' }}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-navy-800">{{ $notification->title }}</p>
                        @if($notification->body)<p class="mt-0.5 text-sm text-slate-600">{{ $notification->body }}</p>@endif
                        <p class="mt-1 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                @if(!$notification->read_at)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                        @csrf
                        <button type="submit" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Mark read</button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
</div>
<div class="mt-6">{{ $notifications->links('components.pagination') }}</div>
@endif
@endsection
