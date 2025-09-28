<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade')->comment('ID invoice');
            $table->foreignId('invoice_term_id')->nullable()->constrained('invoice_terms')->onDelete('set null')->comment('ID termin invoice');
            $table->decimal('amount', 15, 2)->comment('Jumlah pembayaran');
            $table->date('payment_date')->comment('Tanggal pembayaran');
            $table->enum('payment_method', ['cash', 'transfer', 'check', 'other'])->default('transfer')->comment('Metode pembayaran');
            $table->string('reference_number')->nullable()->comment('Nomor referensi transfer');
            $table->text('notes')->nullable()->comment('Catatan pembayaran');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('invoice_id');
            $table->index('invoice_term_id');
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
