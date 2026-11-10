<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\User;
use App\Services\OcrTextExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class OcrExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public DocumentVersion $version,
        public FileObject $fileObject,
        public array $options = [],
        public ?User $actor = null
    ) {}

    public function handle(OcrTextExtractionService $ocrService): void
    {
        $ocrService->executeExtraction($this->version, $this->fileObject, $this->options, $this->actor);
    }

    public function failed(?Throwable $exception): void
    {
        // Extraction failure state is handled in executeExtraction
    }
}
