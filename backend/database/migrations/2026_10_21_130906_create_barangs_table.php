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
        Schema::create('barangs', function (Blueprint $table) {
            $table->id();
            $table->string('nama_barang');
            
            // Relasi ke kategori (BR-28: Barang wajib punya kategori)
            $table->foreignId('kategori_id')->constrained('kategoris')->restrictOnDelete();
            
            // Kolom Stok (Sesuai Invarian BR-01: tersedia + rusak + dipinjam + menunggu_verifikasi = total)
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('tersedia')->default(0);
            $table->unsignedInteger('dipinjam')->default(0);
            $table->unsignedInteger('menunggu_verifikasi')->default(0);
            $table->unsignedInteger('rusak')->default(0);
            
            // Aturan D-12: Penanda boleh diperpanjang (default true/ya)
            $table->boolean('boleh_diperpanjang')->default(true);
            
            $table->timestamps();
            $table->softDeletes(); // Untuk soft-delete (BR-14)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangs');
    }
};