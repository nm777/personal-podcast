<?php

namespace App\Services\SourceProcessors;

use App\Services\MediaProcessing\UnifiedDuplicateProcessor;
use App\Services\YouTubeUrlValidator;
use Illuminate\Http\RedirectResponse;

class SourceProcessorFactory
{
    public static function create(string $sourceType): UnifiedSourceProcessor
    {
        $duplicateProcessor = app(UnifiedDuplicateProcessor::class);
        $libraryItemFactory = app(LibraryItemFactory::class);

        $fileUploadProcessor = app(FileUploadProcessor::class, [
            'duplicateProcessor' => $duplicateProcessor,
            'libraryItemFactory' => $libraryItemFactory,
        ]);

        $strategy = match ($sourceType) {
            'upload' => app(UploadStrategy::class),
            'url' => app(UrlStrategy::class),
            'youtube' => app(YouTubeStrategy::class),
            default => throw new \InvalidArgumentException("Unsupported source type: {$sourceType}"),
        };

        $urlSourceProcessor = app(UrlSourceProcessor::class, [
            'libraryItemFactory' => $libraryItemFactory,
            'strategy' => $strategy,
            'duplicateProcessor' => $duplicateProcessor,
        ]);

        return app(UnifiedSourceProcessor::class, [
            'fileUploadProcessor' => $fileUploadProcessor,
            'urlSourceProcessor' => $urlSourceProcessor,
            'strategy' => $strategy,
        ]);
    }

    public static function validate(string $sourceType, ?string $sourceUrl): ?RedirectResponse
    {
        if ($sourceType === 'youtube' && ! YouTubeUrlValidator::isValidYouTubeUrl($sourceUrl)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['source_url' => 'Invalid YouTube URL']);
        }

        return null;
    }
}
