<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Создать 3 пользователей
        $users = User::factory(3)->create();

        // Создать 8 постов (часть draft, часть published)
        $posts = Post::factory(8)
            ->for($users->random())
            ->create();

        // Для каждого поста создать по 2-3 комментария
        $posts->each(function (Post $post) use ($users) {
            Comment::factory(random_int(2, 3))
                ->for($post)
                ->for($users->random())
                ->create();
        });
    }
}
