import CreateFeedForm from '@/components/create-feed-form';
import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import MediaPlayer from '@/components/media-player';
import MediaUploadButton from '@/components/media-upload-button';
import SheetPanel from '@/components/sheet-panel';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useIsMobile } from '@/hooks/use-mobile';
import AppLayout from '@/layouts/app-layout';
import { formatDuration, formatFileSize } from '@/lib/format';
import { ProcessingStatusHelper } from '@/lib/processing-status';
import { getAbsoluteRssUrl, getApplePodcastsUrl, getGooglePodcastsUrl } from '@/lib/subscribe-urls';
import { type Feed, type LibraryItem } from '@/types';
import { useToast } from '@/hooks/use-toast';
import { Head, Link, router, usePage, useForm } from '@inertiajs/react';
import { AlertCircle, Copy, Edit, Eye, EyeOff, FileAudio, FolderPlus, Pencil, Play, RefreshCw, Rss, Smartphone, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

type Tab = 'feeds' | 'library';

export default function Dashboard({ activeTab: activeTabProp }: { activeTab?: Tab }) {
    const isMobile = useIsMobile();
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const pageProps = usePage<any>().props;
    const feeds: Feed[] = pageProps.feeds;
    const libraryItems: LibraryItem[] = pageProps.libraryItems;
    const flash: { success?: string; warning?: string } | undefined = pageProps.flash;

    const activeTab = activeTabProp ?? 'feeds';
    const [deleteFeedDialogOpen, setDeleteFeedDialogOpen] = useState(false);
    const [feedToDelete, setFeedToDelete] = useState<number | null>(null);
    const [deleteItemDialogOpen, setDeleteItemDialogOpen] = useState(false);
    const [itemToDelete, setItemToDelete] = useState<number | null>(null);
    const [playingItem, setPlayingItem] = useState<LibraryItem | null>(null);
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [itemToEdit, setItemToEdit] = useState<LibraryItem | null>(null);
    const { toast } = useToast();

    const {
        delete: destroyItemForm,
        post: retryForm,
        put,
        processing: itemProcessing,
        errors,
        data,
        setData,
    } = useForm({
        title: '',
        description: '',
        published_at: '',
    });

    useEffect(() => {
        const hasProcessingItems = libraryItems.some(
            (item) =>
                ProcessingStatusHelper.from(item.processing_status).isPending() ||
                ProcessingStatusHelper.from(item.processing_status).isProcessing(),
        );

        if (!hasProcessingItems) return;

        const interval = setInterval(() => {
            router.reload({ only: ['feeds', 'libraryItems'] });
        }, 5000);

        return () => clearInterval(interval);
    }, [libraryItems]);

    const handleDeleteFeedClick = (feedId: number) => {
        setFeedToDelete(feedId);
        setDeleteFeedDialogOpen(true);
    };

    const handleDeleteFeedConfirm = () => {
        if (feedToDelete) {
            router.delete(route('feeds.destroy', feedToDelete), {
                onSuccess: () => {
                    setDeleteFeedDialogOpen(false);
                    setFeedToDelete(null);
                },
                onError: () => {
                    toast({ title: 'Error', description: 'Failed to delete feed.', variant: 'destructive' });
                    setDeleteFeedDialogOpen(false);
                    setFeedToDelete(null);
                },
            });
        }
    };

    const handleCopyUrl = async (feed: Feed) => {
        const fullUrl = getAbsoluteRssUrl(feed);
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(fullUrl);
            } else {
                const textArea = document.createElement('textarea');
                textArea.value = fullUrl;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
            }
            toast({ title: 'URL copied!', description: 'Feed URL has been copied to your clipboard.' });
        } catch {
            toast({ title: 'Failed to copy', description: 'Could not copy the URL to clipboard.', variant: 'destructive' });
        }
    };

    const handleDeleteItemClick = (itemId: number) => {
        setItemToDelete(itemId);
        setDeleteItemDialogOpen(true);
    };

    const handleDeleteItemConfirm = () => {
        if (itemToDelete) {
            destroyItemForm(route('library.destroy', itemToDelete), {
                onSuccess: () => {
                    setDeleteItemDialogOpen(false);
                    setItemToDelete(null);
                    router.reload({ only: ['libraryItems'] });
                },
            });
        }
    };

    const handleRetry = (itemId: number) => {
        retryForm(route('library.retry', itemId), {
            onSuccess: () => {
                router.reload({ only: ['libraryItems'] });
            },
        });
    };

    const handleEditClick = (item: LibraryItem) => {
        setItemToEdit(item);
        setData('title', item.title);
        setData('description', item.description || '');
        setData('published_at', item.published_at ? item.published_at.split('T')[0] : '');
        setEditDialogOpen(true);
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (itemToEdit) {
            put(route('library.update', itemToEdit.id), {
                onSuccess: () => {
                    setEditDialogOpen(false);
                    setItemToEdit(null);
                    router.reload({ only: ['libraryItems'] });
                },
            });
        }
    };

    const handleEditDialogClose = () => {
        setEditDialogOpen(false);
        setItemToEdit(null);
        setData('title', '');
        setData('description', '');
        setData('published_at', '');
    };

    const handleUploadSuccess = () => {
        router.reload({ only: ['feeds', 'libraryItems'] });
    };

    return (
        <AppLayout>
            <Head title="Dashboard" />

            {flash?.success && (
                <Alert className="mb-4 border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">
                    <AlertDescription>{flash.success}</AlertDescription>
                </Alert>
            )}
            {flash?.warning && (
                <Alert className="mb-4 border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                    <AlertDescription>{flash.warning}</AlertDescription>
                </Alert>
            )}

            <div className="flex items-center gap-1 border-b">
                <Link
                    href={route('dashboard')}
                    className={`px-4 py-2 text-sm font-medium transition-colors ${
                        activeTab === 'feeds'
                            ? 'border-b-2 border-foreground text-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    }`}
                >
                    Feeds
                </Link>
                <Link
                    href={route('library.index')}
                    className={`px-4 py-2 text-sm font-medium transition-colors ${
                        activeTab === 'library'
                            ? 'border-b-2 border-foreground text-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    }`}
                >
                    Library
                </Link>

                <div className="ml-auto flex items-center gap-2">
                    <CreateFeedForm
                        renderTrigger={(onClick) =>
                            isMobile ? (
                                <Button size="icon" className="h-8 w-8" onClick={onClick}>
                                    <FolderPlus className="h-4 w-4" />
                                </Button>
                            ) : (
                                <Button size="sm" onClick={onClick}>
                                    + Feed
                                </Button>
                            )
                        }
                    />
                    <MediaUploadButton
                        onUploadSuccess={handleUploadSuccess}
                        feeds={feeds}
                        variant="default"
                        size={isMobile ? 'icon' : 'sm'}
                        iconOnly={isMobile}
                    />
                </div>
            </div>

            <div className="mt-4">
                {activeTab === 'feeds' ? (
                    feeds.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-16">
                            <Rss className="mb-4 h-10 w-10 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">No feeds yet. Create your first feed to get started.</p>
                        </div>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {feeds.map((feed) => (
                                <div key={feed.id} className="px-4 py-3">
                                    <p className="font-medium md:truncate">{feed.title}</p>
                                    <div className="mt-1 flex items-center gap-2">
                                        <span className="text-xs text-muted-foreground">{feed.items_count ?? 0} items</span>
                                        <span
                                            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                                                feed.is_public
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'
                                            }`}
                                        >
                                            {feed.is_public ? <Eye className="h-3 w-3" /> : <EyeOff className="h-3 w-3" />}
                                            {feed.is_public ? 'Public' : 'Private'}
                                        </span>
                                        <div className="ml-auto flex items-center gap-1">
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8" asChild>
                                                        <a href={getApplePodcastsUrl(feed)} target="_blank" rel="noopener noreferrer">
                                                            <FileAudio className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Apple Podcasts</TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8" asChild>
                                                        <a href={getGooglePodcastsUrl(feed)} target="_blank" rel="noopener noreferrer">
                                                            <Smartphone className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Google Podcasts</TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => handleCopyUrl(feed)}>
                                                        <Copy className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Copy RSS URL</TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8" asChild>
                                                        <Link href={route('feeds.edit', feed.id)}>
                                                            <Edit className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Edit</TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 text-destructive hover:text-destructive"
                                                        onClick={() => handleDeleteFeedClick(feed.id)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Delete</TooltipContent>
                                            </Tooltip>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )
                ) : libraryItems.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16">
                        <FileAudio className="mb-4 h-10 w-10 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">No media files yet. Upload your first file to get started.</p>
                    </div>
                ) : (
                    <div className="divide-y rounded-lg border">
                        {libraryItems.map((item) => {
                            const status = ProcessingStatusHelper.from(item.processing_status);
                            const isComplete = status.hasCompleted();
                            const isActive = status.isPending() || status.isProcessing();
                            const isFailed = status.hasFailed();

                            return (
                                <div key={item.id} className="flex items-center gap-4 px-4 py-3">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-8 w-8 shrink-0"
                                        disabled={!isComplete || !item.media_file}
                                        onClick={() => item.media_file && setPlayingItem(item)}
                                    >
                                        <Play className="h-4 w-4" />
                                    </Button>
                                    <div className="min-w-0 flex-1">
                                        <p className={`text-sm font-medium md:truncate ${!isComplete ? 'text-muted-foreground' : ''}`}>
                                            {item.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {(item.published_at || item.created_at).split('T')[0]}
                                            {item.media_file && (
                                                <>
                                                    {' '}· {formatFileSize(item.media_file.filesize)}
                                                    {item.media_file.duration && (
                                                        <> · {formatDuration(item.media_file.duration)}</>
                                                    )}
                                                </>
                                            )}
                                        </p>
                                    </div>

                                    {item.is_duplicate && (
                                        <span className="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                            <AlertCircle className="h-3 w-3" />
                                            Dup
                                        </span>
                                    )}

                                    <div className="flex items-center gap-1">
                                        {isComplete && (
                                            <span className="text-xs text-green-600 dark:text-green-400">
                                                {status.getIcon()}
                                            </span>
                                        )}
                                        {isActive && (
                                            <span className="flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400">
                                                {status.getIcon()}
                                                {status.getDisplayName()}
                                            </span>
                                        )}
                                        {isFailed && (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <span className="flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                                                        {status.getIcon()}
                                                        Failed
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{item.processing_error || 'Processing failed.'}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        )}
                                        {isComplete && (
                                            <>
                                                <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => handleEditClick(item)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8 text-destructive hover:text-destructive"
                                                    onClick={() => handleDeleteItemClick(item.id)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </>
                                        )}
                                        {isFailed && (
                                            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => handleRetry(item.id)}>
                                                <RefreshCw className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            <DeleteConfirmDialog
                isOpen={deleteFeedDialogOpen}
                onClose={() => { setDeleteFeedDialogOpen(false); setFeedToDelete(null); }}
                onConfirm={handleDeleteFeedConfirm}
                title="Delete Feed"
                description="Are you sure you want to delete this feed? This action cannot be undone."
                confirmText="Delete Feed"
                variant="destructive"
            />

            <DeleteConfirmDialog
                isOpen={deleteItemDialogOpen}
                onClose={() => { setDeleteItemDialogOpen(false); setItemToDelete(null); }}
                onConfirm={handleDeleteItemConfirm}
                title="Delete Media Item"
                description="Are you sure you want to remove this item from your library? This action cannot be undone."
                confirmText="Delete"
                variant="destructive"
            />

            {playingItem && (
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                <MediaPlayer libraryItem={playingItem as any} isOpen={true} onClose={() => setPlayingItem(null)} />
            )}

            <SheetPanel
                open={editDialogOpen}
                onOpenChange={handleEditDialogClose}
                title="Edit Media"
                onSubmit={handleEditSubmit}
                footer={
                    <>
                        <Button type="button" variant="outline" onClick={handleEditDialogClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={itemProcessing}>
                            {itemProcessing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-2">
                    <Label htmlFor="title">Title</Label>
                    <Input
                        id="title"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        placeholder="Enter title"
                        required
                    />
                    {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="description">Description</Label>
                    <Textarea
                        id="description"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        placeholder="Enter description (optional)"
                        rows={3}
                    />
                    {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="edit-published_at">Publish Date</Label>
                    <Input
                        id="edit-published_at"
                        type="date"
                        value={data.published_at}
                        onChange={(e) => setData('published_at', e.target.value)}
                    />
                    {errors.published_at && <p className="text-sm text-destructive">{errors.published_at}</p>}
                </div>
            </SheetPanel>
        </AppLayout>
    );
}
