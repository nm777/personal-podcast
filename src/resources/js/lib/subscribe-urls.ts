import { type Feed } from '@/types';

export function getFullRssUrl(feed: Feed): string {
    const baseUrl = `/rss/${feed.user_guid}/${feed.slug}`;
    if (!feed.is_public && feed.token) {
        return `${baseUrl}?token=${feed.token}`;
    }
    return baseUrl;
}

export function getAbsoluteRssUrl(feed: Feed): string {
    return window.location.origin + getFullRssUrl(feed);
}

export function getApplePodcastsUrl(feed: Feed): string {
    const fullUrl = getAbsoluteRssUrl(feed);
    return `podcast://${fullUrl.replace('https://', '').replace('http://', '')}`;
}

export function getGooglePodcastsUrl(feed: Feed): string {
    const fullUrl = getAbsoluteRssUrl(feed);
    return `https://podcasts.google.com/subscribe?url=${encodeURIComponent(fullUrl)}`;
}
