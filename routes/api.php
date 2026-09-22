<?php

use App\Http\Controllers\PostController;
use App\Http\Controllers\PostTagController;
use App\Http\Controllers\PublishController;
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

// Блок Б — код-ревью, не рефакторить
Route::post('/publish/batch', [PublishController::class, 'batch']);
Route::get('/publish/report', [PublishController::class, 'report']);
