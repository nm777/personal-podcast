<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeedItemResource;
use App\Http\Resources\FeedResource;
use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FeedController extends Controller
{
    /**
     * Display a listing of resource.
     */
    public function index()
    {
        $feeds = Feed::where('user_id', Auth::user()->id)->get();

        return FeedResource::collection($feeds);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover_image_url' => 'nullable|url',
            'is_public' => 'boolean',
            'slug' => 'required|string|max:255|unique:feeds,slug,NULL,id,user_id,'.Auth::user()->id,
        ]);

        $feed = Feed::create([
            'user_id' => Auth::user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'cover_image_url' => $request->cover_image_url,
            'is_public' => $request->is_public ?? false,
            'slug' => $request->slug,
            'user_guid' => (string) Str::uuid(),
            'token' => (string) Str::random(32),
        ]);

        return (new FeedResource($feed))->response()->setStatusCode(201);
    }

    public function addItems(Request $request, Feed $feed)
    {
        $this->authorize('update', $feed);

        $request->validate([
            'items' => 'required|array',
            'items.*.library_item_id' => 'required|exists:library_items,id,user_id,'.Auth::user()->id,
            'items.*.sequence' => 'required|integer',
        ]);

        foreach ($request->items as $item) {
            $feed->items()->create($item);
        }

        return FeedItemResource::collection($feed->items)->response()->setStatusCode(201);
    }

    public function removeItems(Request $request, Feed $feed)
    {
        $this->authorize('update', $feed);

        $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'required|exists:feed_items,id,feed_id,'.$feed->id,
        ]);

        $feed->items()->whereIn('id', $request->item_ids)->delete();

        return response()->json(null, 204);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
