<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Research lifecycle notifications — sent synchronously so in-app bell and mail
 * work without a queue worker. (Mail still uses SMTP during the HTTP request.)
 */
abstract class QueuedResearchNotification extends Notification
{
    use SendsResearchNotificationMail;
}
