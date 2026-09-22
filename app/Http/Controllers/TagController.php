<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Response;

class TagController extends Controller
{
    public function index()
    {
        // withCount вместо подсчёта постов на каждом теге — один запрос вместо N.
        $tags = Tag::withCount('posts')->orderBy('name')->paginate(15);

        return TagResource::collection($tags);
    }

    public function show(Tag $tag)
    {
        return new TagResource($tag->loadCount('posts'));
    }

    public function store(StoreTagRequest $request)
    {
        $tag = Tag::create($request->validated());

        return (new TagResource($tag))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateTagRequest $request, Tag $tag)
    {
        $tag->update($request->validated());

        return new TagResource($tag);
    }

    public function destroy(Tag $tag)
    {
        // Связи в post_tag уберёт каскад на уровне БД.
        $tag->delete();

        return response()->noContent();
    }
}
