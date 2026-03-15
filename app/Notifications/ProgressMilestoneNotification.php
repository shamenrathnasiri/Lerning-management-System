<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProgressMilestoneNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Enrollment $enrollment,
        protected int $milestone
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $course = $this->enrollment->course;

        $messages = [
            25 => "Great start! You've completed 25% of \"{$course->title}\". Keep going! 🚀",
            50 => "Halfway there! 50% of \"{$course->title}\" complete. You're doing amazing! 💪",
            75 => "Almost done! 75% of \"{$course->title}\" complete. The finish line is in sight! 🏃",
            100 => "Congratulations! You've completed \"{$course->title}\"! 🎉🏆",
        ];

        $emojis = [25 => '🚀', 50 => '💪', 75 => '🏃', 100 => '🏆'];

        return [
            'type'          => 'progress_milestone',
            'enrollment_id' => $this->enrollment->id,
            'course_id'     => $course->id,
            'course_title'  => $course->title,
            'course_slug'   => $course->slug,
            'milestone'     => $this->milestone,
            'message'       => $messages[$this->milestone] ?? "{$this->milestone}% of \"{$course->title}\" complete!",
            'emoji'         => $emojis[$this->milestone] ?? '📊',
        ];
    }
}
