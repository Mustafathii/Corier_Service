<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();

            // Sender Information
            $table->string('sender_name');
            $table->string('sender_phone');
            $table->text('sender_address');
            $table->string('sender_city');

            // Receiver Information
            $table->string('receiver_name');
            $table->string('receiver_phone');
            $table->text('receiver_address');
            $table->string('receiver_city');

            // Shipment Details
            $table->string('package_type');
            $table->decimal('weight', 8, 2);
            $table->text('description')->nullable();
            $table->decimal('declared_value', 10, 2)->nullable();

            // Status and Tracking
            $table->enum('status', [
                'pending',
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'failed_delivery',
                'returned',
                'canceled'
            ])->default('pending');

            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();

            // Dates
            $table->datetime('pickup_date')->nullable();
            $table->datetime('expected_delivery_date')->nullable();
            $table->datetime('actual_delivery_date')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
