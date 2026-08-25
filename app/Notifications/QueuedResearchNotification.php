<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Research lifecycle notifications — always queued so HTTP requests are not blocked by SMTP.
 */
abstract class QueuedResearchNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsResearchNotificationMail;
}
