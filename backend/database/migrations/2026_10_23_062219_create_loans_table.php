<?php

use App\Support\Skema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Status TIDAK disimpan: dihitung dari data
        Schema::create('loans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode_pinjam', 12)->unique();
            $table->string('nama_peminjam', 100);
            $table->unsignedInteger('division_id');
            $table->enum('tipe_peminjam', ['karyawan', 'magang']);
            $table->string('telepon', 20)->nullable();
            $table->date('tanggal_pinjam');
            $table->date('batas_kembali_awal');
            $table->date('batas_kembali');
            $table->char('idempotency_key', 36)->nullable()->unique();
            $table->dateTime('dibuat_pada')->useCurrent();
            $table->dateTime('dibatalkan_pada')->nullable();
            $table->string('alasan_batal', 500)->nullable();
            $table->unsignedInteger('dibatalkan_oleh')->nullable();

            $table->index('batas_kembali');
            $table->index('tipe_peminjam');

            $table->foreign('division_id')->references('id')->on('divisions')->restrictOnDelete();
            $table->foreign('dibatalkan_oleh')->references('id')->on('users')->restrictOnDelete();
        });

        Skema::check('loans', 'chk_loans_batas', '`batas_kembali` >= `tanggal_pinjam`');
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};