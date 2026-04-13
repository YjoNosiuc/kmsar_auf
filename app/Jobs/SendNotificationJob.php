<?php

namespace App\Jobs;

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
        // Resolve recipients + mail / database notifications per notificationKey (KMSAR §12 table).
    }
}
