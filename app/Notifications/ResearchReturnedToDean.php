<?php

namespace App\Notifications;

use App\Models\Research;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResearchReturnedToDean extends Notification
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
                                  . $this->research->reference_number
                                  . ' has been returned by OVPRI '
                                  . 'for your review and action.',
            'action_url'       => route('approval.review',
                                    $this->research),
            'type'             => 'returned_to_dean',
        ];
    }
}
