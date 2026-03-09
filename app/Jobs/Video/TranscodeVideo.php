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
 * Transcodes the uploaded video to multiple quality levels using ffmpeg.
 * Stores transcoded versions on S3 and updates the lesson's video_quality_urls.
 * Falls back gracefully to the original when ffmpeg is not available.
 */
class TranscodeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 7200; // 2 hours

    /** @var array<string> */
    private const RESOLUTION_MAP = [
        '360p'  => '640x360',
        '720p'  => '1280x720',
        '1080p' => '1920x1080',
    ];

    public function __construct(
        public readonly Lesson $lesson,
        public readonly string $s3Key,
        public readonly array  $qualities = ['360p', '720p'],
    ) {}

    public function handle(): void
    {
        $record      = ProcessingRecord::where('lesson_id', $this->lesson->id)->latest()->first();
        $qualityUrls = [];

        if (! $this->ffmpegAvailable()) {
            // Store signed URL to original as a fallback
            $qualityUrls['original'] = $this->s3Key;
            $record?->markAsCompleted($qualityUrls);
            $this->lesson->update([
                'video_quality_urls' => $qualityUrls,
                'processing_status'  => 'completed',
            ]);
            return;
        }

        $localVideo = tempnam(sys_get_temp_dir(), 'lms_src_');

        try {
            file_put_contents($localVideo, Storage::disk('s3')->get($this->s3Key));

            foreach ($this->qualities as $quality) {
                $resolution = self::RESOLUTION_MAP[$quality] ?? null;
                if (! $resolution) {
                    continue;
                }

                $localOut = tempnam(sys_get_temp_dir(), 'lms_out_') . '.mp4';
                $outKey   = 'lessons/videos/' . $this->lesson->id . "/transcoded_{$quality}.mp4";

                $cmd = sprintf(
                    'ffmpeg -i %s -vf scale=%s -c:v libx264 -crf 22 -preset fast -c:a aac -b:a 128k %s 2>&1',
                    escapeshellarg($localVideo),
                    $resolution,
                    escapeshellarg($localOut)
                );
                exec($cmd, $output, $exitCode);

                if ($exitCode === 0 && file_exists($localOut) && filesize($localOut) > 0) {
                    Storage::disk('s3')->put($outKey, file_get_contents($localOut), 'private');
                    $qualityUrls[$quality] = $outKey;
                }

                @unlink($localOut);
            }

            $qualityUrls['original'] = $this->s3Key;

            $record?->markAsCompleted($qualityUrls);
            $this->lesson->update([
                'video_quality_urls' => $qualityUrls,
                'processing_status'  => 'completed',
            ]);
        } catch (\Throwable $e) {
            $record?->markAsFailed($e->getMessage());
            $this->lesson->update(['processing_status' => 'failed']);
            Log::error("[TranscodeVideo] lesson {$this->lesson->id}: {$e->getMessage()}");
            throw $e;
        } finally {
            @unlink($localVideo);
        }
    }

    private function ffmpegAvailable(): bool
    {
        exec('ffmpeg -version 2>&1', $out, $code);
        return $code === 0;
    }
}
