<?php

namespace App\Console\Commands;

use App\Services\EnrollmentService;
use Illuminate\Console\Command;

class ProcessEnrollments extends Command
{
    protected $signature = 'enrollments:process
                            {--expire : Expire enrollments past their expiration date}
                            {--remind : Send reminders to inactive students}
                            {--inactive-days=14 : Number of days of inactivity before sending a reminder}
                            {--all : Run all enrollment processing tasks}';

    protected $description = 'Process enrollments: expire stale enrollments and send reminders to inactive students.';

    public function __construct(
        protected EnrollmentService $enrollmentService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $runAll = $this->option('all');

        if ($runAll || $this->option('expire')) {
            $this->processExpirations();
        }

        if ($runAll || $this->option('remind')) {
            $this->sendReminders();
        }

        if (!$runAll && !$this->option('expire') && !$this->option('remind')) {
            $this->error('Please specify an action: --expire, --remind, or --all');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function processExpirations(): void
    {
        $this->info('Processing expired enrollments...');

        $count = $this->enrollmentService->expireEnrollments();

        $this->info("  ✓ {$count} enrollment(s) marked as expired.");
    }

    protected function sendReminders(): void
    {
        $inactiveDays = (int) $this->option('inactive-days');

        $this->info("Sending reminders to students inactive for {$inactiveDays}+ days...");

        $count = $this->enrollmentService->sendInactivityReminders($inactiveDays);

        $this->info("  ✓ {$count} reminder(s) sent.");
    }
}
