<?php
// database/migrations/YYYY_MM_DD_HHMMSS_drop_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // إيقاف فحص المفاتيح الخارجية مؤقتاً
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // البحث عن أي مفاتيح خارجية تشير إلى جدول payments
        $this->dropPaymentsForeignKeys();

        // حذف أي مفاتيح خارجية من جدول payments نفسه
        $this->dropForeignKeysFromPayments();

        // حذف جدول payments
        Schema::dropIfExists('payments');

        // حذف أي جداول متعلقة بالمدفوعات
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('payment_logs');
        Schema::dropIfExists('payment_transactions');

        // إعادة تفعيل فحص المفاتيح الخارجية
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        echo "✅ تم حذف جدول payments وجميع الجداول المتعلقة به بنجاح!\n";
    }

    /**
     * البحث عن وحذف المفاتيح الخارجية التي تشير لجدول payments
     */
    private function dropPaymentsForeignKeys(): void
    {
        $foreignKeys = DB::select("
            SELECT
                TABLE_NAME,
                COLUMN_NAME,
                CONSTRAINT_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE
                REFERENCED_TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME = 'payments'
        ");

        foreach ($foreignKeys as $fk) {
            $tableName = $fk->TABLE_NAME;
            $constraintName = $fk->CONSTRAINT_NAME;

            try {
                echo "🔧 حذف المفتاح الخارجي: {$constraintName} من جدول: {$tableName}\n";
                DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`");
            } catch (Exception $e) {
                echo "⚠️ لم يتم حذف {$constraintName}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * حذف المفاتيح الخارجية من جدول payments نفسه
     */
    private function dropForeignKeysFromPayments(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            // قائمة بالمفاتيح الخارجية المحتملة في جدول payments
            $possibleForeignKeys = [
                'user_id',
                'invoice_id',
                'shipment_id',
                'customer_id',
                'driver_id',
                'payment_method_id'
            ];

            foreach ($possibleForeignKeys as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    try {
                        $table->dropForeign(['payments_' . $column . '_foreign']);
                        echo "🔧 حذف مفتاح خارجي: payments_{$column}_foreign\n";
                    } catch (Exception $e) {
                        // المفتاح غير موجود أو له اسم مختلف
                        echo "⚠️ لم يتم العثور على مفتاح: payments_{$column}_foreign\n";
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // لا يمكن التراجع - هذا حذف نهائي
        throw new Exception('Cannot reverse payments table deletion. Table and data are permanently deleted.');
    }
};
