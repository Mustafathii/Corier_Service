<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('shipment_id')->nullable()->constrained(); // ربط بالشحنة

            // Item Details
            $table->string('description'); // وصف البند
            $table->string('item_type')->default('shipment'); // shipment, service, fee, commission
            $table->integer('quantity')->default(1); // الكمية
            $table->decimal('unit_price', 8, 2); // سعر الوحدة
            $table->decimal('total_price', 8, 2); // السعر الإجمالي

            // Additional Details
            $table->string('tracking_number')->nullable(); // رقم التتبع للشحنة
            $table->string('service_type')->nullable(); // نوع الخدمة (express, standard, same_day)
            $table->text('notes')->nullable(); // ملاحظات

            $table->timestamps();

            // Indexes
            $table->index(['invoice_id', 'item_type']);
            $table->index('shipment_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoice_items');
    }
};
