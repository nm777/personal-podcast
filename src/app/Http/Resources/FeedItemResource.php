<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedItemResource extends JsonResource
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
            'feed_id' => $this->feed_id,
            'library_item_id' => $this->library_item_id,
            'sequence' => $this->sequence,
            'library_item' => $this->when($this->libraryItem, LibraryItemResource::make($this->libraryItem)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
