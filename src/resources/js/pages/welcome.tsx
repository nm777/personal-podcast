import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="PodKeep - Build Custom Podcast Feeds">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>

            <div className="flex min-h-screen flex-col bg-background">
                <header className="border-b p-5">
                    <div className="mx-auto flex max-w-3xl items-center justify-between">
                        <span className="text-lg font-semibold">PodKeep</span>
                        <nav className="flex gap-3">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background transition-colors hover:bg-foreground/90"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="rounded-md px-4 py-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background transition-colors hover:bg-foreground/90"
                                    >
                                        Get Started
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="flex flex-1 items-center justify-center px-5 py-20">
                    <div className="mx-auto max-w-xl text-center">
                        <h1 className="mb-4 text-4xl font-bold tracking-tight">PodKeep</h1>
                        <p className="mb-8 text-lg text-muted-foreground">Build custom podcast feeds from your media files.</p>
                        <div className="flex items-center justify-center gap-3">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-md bg-foreground px-6 py-2.5 text-sm font-medium text-background transition-colors hover:bg-foreground/90"
                                >
                                    Go to Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('register')}
                                        className="rounded-md bg-foreground px-6 py-2.5 text-sm font-medium text-background transition-colors hover:bg-foreground/90"
                                    >
                                        Get Started
                                    </Link>
                                    <Link
                                        href={route('login')}
                                        className="rounded-md border px-6 py-2.5 text-sm font-medium transition-colors hover:bg-muted"
                                    >
                                        Log in
                                    </Link>
                                </>
                            )}
                        </div>
                        <p className="mt-12 text-sm text-muted-foreground">Upload your audio, organize into feeds, subscribe in any podcast app.</p>
                    </div>
                </main>
            </div>
        </>
    );
}
