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
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade')->comment('ID akun');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null')->comment('ID invoice');
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('set null')->comment('ID proyek');
            $table->enum('type', ['income', 'expense'])->comment('Tipe transaksi');
            $table->decimal('amount', 15, 2)->comment('Jumlah transaksi');
            $table->date('transaction_date')->comment('Tanggal transaksi');
            $table->string('description')->comment('Deskripsi transaksi');
            $table->string('reference_number')->nullable()->comment('Nomor referensi');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('account_id');
            $table->index('transaction_date');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_transactions');
    }
};
