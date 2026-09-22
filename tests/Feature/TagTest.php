<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // --- Чтение (публичное) --------------------------------------------

    public function test_guest_can_list_tags_with_post_counts()
    {
        $tag = Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
        Post::factory(2)->for($this->user)->create()
            ->each(fn (Post $post) => $post->tags()->attach($tag));

        Tag::factory()->create(['name' => 'PHP', 'slug' => 'php']);

        DB::enableQueryLog();
        $response = $this->getJson('/api/tags');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.slug', 'laravel');
        $response->assertJsonPath('data.0.posts_count', 2);
        $response->assertJsonPath('data.1.posts_count', 0);
        $response->assertJsonPath('meta.total', 2);

        // withCount: счётчики берутся одним запросом, а не по запросу на тег.
        $this->assertLessThanOrEqual(3, $queries, "Похоже на N+1: выполнено {$queries} запросов");
    }

    public function test_guest_can_show_single_tag()
    {
        $tag = Tag::factory()->create();

        $response = $this->getJson("/api/tags/{$tag->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $tag->id);
        $response->assertJsonPath('data.posts_count', 0);
    }

    // --- Создание ------------------------------------------------------

    public function test_can_create_tag_with_slug_generated_from_name()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/tags', ['name' => 'Clean Architecture']);

        $response->assertStatus(201);
        $response->assertJsonPath('data.slug', 'clean-architecture');

        $this->assertDatabaseHas('tags', [
            'name' => 'Clean Architecture',
            'slug' => 'clean-architecture',
        ]);
    }

    public function test_cyrillic_name_is_transliterated_into_a_valid_slug()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/tags', ['name' => 'Чистый Код']);

        $response->assertStatus(201);

        // Проверяем контракт (из кириллицы получается пригодный латинский slug),
        // а не конкретную таблицу транслитерации Str::slug — она деталь реализации.
        $this->assertMatchesRegularExpression(
            '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            $response->json('data.slug')
        );
    }

    public function test_can_create_tag_with_explicit_slug()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/tags', ['name' => 'Laravel 11', 'slug' => 'laravel-11']);

        $response->assertStatus(201);
        $response->assertJsonPath('data.slug', 'laravel-11');
    }

    public function test_tag_creation_requires_name()
    {
        $response = $this->actingAs($this->user)->postJson('/api/tags', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
        $this->assertDatabaseCount('tags', 0);
    }

    public function test_tag_creation_rejects_duplicate_slug()
    {
        Tag::factory()->create(['slug' => 'laravel']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/tags', ['name' => 'Laravel']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_tag_creation_rejects_malformed_slug()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/tags', ['name' => 'Laravel', 'slug' => 'Laravel Framework!']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
    }

    public function test_tag_creation_fails_when_slug_cannot_be_built_from_name()
    {
        // Str::slug не умеет иероглифы и вернёт пустую строку — тег с пустым
        // slug создаваться не должен, клиент должен получить внятную ошибку.
        $response = $this->actingAs($this->user)
            ->postJson('/api/tags', ['name' => '日本語']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
        $this->assertDatabaseCount('tags', 0);
    }

    public function test_guest_cannot_create_tag()
    {
        $response = $this->postJson('/api/tags', ['name' => 'Laravel']);

        $response->assertStatus(401);
        $this->assertDatabaseCount('tags', 0);
    }

    // --- Обновление ----------------------------------------------------

    public function test_renaming_tag_does_not_change_its_slug()
    {
        $tag = Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/tags/{$tag->id}", ['name' => 'Laravel 11']);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Laravel 11');
        // Slug — часть публичного адреса, молча его не меняем.
        $response->assertJsonPath('data.slug', 'laravel');
    }

    public function test_tag_can_keep_its_own_slug_on_update()
    {
        $tag = Tag::factory()->create(['slug' => 'laravel']);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/tags/{$tag->id}", ['name' => 'Laravel', 'slug' => 'laravel']);

        $response->assertStatus(200);
    }

    public function test_update_rejects_slug_taken_by_another_tag()
    {
        Tag::factory()->create(['slug' => 'php']);
        $tag = Tag::factory()->create(['slug' => 'laravel']);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/tags/{$tag->id}", ['slug' => 'php']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'slug' => 'laravel']);
    }

    public function test_guest_cannot_update_tag()
    {
        $tag = Tag::factory()->create(['name' => 'Laravel']);

        $response = $this->patchJson("/api/tags/{$tag->id}", ['name' => 'Hacked']);

        $response->assertStatus(401);
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Laravel']);
    }

    // --- Удаление ------------------------------------------------------

    public function test_deleting_tag_also_removes_its_links_to_posts()
    {
        $tag = Tag::factory()->create();
        $post = Post::factory()->for($this->user)->create();
        $post->tags()->attach($tag);

        $response = $this->actingAs($this->user)->deleteJson("/api/tags/{$tag->id}");

        $response->assertStatus(204);
        $this->assertModelMissing($tag);
        $this->assertDatabaseCount('post_tag', 0);
    }

    public function test_guest_cannot_delete_tag()
    {
        $tag = Tag::factory()->create();

        $response = $this->deleteJson("/api/tags/{$tag->id}");

        $response->assertStatus(401);
        $this->assertModelExists($tag);
    }

    // --- Авторизация в справочнике ------------------------------------
    //
    // Слепая зона прошлой версии тестов: везде проверялся только гость (401),
    // а кейс «другой аутентифицированный пользователь» не покрывался нигде.
    // Из-за этого мимо 42 зелёных тестов прошла дыра: политика стояла на
    // /posts/{post}/tags, но не на самом справочнике, и чужой тег можно было
    // удалить в обход — каскад вычищал связи у постов всех пользователей.

    public function test_user_cannot_delete_tag_used_by_someone_elses_post()
    {
        $tag = Tag::factory()->create();
        $stranger = User::factory()->create();
        Post::factory()->for($stranger)->create()->tags()->attach($tag);

        $response = $this->actingAs($this->user)->deleteJson("/api/tags/{$tag->id}");

        $response->assertStatus(403);
        $this->assertModelExists($tag);
        $this->assertDatabaseCount('post_tag', 1);
    }

    public function test_user_cannot_rename_tag_used_by_someone_elses_post()
    {
        $tag = Tag::factory()->create(['name' => 'Laravel']);
        $stranger = User::factory()->create();
        Post::factory()->for($stranger)->create()->tags()->attach($tag);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/tags/{$tag->id}", ['name' => 'Захвачено']);

        $response->assertStatus(403);
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Laravel']);
    }

    public function test_user_can_delete_tag_used_only_by_own_posts()
    {
        $tag = Tag::factory()->create();
        Post::factory()->for($this->user)->create()->tags()->attach($tag);

        $response = $this->actingAs($this->user)->deleteJson("/api/tags/{$tag->id}");

        $response->assertStatus(204);
        $this->assertModelMissing($tag);
    }

    public function test_user_can_delete_unused_tag()
    {
        $tag = Tag::factory()->create();

        $this->actingAs($this->user)
            ->deleteJson("/api/tags/{$tag->id}")
            ->assertStatus(204);
    }

    // Модификатор D у regex проверяется на уровне правила, а не через HTTP:
    // глобальный middleware TrimStrings срезал бы перевод строки до валидации,
    // и дефект остался бы невидимым. Правило должно держать оборону само.
    public function test_slug_rule_rejects_trailing_newline()
    {
        $rules = (new \App\Http\Requests\StoreTagRequest)->rules();

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['name' => 'Laravel', 'slug' => "laravel\n"],
            $rules
        );

        $this->assertTrue($validator->fails(), 'Slug с переводом строки должен отклоняться');
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    public function test_show_returns_404_for_unknown_tag()
    {
        $this->getJson('/api/tags/999999')->assertStatus(404);
    }
}
