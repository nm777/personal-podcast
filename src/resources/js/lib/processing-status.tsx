import { CheckCircle, Loader2, XCircle } from 'lucide-react';
import React from 'react';

export const ProcessingStatus = {
    PENDING: 'pending',
    PROCESSING: 'processing',
    COMPLETED: 'completed',
    FAILED: 'failed',
} as const;

export type ProcessingStatusType = 'pending' | 'processing' | 'completed' | 'failed';

export interface ProcessingStatusMethods {
    isPending(): boolean;
    isProcessing(): boolean;
    hasCompleted(): boolean;
    hasFailed(): boolean;
    getDisplayName(): string;
    getIcon(): React.ReactNode;
    getColor(): string;
}

export class ProcessingStatusHelper implements ProcessingStatusMethods {
    constructor(private status: ProcessingStatusType) {}

    isPending(): boolean {
        return this.status === ProcessingStatus.PENDING;
    }

    isProcessing(): boolean {
        return this.status === ProcessingStatus.PROCESSING;
    }

    hasCompleted(): boolean {
        return this.status === ProcessingStatus.COMPLETED;
    }

    hasFailed(): boolean {
        return this.status === ProcessingStatus.FAILED;
    }

    getDisplayName(): string {
        switch (this.status) {
            case ProcessingStatus.PENDING:
                return 'Pending';
            case ProcessingStatus.PROCESSING:
                return 'Processing';
            case ProcessingStatus.COMPLETED:
                return 'Completed';
            case ProcessingStatus.FAILED:
                return 'Failed';
            default:
                return 'Unknown';
        }
    }

    getIcon(): React.ReactNode {
        switch (this.status) {
            case ProcessingStatus.PROCESSING:
                return <Loader2 className="h-4 w-4 animate-spin text-blue-500" />;
            case ProcessingStatus.COMPLETED:
                return <CheckCircle className="h-4 w-4 text-green-500" />;
            case ProcessingStatus.FAILED:
                return <XCircle className="h-4 w-4 text-red-500" />;
            default:
                return <Loader2 className="h-4 w-4 text-gray-400" />;
        }
    }

    getColor(): string {
        switch (this.status) {
            case ProcessingStatus.PROCESSING:
                return 'text-blue-600';
            case ProcessingStatus.COMPLETED:
                return 'text-green-600';
            case ProcessingStatus.FAILED:
                return 'text-red-600';
            default:
                return 'text-gray-600';
        }
    }

    static from(status: string): ProcessingStatusHelper {
        return new ProcessingStatusHelper(status as ProcessingStatusType);
    }
}
