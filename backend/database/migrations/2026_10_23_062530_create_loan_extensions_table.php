<?php

use App\Support\Skema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_extensions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('loan_id');
            $table->date('batas_lama');
            $table->date('batas_baru');
            $table->integer('tambahan_hari');
            $table->string('alasan', 500)->nullable();
            $table->enum('diajukan_oleh', ['peminjam', 'admin']);
            $table->unsignedInteger('user_id')->nullable();
            $table->dateTime('dibuat_pada')->useCurrent();

            $table->foreign('loan_id')->references('id')->on('loans')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Skema::check('loan_extensions', 'chk_loan_extensions_hari', '`tambahan_hari` >= 1');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_extensions');
    }
};