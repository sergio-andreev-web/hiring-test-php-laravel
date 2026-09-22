<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\UniqueConstraintViolationException;
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
     * Ровно два запроса независимо от размера пачки: выбрать уже привязанные
     * теги и одной вставкой добавить недостающие. syncWithoutDetaching здесь
     * не подходит — внутри он вызывает attach() отдельно на каждый тег, то
     * есть пачка из 50 тегов превращается в 50 INSERT-ов.
     *
     * Идемпотентность и защита от гонки:
     *
     * 1. Выборка уже привязанных тегов делает повторный вызов с теми же
     *    идентификаторами пустой операцией.
     * 2. Транзакция даёт атомарность: пост не останется с наполовину
     *    применённым набором, если вставка упадёт.
     * 3. Перехват UniqueConstraintViolationException закрывает гонку. Пункт 1 —
     *    это read-then-write без блокировки, поэтому два одновременных запроса
     *    с одним тегом могут оба увидеть «связи нет» и оба попытаться
     *    вставить. Составной первичный ключ post_tag не даст появиться дублю,
     *    но проигравший запрос получил бы 500 на ровном месте. Нужное
     *    состояние при этом уже достигнуто — связь существует, — поэтому
     *    исключение поглощается, и операция остаётся идемпотентной и под
     *    конкурентной нагрузкой.
     *
     * @param  array<int, int>  $tagIds
     */
    public function attachTags(array $tagIds): void
    {
        try {
            DB::transaction(function () use ($tagIds) {
                $alreadyAttached = $this->tags()
                    ->whereIn('tags.id', $tagIds)
                    ->pluck('tags.id')
                    ->all();

                $missing = array_values(array_diff($tagIds, $alreadyAttached));

                if ($missing !== []) {
                    $this->tags()->attach($missing);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Связь уже создана параллельным запросом — результат тот же.
        }
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
