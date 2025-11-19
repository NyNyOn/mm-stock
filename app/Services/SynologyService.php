<?php

namespace App\Services;

use Illuminate\Notifications\Notifiable;

class SynologyService
{
    use Notifiable;

    /**
     * Synology Chat notifications are not routed to a specific user,
     * but to a channel defined by the webhook URL.
     *
     * @return string|null
     */
    public function routeNotificationForSynology()
    {
        return config('services.synology.chat_webhook_url');
    }
}
