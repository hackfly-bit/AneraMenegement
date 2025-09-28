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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Kode akun');
            $table->string('name')->comment('Nama akun');
            $table->enum('type', ['asset', 'liability', 'income', 'expense', 'equity'])->comment('Tipe akun');
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->onDelete('set null')->comment('Parent akun untuk hierarki');
            $table->boolean('is_active')->default(true)->comment('Status aktif akun');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('code');
            $table->index('type');
            $table->index('parent_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
