<?php
// database/migrations/YYYY_MM_DD_HHMMSS_fix_drop_invoice_tables.php

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

        // الطريقة الأولى: حذف المفاتيح الخارجية أولاً
        $this->dropForeignKeys();

        // ثم حذف الجداول بالترتيب الصحيح
        $this->dropTables();

        // إعادة تفعيل فحص المفاتيح الخارجية
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * حذف جميع المفاتيح الخارجية المتعلقة بالفواتير
     */
    private function dropForeignKeys(): void
    {
        // حذف المفتاح الخارجي من جدول payments
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                // حذف المفتاح الخارجي
                if (Schema::hasColumn('payments', 'invoice_id')) {
                    $table->dropForeign(['invoice_id']);
                    $table->dropColumn('invoice_id');
                }
            });
        }

        // حذف المفاتيح الخارجية من أي جداول أخرى
        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (Schema::hasColumn('shipments', 'invoice_id')) {
                    $table->dropForeign(['invoice_id']);
                    $table->dropColumn('invoice_id');
                }
            });
        }

        // حذف من جدول invoice_items إذا كان موجود
        if (Schema::hasTable('invoice_items')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                if (Schema::hasColumn('invoice_items', 'invoice_id')) {
                    $table->dropForeign(['invoice_id']);
                }
            });
        }

        // البحث عن أي مفاتيح خارجية أخرى تشير للفواتير
        $this->findAndDropInvoiceForeignKeys();
    }

    /**
     * البحث عن وحذف جميع المفاتيح الخارجية المتعلقة بالفواتير
     */
    private function findAndDropInvoiceForeignKeys(): void
    {
        // استعلام للبحث عن جميع المفاتيح الخارجية التي تشير لجدول invoices
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
                AND REFERENCED_TABLE_NAME = 'invoices'
        ");

        foreach ($foreignKeys as $fk) {
            $tableName = $fk->TABLE_NAME;
            $constraintName = $fk->CONSTRAINT_NAME;

            echo "🔧 حذف المفتاح الخارجي: {$constraintName} من جدول: {$tableName}\n";

            // حذف المفتاح الخارجي
            DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`");
        }
    }

    /**
     * حذف الجداول بالترتيب الصحيح
     */
    private function dropTables(): void
    {
        // ترتيب الحذف مهم جداً: الجداول التابعة أولاً
        $tablesToDrop = [
            'invoice_items',
            'invoice_payments',
            'invoice_settings',
            'invoice_templates',
            'invoices', // الجدول الرئيسي أخيراً
        ];

        foreach ($tablesToDrop as $table) {
            if (Schema::hasTable($table)) {
                echo "🗑️ حذف جدول: {$table}\n";
                Schema::dropIfExists($table);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // لا يمكن التراجع - هذا حذف نهائي
        throw new Exception('Cannot reverse invoice tables deletion. Tables and data are permanently deleted.');
    }
};
