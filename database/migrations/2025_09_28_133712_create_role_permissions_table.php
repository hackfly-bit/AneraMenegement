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
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade')->comment('ID role');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade')->comment('ID permission');
            $table->timestamps();

            // Unique constraint untuk mencegah duplikasi
            $table->unique(['role_id', 'permission_id']);
            
            // Indexes untuk performa
            $table->index('role_id');
            $table->index('permission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
