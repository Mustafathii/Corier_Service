<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Step 1: Clean up orphaned records first
        DB::statement('UPDATE shipments SET driver_id = NULL WHERE driver_id IS NOT NULL AND driver_id NOT IN (SELECT id FROM users)');

        Schema::table('shipments', function (Blueprint $table) {
            // Step 2: Check and drop existing foreign key if it exists
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_NAME = 'shipments'
                AND TABLE_SCHEMA = DATABASE()
                AND COLUMN_NAME = 'driver_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            // Drop foreign key if it exists
            foreach ($foreignKeys as $fk) {
                try {
                    DB::statement("ALTER TABLE shipments DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                } catch (\Exception $e) {
                    // Continue if foreign key doesn't exist
                }
            }

            // Step 3: Add new foreign key referencing users table
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Find and drop the foreign key
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_NAME = 'shipments'
                AND TABLE_SCHEMA = DATABASE()
                AND COLUMN_NAME = 'driver_id'
                AND REFERENCED_TABLE_NAME = 'users'
            ");

            foreach ($foreignKeys as $fk) {
                try {
                    DB::statement("ALTER TABLE shipments DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                } catch (\Exception $e) {
                    // Continue if foreign key doesn't exist
                }
            }
        });
    }
};
