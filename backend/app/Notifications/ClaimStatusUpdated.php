<?php

namespace App\Notifications;

use App\Models\Claim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClaimStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Claim $claim) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the array representation of the notification (database channel).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'claim_id' => $this->claim->id,
            'status' => $this->claim->status,
            'decision_reason' => $this->claim->decision_reason,
            'report_uuid' => $this->claim->report?->uuid,
            'report_title' => $this->claim->report?->title,
            'summary' => sprintf(
                'Your claim for "%s" was %s.',
                $this->claim->report?->title ?? 'a report',
                $this->claim->status,
            ),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Update on your FindBack claim')
            ->greeting('Hello!')
            ->line('Your claim status is now: '.$this->claim->status.'.')
            ->line('Report: "'.($this->claim->report?->title ?? 'a report').'"');

        if ($this->claim->decision_reason) {
            $mail->line('Reason: '.$this->claim->decision_reason);
        }

        return $mail
            ->action('View on FindBack', url('/'))
            ->line('Thank you for using FindBack!');
    }
}
