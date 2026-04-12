import DeleteConfirmDialog from '@/components/delete-confirm-dialog';
import MediaPlayer from '@/components/media-player';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { ProcessingStatusHelper, ProcessingStatusType } from '@/lib/processing-status';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertCircle, FileAudio, FileVideo, Pencil, Play, RefreshCw, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface MediaFile {
    id: number;
    file_path: string;
    file_hash: string;
    mime_type: string;
    filesize: number;
    duration?: number;
    public_url?: string;
    created_at: string;
    updated_at: string;
}

interface LibraryItem {
    id: number;
    user_id: number;
    media_file_id: number;
    title: string;
    description?: string;
    source_type: string;
    source_url?: string;
    is_duplicate: boolean;
    duplicate_detected_at?: string;
    processing_status: ProcessingStatusType;
    processing_started_at?: string;
    processing_completed_at?: string;
    processing_error?: string;
    published_at?: string;
    created_at: string;
    updated_at: string;
    media_file?: MediaFile;
}

interface LibraryIndexProps {
    libraryItems: LibraryItem[];
    flash?: {
        success?: string;
        warning?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Library',
        href: '/library',
    },
];

export default function LibraryIndex({ libraryItems, flash }: LibraryIndexProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [itemToDelete, setItemToDelete] = useState<number | null>(null);
    const [playingItem, setPlayingItem] = useState<LibraryItem | null>(null);
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [itemToEdit, setItemToEdit] = useState<LibraryItem | null>(null);
    const {
        delete: destroyForm,
        post: retryForm,
        put,
        processing,
        errors,
        data,
        setData,
    } = useForm({
        title: '',
        description: '',
        published_at: '',
    });

    // Auto-refresh for processing items using custom polling
    useEffect(() => {
        const hasProcessingItems = libraryItems.some(
            (item) =>
                ProcessingStatusHelper.from(item.processing_status).isPending() || ProcessingStatusHelper.from(item.processing_status).isProcessing(),
        );

        if (!hasProcessingItems) return;

        const interval = setInterval(() => {
            router.reload({ only: ['libraryItems'] });
        }, 5000);

        return () => clearInterval(interval);
    }, [libraryItems]);

    const handleDeleteClick = (itemId: number) => {
        setItemToDelete(itemId);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (itemToDelete) {
            destroyForm(route('library.destroy', itemToDelete), {
                onSuccess: () => {
                    // Item deleted successfully
                    router.reload({ only: ['libraryItems'] });
                },
            });
        }
    };

    const handleRetry = (itemId: number) => {
        retryForm(route('library.retry', itemId), {
            onSuccess: () => {
                // Item retry initiated
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

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const getFileIcon = (mimeType?: string) => {
        if (mimeType?.startsWith('audio/')) {
            return <FileAudio className="h-8 w-8 text-blue-500" />;
        }
        if (mimeType?.startsWith('video/')) {
            return <FileVideo className="h-8 w-8 text-purple-500" />;
        }
        return <FileAudio className="h-8 w-8 text-gray-500" />;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Media Library" />

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

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <h1 className="text-2xl font-bold">Media Library</h1>

                {libraryItems.length === 0 ? (
                    <Card className="flex items-center justify-center p-12">
                        <div className="text-center">
                            <h3 className="mt-4 text-lg font-semibold">No media files yet</h3>
                            <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">Upload your first media file to get started</p>
                        </div>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {libraryItems.map((item) => (
                            <Card
                                key={item.id}
                                className={`relative overflow-hidden ${item.is_duplicate ? 'border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-900/10' : ''}`}
                            >
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex min-w-0 flex-1 items-center gap-3">
                                            {getFileIcon(item.media_file?.mime_type)}
                                            <div className="min-w-0 flex-1">
                                                <button
                                                    onClick={() => setPlayingItem(item)}
                                                    className="flex w-full cursor-pointer items-center gap-2 truncate border-0 bg-transparent text-left text-lg leading-tight font-medium transition-colors hover:text-foreground/80"
                                                    title={item.title}
                                                >
                                                    <Play className="h-4 w-4" />
                                                    {item.title}
                                                </button>
                                                <CardDescription className="text-xs">
                                                    {(item.published_at || item.created_at).split('T')[0]}
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <div className="flex gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleEditClick(item)}
                                                className="h-8 w-8 p-0 hover:bg-gray-100 dark:hover:bg-gray-800"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleDeleteClick(item.id)}
                                                className="h-8 w-8 p-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    {item.is_duplicate && (
                                        <div className="flex items-center gap-2 text-xs text-amber-700 dark:text-amber-300">
                                            <AlertCircle className="h-3 w-3" />
                                            <span>Duplicate file - will be removed automatically</span>
                                        </div>
                                    )}
                                    {ProcessingStatusHelper.from(item.processing_status).isPending() ||
                                    ProcessingStatusHelper.from(item.processing_status).isProcessing() ? (
                                        <div
                                            className={`flex items-center gap-2 text-xs ${ProcessingStatusHelper.from(item.processing_status).getColor()}`}
                                        >
                                            {ProcessingStatusHelper.from(item.processing_status).getIcon()}
                                            <span>{ProcessingStatusHelper.from(item.processing_status).getDisplayName()}...</span>
                                        </div>
                                    ) : null}
                                    {ProcessingStatusHelper.from(item.processing_status).hasFailed() ? (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <div
                                                    className={`flex items-center gap-2 text-xs ${ProcessingStatusHelper.from(item.processing_status).getColor()} cursor-help`}
                                                >
                                                    {ProcessingStatusHelper.from(item.processing_status).getIcon()}
                                                    <span>{ProcessingStatusHelper.from(item.processing_status).getDisplayName()}</span>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-5 w-5 p-0"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            handleRetry(item.id);
                                                        }}
                                                    >
                                                        <RefreshCw className="h-3 w-3" />
                                                    </Button>
                                                </div>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{item.processing_error || 'Processing failed. Click retry to try again.'}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    ) : null}
                                </CardHeader>
                                <CardContent className="overflow-hidden">
                                    {item.description && (
                                        <p className="mb-3 line-clamp-2 overflow-hidden text-sm text-gray-600 dark:text-gray-400">
                                            {item.description}
                                        </p>
                                    )}
                                    {item.media_file && (
                                        <div className="text-xs text-gray-500 dark:text-gray-400">
                                            <p>Size: {formatFileSize(item.media_file.filesize)}</p>
                                            <p>Type: {item.media_file.mime_type}</p>
                                            {item.media_file.duration && (
                                                <p>
                                                    Duration: {Math.floor(item.media_file.duration / 60)}:
                                                    {(item.media_file.duration % 60).toString().padStart(2, '0')}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <DeleteConfirmDialog
                isOpen={deleteDialogOpen}
                onClose={() => setDeleteDialogOpen(false)}
                onConfirm={handleDeleteConfirm}
                title="Delete Media Item"
                description="Are you sure you want to remove this item from your library? This action cannot be undone."
                confirmText="Delete"
                cancelText="Cancel"
                variant="destructive"
            />

            {/* Media Player Modal */}
            {playingItem && <MediaPlayer libraryItem={playingItem} isOpen={true} onClose={() => setPlayingItem(null)} />}

            {/* Edit Dialog */}
            <Dialog open={editDialogOpen} onOpenChange={handleEditDialogClose}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>Edit Media Details</DialogTitle>
                        <DialogDescription>Update the title and description for this media file</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleEditSubmit}>
                        <div className="space-y-4 py-4">
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
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={handleEditDialogClose}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
