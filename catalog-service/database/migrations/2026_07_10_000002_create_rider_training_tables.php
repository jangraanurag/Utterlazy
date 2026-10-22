<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_training_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description');
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rider_training_lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->string('title');
            $table->text('content');
            $table->string('video_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('rider_training_modules')->cascadeOnDelete();
        });

        Schema::create('rider_training_quiz_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->text('question');
            $table->json('options');
            $table->unsignedInteger('correct_index');
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('rider_training_modules')->cascadeOnDelete();
        });

        Schema::create('rider_training_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('rider_id');
            $table->uuid('module_id');
            $table->json('completed_lessons')->default('[]');
            $table->unsignedInteger('quiz_score')->default(0);
            $table->boolean('quiz_passed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('rider_id')->references('id')->on('riders')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('rider_training_modules')->cascadeOnDelete();
            $table->unique(['rider_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_training_progress');
        Schema::dropIfExists('rider_training_quiz_questions');
        Schema::dropIfExists('rider_training_lessons');
        Schema::dropIfExists('rider_training_modules');
    }
};
