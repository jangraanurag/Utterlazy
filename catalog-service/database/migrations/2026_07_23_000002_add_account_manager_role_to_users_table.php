<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL doesn't allow adding values to ENUM without ALTER.
        // Skip on other drivers (SQLite used by tests has no strict ENUM).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN admin_role ENUM('super_admin', 'city_manager', 'support_agent', 'finance_manager', 'account_manager') NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN admin_role ENUM('super_admin', 'city_manager', 'support_agent', 'finance_manager') NULL DEFAULT NULL");
        }
    }
};
