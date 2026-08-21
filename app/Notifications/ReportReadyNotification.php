<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportReadyNotification extends Notification
{
    public function __construct(
        public string $token,
        public string $reportType,
        public string $format
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toDatabase($notifiable);

        return (new MailMessage)
            ->subject(__('KMSAR — Report ready'))
            ->view('emails.notification', [
                'recipientName' => $notifiable->name ?? $notifiable->first_name ?? 'User',
                'bodyText' => $data['message'],
                'referenceNumber' => null,
                'researchTitle' => null,
                'remarks' => null,
                'actionUrl' => $data['url'],
                'actionLabel' => __('Download report'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Report ready'),
            'message' => __('Your :type export (:fmt) is ready to download.', [
                'type' => str_replace('_', ' ', $this->reportType),
                'fmt' => strtoupper($this->format),
            ]),
            'url' => route('reports.download', ['token' => $this->token]),
            'report_type' => $this->reportType,
            'format' => $this->format,
        ];
    }
}
