<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class RssController extends Controller
{
    public function show(Request $request, $user_guid, $feed_slug)
    {
        $feed = Feed::where('user_guid', $user_guid)
            ->where('slug', $feed_slug)
            ->with(['items.libraryItem.mediaFile'])
            ->first();

        if (! $feed) {
            abort(404);
        }

        if (! $feed->is_public && $request->token !== $feed->token) {
            abort(404);
        }

        $cacheKey = "rss.{$feed->id}";
        $cacheDuration = config('constants.cache.rss_feed_duration_seconds');

        $xml = Cache::remember($cacheKey, $cacheDuration, function () use ($feed) {
            $rssXml = view('rss', compact('feed'))->render();

            $dom = new \DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            $previousUseErrors = libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($rssXml);
            $xmlErrors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);

            if (! $loaded || count($xmlErrors) > 0) {
                Log::error('RSS feed generated malformed XML', [
                    'feed_id' => $feed->id,
                    'errors' => collect($xmlErrors)->map->message->all(),
                ]);

                return $rssXml;
            }

            return $dom->saveXML();
        });

        return Response::make($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
