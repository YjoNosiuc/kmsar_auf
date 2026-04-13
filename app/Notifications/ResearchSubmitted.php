<?php

namespace App\Notifications;

use App\Models\Research;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResearchSubmitted extends Notification
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
            'message'          => 'A new research '
                                  . $this->research->reference_number
                                  . ' has been submitted for your review by '
                                  . ($this->research->primaryAuthor?->first_name
                                     ?? $this->research->primaryAuthor?->name
                                     ?? 'a faculty member')
                                  . '.',
            'action_url'       => route('approval.review',
                                    $this->research),
            'type'             => 'submitted',
        ];
    }
}
