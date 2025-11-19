import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Podcast Feed - Create Custom RSS Feeds">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>

            <div className="flex min-h-screen flex-col" style={{ background: 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)' }}>
                {/* Header */}
                <header className="border-b border-white/10 p-5">
                    <div className="mx-auto flex max-w-7xl items-center justify-between">
                        <div className="text-2xl font-semibold text-blue-400">🎙️ Podcast Feed</div>
                        <nav className="flex gap-5">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-600"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link href={route('login')} className="rounded-lg px-4 py-2 text-gray-300 transition-colors hover:bg-white/10">
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-600"
                                    >
                                        Get Started
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Main Content */}
                <main className="flex-1 px-5 py-20">
                    <div className="mx-auto max-w-7xl">
                        {/* Hero Section */}
                        <section className="mb-20 text-center">
                            <h1 className="mb-6 bg-gradient-to-r from-blue-400 to-purple-400 bg-clip-text text-5xl font-bold text-transparent md:text-7xl">
                                Create Custom Podcast Feeds
                            </h1>
                            <p className="mx-auto mb-12 max-w-3xl text-xl text-gray-400 md:text-2xl">
                                Upload audio files or add YouTube links to build personalized RSS feeds for your audience. Simple, fast, and reliable.
                            </p>
                        </section>

                        {/* Features Grid */}
                        <section className="mb-20 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                            <div className="rounded-xl border border-white/10 bg-slate-800/50 p-8 text-center backdrop-blur-sm">
                                <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-purple-500 text-2xl">
                                    📁
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-100">Upload Audio Files</h3>
                                <p className="leading-relaxed text-gray-400">
                                    Upload MP3, WAV, and other audio formats directly to your library. We handle processing and optimization.
                                </p>
                            </div>

                            <div className="rounded-xl border border-white/10 bg-slate-800/50 p-8 text-center backdrop-blur-sm">
                                <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-purple-500 text-2xl">
                                    🎥
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-100">YouTube Integration</h3>
                                <p className="leading-relaxed text-gray-400">
                                    Simply paste a YouTube link and we'll extract audio automatically. Perfect for repurposing video content.
                                </p>
                            </div>

                            <div className="rounded-xl border border-white/10 bg-slate-800/50 p-8 text-center backdrop-blur-sm">
                                <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-purple-500 text-2xl">
                                    📡
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-100">Custom RSS Feeds</h3>
                                <p className="leading-relaxed text-gray-400">
                                    Create multiple feeds with different content. Each feed gets its own RSS URL for podcast platforms.
                                </p>
                            </div>

                            <div className="rounded-xl border border-white/10 bg-slate-800/50 p-8 text-center backdrop-blur-sm">
                                <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-purple-500 text-2xl">
                                    🔄
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-100">Auto-Duplication Detection</h3>
                                <p className="leading-relaxed text-gray-400">
                                    Smart detection prevents duplicate files, saving you storage space and keeping your feeds clean.
                                </p>
                            </div>

                            <div className="rounded-xl border border-white/10 bg-slate-800/50 p-8 text-center backdrop-blur-sm">
                                <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-purple-500 text-2xl">
                                    ⚡
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-100">Fast Processing</h3>
                                <p className="leading-relaxed text-gray-400">
                                    Background processing ensures your files are ready quickly without slowing down your workflow.
                                </p>
                            </div>

                            <div className="rounded-xl border border-white/10 bg-slate-800/50 p-8 text-center backdrop-blur-sm">
                                <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-purple-500 text-2xl">
                                    👥
                                </div>
                                <h3 className="mb-4 text-xl font-semibold text-gray-100">Multi-User Support</h3>
                                <p className="leading-relaxed text-gray-400">
                                    Each user has their own private library and feeds. Your content remains secure and separate.
                                </p>
                            </div>
                        </section>

                        {/* Call to Action */}
                        <section className="rounded-2xl border border-white/10 bg-slate-800/30 p-16 text-center backdrop-blur-sm">
                            <h2 className="mb-6 text-4xl font-bold text-gray-100">Ready to Start Your Podcast?</h2>
                            <p className="mx-auto mb-10 max-w-2xl text-xl text-gray-400">
                                Join creators who are already building their audio presence with our simple platform.
                            </p>
                            <div className="flex flex-col items-center justify-center gap-6 sm:flex-row">
                                <Link
                                    href={route('register')}
                                    className="transform rounded-xl bg-gradient-to-r from-blue-500 to-purple-500 px-8 py-4 text-lg font-semibold text-white transition-all hover:-translate-y-1 hover:from-blue-600 hover:to-purple-600 hover:shadow-lg hover:shadow-blue-500/25"
                                >
                                    Create Free Account
                                </Link>
                                <Link
                                    href={route('login')}
                                    className="rounded-xl border border-blue-400 px-8 py-4 text-lg font-semibold text-blue-400 transition-colors hover:bg-blue-400/10"
                                >
                                    Sign In
                                </Link>
                            </div>
                        </section>
                    </div>
                </main>
            </div>
        </>
    );
}
