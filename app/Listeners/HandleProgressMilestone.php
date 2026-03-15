<?php

namespace App\Listeners;

use App\Events\ProgressMilestoneReached;
use App\Events\CourseCompleted;
use App\Models\Certificate;
use App\Notifications\CertificateAvailableNotification;
use App\Notifications\ProgressMilestoneNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleProgressMilestone implements ShouldQueue
{
    public function handle(ProgressMilestoneReached $event): void
    {
        // Notify the user about their milestone
        $event->user->notify(
            new ProgressMilestoneNotification(
                $event->enrollment,
                $event->milestone
            )
        );
    }
}
