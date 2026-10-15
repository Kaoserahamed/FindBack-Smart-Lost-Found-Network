<?php

namespace App\Notifications;

use App\Models\ItemMatch;
use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PotentialMatchFound extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public ItemMatch $match) {}

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
     * The recipient owns ONE side of the match, so the report shown to them
     * must be the *other* side - previously this always returned the found
     * report, meaning the owner of the found report was notified about their
     * own report rather than the lost one.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $report = $this->counterpartReport($notifiable);

        return [
            'match_id' => $this->match->id,
            'score' => $this->match->score,
            'reasons' => $this->match->reasons,
            'report_uuid' => $report?->uuid,
            'report_title' => $report?->title,
            'summary' => sprintf(
                'A potential match (score %d/100) was found for "%s".',
                $this->match->score,
                $report?->title ?? 'a report',
            ),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $report = $this->counterpartReport($notifiable);

        return (new MailMessage)
            ->subject('Potential match found on FindBack')
            ->greeting('Hello!')
            ->line('A potential match was found for one of your reports.')
            ->line('Match score: '.$this->match->score.'/100')
            ->when($report, fn (MailMessage $mail) => $mail->line('Related report: "'.$report->title.'"'))
            ->action('View on FindBack', url('/'))
            ->line('Thank you for using FindBack!');
    }

    /**
     * The report on the opposite side of this match from the recipient.
     */
    protected function counterpartReport(object $notifiable): ?Report
    {
        // If the recipient owns the lost report, the interesting report is the
        // found one, and vice versa.
        if ($this->match->lostReport?->user_id === $notifiable->getKey()) {
            return $this->match->foundReport;
        }

        if ($this->match->foundReport?->user_id === $notifiable->getKey()) {
            return $this->match->lostReport;
        }

        // Fallback (e.g. a moderator-style recipient): show the found side.
        return $this->match->foundReport ?? $this->match->lostReport;
    }
}
