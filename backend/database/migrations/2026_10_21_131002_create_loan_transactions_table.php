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
        // 1. Tabel Utama Peminjaman / Pemesanan (Loans)
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            // Format kode pinjam PJM-XXXXXX (BR-17)
            $table->string('kode_pinjam')->unique();
            
            $table->string('nama_peminjam', 100);
            
            // Relasi ke divisi aktif (BR-27)
            $table->foreignId('divisi_id')->constrained('divisis')->restrictOnDelete();
            
            // Tipe peminjam: 'karyawan' atau 'magang' (BR-30)
            $table->enum('tipe_peminjam', ['karyawan', 'magang']);
            
            $table->string('telepon', 15)->nullable();
            
            // Tanggal pinjam / pemesanan (D-6, BR-23)
            $table->date('tanggal_pinjam');
            // Batas kembali / jatuh tempo
            $table->date('batas_kembali');
            
            // Kuota perpanjangan yang sudah terpakai (BR-11)
            $table->unsignedInteger('jumlah_perpanjangan')->default(0);
            
            // Status utama peminjaman
            $table->enum('status', ['dipesan', 'dipinjam', 'terlambat', 'menunggu_verifikasi', 'selesai', 'dibatalkan'])
                  ->default('dipinjam');
                  
            $table->timestamps();
        });

        // 2. Tabel Detail Barang per Peminjaman (Loan Items)
        Schema::create('loan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barangs')->restrictOnDelete();
            
            $table->unsignedInteger('qty_dipinjam');
            $table->unsignedInteger('qty_menunggu')->default(0); // Menunggu verifikasi admin
            $table->unsignedInteger('qty_kembali_baik')->default(0);
            $table->unsignedInteger('qty_kembali_rusak')->default(0);
            $table->unsignedInteger('qty_hilang')->default(0);
            
            $table->timestamps();
        });

        // 3. Buku Besar Perubahan Stok / Ledger (FR-S-02)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barangs')->restrictOnDelete();
            
            // Sumber transaksi terkait (bisa loan_id atau null jika koreksi admin)
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            
            // Jenis aksi: pinjam, pesan, kembali_baik, kembali_rusak, hilang, perbaikan, afkir, koreksi
            $table->string('tipe_aksi', 50);
            
            // Perubahan jumlah (bisa positif atau negatif)
            $table->integer('perubahan_tersedia')->default(0);
            $table->integer('perubahan_dipinjam')->default(0);
            $table->integer('perubahan_menunggu')->default(0);
            $table->integer('perubahan_rusak')->default(0);
            $table->integer('perubahan_total')->default(0);
            
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('loan_items');
        Schema::dropIfExists('loans');
    }
};