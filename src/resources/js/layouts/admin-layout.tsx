import AppLayout from '@/layouts/app-layout';
import { type ReactNode } from 'react';

interface AdminLayoutProps {
    children: ReactNode;
}

export default ({ children }: AdminLayoutProps) => <AppLayout>{children}</AppLayout>;
