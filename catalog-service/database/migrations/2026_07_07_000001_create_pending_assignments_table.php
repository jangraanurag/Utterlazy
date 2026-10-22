<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->integer('attempt_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_assignments');
    }
};
