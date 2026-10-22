<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fcm_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('fcm_tokens', 'rider_id')) {
                $table->foreignUuid('rider_id')->nullable()->after('restaurant_id')->constrained('riders')->nullOnDelete();
                $table->index(['rider_id', 'app_type']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('fcm_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rider_id');
        });
    }
};
