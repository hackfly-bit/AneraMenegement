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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nama klien');
            $table->string('email')->unique()->nullable()->comment('Email klien');
            $table->string('phone')->nullable()->comment('Nomor telepon');
            $table->text('address')->nullable()->comment('Alamat klien');
            $table->string('identity_number')->nullable()->comment('Nomor identitas (KTP/NPWP/etc)');
            $table->text('notes')->nullable()->comment('Catatan tambahan');
            $table->enum('status', ['active', 'archived'])->default('active')->comment('Status klien');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('email');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
