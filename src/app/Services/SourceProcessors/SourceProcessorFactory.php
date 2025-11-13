<?php

namespace App\Services\SourceProcessors;

use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use App\Services\YouTubeUrlValidator;

class SourceProcessorFactory
{
    public static function create(string $sourceType): UnifiedSourceProcessor
    {
        $duplicateProcessor = app(UnifiedDuplicateProcessor::class);
        $libraryItemFactory = app(LibraryItemFactory::class);

        $fileUploadProcessor = new FileUploadProcessor($duplicateProcessor, $libraryItemFactory);

        $strategy = match ($sourceType) {
            'upload' => new UploadStrategy,
            'url' => new UrlStrategy,
            'youtube' => new YouTubeStrategy,
            default => throw new \InvalidArgumentException("Unsupported source type: {$sourceType}"),
        };

        $urlSourceProcessor = new UrlSourceProcessor($libraryItemFactory, $strategy);

        return new UnifiedSourceProcessor($fileUploadProcessor, $urlSourceProcessor, $strategy);
    }

    public static function validate(string $sourceType, ?string $sourceUrl): ?\Illuminate\Http\RedirectResponse
    {
        if ($sourceType === 'youtube' && ! YouTubeUrlValidator::isValidYouTubeUrl($sourceUrl)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['source_url' => 'Invalid YouTube URL']);
        }

        return null;
    }
}
