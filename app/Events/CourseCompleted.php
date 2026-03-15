<?php

namespace App\Events;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourseCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public Enrollment $enrollment
    ) {}
}
