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
        Schema::create('project_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade')->comment('ID project');
            $table->foreignId('product_id')->constrained()->onDelete('cascade')->comment('ID product');
            $table->integer('quantity')->default(1)->comment('Jumlah produk');
            $table->decimal('unit_price', 15, 2)->comment('Harga per unit');
            $table->decimal('total_price', 15, 2)->comment('Total harga');
            $table->text('notes')->nullable()->comment('Catatan tambahan');
            $table->timestamps();

            // Unique constraint untuk mencegah duplikasi
            $table->unique(['project_id', 'product_id']);
            
            // Indexes untuk performa
            $table->index('project_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_products');
    }
};
