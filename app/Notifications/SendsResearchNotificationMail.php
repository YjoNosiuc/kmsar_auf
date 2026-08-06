<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Shared mail + database channels for research lifecycle notifications.
 */
trait SendsResearchNotificationMail
{
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);
        $name = $notifiable->name ?? $notifiable->first_name ?? 'User';

        $mail = (new MailMessage)
            ->subject($this->mailSubject($data))
            ->greeting('Hello '.$name.',')
            ->line($data['message'] ?? 'You have a new notification in KMSAR.');

        if (! empty($data['action_url'])) {
            $mail->action(__('Open in KMSAR'), $data['action_url']);
        }

        return $mail->line(__('This is an automated message from KMSAR — Angeles University Foundation.'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mailSubject(array $data): string
    {
        $ref = (string) ($data['reference_number'] ?? '');
        $prefix = $ref !== '' ? 'KMSAR ['.$ref.']' : 'KMSAR';

        return match ($data['type'] ?? '') {
            'submitted' => $prefix.' — New research submitted for review',
            'submission_confirmed' => $prefix.' — Submission confirmed',
            'endorsed' => $prefix.' — Endorsed by college dean',
            'endorsed_to_ovpri', 'ovpri_pending' => $prefix.' — Awaiting OVPRI review',
            'approved' => $prefix.' — Research approved',
            'rejected' => $prefix.' — Research rejected',
            'returned' => $prefix.' — Returned for revision',
            'returned_to_dean' => $prefix.' — Returned to college for review',
            'progress_updated' => $prefix.' — Progress update submitted',
            default => $prefix.' — Notification',
        };
    }
}
