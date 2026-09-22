<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Управлять тегами поста может только его автор.
     */
    public function manageTags(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }
}
