<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'cover_image_url' => $this->cover_image_url,
            'is_public' => $this->is_public,
            'slug' => $this->slug,
            'user_guid' => $this->user_guid,
            'token' => $this->token,
            'items_count' => $this->when(isset($this->items_count), $this->items_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
