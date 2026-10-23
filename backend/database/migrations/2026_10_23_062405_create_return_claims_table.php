<?php

use App\Support\Skema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_claims', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode_klaim', 12)->unique();
            $table->unsignedInteger('loan_id');
            $table->string('catatan_peminjam', 500)->nullable();
            $table->dateTime('diklaim_pada')->useCurrent();
            $table->integer('hari_terlambat')->default(0); // dibekukan saat klaim dikirim (BR-10)
            $table->enum('status', ['menunggu', 'diverifikasi'])->default('menunggu');
            $table->unsignedInteger('diverifikasi_oleh')->nullable();
            $table->dateTime('diverifikasi_pada')->nullable();
            $table->string('catatan_admin', 500)->nullable();
            $table->char('idempotency_key', 36)->nullable()->unique();

            $table->index(['status', 'diklaim_pada']);

            $table->foreign('loan_id')->references('id')->on('loans')->restrictOnDelete();
            $table->foreign('diverifikasi_oleh')->references('id')->on('users')->restrictOnDelete();
        });

        Skema::check('return_claims', 'chk_return_claims_terlambat', '`hari_terlambat` >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('return_claims');
    }
};