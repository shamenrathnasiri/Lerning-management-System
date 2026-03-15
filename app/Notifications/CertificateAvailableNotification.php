<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateAvailableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Enrollment $enrollment
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->enrollment->course;

        return (new MailMessage)
            ->subject("🏆 Your Certificate for \"{$course->title}\" is Ready!")
            ->greeting("Congratulations {$notifiable->name}! 🎉")
            ->line("You have successfully completed **{$course->title}**!")
            ->line("Your certificate of completion is now available for download.")
            ->line('**Course Details:**')
            ->line("• **Course:** {$course->title}")
            ->line("• **Completed On:** " . ($this->enrollment->completed_at?->format('F j, Y') ?? 'N/A'))
            ->line("• **Enrolled On:** " . $this->enrollment->enrolled_at->format('F j, Y'))
            ->action('Download Certificate', url('/certificates'))
            ->line('Share your achievement on LinkedIn and social media!')
            ->line('Keep up the great work and explore more courses to continue your learning journey. 🚀');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'certificate_available',
            'enrollment_id' => $this->enrollment->id,
            'course_id'     => $this->enrollment->course_id,
            'course_title'  => $this->enrollment->course->title,
            'course_slug'   => $this->enrollment->course->slug,
            'completed_at'  => $this->enrollment->completed_at?->toISOString(),
            'message'       => "Your certificate for \"{$this->enrollment->course->title}\" is ready! Download it now.",
        ];
    }
}
