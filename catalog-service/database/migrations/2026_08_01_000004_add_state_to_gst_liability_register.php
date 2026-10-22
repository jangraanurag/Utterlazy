<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scale GstLiabilityRegister per state (each state GSTIN files separately):
 * add state column, unique (state, filing_period) instead of company-wide
 * filing_period only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gst_liability_register', function (Blueprint $table) {
            $table->string('state')->nullable()->after('filing_period');
        });

        Schema::table('gst_liability_register', function (Blueprint $table) {
            $table->dropUnique('gst_liability_register_filing_period_unique');
        });

        Schema::table('gst_liability_register', function (Blueprint $table) {
            $table->unique(['state', 'filing_period']);
        });
    }

    public function down(): void
    {
        Schema::table('gst_liability_register', function (Blueprint $table) {
            $table->dropUnique('gst_liability_register_state_filing_period_unique');
        });
        Schema::table('gst_liability_register', function (Blueprint $table) {
            $table->unique('filing_period');
        });
        Schema::table('gst_liability_register', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
