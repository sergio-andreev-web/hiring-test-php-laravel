<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /**
     * Привязать теги к посту, не трогая уже привязанные.
     *
     * syncWithoutDetaching идемпотентен: повторный вызов с теми же
     * идентификаторами не создаёт дублей. Транзакция нужна потому, что при
     * нескольких тегах это несколько INSERT-ов: если один упадёт (например,
     * тег удалили между валидацией и записью), пост не останется с наполовину
     * применённым набором тегов.
     *
     * @param  array<int, int>  $tagIds
     */
    public function attachTags(array $tagIds): void
    {
        DB::transaction(function () use ($tagIds) {
            $this->tags()->syncWithoutDetaching($tagIds);
        });
    }

    /**
     * Отвязать тег от поста. Операция идемпотентна: если связи не было,
     * состояние просто не меняется.
     */
    public function detachTag(Tag $tag): void
    {
        $this->tags()->detach($tag->getKey());
    }
}
