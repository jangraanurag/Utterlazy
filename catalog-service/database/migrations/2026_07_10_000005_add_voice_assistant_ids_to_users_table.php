<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('alexa_user_id')->nullable()->unique()->after('admin_role');
            $table->string('google_assistant_id')->nullable()->unique()->after('alexa_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['alexa_user_id', 'google_assistant_id']);
        });
    }
};
