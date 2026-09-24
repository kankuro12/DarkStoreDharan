<?php

namespace App\Jobs;

use App\Services\Inventory\StockReservationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredReservations implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(StockReservationService $reservationService): void
    {
        $releasedCount = $reservationService->releaseExpired();

        if ($releasedCount > 0) {
            Log::info("Released {$releasedCount} expired stock reservation(s) back to available inventory.");
        }
    }
}
