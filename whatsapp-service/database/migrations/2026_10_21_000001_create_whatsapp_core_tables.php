<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_contacts', function (Blueprint $table) {
            $table->string('wa_id', 15)->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->enum('language', ['hi', 'en'])->default('en');
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_consents', function (Blueprint $table) {
            $table->string('wa_id', 15)->primary();
            $table->boolean('utility')->default(true);
            $table->boolean('marketing')->default(false);
            $table->timestamp('marketing_opted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_sessions', function (Blueprint $table) {
            $table->string('wa_id', 15)->primary();
            $table->string('state', 40)->default('WELCOME');
            $table->json('context')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('wa_messages', function (Blueprint $table) {
            $table->string('wa_message_id', 64)->primary();
            $table->string('wa_id', 15)->index();
            $table->enum('direction', ['in', 'out']);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('wa_carts', function (Blueprint $table) {
            $table->string('wa_id', 15)->primary();
            $table->uuid('restaurant_id')->nullable();
            $table->json('lines');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_carts');
        Schema::dropIfExists('wa_messages');
        Schema::dropIfExists('wa_sessions');
        Schema::dropIfExists('wa_consents');
        Schema::dropIfExists('wa_contacts');
    }
};
