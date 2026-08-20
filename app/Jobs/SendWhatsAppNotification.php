<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\WhatsAppNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $notificationLogId) {}

    public function handle(WhatsAppNotificationService $whatsApp): void
    {
        $log = NotificationLog::findOrFail($this->notificationLogId);
        $whatsApp->sendNow($log);
    }
}
