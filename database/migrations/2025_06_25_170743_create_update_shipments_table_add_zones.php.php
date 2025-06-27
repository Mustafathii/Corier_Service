<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // إضافة علاقات مع الجداول الجديدة
            $table->foreignId('governorate_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('city_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('zone_id')->nullable()->constrained()->onDelete('set null');

            // إضافة فهارس للبحث السريع
            $table->index(['governorate_id', 'city_id']);
            $table->index(['zone_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['governorate_id']);
            $table->dropForeign(['city_id']);
            $table->dropForeign(['zone_id']);
            $table->dropColumn(['governorate_id', 'city_id', 'zone_id']);
        });
    }
};
