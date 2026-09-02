<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $notifications = auth()->user()->notificationsData()->latest()->paginate(15);
        return view('storefront.notifications.index', compact('notifications'));
    }

    public function markAllRead()
    {
        auth()->user()->notificationsData()->unread()->update(['read_at' => now()]);
        return back();
    }

    public function markRead(Request $request, $id)
    {
        $notification = auth()->user()->notificationsData()->findOrFail($id);
        $notification->markAsRead();
        return back();
    }

    public function open(Request $request, $id)
    {
        $notification = $request->user()->notificationsData()->findOrFail($id);
        $notification->markAsRead();
        return redirect($notification->link ?: route('notifications.index'));
    }
}
