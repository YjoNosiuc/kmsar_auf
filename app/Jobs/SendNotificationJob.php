<?php

namespace App\Jobs;

use App\Models\Research;
use App\Notifications\ResearchResubmitted;
use App\Support\ResearchDeanRouting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue-backed notifications (KMSAR §12). Dispatched only after DB transactions commit.
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $researchId,
        public string $notificationKey
    ) {}

    public function handle(): void
    {
        $research = Research::query()
            ->with(['primaryAuthor', 'researchAuthors', 'motherCollege'])
            ->find($this->researchId);

        if ($research === null) {
            return;
        }

        match ($this->notificationKey) {
            'resubmitted' => $this->notifyDeansOfResubmit($research),
            default => null,
        };
    }

    private function notifyDeansOfResubmit(Research $research): void
    {
        foreach (ResearchDeanRouting::deanUsersFor($research) as $dean) {
            $dean->notify(new ResearchResubmitted($research));
        }
    }
}
