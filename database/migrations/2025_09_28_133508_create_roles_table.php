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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Nama role');
            $table->string('display_name')->comment('Nama tampilan role');
            $table->text('description')->nullable()->comment('Deskripsi role');
            $table->boolean('is_active')->default(true)->comment('Status aktif role');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('name');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
