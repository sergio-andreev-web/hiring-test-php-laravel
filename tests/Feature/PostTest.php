<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_list_posts()
    {
        $posts = Post::factory(3)->for($this->user)->create();

        $response = $this->getJson('/api/posts');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_post()
    {
        $postData = [
            'title' => 'Test Post',
            'body' => 'This is a test post with enough content.',
            'status' => 'draft',
        ];

        $response = $this->postJson('/api/posts', $postData);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Test Post');
        $response->assertJsonPath('data.user.id', $this->user->id);

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_show_post()
    {
        $post = Post::factory()->for($this->user)->create();

        $response = $this->getJson("/api/posts/{$post->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $post->id);
        $response->assertJsonPath('data.title', $post->title);
    }

    public function test_can_update_post()
    {
        $post = Post::factory()->for($this->user)->create();

        $updateData = [
            'title' => 'Updated Title',
            'body' => 'Updated content with enough text.',
        ];

        $response = $this->patchJson("/api/posts/{$post->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_can_delete_post()
    {
        $post = Post::factory()->for($this->user)->create();

        $response = $this->deleteJson("/api/posts/{$post->id}");

        $response->assertStatus(204);
        $this->assertModelMissing($post);
    }

    public function test_validation_fails_with_invalid_data()
    {
        $invalidData = [
            'title' => '',
            'body' => 'Short',
        ];

        $response = $this->postJson('/api/posts', $invalidData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'body']);
    }

    public function test_can_paginate_posts()
    {
        Post::factory(20)->for($this->user)->create();

        $response = $this->getJson('/api/posts');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 20);
    }
}
