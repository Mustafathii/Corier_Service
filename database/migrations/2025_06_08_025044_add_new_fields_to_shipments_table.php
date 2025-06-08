<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Client type and seller relationship
            if (!Schema::hasColumn('shipments', 'is_existing_seller')) {
                $table->boolean('is_existing_seller')->default(true)->after('tracking_number');
            }

            if (!Schema::hasColumn('shipments', 'seller_id')) {
                $table->foreignId('seller_id')->nullable()->constrained('users')->onDelete('set null')->after('tracking_number');
            }

            // Add city field (you already have receiver_city, but we need a general city field)
            if (!Schema::hasColumn('shipments', 'city')) {
                $table->string('city')->nullable()->after('receiver_city');
            }

            // Shipment type
            if (!Schema::hasColumn('shipments', 'shipment_type')) {
                $table->enum('shipment_type', ['express', 'standard', 'same_day'])->default('standard')->after('expected_delivery_date');
            }

            // Package quantity
            if (!Schema::hasColumn('shipments', 'quantity')) {
                $table->integer('quantity')->default(1)->after('weight');
            }

            // Payment information
            if (!Schema::hasColumn('shipments', 'payment_method')) {
                $table->enum('payment_method', ['cod', 'prepaid', 'electronic_wallet'])->default('cod')->after('description');
            }

            if (!Schema::hasColumn('shipments', 'shipping_cost')) {
                $table->decimal('shipping_cost', 8, 2)->default(0)->after('payment_method');
            }

            if (!Schema::hasColumn('shipments', 'cod_amount')) {
                $table->decimal('cod_amount', 8, 2)->nullable()->after('shipping_cost');
            }

            // Additional notes for internal use
            if (!Schema::hasColumn('shipments', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('notes');
            }

            // Track who created the shipment (only if it doesn't exist)
            if (!Schema::hasColumn('shipments', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('internal_notes');
            }
        });

        // Add indexes separately to avoid conflicts
        Schema::table('shipments', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('shipments');

            if (!array_key_exists('shipments_status_created_at_index', $indexesFound)) {
                $table->index(['status', 'created_at']);
            }

            if (!array_key_exists('shipments_driver_id_status_index', $indexesFound)) {
                $table->index(['driver_id', 'status']);
            }

            if (!array_key_exists('shipments_seller_id_created_at_index', $indexesFound)) {
                $table->index(['seller_id', 'created_at']);
            }

            if (!array_key_exists('shipments_expected_delivery_date_index', $indexesFound)) {
                $table->index('expected_delivery_date');
            }

            if (!array_key_exists('shipments_shipment_type_index', $indexesFound)) {
                $table->index('shipment_type');
            }

            if (!array_key_exists('shipments_payment_method_index', $indexesFound)) {
                $table->index('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Drop indexes first (check if they exist)
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('shipments');

            if (array_key_exists('shipments_status_created_at_index', $indexesFound)) {
                $table->dropIndex(['status', 'created_at']);
            }
            if (array_key_exists('shipments_driver_id_status_index', $indexesFound)) {
                $table->dropIndex(['driver_id', 'status']);
            }
            if (array_key_exists('shipments_seller_id_created_at_index', $indexesFound)) {
                $table->dropIndex(['seller_id', 'created_at']);
            }
            if (array_key_exists('shipments_expected_delivery_date_index', $indexesFound)) {
                $table->dropIndex(['expected_delivery_date']);
            }
            if (array_key_exists('shipments_shipment_type_index', $indexesFound)) {
                $table->dropIndex(['shipment_type']);
            }
            if (array_key_exists('shipments_payment_method_index', $indexesFound)) {
                $table->dropIndex(['payment_method']);
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            // Drop foreign key constraints (check if they exist)
            if (Schema::hasColumn('shipments', 'seller_id')) {
                $table->dropForeign(['seller_id']);
            }
            if (Schema::hasColumn('shipments', 'created_by')) {
                $table->dropForeign(['created_by']);
            }

            // Drop columns (check if they exist)
            $columnsToDrop = [];

            if (Schema::hasColumn('shipments', 'is_existing_seller')) {
                $columnsToDrop[] = 'is_existing_seller';
            }
            if (Schema::hasColumn('shipments', 'seller_id')) {
                $columnsToDrop[] = 'seller_id';
            }
            if (Schema::hasColumn('shipments', 'city')) {
                $columnsToDrop[] = 'city';
            }
            if (Schema::hasColumn('shipments', 'shipment_type')) {
                $columnsToDrop[] = 'shipment_type';
            }
            if (Schema::hasColumn('shipments', 'quantity')) {
                $columnsToDrop[] = 'quantity';
            }
            if (Schema::hasColumn('shipments', 'payment_method')) {
                $columnsToDrop[] = 'payment_method';
            }
            if (Schema::hasColumn('shipments', 'shipping_cost')) {
                $columnsToDrop[] = 'shipping_cost';
            }
            if (Schema::hasColumn('shipments', 'cod_amount')) {
                $columnsToDrop[] = 'cod_amount';
            }
            if (Schema::hasColumn('shipments', 'internal_notes')) {
                $columnsToDrop[] = 'internal_notes';
            }

            // Only drop columns that exist and were added by this migration
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
