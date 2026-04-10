<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedRequest;
use App\Models\Feed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class FeedController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): RedirectResponse|JsonResponse
    {
        $feeds = $request->user()->feeds()->latest()->get();

        if ($request->expectsJson()) {
            return response()->json($feeds);
        }

        return redirect()->route('dashboard');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FeedRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $slug = $this->generateUniqueSlug($validated['title']);

        $feed = $request->user()->feeds()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'slug' => $slug,
            'user_guid' => Str::uuid(),
            'token' => Str::random(64),
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return redirect()->route('feeds.edit', $feed)->with('success', 'Feed created successfully!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Feed $feed): Response
    {
        Gate::authorize('update', $feed);

        $feed->load(['items.libraryItem', 'items.libraryItem.mediaFile']);

        $userLibraryItems = $request->user()->libraryItems()->with('mediaFile')->limit(100)->get();

        return Inertia::render('feeds/edit', [
            'feed' => $feed,
            'userLibraryItems' => $userLibraryItems,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FeedRequest $request, Feed $feed): RedirectResponse
    {
        Gate::authorize('update', $feed);

        $validated = $request->validated();

        $slug = $this->generateUniqueSlug($validated['title'], $feed->id);

        $feed->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'slug' => $slug,
            'is_public' => $validated['is_public'] ?? false,
        ]);

        if (isset($validated['items'])) {
            $this->syncFeedItems($feed, $validated['items']);
        }

        // Clear RSS cache when feed is updated
        Cache::forget("rss.{$feed->id}");

        return redirect()->route('dashboard')->with('success', 'Feed updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Feed $feed): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $feed);

        // Clear RSS cache before deleting
        Cache::forget("rss.{$feed->id}");

        $feed->delete();

        if ($request->expectsJson()) {
            return response()->json(null, 204);
        }

        return redirect()->route('dashboard')->with('success', 'Feed deleted successfully!');
    }

    private function syncFeedItems(Feed $feed, array $items): void
    {
        $newItemIds = collect($items)->pluck('library_item_id');

        $feed->items()
            ->whereNotIn('library_item_id', $newItemIds)
            ->delete();

        if (empty($items)) {
            return;
        }

        $feed->items()->upsert(
            collect($items)->map(fn ($item, $index) => [
                'feed_id' => $feed->id,
                'library_item_id' => $item['library_item_id'],
                'sequence' => $index,
            ])->values()->all(),
            ['feed_id', 'library_item_id'],
            ['sequence'],
        );
    }

    private function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        $user = request()->user();
        $query = $user->feeds()->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug.'-'.$count++;
            $query = $user->feeds()->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
