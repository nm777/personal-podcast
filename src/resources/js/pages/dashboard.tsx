import CreateFeedForm from '@/components/create-feed-form';
import FeedList from '@/components/feed-list';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface Feed {
    id: number;
    title: string;
    description?: string;
    is_public: boolean;
    slug: string;
    user_guid: string;
    token?: string;
    items_count: number;
    created_at: string;
    updated_at: string;
}

interface DashboardProps {
    feeds: Feed[];
    flash?: {
        success?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard({ feeds, flash }: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            {flash?.success && (
                <Alert className="mb-4 border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">
                    <AlertDescription>{flash.success}</AlertDescription>
                </Alert>
            )}
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Your Feeds</h2>
                        <CreateFeedForm
                            showCard={false}
                            renderTrigger={(onClick) => (
                                <Button size="sm" onClick={onClick}>
                                    Create New Feed
                                </Button>
                            )}
                        />
                    </div>

                    <FeedList feeds={feeds} />
                </div>
            </div>
        </AppLayout>
    );
}
