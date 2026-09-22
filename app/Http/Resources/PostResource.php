<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'user' => new UserResource($this->whenLoaded('user')),
            // whenLoaded, а не $this->tags: ключ появляется только когда связь
            // загружена заранее, поэтому ресурс не может сам спровоцировать N+1.
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
