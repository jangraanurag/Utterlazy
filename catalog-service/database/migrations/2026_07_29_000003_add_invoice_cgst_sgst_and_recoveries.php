<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'cgst_rate')) {
                $table->decimal('cgst_rate', 5, 2)->default(9.00)->after('amount');
            }
            if (! Schema::hasColumn('invoices', 'cgst_amount')) {
                $table->decimal('cgst_amount', 12, 2)->default(0)->after('cgst_rate');
            }
            if (! Schema::hasColumn('invoices', 'sgst_rate')) {
                $table->decimal('sgst_rate', 5, 2)->default(9.00)->after('cgst_amount');
            }
            if (! Schema::hasColumn('invoices', 'sgst_amount')) {
                $table->decimal('sgst_amount', 12, 2)->default(0)->after('sgst_rate');
            }
            if (! Schema::hasColumn('invoices', 'convenience_fee')) {
                $table->decimal('convenience_fee', 12, 2)->default(0)->after('sgst_amount');
            }
            if (! Schema::hasColumn('invoices', 'pdf_path')) {
                $table->string('pdf_path')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('invoices', 'entered_by')) {
                $table->unsignedBigInteger('entered_by')->nullable()->after('pdf_path');
            }
        });

        if (! Schema::hasTable('recoveries')) {
            Schema::create('recoveries', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
                $table->foreignUuid('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->string('reference_type')->nullable();
                $table->string('reference_id')->nullable();
                $table->decimal('amount', 12, 2);
                $table->text('notes')->nullable();
                $table->date('recovery_date');
                $table->enum('status', ['pending', 'invoiced', 'paid', 'waived'])->default('pending');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recoveries');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'cgst_rate', 'cgst_amount', 'sgst_rate', 'sgst_amount',
                'convenience_fee', 'pdf_path', 'entered_by',
            ]);
        });
    }
};
