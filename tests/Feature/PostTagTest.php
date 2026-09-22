<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostTagTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->post = Post::factory()->for($this->user)->create();
    }

    // --- Привязка тегов ------------------------------------------------

    public function test_can_attach_single_tag_to_post()
    {
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/posts/{$this->post->id}/tags", ['tag_ids' => [$tag->id]]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.tags');
        $response->assertJsonPath('data.tags.0.id', $tag->id);
        $response->assertJsonPath('data.tags.0.slug', $tag->slug);

        $this->assertDatabaseHas('post_tag', [
            'post_id' => $this->post->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_can_attach_several_tags_in_one_request()
    {
        $tags = Tag::factory(3)->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/posts/{$this->post->id}/tags", [
                'tag_ids' => $tags->pluck('id')->all(),
            ]);

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data.tags');

        $this->assertDatabaseCount('post_tag', 3);
    }

    public function test_attaching_the_same_tag_twice_does_not_create_duplicates()
    {
        $tag = Tag::factory()->create();
        $payload = ['tag_ids' => [$tag->id]];

        $this->actingAs($this->user)
            ->postJson("/api/posts/{$this->post->id}/tags", $payload)
            ->assertStatus(200);

        $response = $this->actingAs($this->user)
            ->postJson("/api/posts/{$this->post->id}/tags", $payload);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.tags');

        $this->assertDatabaseCount('post_tag', 1);
    }

    // --- Валидация -----------------------------------------------------

    public function test_attach_is_rejected_when_any_tag_does_not_exist_and_nothing_is_written()
    {
        $existingTag = Tag::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/posts/{$this->post->id}/tags", [
                'tag_ids' => [$existingTag->id, 999999],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tag_ids.1']);

        // Ключевое: валидный тег из той же пачки тоже не записан —
        // запрос отклонён целиком, до обращения к БД на запись.
        $this->assertDatabaseCount('post_tag', 0);
    }

    public function test_attach_is_rejected_without_tag_ids()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/posts/{$this->post->id}/tags", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tag_ids']);
    }

    public function test_attach_is_rejected_with_duplicated_ids()
    {
        $tag = Tag::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/posts/{$this->post->id}/tags", [
                'tag_ids' => [$tag->id, $tag->id],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tag_ids.0', 'tag_ids.1']);
    }

    // --- Доступ --------------------------------------------------------

    public function test_guest_cannot_attach_tags()
    {
        $tag = Tag::factory()->create();

        $response = $this->postJson("/api/posts/{$this->post->id}/tags", [
            'tag_ids' => [$tag->id],
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('post_tag', 0);
    }

    public function test_user_cannot_attach_tags_to_someone_elses_post()
    {
        $tag = Tag::factory()->create();
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)
            ->postJson("/api/posts/{$this->post->id}/tags", ['tag_ids' => [$tag->id]]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('post_tag', 0);
    }

    public function test_user_cannot_detach_tag_from_someone_elses_post()
    {
        $tag = Tag::factory()->create();
        $this->post->tags()->attach($tag);
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)
            ->deleteJson("/api/posts/{$this->post->id}/tags/{$tag->id}");

        $response->assertStatus(403);
        $this->assertDatabaseCount('post_tag', 1);
    }

    // --- Отвязка -------------------------------------------------------

    public function test_can_detach_tag_without_touching_the_others()
    {
        $tags = Tag::factory(3)->create();
        $this->post->tags()->attach($tags->pluck('id')->all());
        $removed = $tags->first();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/posts/{$this->post->id}/tags/{$removed->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('post_tag', [
            'post_id' => $this->post->id,
            'tag_id' => $removed->id,
        ]);
        $this->assertDatabaseCount('post_tag', 2);
    }

    public function test_detaching_a_tag_that_is_not_attached_is_idempotent()
    {
        $attached = Tag::factory()->create();
        $notAttached = Tag::factory()->create();
        $this->post->tags()->attach($attached);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/posts/{$this->post->id}/tags/{$notAttached->id}");

        $response->assertStatus(204);
        $this->assertDatabaseCount('post_tag', 1);
    }

    public function test_detach_returns_404_for_unknown_tag()
    {
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/posts/{$this->post->id}/tags/999999");

        $response->assertStatus(404);
    }

    // --- Чтение поста с тегами -----------------------------------------

    public function test_post_is_returned_with_its_tags()
    {
        $tags = Tag::factory(2)->create();
        $this->post->tags()->attach($tags->pluck('id')->all());

        $response = $this->actingAs($this->user)
            ->getJson("/api/posts/{$this->post->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data.tags');
        $response->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'tags' => [['id', 'name', 'slug']],
            ],
        ]);
        $this->assertEqualsCanonicalizing(
            $tags->pluck('id')->all(),
            $response->json('data.tags.*.id')
        );
    }

    public function test_posts_list_loads_tags_without_n_plus_one_queries()
    {
        $posts = Post::factory(5)->for($this->user)->create();
        $tags = Tag::factory(2)->create();
        $posts->each(fn (Post $post) => $post->tags()->attach($tags->pluck('id')->all()));

        DB::enableQueryLog();

        $response = $this->actingAs($this->user)->getJson('/api/posts');

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200);

        // Ожидаем константу: count + posts + users + tags.
        // При ленивой загрузке число запросов росло бы вместе с числом постов.
        $this->assertLessThanOrEqual(5, $queries, "Похоже на N+1: выполнено {$queries} запросов");
    }

    public function test_attaching_many_tags_does_not_scale_queries_with_batch_size()
    {
        $tags = Tag::factory(20)->create();

        DB::enableQueryLog();

        $response = $this->actingAs($this->user)
            ->postJson("/api/posts/{$this->post->id}/tags", [
                'tag_ids' => $tags->pluck('id')->all(),
            ]);

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200);

        // Правило exists выполняло бы отдельный SELECT на каждый идентификатор:
        // 20 тегов стоили бы 20 запросов только на проверку существования.
        // Проверка вынесена в один whereIn, поэтому число запросов не зависит
        // от размера пачки.
        $this->assertLessThanOrEqual(12, $queries, "Валидация масштабируется с размером пачки: {$queries} запросов на 20 тегов");
    }

    // --- Целостность данных --------------------------------------------

    public function test_deleting_a_tag_removes_its_links()
    {
        $tags = Tag::factory(2)->create();
        $this->post->tags()->attach($tags->pluck('id')->all());

        $tags->first()->delete();

        $this->assertDatabaseCount('post_tag', 1);
        $this->assertDatabaseMissing('post_tag', ['tag_id' => $tags->first()->id]);
    }

    public function test_deleting_a_post_removes_its_links()
    {
        $tag = Tag::factory()->create();
        $this->post->tags()->attach($tag);

        $this->post->delete();

        $this->assertDatabaseCount('post_tag', 0);
    }
}
