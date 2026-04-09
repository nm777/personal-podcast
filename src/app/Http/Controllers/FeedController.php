<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedRequest;
use App\Models\Feed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FeedController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $feeds = Auth::user()->feeds()->latest()->get();

        if (request()->expectsJson()) {
            return response()->json($feeds);
        }

        return redirect()->route('dashboard');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FeedRequest $request)
    {
        $validated = $request->validated();

        $slug = $this->generateUniqueSlug($validated['title']);

        $feed = Auth::user()->feeds()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'slug' => $slug,
            'user_guid' => Str::uuid(),
            'token' => Str::random(32),
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return redirect()->route('feeds.edit', $feed)->with('success', 'Feed created successfully!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Feed $feed)
    {
        Gate::authorize('update', $feed);

        $feed->load(['items.libraryItem', 'items.libraryItem.mediaFile']);

        $userLibraryItems = Auth::user()->libraryItems()->with('mediaFile')->get();

        return Inertia::render('feeds/edit', [
            'feed' => $feed,
            'userLibraryItems' => $userLibraryItems,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FeedRequest $request, Feed $feed)
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
    public function destroy(Feed $feed)
    {
        Gate::authorize('delete', $feed);

        // Clear RSS cache before deleting
        Cache::forget("rss.{$feed->id}");

        $feed->delete();

        if (request()->expectsJson()) {
            return response()->json(null, 204);
        }

        return redirect()->route('dashboard')->with('success', 'Feed deleted successfully!');
    }

    private function syncFeedItems(Feed $feed, array $items): void
    {
        $currentItems = $feed->items->keyBy('library_item_id');
        $newItemIds = collect($items)->pluck('library_item_id');

        // Delete items that are no longer in the list
        $feed->items()
            ->whereNotIn('library_item_id', $newItemIds)
            ->delete();

        // Update or create items
        foreach ($items as $index => $item) {
            $feed->items()->updateOrCreate(
                [
                    'library_item_id' => $item['library_item_id'],
                ],
                [
                    'sequence' => $index,
                ]
            );
        }
    }

    private function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        $query = Auth::user()->feeds()->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug.'-'.$count++;
            $query = Auth::user()->feeds()->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
