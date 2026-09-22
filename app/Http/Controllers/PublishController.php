<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

// Do not refactor this file — it is the subject of Block B (code review).
class PublishController extends Controller
{
    public function batch(Request $request)
    {
        $postIds = $request->input('post_ids', []);
        $reason = $request->input('publish_reason');

        $result = [];

        foreach ($postIds as $postId) {
            $post = Post::find($postId);

            if (!$post) {
                continue;
            }

            $shouldPublish = strlen($reason) > 10;
            if (!$shouldPublish) {
                continue;
            }

            $post->update([
                'status' => $request->input('status', 'published'),
                'published_at' => now(),
                'body' => $request->input('body', $post->body),
            ]);

            $user = $post->user;
            $commentCount = $post->comments()->count();

            $score = $commentCount * 10 + strlen($post->body);

            $logMessage = "Post {$post->id} published by {$user->name}. Score: {$score}";

            $result[] = [
                'id' => $post->id,
                'title' => $post->title,
                'user' => $user->name,
                'log' => $logMessage,
            ];
        }

        return response()->json([
            'published' => count($result),
            'results' => $result,
        ]);
    }

    public function report()
    {
        $posts = Post::all();

        $report = [];
        foreach ($posts as $post) {
            $user = $post->user;
            $comments = $post->comments;

            $report[] = [
                'post_id' => $post->id,
                'title' => $post->title,
                'author' => $user->name,
                'comment_count' => count($comments),
                'comments' => $comments->pluck('body')->toArray(),
            ];
        }

        return response()->json($report);
    }
}
