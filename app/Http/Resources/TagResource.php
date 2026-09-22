<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            // Появляется только там, где счётчик посчитали через withCount.
            'posts_count' => $this->whenCounted('posts'),
        ];
    }
}
