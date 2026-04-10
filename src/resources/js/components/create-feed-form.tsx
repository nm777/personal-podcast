import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

interface CreateFeedFormProps {
    renderTrigger?: (onClick: () => void) => React.ReactNode;
    showCard?: boolean;
}

export default function CreateFeedForm({ renderTrigger, showCard = true }: CreateFeedFormProps) {
    const [isExpanded, setIsExpanded] = useState(false);
    const { toast } = useToast();

    const { data, setData, post, processing, errors, reset } = useForm<{
        title: string;
        description: string;
        is_public: boolean;
    }>({
        title: '',
        description: '',
        is_public: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post(route('feeds.store'), {
            onSuccess: () => {
                reset();
                setIsExpanded(false);
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: 'Failed to create feed. Please try again.',
                    variant: 'destructive',
                });
            },
        });
    };

    const handleCancel = () => {
        reset();
        setIsExpanded(false);
    };

    if (!isExpanded) {
        if (renderTrigger) {
            return renderTrigger(() => setIsExpanded(true));
        }

        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center py-8">
                    <Button onClick={() => setIsExpanded(true)} className="w-full max-w-sm">
                        Create New Feed
                    </Button>
                </CardContent>
            </Card>
        );
    }

    const formContent = (
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
                {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
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
                {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
            </div>

            <div className="flex items-center space-x-2">
                <input
                    type="checkbox"
                    id="is_public"
                    checked={data.is_public}
                    onChange={(e) => setData('is_public', e.target.checked)}
                    className="rounded border-gray-300"
                />
                <Label htmlFor="is_public">Make this feed public</Label>
            </div>

            <div className="flex gap-2 pt-4">
                <Button type="submit" disabled={processing}>
                    {processing ? 'Creating...' : 'Create Feed'}
                </Button>
                <Button type="button" variant="outline" onClick={handleCancel}>
                    Cancel
                </Button>
            </div>
        </form>
    );

    if (!showCard) {
        return formContent;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Create New Feed</CardTitle>
                <CardDescription>Set up a new podcast feed to organize and share your content.</CardDescription>
            </CardHeader>
            <CardContent>{formContent}</CardContent>
        </Card>
    );
}
