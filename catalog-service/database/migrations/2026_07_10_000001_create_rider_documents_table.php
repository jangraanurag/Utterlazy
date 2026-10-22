<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('rider_id');
            $table->enum('document_type', [
                'aadhaar_front',
                'aadhaar_back',
                'driving_license_front',
                'driving_license_back',
                'pan_card',
                'selfie',
                'vehicle_rc',
                'insurance',
            ]);
            $table->string('file_path');
            $table->string('document_number')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamps();

            $table->foreign('rider_id')->references('id')->on('riders')->cascadeOnDelete();
            $table->unique(['rider_id', 'document_type']);
            $table->index(['rider_id', 'status']);
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])
                ->default('unverified')
                ->after('is_verified');
            $table->text('verification_notes')->nullable()->after('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'verification_notes']);
        });
        Schema::dropIfExists('rider_documents');
    }
};
