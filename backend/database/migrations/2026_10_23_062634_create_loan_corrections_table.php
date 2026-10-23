<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_corrections', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('loan_id');
            $table->string('kolom', 50);
            $table->string('nilai_lama', 500)->nullable();
            $table->string('nilai_baru', 500)->nullable();
            $table->string('alasan', 500);
            $table->unsignedInteger('user_id');
            $table->dateTime('dibuat_pada')->useCurrent();

            $table->foreign('loan_id')->references('id')->on('loans')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_corrections');
    }
};