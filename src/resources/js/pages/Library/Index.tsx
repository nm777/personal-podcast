import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

export default function LibraryIndex() {
    return (
        <AppLayout>
            <Head title="Library" />
            <div className="py-16 text-center">
                <p className="text-muted-foreground">This page has moved. <a href={route('dashboard')} className="underline">Go to Feeds</a>.</p>
            </div>
        </AppLayout>
    );
}
