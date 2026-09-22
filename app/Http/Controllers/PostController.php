<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with('user')->paginate(15);
        return PostResource::collection($posts);
    }

    public function show(Post $post)
    {
        return new PostResource($post->load('user'));
    }

    public function store(StorePostRequest $request)
    {
        $post = auth()->user()->posts()->create($request->validated());

        return (new PostResource($post->load('user')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdatePostRequest $request, Post $post)
    {
        $post->update($request->validated());

        return new PostResource($post->load('user'));
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return response()->noContent();
    }
}
