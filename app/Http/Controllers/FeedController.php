<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedRequest;
use App\Models\Feed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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

        $feed = Auth::user()->feeds()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'slug' => Str::slug($validated['title']),
            'user_guid' => Str::uuid(),
            'token' => Str::random(32),
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return redirect()->route('dashboard')->with('success', 'Feed created successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Feed $feed)
    {
        Gate::authorize('delete', $feed);

        $feed->delete();

        if (request()->expectsJson()) {
            return response()->json(null, 204);
        }

        return redirect()->route('dashboard')->with('success', 'Feed deleted successfully!');
    }
}
