<?php

namespace App\Notifications;

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
        return ['database'];
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
