<?php
namespace App\Http\Controllers\Seller;
use App\Http\Controllers\Controller;
use App\Models\NotificationModel;
class NotificationController extends Controller {
 public function index(){ $notifications=auth()->user()->notificationsData()->latest()->paginate(20); return view('seller.notifications.index',compact('notifications')); }
 public function read(NotificationModel $notification){ abort_unless($notification->user_id===auth()->id(),403); $notification->update(['read_at'=>now()]); return back(); }
 public function readAll(){ auth()->user()->notificationsData()->whereNull('read_at')->update(['read_at'=>now()]); return back()->with('success','All notifications marked as read.'); }
}
