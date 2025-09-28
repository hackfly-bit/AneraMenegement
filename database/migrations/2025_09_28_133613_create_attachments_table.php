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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type')->comment('Tipe model yang memiliki attachment');
            $table->unsignedBigInteger('attachable_id')->comment('ID model yang memiliki attachment');
            $table->string('filename')->comment('Nama file asli');
            $table->string('stored_filename')->comment('Nama file yang disimpan');
            $table->string('path')->comment('Path file');
            $table->string('mime_type')->comment('Tipe MIME file');
            $table->unsignedBigInteger('size')->comment('Ukuran file dalam bytes');
            $table->text('description')->nullable()->comment('Deskripsi file');
            $table->timestamps();

            // Indexes untuk performa
            $table->index(['attachable_type', 'attachable_id']);
            $table->index('mime_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
