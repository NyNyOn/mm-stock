<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use App\Notifications\EquipmentRequested; // ✅ 1. เพิ่ม use statement นี้

class SynologyChannel
{
    /**
     * Send the given notification.
     * ✅ 2. เพิ่ม DocBlock ตรงนี้
     * @param  \Illuminate\Notifications\Notifiable  $notifiable
     * @param  \App\Notifications\EquipmentRequested  $notification
     * @return void
     */
    public function send(object $notifiable, Notification $notification): void
    {
        // ตอนนี้เส้นสีแดงจะหายไปแล้ว
        $notification->toSynology($notifiable);
    }
}
