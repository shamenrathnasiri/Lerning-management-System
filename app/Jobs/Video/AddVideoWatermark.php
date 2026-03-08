<?php

namespace App\Jobs\Video;

use App\Models\Lesson;
use App\Models\VideoProcessingJob as ProcessingRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Overlays a semi-transparent text watermark on the video using ffmpeg's
 * drawtext filter. Uploads the result to S3 and updates the lesson record.
 */
class AddVideoWatermark implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 7200;

    public function __construct(
        public readonly Lesson $lesson,
        public readonly string $s3Key,
        public readonly string $watermarkText = '',
    ) {}

    public function handle(): void
    {
        if (! $this->ffmpegAvailable()) {
            Log::warning("[AddVideoWatermark] ffmpeg not found — skipping for lesson {$this->lesson->id}");
            return;
        }

        $record     = ProcessingRecord::where('lesson_id', $this->lesson->id)->latest()->first();
        $localVideo = tempnam(sys_get_temp_dir(), 'lms_wm_src_');
        $localOut   = tempnam(sys_get_temp_dir(), 'lms_wm_out_') . '.mp4';

        try {
            file_put_contents($localVideo, Storage::disk('s3')->get($this->s3Key));

            // Sanitise watermark text (no shell injection via escapeshellarg on the whole filter value)
            $text   = preg_replace('/[^a-zA-Z0-9 \-_.]/', '', $this->watermarkText ?: config('app.name', 'LMS'));
            $filter = "drawtext=text='{$text}':fontsize=24:fontcolor=white@0.5:x=w-tw-10:y=h-th-10";

            $cmd = sprintf(
                'ffmpeg -i %s -vf %s -codec:a copy %s 2>&1',
                escapeshellarg($localVideo),
                escapeshellarg($filter),
                escapeshellarg($localOut)
            );
            exec($cmd, $output, $exitCode);

            if ($exitCode === 0 && file_exists($localOut) && filesize($localOut) > 0) {
                $watermarkedKey = 'lessons/videos/' . $this->lesson->id . '/watermarked.mp4';
                Storage::disk('s3')->put($watermarkedKey, file_get_contents($localOut), 'private');

                $record?->update(['watermark_applied' => true]);
                $this->lesson->update([
                    'video_watermark' => true,
                    's3_key'          => $watermarkedKey,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("[AddVideoWatermark] lesson {$this->lesson->id}: {$e->getMessage()}");
        } finally {
            @unlink($localVideo);
            @unlink($localOut);
        }
    }

    private function ffmpegAvailable(): bool
    {
        exec('ffmpeg -version 2>&1', $out, $code);
        return $code === 0;
    }
}
