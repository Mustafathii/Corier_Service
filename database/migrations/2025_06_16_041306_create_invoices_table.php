<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Invoice Info
            $table->string('invoice_number')->unique(); // INV-2024-0001
            $table->enum('type', ['customer', 'driver_commission']); // نوع الفاتورة
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');

            // Customer/Driver Info
            $table->foreignId('customer_id')->nullable()->constrained('users'); // للعملاء
            $table->foreignId('driver_id')->nullable()->constrained('users'); // للسائقين
            $table->string('customer_name')->nullable(); // للعملاء الغير مسجلين
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('customer_address')->nullable();

            // Financial Details
            $table->decimal('subtotal', 10, 2)->default(0); // المبلغ قبل الضريبة
            $table->decimal('tax_rate', 5, 2)->default(0); // نسبة الضريبة
            $table->decimal('tax_amount', 10, 2)->default(0); // مبلغ الضريبة
            $table->decimal('discount_amount', 10, 2)->default(0); // الخصم
            $table->decimal('total_amount', 10, 2); // المبلغ الإجمالي
            $table->decimal('paid_amount', 10, 2)->default(0); // المبلغ المدفوع
            $table->decimal('remaining_amount', 10, 2)->default(0); // المبلغ المتبقي

            // Dates
            $table->date('issue_date'); // تاريخ الإصدار
            $table->date('due_date'); // تاريخ الاستحقاق
            $table->datetime('paid_at')->nullable(); // تاريخ الدفع

            // Additional Info
            $table->text('notes')->nullable(); // ملاحظات
            $table->json('payment_methods')->nullable(); // طرق الدفع المقبولة
            $table->string('period_from')->nullable(); // فترة الفاتورة من
            $table->string('period_to')->nullable(); // فترة الفاتورة إلى

            $table->timestamps();

            // Indexes
            $table->index(['customer_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index(['issue_date', 'due_date']);
            $table->index('invoice_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
