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
        Schema::create('invoice_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade')->comment('ID invoice');
            $table->integer('term_number')->comment('Urutan termin');
            $table->decimal('percentage', 5, 2)->comment('Persentase dari total');
            $table->decimal('amount', 15, 2)->comment('Jumlah dalam rupiah');
            $table->date('due_date')->comment('Tanggal jatuh tempo termin');
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending')->comment('Status termin');
            $table->string('description')->nullable()->comment('Deskripsi termin');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('invoice_id');
            $table->index('due_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_terms');
    }
};
