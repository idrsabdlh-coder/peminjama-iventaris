<?php

use App\Support\Skema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama', 150);
            $table->text('deskripsi')->nullable();
            $table->unsignedInteger('kategori_id');
            $table->integer('total');
            $table->integer('tersedia');
            $table->integer('rusak')->default(0);
            $table->boolean('boleh_diperpanjang')->default(true);
            $table->dateTime('dibuat_pada')->useCurrent();
            $table->dateTime('diubah_pada')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('dihapus_pada')->nullable();

            // Nama hanya harus unik di antara barang aktif (BR-14)
            $table->string('nama_aktif', 150)->nullable()
                ->storedAs('IF(`dihapus_pada` IS NULL, `nama`, NULL)')
                ->unique();

            $table->foreign('kategori_id')->references('id')->on('categories')->restrictOnDelete();
        });

        Skema::check('items', 'chk_items_total', '`total` >= 0');
        Skema::check('items', 'chk_items_tersedia', '`tersedia` >= 0');
        Skema::check('items', 'chk_items_rusak', '`rusak` >= 0');
        Skema::check('items', 'chk_items_komposisi', '`tersedia` + `rusak` <= `total`');
        Skema::check('items', 'chk_items_boleh', '`boleh_diperpanjang` IN (0, 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};