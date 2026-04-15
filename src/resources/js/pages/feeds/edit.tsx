import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatDuration, formatFileSize } from '@/lib/format';
import { type Feed, type FeedItem, type LibraryItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { GripVertical, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface EditFeedProps {
    feed: Feed;
    userLibraryItems: LibraryItem[];
}

export default function EditFeed({ feed, userLibraryItems }: EditFeedProps) {
    const [draggedIndex, setDraggedIndex] = useState<number | null>(null);

    const { data, setData, put, processing, errors } = useForm({
        title: feed.title,
        description: feed.description || '',
        is_public: feed.is_public,
        items: (feed.items ?? []).map((item: FeedItem) => ({
            id: item.id,
            library_item_id: item.library_item_id,
            sequence: item.sequence,
        })),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/feeds/${feed.id}`);
    };

    const addLibraryItem = (libraryItemId: number) => {
        const newItem = {
            id: Date.now(),
            library_item_id: libraryItemId,
            sequence: data.items.length,
        };
        setData('items', [...data.items, newItem]);
    };

    const removeItem = (index: number) => {
        const newItems = data.items.filter((_, i) => i !== index);
        setData(
            'items',
            newItems.map((item, i) => ({ ...item, sequence: i })),
        );
    };

    const handleDragStart = (index: number) => {
        setDraggedIndex(index);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
    };

    const handleDrop = (e: React.DragEvent, dropIndex: number) => {
        e.preventDefault();
        if (draggedIndex === null) return;

        const draggedItem = data.items[draggedIndex];
        const newItems = [...data.items];
        newItems.splice(draggedIndex, 1);
        newItems.splice(dropIndex, 0, draggedItem);

        setData(
            'items',
            newItems.map((item, i) => ({ ...item, sequence: i })),
        );
        setDraggedIndex(null);
    };

    const getLibraryItem = (libraryItemId: number) => {
        return userLibraryItems.find((item) => item.id === libraryItemId);
    };

    const availableLibraryItems = userLibraryItems.filter((item) => !data.items.some((feedItem) => feedItem.library_item_id === item.id));

    return (
        <AppLayout>
            <Head title={`Edit Feed: ${feed.title}`} />

            <div className="space-y-6">
                <h1 className="text-xl font-semibold">{feed.title}</h1>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            type="text"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="Enter feed title"
                            required
                        />
                        {errors.title && <InputError message={errors.title} />}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Enter feed description (optional)"
                            rows={3}
                        />
                        {errors.description && <InputError message={errors.description} />}
                    </div>

                    <div className="flex items-center space-x-2">
                        <Checkbox id="is_public" checked={data.is_public} onCheckedChange={(checked) => setData('is_public', checked === true)} />
                        <Label htmlFor="is_public">Make this feed public</Label>
                    </div>

                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </form>

                <div className="space-y-3">
                    <h2 className="text-base font-medium">Feed Items</h2>

                    {data.items.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            No items in this feed yet. Add items from your library below.
                        </p>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {data.items.map((item, index) => {
                                const libraryItem = getLibraryItem(item.library_item_id);
                                if (!libraryItem) return null;

                                return (
                                    <div
                                        key={item.library_item_id}
                                        draggable
                                        onDragStart={() => handleDragStart(index)}
                                        onDragOver={handleDragOver}
                                        onDrop={(e) => handleDrop(e, index)}
                                        className="flex cursor-move items-center gap-3 px-4 py-3 hover:bg-muted/50"
                                    >
                                        <GripVertical className="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">{libraryItem.title}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {libraryItem.media_file ? (
                                                    <>
                                                        {formatDuration(libraryItem.media_file.duration)} ·{' '}
                                                        {formatFileSize(libraryItem.media_file.filesize)}
                                                    </>
                                                ) : (
                                                    'Processing...'
                                                )}
                                            </p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => removeItem(index)}
                                            className="shrink-0 text-destructive hover:text-destructive"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {availableLibraryItems.length > 0 && (
                        <div className="space-y-2 border-t pt-4">
                            <Label className="text-sm font-medium">Add Library Items</Label>
                            <div className="max-h-48 space-y-1 overflow-y-auto">
                                {availableLibraryItems.map((libraryItem) => (
                                    <div key={libraryItem.id} className="flex items-center gap-2 rounded-md border p-2">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">{libraryItem.title}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {libraryItem.media_file ? (
                                                    <>
                                                        {formatDuration(libraryItem.media_file.duration)} ·{' '}
                                                        {formatFileSize(libraryItem.media_file.filesize)}
                                                    </>
                                                ) : (
                                                    'Processing...'
                                                )}
                                            </p>
                                        </div>
                                        <Button variant="ghost" size="sm" onClick={() => addLibraryItem(libraryItem.id)} className="shrink-0">
                                            <Plus className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {availableLibraryItems.length === 0 && data.items.length > 0 && (
                        <p className="text-center text-sm text-muted-foreground">All library items are already in this feed</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
