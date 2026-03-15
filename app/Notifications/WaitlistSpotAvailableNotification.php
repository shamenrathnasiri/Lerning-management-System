<?php

namespace App\Notifications;

use App\Models\EnrollmentWaitlist;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistSpotAvailableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected EnrollmentWaitlist $waitlistEntry
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->waitlistEntry->course;
        $expiryHours = $this->waitlistEntry->expires_at
            ? now()->diffInHours($this->waitlistEntry->expires_at)
            : 48;

        return (new MailMessage)
            ->subject("🎯 A spot opened up in \"{$course->title}\"!")
            ->greeting("Great news, {$notifiable->name}!")
            ->line("A spot has opened up in **{$course->title}** — and you're next on the waitlist!")
            ->line("⏰ **You have {$expiryHours} hours to claim your spot** before it's offered to the next person.")
            ->action('Enroll Now', url('/courses/' . $course->slug . '?action=enroll'))
            ->line('Don\'t miss out on this opportunity!')
            ->salutation('Happy Learning!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'waitlist_spot_available',
            'waitlist_id'  => $this->waitlistEntry->id,
            'course_id'    => $this->waitlistEntry->course_id,
            'course_title' => $this->waitlistEntry->course->title,
            'course_slug'  => $this->waitlistEntry->course->slug,
            'expires_at'   => $this->waitlistEntry->expires_at?->toISOString(),
            'message'      => "A spot opened up in \"{$this->waitlistEntry->course->title}\"! Enroll now before it's gone.",
        ];
    }
}
