<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('department');
            $table->string('unit_type');
            $table->uuid('parent_org_unit_id')->nullable();
            $table->json('geography_scope')->nullable();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_org_unit_id')->references('id')->on('org_units')->nullOnDelete();
            $table->index(['department', 'unit_type']);
        });

        Schema::create('org_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('org_unit_id');
            $table->string('title');
            $table->uuid('assigned_user_id')->nullable();
            $table->uuid('backup_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('org_unit_id')->references('id')->on('org_units')->cascadeOnDelete();
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('backup_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('org_unit_id');
        });

        Schema::create('approval_threshold_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('department');
            $table->string('unit_type');
            $table->string('entity_type');
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->integer('sla_hours')->default(24);
            $table->json('geography_scope')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department', 'unit_type', 'entity_type'], 'threshold_dept_unit_type_entity_idx');
        });

        Schema::create('approval_workflow_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('department');
            $table->string('entity_type');
            $table->string('entity_id');
            $table->uuid('origin_org_unit_id');
            $table->uuid('current_org_unit_id');
            $table->uuid('current_org_role_id')->nullable();
            $table->string('status');
            $table->decimal('amount', 14, 2)->nullable();
            $table->boolean('is_exception')->default(false);
            $table->json('threshold_snapshot')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('origin_org_unit_id')->references('id')->on('org_units');
            $table->foreign('current_org_unit_id')->references('id')->on('org_units');
            $table->foreign('current_org_role_id')->references('id')->on('org_roles')->nullOnDelete();
            $table->index(['status', 'sla_due_at']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_instance_id');
            $table->uuid('org_unit_id');
            $table->uuid('org_role_id')->nullable();
            $table->uuid('actor_user_id')->nullable();
            $table->string('action');
            $table->text('comment')->nullable();
            $table->uuid('to_org_unit_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('workflow_instance_id')->references('id')->on('approval_workflow_instances')->cascadeOnDelete();
            $table->foreign('org_unit_id')->references('id')->on('org_units');
            $table->foreign('to_org_unit_id')->references('id')->on('org_units')->nullOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['workflow_instance_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflow_instances');
        Schema::dropIfExists('approval_threshold_configs');
        Schema::dropIfExists('org_roles');
        Schema::dropIfExists('org_units');
    }
};
