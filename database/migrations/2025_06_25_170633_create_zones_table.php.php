<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('governorate_id')->constrained()->onDelete('cascade');
            $table->foreignId('city_id')->constrained()->onDelete('cascade');
            $table->string('zone_name');
            $table->decimal('shipping_cost', 8, 2)->default(0);
            $table->decimal('express_cost', 8, 2)->nullable();
            $table->decimal('same_day_cost', 8, 2)->nullable();
            $table->decimal('cod_fee', 8, 2)->default(0);
            $table->integer('estimated_delivery_days')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['governorate_id', 'city_id', 'is_active']);
            $table->unique(['governorate_id', 'city_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
