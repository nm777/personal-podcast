import InputError from '@/components/input-error';
import SheetPanel from '@/components/sheet-panel';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

interface CreateFeedFormProps {
    renderTrigger?: (onClick: () => void) => React.ReactNode;
}

export default function CreateFeedForm({ renderTrigger }: CreateFeedFormProps) {
    const [isOpen, setIsOpen] = useState(false);
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
                setIsOpen(false);
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
        setIsOpen(false);
    };

    const trigger = renderTrigger ? renderTrigger(() => setIsOpen(true)) : <Button onClick={() => setIsOpen(true)}>+ Feed</Button>;

    return (
        <SheetPanel
            open={isOpen}
            onOpenChange={setIsOpen}
            trigger={trigger}
            title="New Feed"
            onSubmit={handleSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" onClick={handleCancel}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Creating...' : 'Create Feed'}
                    </Button>
                </>
            }
        >
            <div className="space-y-2">
                <Label htmlFor="feed-title">Title</Label>
                <Input
                    id="feed-title"
                    type="text"
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                    placeholder="Enter feed title"
                    required
                />
                {errors.title && <InputError message={errors.title} />}
            </div>

            <div className="space-y-2">
                <Label htmlFor="feed-description">Description</Label>
                <Textarea
                    id="feed-description"
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
        </SheetPanel>
    );
}
