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
 * Extracts a representative frame from the uploaded video using ffmpeg
 * and uploads the thumbnail to S3. Falls back gracefully when ffmpeg is absent.
 */
class GenerateVideoThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300;

    public function __construct(
        public readonly Lesson $lesson,
        public readonly string $s3Key,
    ) {}

    public function handle(): void
    {
        if (! $this->ffmpegAvailable()) {
            Log::info("[GenerateVideoThumbnail] ffmpeg not found — skipping for lesson {$this->lesson->id}");
            return;
        }

        $record       = ProcessingRecord::where('lesson_id', $this->lesson->id)->latest()->first();
        $localVideo   = tempnam(sys_get_temp_dir(), 'lms_vid_');
        $localThumb   = tempnam(sys_get_temp_dir(), 'lms_thumb_') . '.jpg';

        try {
            file_put_contents($localVideo, Storage::disk('s3')->get($this->s3Key));

            $cmd = sprintf(
                'ffmpeg -i %s -ss 00:00:05 -vframes 1 -q:v 2 %s 2>&1',
                escapeshellarg($localVideo),
                escapeshellarg($localThumb)
            );
            exec($cmd, $output, $exitCode);

            if ($exitCode === 0 && file_exists($localThumb) && filesize($localThumb) > 0) {
                $thumbKey = 'lessons/thumbnails/' . $this->lesson->id . '/thumb.jpg';
                    /** @var \Illuminate\Contracts\Filesystem\Cloud $s3 */
                    $s3 = Storage::disk('s3');
                    $s3->put($thumbKey, file_get_contents($localThumb), 'public');

                    $thumbUrl = $s3->url($thumbKey);
                $this->lesson->update(['thumbnail_path' => $thumbUrl]);

                    $thumbnails = is_array($record?->thumbnails) ? $record->thumbnails : [];
                    $thumbnails['default'] = $thumbKey;
                    $record?->update(['thumbnails' => $thumbnails]);
            }
        } catch (\Throwable $e) {
            Log::error("[GenerateVideoThumbnail] lesson {$this->lesson->id}: {$e->getMessage()}");
        } finally {
            @unlink($localVideo);
            @unlink($localThumb);
        }
    }

    private function ffmpegAvailable(): bool
    {
        exec('ffmpeg -version 2>&1', $out, $code);
        return $code === 0;
    }
}
