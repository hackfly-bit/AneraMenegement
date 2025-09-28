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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nama produk/layanan');
            $table->string('category')->nullable()->comment('Kategori produk');
            $table->decimal('base_price', 15, 2)->comment('Harga dasar');
            $table->text('description')->nullable()->comment('Deskripsi produk');
            $table->boolean('is_active')->default(true)->comment('Status aktif produk');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('is_active');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
