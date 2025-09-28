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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade')->comment('ID invoice');
            $table->foreignId('product_id')->constrained()->onDelete('cascade')->comment('ID product');
            $table->string('description')->comment('Deskripsi item');
            $table->integer('quantity')->comment('Jumlah item');
            $table->decimal('unit_price', 15, 2)->comment('Harga per unit');
            $table->decimal('total_price', 15, 2)->comment('Total harga');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('invoice_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
