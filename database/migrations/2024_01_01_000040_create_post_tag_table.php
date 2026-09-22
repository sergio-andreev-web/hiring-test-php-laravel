<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Композитный PK: строка связи уникальна парой (post_id, tag_id).
            // Суррогатный id не нужен, а уникальность гарантируется на уровне БД,
            // а не только кодом приложения.
            $table->primary(['post_id', 'tag_id']);

            // Левая колонка PK обслуживает выборку тегов поста; для обратного
            // направления (посты тега) нужен отдельный индекс.
            $table->index('tag_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('post_tag');
    }
};
