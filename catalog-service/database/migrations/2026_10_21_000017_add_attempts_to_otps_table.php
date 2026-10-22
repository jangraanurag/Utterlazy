<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempts')->default(0)->after('verified');
            $table->index(['phone', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->dropIndex(['phone', 'created_at']);
            $table->dropColumn('attempts');
        });
    }
};
