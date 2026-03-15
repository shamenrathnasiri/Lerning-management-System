<?php

namespace App\Listeners;

use App\Events\CourseCompleted;
use App\Models\Certificate;
use App\Notifications\CertificateAvailableNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleCourseCompleted implements ShouldQueue
{
    public function handle(CourseCompleted $event): void
    {
        $enrollment = $event->enrollment;
        $user = $event->user;

        // Auto-generate certificate if course has a template
        $course = $enrollment->course;

        if ($course->certificate_template) {
            $existingCert = Certificate::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            if (!$existingCert) {
                $certificate = Certificate::create([
                    'user_id'   => $user->id,
                    'course_id' => $course->id,
                    'title'     => "Certificate of Completion — {$course->title}",
                    'issued_at' => now(),
                ]);

                // Notify about certificate availability
                $user->notify(new CertificateAvailableNotification($enrollment));
            }
        }
    }
}
