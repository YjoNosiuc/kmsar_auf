<?php

namespace App\Notifications;

use App\Models\Research;

class ResearchProgressUpdated extends QueuedResearchNotification
{
    use SendsResearchNotificationMail;

    public function __construct(
        public Research $research
    ) {}


    public function toArray(object $notifiable): array
    {
        $status = (string) ($this->research->status ?? '');

        return $this->baseResearchPayload($this->research, [
            'message'          => 'Research '
                                  . $this->research->reference_number
                                  . ' progress has been updated to: '
                                  . ucwords(str_replace(
                                      '_', ' ',
                                      $status
                                  ))
                                  . ' by the faculty.',
            'action_url'       => route('approval.review',
                                    $this->research),
            'type'             => 'progress_updated',
        ]);
    }
}
