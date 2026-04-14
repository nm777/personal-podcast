import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import type { ReactNode } from 'react';

interface SheetPanelProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    trigger?: ReactNode;
    title: ReactNode;
    children: ReactNode;
    footer: ReactNode;
    onSubmit?: (e: React.FormEvent) => void;
}

export default function SheetPanel({ open, onOpenChange, trigger, title, children, footer, onSubmit }: SheetPanelProps) {
    const isMobile = useIsMobile();

    const inner = (
        <div className="flex h-full max-w-full flex-col overflow-hidden">
            <div className="border-b px-4 py-3">
                <SheetTitle className="text-base">{title}</SheetTitle>
            </div>
            <div className="flex-1 space-y-4 overflow-x-hidden overflow-y-auto px-4 py-4">
                {children}
            </div>
            <div className="flex justify-end gap-2 border-t px-4 py-3">
                {footer}
            </div>
        </div>
    );

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            {trigger && <SheetTrigger asChild>{trigger}</SheetTrigger>}
            <SheetContent
                side={isMobile ? 'bottom' : 'right'}
                hideClose
                className={isMobile ? 'h-svh w-full overflow-x-hidden p-0 rounded-none' : 'w-full sm:max-w-md overflow-x-hidden p-0'}
            >
                {onSubmit ? (
                    <form onSubmit={onSubmit} className="flex h-full flex-col">
                        {inner}
                    </form>
                ) : inner}
            </SheetContent>
        </Sheet>
    );
}
