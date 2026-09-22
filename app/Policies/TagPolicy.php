<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    /**
     * Теги — общий справочник без владельца, поэтому «свой/чужой» здесь
     * неприменимо. Ограничиваем по радиусу поражения: менять запись можно
     * только пока это не затрагивает чужой контент.
     */
    public function update(User $user, Tag $tag): bool
    {
        return ! $this->usedByOtherUsers($user, $tag);
    }

    /**
     * Удаление опаснее правки: post_tag.tag_id объявлен cascadeOnDelete,
     * поэтому удаление тега молча вычищает связи у всех постов, где он стоял.
     */
    public function delete(User $user, Tag $tag): bool
    {
        return ! $this->usedByOtherUsers($user, $tag);
    }

    /**
     * Стоит ли тег хотя бы на одном посте, который пользователю не принадлежит.
     */
    private function usedByOtherUsers(User $user, Tag $tag): bool
    {
        return $tag->posts()
            ->where('posts.user_id', '!=', $user->getKey())
            ->exists();
    }
}
