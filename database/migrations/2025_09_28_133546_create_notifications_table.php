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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ID user penerima');
            $table->string('title')->comment('Judul notifikasi');
            $table->text('message')->comment('Isi pesan notifikasi');
            $table->enum('type', ['info', 'warning', 'error', 'success'])->default('info')->comment('Tipe notifikasi');
            $table->json('data')->nullable()->comment('Data tambahan dalam format JSON');
            $table->boolean('is_read')->default(false)->comment('Status sudah dibaca');
            $table->dateTime('read_at')->nullable()->comment('Waktu dibaca');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('user_id');
            $table->index('is_read');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
