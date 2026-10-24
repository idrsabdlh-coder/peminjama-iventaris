<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('aksi', 60);
            $table->string('entitas', 60);
            $table->unsignedInteger('entitas_id')->nullable();
            $table->json('detail')->nullable();
            $table->dateTime('dibuat_pada')->useCurrent();

            $table->index(['entitas', 'entitas_id']);

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};