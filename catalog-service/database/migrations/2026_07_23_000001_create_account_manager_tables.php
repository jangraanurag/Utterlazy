<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Assignments: AM → Restaurant
        Schema::create('account_manager_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('account_manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->uuid('assigned_by')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique(['account_manager_id', 'restaurant_id', 'is_active'], 'am_assignment_unique');
            $table->timestamps();
        });

        // Add approval status to sponsored_listings
        Schema::table('sponsored_listings', function (Blueprint $table) {
            $table->string('approval_status', 30)->default('not_required')->after('rejection_reason'); // not_required, pending, approved, rejected, expired
            $table->string('approved_via', 30)->nullable()->after('approved_at'); // email_otp
        });

        // Add approval fields to offers table
        Schema::table('offers', function (Blueprint $table) {
            $table->string('created_by_type', 30)->nullable()->after('status'); // restaurant, account_manager, admin
            $table->uuid('created_by_id')->nullable()->after('created_by_type');
            $table->string('approval_status', 30)->default('not_required')->after('created_by_id'); // not_required, pending, approved, rejected, expired
            $table->timestamp('approved_at')->nullable()->after('approval_status');
            $table->string('approved_via', 30)->nullable()->after('approved_at'); // email_otp
            $table->string('rejection_reason')->nullable()->after('approved_via');
        });

        // Approval OTPs
        Schema::create('restaurant_approval_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('entity_type', 50); // sponsored_listing, offer
            $table->uuid('entity_id');
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->integer('attempt_count')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });

        // AM Activity Log
        Schema::create('account_manager_activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('account_manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('restaurant_id')->nullable()->constrained('restaurants')->nullOnDelete();
            $table->string('action', 100); // created_ad, created_offer, approved, rejected
            $table->string('entity_type', 50)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['account_manager_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_manager_activity_log');
        Schema::dropIfExists('restaurant_approval_otps');
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['created_by_type', 'created_by_id', 'approval_status', 'approved_at', 'approved_via', 'rejection_reason']);
        });
        Schema::dropIfExists('account_manager_assignments');
    }
};
