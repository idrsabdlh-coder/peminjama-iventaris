<?php

use App\Support\Skema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
{
    Schema::create('items', function (Blueprint $table) {
        $table->increments('id');
        $table->string('nama', 150);
        $table->text('deskripsi')->nullable();
        $table->string('tipe')->nullable();
        $table->unsignedInteger('kategori_id');
        $table->integer('total');
        $table->integer('tersedia');
        $table->integer('kondisi')->default(0); // jumlah unit rusak
        $table->boolean('boleh_diperpanjang')->default(true);
        $table->dateTime('dibuat_pada')->useCurrent();
        $table->dateTime('diubah_pada')->useCurrent()->useCurrentOnUpdate();
        $table->dateTime('dihapus_pada')->nullable();
        $table->string('nama_aktif', 150)->nullable()
              ->storedAs('IF(`dihapus_pada` IS NULL, `nama`, NULL)')
              ->unique();

        $table->foreign('kategori_id')->references('id')->on('categories')
              ->restrictOnDelete();
    });

    DB::statement('ALTER TABLE items
        ADD CONSTRAINT chk_items_total CHECK (total >= 0),
        ADD CONSTRAINT chk_items_tersedia CHECK (tersedia >= 0),
        ADD CONSTRAINT chk_items_kondisi CHECK (kondisi >= 0),
        ADD CONSTRAINT chk_items_komposisi CHECK (tersedia + kondisi <= total),
        ADD CONSTRAINT chk_items_boleh CHECK (boleh_diperpanjang IN (0,1))');
}

   
  public function down(): void
  {
      Schema::dropIfExists('items');
  }
};