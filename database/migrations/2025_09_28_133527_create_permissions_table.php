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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Nama permission');
            $table->string('display_name')->comment('Nama tampilan permission');
            $table->text('description')->nullable()->comment('Deskripsi permission');
            $table->string('module')->comment('Modul terkait');
            $table->boolean('is_active')->default(true)->comment('Status aktif permission');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('name');
            $table->index('module');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
