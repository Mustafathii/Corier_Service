<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use raw SQL to modify the enum column default value
        DB::statement("
            ALTER TABLE shipments
            MODIFY COLUMN status ENUM(
                'pending',
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'failed_delivery',
                'returned',
                'canceled'
            ) DEFAULT 'in_transit'
        ");
    }

    public function down(): void
    {
        // Revert back to 'pending' as default
        DB::statement("
            ALTER TABLE shipments
            MODIFY COLUMN status ENUM(
                'pending',
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'failed_delivery',
                'returned',
                'canceled'
            ) DEFAULT 'pending'
        ");
    }
};
