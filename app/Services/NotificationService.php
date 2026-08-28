<?php

namespace App\Services;

use App\Models\NotificationModel;

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
}
