<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachTagsRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Tag;

class PostTagController extends Controller
{
    /**
     * Привязать один или несколько тегов к посту.
     */
    public function store(AttachTagsRequest $request, Post $post)
    {
        $post->attachTags($request->tagIds());

        return new PostResource($post->load('user', 'tags'));
    }

    /**
     * Отвязать тег от поста.
     */
    public function destroy(Post $post, Tag $tag)
    {
        $post->detachTag($tag);

        return response()->noContent();
    }
}
