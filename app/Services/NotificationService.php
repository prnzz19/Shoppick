<?php

namespace App\Services;

use App\Models\NotificationModel;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public static function send($userId, string $title, string $body = null, string $type = 'general', string $link = null, $data = null, string $icon = null): NotificationModel
    {
        return NotificationModel::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'icon' => $icon ?: 'bell',
            'link' => $link,
            'data' => $data,
        ]);
    }

    public static function notifyOrder($user, $title, $body, $orderNumber, $linkPrefix = '') : void
    {
        static::send(
            $user->id,
            $title,
            $body,
            'order',
            $linkPrefix . route('orders.show', $orderNumber, false),
            ['order_number' => $orderNumber],
            'package'
        );
    }

    public static function notifyPromo($user, $title, $body, $voucherCode = null): void
    {
        static::send($user->id, $title, $body, 'promotion', route('home', false) . '#deals', ['code' => $voucherCode], 'tag');
    }

    public static function registrationDecision(User $user, string $title, string $body, string $link, array $data=[]): void
    {
        static::send($user->id,$title,$body,'registration',$link,$data,'user');
        $recipientName = $user->first_name ?: $user->name;
        $emailBody = "Hello {$recipientName},\n\n{$body}\n\nSHOPPICK";
        try { Mail::raw($emailBody,fn($message)=>$message->to($user->email,$user->name)->subject($title)); }
        catch (\Throwable $exception) { report($exception); }
    }
}
