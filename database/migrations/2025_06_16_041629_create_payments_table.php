<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained();
            $table->string('payment_number')->unique(); // PAY-2024-0001

            // Payment Details
            $table->decimal('amount', 10, 2); // المبلغ المدفوع
            $table->enum('payment_method', [
                'cash',
                'bank_transfer',
                'credit_card',
                'electronic_wallet',
                'cheque',
                'vodafone_cash',
                'orange_cash',
                'etisalat_cash'
            ]);
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');

            // Transaction Info
            $table->string('transaction_id')->nullable(); // معرف المعاملة من البنك
            $table->string('reference_number')->nullable(); // رقم مرجعي
            $table->datetime('payment_date'); // تاريخ الدفع
            $table->datetime('processed_at')->nullable(); // تاريخ المعالجة

            // Additional Info
            $table->text('notes')->nullable(); // ملاحظات
            $table->json('gateway_response')->nullable(); // استجابة بوابة الدفع
            $table->string('receipt_url')->nullable(); // رابط الإيصال

            // User Info
            $table->foreignId('received_by')->nullable()->constrained('users'); // من استلم الدفعة

            $table->timestamps();

            // Indexes
            $table->index(['invoice_id', 'status']);
            $table->index(['payment_method', 'status']);
            $table->index('payment_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
