<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Food Image Gallery (customer-uploaded dish photos)
        Schema::create('food_gallery', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('menu_item_id')->nullable();
            $table->uuid('order_id')->nullable();
            $table->string('image_url');
            $table->text('caption')->nullable();
            $table->integer('likes_count')->default(0);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Food Blog / Magazine
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('featured_image')->nullable();
            $table->json('tags')->nullable();
            $table->string('author')->nullable();
            $table->string('status')->default('draft'); // draft, published
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Recipe Integration
        Schema::create('recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description');
            $table->json('ingredients');
            $table->json('steps')->nullable();
            $table->string('image_url')->nullable();
            $table->string('cuisine')->nullable();
            $table->integer('prep_time_min')->nullable();
            $table->integer('cook_time_min')->nullable();
            $table->string('difficulty')->default('medium');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('food_gallery');
    }
};
