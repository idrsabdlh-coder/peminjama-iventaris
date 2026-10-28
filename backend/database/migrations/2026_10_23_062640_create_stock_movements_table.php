<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buku besar stok, hanya ditambah barisnya (BR-01)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('item_id');
            $table->enum('jenis', [
                'TAMBAH_STOK', 'KURANG_STOK', 'PINJAM', 'KOREKSI_PINJAM', 'BATAL_PINJAM',
                'VERIFIKASI_BAIK', 'VERIFIKASI_RUSAK', 'VERIFIKASI_HILANG',
                'PERBAIKAN_SELESAI', 'AFKIR', 'TANDAI_RUSAK',
            ]);
            $table->integer('delta_total')->default(0);
            $table->integer('delta_tersedia')->default(0);
            $table->integer('delta_kondisi')->default(0);
            $table->string('ref_tipe', 30)->nullable();
            $table->unsignedInteger('ref_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('catatan', 500)->nullable();
            $table->dateTime('dibuat_pada')->useCurrent();

            $table->index(['item_id', 'dibuat_pada']);

            $table->foreign('item_id')->references('id')->on('items')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};