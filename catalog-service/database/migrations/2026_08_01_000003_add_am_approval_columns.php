<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prod's recorded account-manager migration predates the approval columns.
 * Idempotent add: sponsored_listings.approval_status / approved_via and
 * offers.created_by_* / approval_status / approved_at / approved_via / rejection_reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsored_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('sponsored_listings', 'approval_status')) {
                $table->string('approval_status', 30)->default('not_required')->after('rejection_reason');
            }
            if (! Schema::hasColumn('sponsored_listings', 'approved_via')) {
                $table->string('approved_via', 30)->nullable()->after('approved_at');
            }
        });

        Schema::table('offers', function (Blueprint $table) {
            if (! Schema::hasColumn('offers', 'created_by_type')) {
                $table->string('created_by_type', 30)->nullable()->after('status');
            }
            if (! Schema::hasColumn('offers', 'created_by_id')) {
                $table->uuid('created_by_id')->nullable()->after('created_by_type');
            }
            if (! Schema::hasColumn('offers', 'approval_status')) {
                $table->string('approval_status', 30)->default('not_required')->after('created_by_id');
            }
            if (! Schema::hasColumn('offers', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('offers', 'approved_via')) {
                $table->string('approved_via', 30)->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('offers', 'rejection_reason')) {
                $table->string('rejection_reason')->nullable()->after('approved_via');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sponsored_listings', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approved_via']);
        });
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['created_by_type', 'created_by_id', 'approval_status', 'approved_at', 'approved_via', 'rejection_reason']);
        });
    }
};
