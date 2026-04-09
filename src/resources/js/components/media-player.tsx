import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { type LibraryItem } from '@/types';
import { X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface MediaPlayerProps {
    libraryItem: LibraryItem;
    isOpen: boolean;
    onClose: () => void;
}

export default function MediaPlayer({ libraryItem, isOpen, onClose }: MediaPlayerProps) {
    const [error, setError] = useState<string | null>(null);

    const audioRef = useRef<HTMLAudioElement>(null);

    useEffect(() => {
        if (!isOpen || !libraryItem.media_file) return;

        const audio = audioRef.current;
        if (audio) {
            const handleError = () => setError('Audio loading failed');
            const handleCanPlay = () => setError(null);

            audio.addEventListener('error', handleError);
            audio.addEventListener('canplay', handleCanPlay);

            return () => {
                audio.removeEventListener('error', handleError);
                audio.removeEventListener('canplay', handleCanPlay);
            };
        }
    }, [isOpen, libraryItem.media_file]);

    if (!isOpen || !libraryItem.media_file) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
            <Card className="w-full max-w-2xl">
                <CardContent className="p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="flex-1 truncate text-lg font-semibold">{libraryItem.title}</h3>
                        <Button variant="ghost" size="sm" onClick={onClose}>
                            <X className="h-4 w-4" />
                        </Button>
                    </div>

                    {error ? (
                        <div className="py-8 text-center">
                            <p className="text-red-500">{error}</p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {/* Audio element */}
                            <audio
                                ref={audioRef}
                                src={libraryItem.media_file.public_url || `/files/${libraryItem.media_file.file_path}`}
                                className="w-full"
                                controls
                                preload="metadata"
                            />

                            {libraryItem.description && (
                                <div className="mt-4 rounded bg-gray-50 p-4 dark:bg-gray-800">
                                    <p className="text-sm text-gray-600 dark:text-gray-400">{libraryItem.description}</p>
                                </div>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
