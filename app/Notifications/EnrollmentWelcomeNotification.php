<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnrollmentWelcomeNotification extends Notification implements ShouldQueue
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
        $enrollmentType = $this->enrollment->enrollment_type_display;

        $mail = (new MailMessage)
            ->subject("🎉 Welcome to \"{$course->title}\"!")
            ->greeting("Hello {$notifiable->name}!")
            ->line("You have been successfully enrolled in **{$course->title}**.")
            ->line("**Enrollment Type:** {$enrollmentType}")
            ->line("**Enrolled On:** " . $this->enrollment->enrolled_at->format('F j, Y'));

        // Add course details
        if ($course->level) {
            $mail->line("**Level:** " . ucfirst($course->level));
        }

        if ($course->formatted_duration) {
            $mail->line("**Duration:** {$course->formatted_duration}");
        }

        if ($this->enrollment->expires_at) {
            $mail->line("**Access Until:** " . $this->enrollment->expires_at->format('F j, Y'));
        }

        // Add specific info based on enrollment type
        if ($this->enrollment->enrollment_type === 'group') {
            $mail->line('You are part of a group enrollment. Learn alongside your team!');
        }

        if ($this->enrollment->amount_paid > 0) {
            $mail->line("**Amount Paid:** $" . number_format($this->enrollment->amount_paid, 2));
        }

        $mail->line('Here\'s what you can do next:')
             ->line('1. 📚 Browse the course curriculum')
             ->line('2. ▶️ Start with the first lesson')
             ->line('3. 💬 Join course discussions')
             ->action('Start Learning', url('/courses/' . $course->slug))
             ->line('Happy Learning! 🚀');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'enrollment_welcome',
            'enrollment_id'   => $this->enrollment->id,
            'course_id'       => $this->enrollment->course_id,
            'course_title'    => $this->enrollment->course->title,
            'course_slug'     => $this->enrollment->course->slug,
            'enrollment_type' => $this->enrollment->enrollment_type,
            'message'         => "Welcome to \"{$this->enrollment->course->title}\"! Start learning now.",
        ];
    }
}
