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
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('Judul konten');
            $table->string('slug')->unique()->comment('URL slug');
            $table->longText('content')->nullable()->comment('Isi konten');
            $table->text('excerpt')->nullable()->comment('Ringkasan konten');
            $table->enum('type', ['page', 'post', 'document'])->default('page')->comment('Tipe konten');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->comment('Status konten');
            $table->dateTime('published_at')->nullable()->comment('Tanggal publikasi');
            $table->string('meta_title')->nullable()->comment('Meta title untuk SEO');
            $table->text('meta_description')->nullable()->comment('Meta description untuk SEO');
            $table->timestamps();

            // Indexes untuk performa
            $table->index('slug');
            $table->index('status');
            $table->index('type');
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
