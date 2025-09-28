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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade')->comment('ID klien');
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('set null')->comment('ID proyek');
            $table->string('invoice_number')->unique()->comment('Nomor invoice');
            $table->date('invoice_date')->comment('Tanggal invoice');
            $table->date('due_date')->comment('Tanggal jatuh tempo');
            $table->decimal('subtotal', 15, 2)->comment('Subtotal sebelum pajak');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Persentase pajak');
            $table->decimal('tax_amount', 15, 2)->default(0)->comment('Jumlah pajak');
            $table->decimal('total_amount', 15, 2)->comment('Total amount');
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft')->comment('Status invoice');
            $table->text('notes')->nullable()->comment('Catatan tambahan');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('client_id');
            $table->index('project_id');
            $table->index('invoice_number');
            $table->index('status');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
