<?php

namespace App\Notifications;

use App\Models\Research;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to OVPRI/CDAIC admins when a dean endorses research (forwarded for final review).
 */
class ResearchPendingOvpriReview extends Notification
{
    use Queueable;

    public function __construct(
        public Research $research
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'research_id'      => $this->research->id,
            'reference_number' => $this->research->reference_number,
            'title'            => $this->research->title,
            'message'          => 'Research '
                .$this->research->reference_number
                .' has been endorsed by the college dean and awaits OVPRI/CDAIC review.',
            'action_url'       => route('ovpri.review', $this->research),
            'type'             => 'ovpri_pending',
        ];
    }
}
