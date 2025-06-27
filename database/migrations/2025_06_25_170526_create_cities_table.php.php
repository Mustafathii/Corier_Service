<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('governorate_id')->constrained()->onDelete('cascade');
            $table->string('city_name_ar');
            $table->string('city_name_en');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['governorate_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
