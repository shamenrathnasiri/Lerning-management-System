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
 * Uploads a temporarily stored video to S3, then dispatches
 * thumbnail generation, transcoding, and optional watermarking jobs.
 */
class ProcessVideoUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 3600; // 1 hour

    public function __construct(
        public readonly Lesson $lesson,
        public readonly string $tempPath,        // local disk relative path
        public readonly string $originalFilename,
        public readonly bool   $applyWatermark = false,
    ) {}

    public function handle(): void
    {
        $record = ProcessingRecord::updateOrCreate(
            ['lesson_id' => $this->lesson->id],
            [
                'original_filename' => $this->originalFilename,
                'original_size'     => Storage::disk('local')->exists($this->tempPath)
                                           ? Storage::disk('local')->size($this->tempPath)
                                           : null,
                'status' => ProcessingRecord::STATUS_PROCESSING,
                'started_at' => now(),
            ]
        );

        try {
            $s3Key  = 'lessons/videos/' . $this->lesson->id . '/' . $this->originalFilename;
            $stream = Storage::disk('local')->readStream($this->tempPath);

            Storage::disk('s3')->writeStream($s3Key, $stream, ['visibility' => 'private']);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $record->update(['s3_key' => $s3Key]);
            $record->markAsQueued();

            $this->lesson->update([
                's3_key'             => $s3Key,
                'processing_status'  => 'processing',
            ]);

            Storage::disk('local')->delete($this->tempPath);

            // Dispatch follow-up jobs on the dedicated queue
            GenerateVideoThumbnail::dispatch($this->lesson, $s3Key)
                ->onQueue('video-processing');

            TranscodeVideo::dispatch($this->lesson, $s3Key, ['360p', '720p', '1080p'])
                ->onQueue('video-processing');

            if ($this->applyWatermark) {
                AddVideoWatermark::dispatch($this->lesson, $s3Key)
                    ->onQueue('video-processing');
            }
        } catch (\Throwable $e) {
            $record->markAsFailed($e->getMessage());
            $this->lesson->update(['processing_status' => 'failed']);
            Log::error("[ProcessVideoUpload] lesson {$this->lesson->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        ProcessingRecord::where('lesson_id', $this->lesson->id)
            ->whereNotIn('status', [ProcessingRecord::STATUS_COMPLETED])
            ->update([
                'status'        => ProcessingRecord::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

        $this->lesson->update(['processing_status' => 'failed']);
    }
}
