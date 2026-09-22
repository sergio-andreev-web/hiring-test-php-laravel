<?php

use App\Http\Controllers\PostController;
use App\Http\Controllers\PostTagController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Baseline endpoint — Resource контроллер для Posts
Route::apiResource('posts', PostController::class);

// Блок А — управление тегами поста.
// auth даёт 401 анониму, ->can() проверяет PostPolicy::manageTags и даёт 403 чужому.
Route::middleware('auth')->group(function () {
    Route::post('posts/{post}/tags', [PostTagController::class, 'store'])
        ->can('manageTags', 'post');

    Route::delete('posts/{post}/tags/{tag}', [PostTagController::class, 'destroy'])
        ->can('manageTags', 'post');
});

// Блок А (опциональное расширение) — справочник тегов.
// Чтение публичное, изменение справочника — только для аутентифицированных.
Route::apiResource('tags', TagController::class)->only(['index', 'show']);
Route::apiResource('tags', TagController::class)
    ->except(['index', 'show'])
    ->middleware('auth');

// Блок Б — код-ревью, не рефакторить
Route::post('/publish/batch', [PublishController::class, 'batch']);
Route::get('/publish/report', [PublishController::class, 'report']);
