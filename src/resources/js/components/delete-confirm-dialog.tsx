import { Button } from '@/components/ui/button';
import SheetPanel from '@/components/sheet-panel';
import { AlertTriangle } from 'lucide-react';
import { ReactNode } from 'react';

interface DeleteConfirmDialogProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    description: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'default' | 'destructive';
    icon?: ReactNode;
}

export default function DeleteConfirmDialog({
    isOpen,
    onClose,
    onConfirm,
    title,
    description,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    variant = 'default',
    icon,
}: DeleteConfirmDialogProps) {
    return (
        <SheetPanel
            open={isOpen}
            onOpenChange={onClose}
            title={
                <div className="flex items-center gap-3">
                    {icon || <AlertTriangle className="h-5 w-5 text-orange-500" />}
                    <span>{title}</span>
                </div>
            }
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {cancelText}
                    </Button>
                    <Button variant={variant} onClick={onConfirm}>
                        {confirmText}
                    </Button>
                </>
            }
        >
            <p className="text-sm text-muted-foreground">{description}</p>
        </SheetPanel>
    );
}
