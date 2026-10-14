<?php

namespace App\Notifications;

use App\Models\ItemMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MatchFound extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public ItemMatch $match) {}

    /**
     * Get the delivery channels for the notification.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification (database channel).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $other = $this->match->lostReport?->type === 'lost'
            ? $this->match->foundReport
            : $this->match->lostReport;

        return [
            'match_id' => $this->match->id,
            'score' => $this->match->score,
            'reasons' => $this->match->reasons,
            'report_title' => $other?->title,
            'message' => 'A potential match was found for one of your reports.',
        ];
    }
}
