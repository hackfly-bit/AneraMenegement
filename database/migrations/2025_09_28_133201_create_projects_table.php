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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade')->comment('ID klien');
            $table->string('name')->comment('Nama proyek');
            $table->text('description')->nullable()->comment('Deskripsi proyek');
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft')->comment('Status proyek');
            $table->decimal('value', 15, 2)->nullable()->comment('Nilai proyek');
            $table->date('start_date')->nullable()->comment('Tanggal mulai');
            $table->date('end_date')->nullable()->comment('Tanggal selesai');
            $table->text('notes')->nullable()->comment('Catatan tambahan');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('client_id');
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
