import AppTopbar from '@/components/app-topbar';
import { Toaster } from '@/components/ui/toaster';
import { type ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
}

export default function AppLayout({ children }: AppLayoutProps) {
    return (
        <>
            <div className="flex min-h-screen w-full flex-col">
                <AppTopbar />
                <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6">{children}</main>
            </div>
            <Toaster />
        </>
    );
}
