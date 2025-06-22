<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            // تعديل الحقول لتكون لها قيم افتراضية
            $table->decimal('subtotal', 10, 2)->default(0)->change();
            $table->decimal('tax_amount', 10, 2)->default(0)->change();
            $table->decimal('total_amount', 10, 2)->default(0)->change();
            $table->decimal('paid_amount', 10, 2)->default(0)->change();
            $table->decimal('remaining_amount', 10, 2)->default(0)->change();
        });
    }

    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->nullable()->change();
            $table->decimal('tax_amount', 10, 2)->nullable()->change();
            $table->decimal('total_amount', 10, 2)->nullable()->change();
            $table->decimal('paid_amount', 10, 2)->nullable()->change();
            $table->decimal('remaining_amount', 10, 2)->nullable()->change();
        });
    }
};
