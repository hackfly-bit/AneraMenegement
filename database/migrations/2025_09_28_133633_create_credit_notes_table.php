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
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade')->comment('ID invoice terkait');
            $table->string('credit_note_number')->unique()->comment('Nomor credit note');
            $table->date('credit_note_date')->comment('Tanggal credit note');
            $table->decimal('amount', 15, 2)->comment('Jumlah credit note');
            $table->text('reason')->comment('Alasan penerbitan credit note');
            $table->enum('status', ['draft', 'issued', 'applied'])->default('draft')->comment('Status credit note');
            $table->text('notes')->nullable()->comment('Catatan tambahan');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('invoice_id');
            $table->index('credit_note_number');
            $table->index('status');
            $table->index('credit_note_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
