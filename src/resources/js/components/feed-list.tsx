import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useToast } from '@/hooks/use-toast';
import { getAbsoluteRssUrl, getApplePodcastsUrl, getGooglePodcastsUrl } from '@/lib/subscribe-urls';
import { type Feed } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Copy, Edit, Eye, EyeOff, FileAudio, Podcast, Rss, Smartphone, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface FeedListProps {
    feeds: Feed[];
    canEdit?: boolean;
}

export default function FeedList({ feeds, canEdit = true }: FeedListProps) {
    const { toast } = useToast();
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [feedToDelete, setFeedToDelete] = useState<number | null>(null);

    const handleDeleteClick = (feedId: number) => {
        setFeedToDelete(feedId);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (feedToDelete) {
            router.delete(route('feeds.destroy', feedToDelete), {
                onSuccess: () => {
                    setDeleteDialogOpen(false);
                    setFeedToDelete(null);
                },
                onError: () => {
                    toast({
                        title: 'Error',
                        description: 'Failed to delete feed. Please try again.',
                        variant: 'destructive',
                    });
                    setDeleteDialogOpen(false);
                    setFeedToDelete(null);
                },
            });
        }
    };

    const handleCopyUrl = async (feed: Feed) => {
        const fullUrl = getAbsoluteRssUrl(feed);

        const fallbackCopyTextToClipboard = (text: string) => {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                toast({
                    title: 'URL copied!',
                    description: 'Feed URL has been copied to your clipboard.',
                });
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
                toast({
                    title: 'Failed to copy',
                    description: 'Could not copy the URL to clipboard.',
                    variant: 'destructive',
                });
            }

            document.body.removeChild(textArea);
        };

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(fullUrl);
                toast({
                    title: 'URL copied!',
                    description: 'Feed URL has been copied to your clipboard.',
                });
            } else {
                fallbackCopyTextToClipboard(fullUrl);
            }
        } catch (err) {
            console.error('Failed to copy URL:', err);
            fallbackCopyTextToClipboard(fullUrl);
        }
    };

    if (feeds.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center py-8">
                    <Rss className="mb-4 h-12 w-12 text-muted-foreground" />
                    <h3 className="mb-2 text-lg font-semibold">No feeds yet</h3>
                    <p className="text-center text-muted-foreground">Create your first feed to get started with your podcast.</p>
                </CardContent>
            </Card>
        );
    }

    return (
        <>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {feeds.map((feed) => (
                    <Card key={feed.id} className="min-w-0">
                        <CardHeader className="pb-3">
                            <div className="flex items-start justify-between">
                                <div className="min-w-0 flex-1">
                                    <CardTitle className="truncate text-lg">{feed.title}</CardTitle>
                                    <CardDescription className="mt-1 line-clamp-2">{feed.description || 'No description provided'}</CardDescription>
                                </div>
                                <div className="ml-2 flex flex-shrink-0 items-center gap-2">
                                    <Badge variant={feed.is_public ? 'default' : 'secondary'}>
                                        {feed.is_public ? (
                                            <>
                                                <Eye className="mr-1 h-3 w-3" />
                                                Public
                                            </>
                                        ) : (
                                            <>
                                                <EyeOff className="mr-1 h-3 w-3" />
                                                Private
                                            </>
                                        )}
                                    </Badge>
                                    <Badge variant="outline">
                                        <FileAudio className="mr-1 h-3 w-3" />
                                        {feed.items_count}
                                    </Badge>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button variant="outline" size="sm" asChild>
                                            <a href={getApplePodcastsUrl(feed)} target="_blank" rel="noopener noreferrer">
                                                <Podcast className="h-4 w-4" />
                                            </a>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Apple Podcasts</TooltipContent>
                                </Tooltip>

                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button variant="outline" size="sm" asChild>
                                            <a href={getGooglePodcastsUrl(feed)} target="_blank" rel="noopener noreferrer">
                                                <Smartphone className="h-4 w-4" />
                                            </a>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Google Podcasts / Android</TooltipContent>
                                </Tooltip>

                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button variant="outline" size="sm" onClick={() => handleCopyUrl(feed)}>
                                            <Copy className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Copy RSS URL</TooltipContent>
                                </Tooltip>

                                {canEdit && (
                                    <>
                                        <div className="mx-1 h-4 w-px bg-border" />
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={route('feeds.edit', feed.id)}>
                                                        <Edit className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Edit Feed</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button variant="destructive" size="sm" onClick={() => handleDeleteClick(feed.id)}>
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Delete Feed</TooltipContent>
                                        </Tooltip>
                                    </>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <DeleteConfirmDialog
                isOpen={deleteDialogOpen}
                onClose={() => {
                    setDeleteDialogOpen(false);
                    setFeedToDelete(null);
                }}
                onConfirm={handleDeleteConfirm}
                title="Delete Feed"
                description="Are you sure you want to delete this feed? This action cannot be undone."
                confirmText="Delete Feed"
                variant="destructive"
            />
        </>
    );
}
