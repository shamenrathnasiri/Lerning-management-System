<?php

namespace App\Notifications;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnrollmentReminderNotification extends Notification implements ShouldQueue
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
        $progress = $this->enrollment->formatted_progress;
        $daysInactive = $this->enrollment->last_activity_at
            ? $this->enrollment->last_activity_at->diffInDays(now())
            : $this->enrollment->enrolled_at->diffInDays(now());

        $mail = (new MailMessage)
            ->subject("📖 We miss you in \"{$course->title}\"!")
            ->greeting("Hey {$notifiable->name}!")
            ->line("It's been **{$daysInactive} days** since your last activity in **{$course->title}**.")
            ->line("Your current progress is **{$progress}** — don't let it go to waste!");

        if ($this->enrollment->expires_at) {
            $daysLeft = $this->enrollment->days_until_expiry;
            if ($daysLeft !== null && $daysLeft <= 30) {
                $mail->line("⚠️ **Your access expires in {$daysLeft} days!** Make the most of your remaining time.");
            }
        }

        $mail->line('Here are some tips to get back on track:')
             ->line('• Set aside just 15 minutes a day for learning')
             ->line('• Pick up where you left off — no need to start over')
             ->line('• Join the course discussion to stay motivated')
             ->action('Continue Learning', url('/courses/' . $course->slug))
             ->line('Every step counts. Keep going! 💪');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'enrollment_reminder',
            'enrollment_id' => $this->enrollment->id,
            'course_id'     => $this->enrollment->course_id,
            'course_title'  => $this->enrollment->course->title,
            'course_slug'   => $this->enrollment->course->slug,
            'progress'      => $this->enrollment->progress_percentage,
            'message'       => "We miss you in \"{$this->enrollment->course->title}\"! Your progress is at {$this->enrollment->formatted_progress}.",
        ];
    }
}
