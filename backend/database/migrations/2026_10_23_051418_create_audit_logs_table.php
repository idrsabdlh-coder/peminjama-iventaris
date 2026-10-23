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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Definisikan secara eksplisit sebagai unsignedBigInteger agar cocok dengan id users
            $table->unsignedInteger('user_id')->nullable();
            
            $table->string('aksi');
            $table->string('entitas')->nullable();
            $table->unsignedBigInteger('entitas_id')->nullable();
            $table->json('detail')->nullable();
            $table->timestamp('dibuat_pada')->useCurrent();

            // Relasi foreign key secara eksplisit
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};