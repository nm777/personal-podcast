<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $feed->title }}</title>
        <description><![CDATA[{!! $feed->description !!}]]></description>
        <image>
            <url>{{ $feed->cover_image_url ?? asset('logo.svg') }}</url>
            <title>{{ $feed->title }}</title>
            <link>{{ route('rss.show', ['user_guid' => $feed->user_guid, 'feed_slug' =>
            $feed->slug]) }}</link>
        </image>
        <language>en-us</language>
        <atom:link
            href="{{ route('rss.show', ['user_guid' => $feed->user_guid, 'feed_slug' => $feed->slug]) }}"
            rel="self" type="application/rss+xml" />
        @foreach ($feed->items as $item)
            @if($item->libraryItem->mediaFile)
        <item>
            <title>{{ $item->libraryItem->title }}</title>
            <description><![CDATA[{!! $item->libraryItem->description !!}]]></description>
            <pubDate>{{ $item->created_at->toRfc822String() }}</pubDate>
            <guid isPermaLink="false">{{ $item->id }}</guid>
            <enclosure url="{{ $item->libraryItem->mediaFile->rss_url }}{{ $feed->is_public ? '' : '?feed_token=' . $feed->token }}"
                length="{{ $item->libraryItem->mediaFile->filesize }}"
                type="{{ $item->libraryItem->mediaFile->mime_type }}" />
        </item>
            @endif
        @endforeach
    </channel>
</rss>
