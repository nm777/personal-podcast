import { Button } from '@/components/ui/button';
import { Component, type ErrorInfo, type ReactNode } from 'react';

interface ErrorBoundaryProps {
    children: ReactNode;
}

interface ErrorBoundaryState {
    hasError: boolean;
    error: Error | null;
}

export default class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
    constructor(props: ErrorBoundaryProps) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error: Error): ErrorBoundaryState {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
        console.error('ErrorBoundary caught:', error, errorInfo);
    }

    private handleReload = () => {
        window.location.reload();
    };

    render(): ReactNode {
        if (this.state.hasError) {
            return (
                <div className="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-900">
                    <div className="mx-auto max-w-md text-center">
                        <h1 className="mb-2 text-2xl font-bold text-gray-900 dark:text-gray-100">Something went wrong</h1>
                        <p className="mb-6 text-gray-600 dark:text-gray-400">An unexpected error occurred. Please try reloading the page.</p>
                        {import.meta.env.DEV && this.state.error && (
                            <pre className="mb-6 max-h-48 overflow-auto rounded bg-gray-100 p-4 text-left text-sm text-red-600 dark:bg-gray-800 dark:text-red-400">
                                {this.state.error.message}
                            </pre>
                        )}
                        <Button onClick={this.handleReload}>Reload Page</Button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
