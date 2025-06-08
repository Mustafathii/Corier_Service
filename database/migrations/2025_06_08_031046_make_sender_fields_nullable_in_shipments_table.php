<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('sender_name')->nullable()->change();
            $table->string('sender_phone')->nullable()->change();
            $table->text('sender_address')->nullable()->change();
            $table->string('sender_city')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('sender_name')->nullable(false)->change();
            $table->string('sender_phone')->nullable(false)->change();
            $table->text('sender_address')->nullable(false)->change();
            $table->string('sender_city')->nullable(false)->change();
        });
    }
};
