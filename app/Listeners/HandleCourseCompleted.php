<?php

namespace App\Listeners;

use App\Events\CourseCompleted;
use App\Services\CertificateService;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleCourseCompleted implements ShouldQueue
{
    public function __construct(
        protected CertificateService $certificateService
    ) {}

    public function handle(CourseCompleted $event): void
    {
        $this->certificateService->generate(
            $event->user,
            $event->enrollment->course,
        );
    }
}
