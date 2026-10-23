<?php

use App\Support\Skema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_claim_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('claim_id');
            $table->unsignedInteger('loan_item_id');
            $table->enum('kondisi_dilaporkan', ['baik', 'rusak', 'hilang']);
            $table->integer('qty');
            // Kosong semua sebelum verifikasi, terisi semua sesudahnya
            $table->integer('qty_baik')->nullable();
            $table->integer('qty_rusak')->nullable();
            $table->integer('qty_hilang')->nullable();
            $table->integer('qty_ditolak')->nullable();

            // Nama dipendekkan: batas nama indeks MySQL 64 karakter
            $table->unique(['claim_id', 'loan_item_id', 'kondisi_dilaporkan'], 'rcl_claim_item_kondisi_unique');
            $table->index('loan_item_id');

            $table->foreign('claim_id')->references('id')->on('return_claims')->restrictOnDelete();
            $table->foreign('loan_item_id')->references('id')->on('loan_items')->restrictOnDelete();
        });

        Skema::check('return_claim_lines', 'chk_rcl_qty', '`qty` >= 1');
        // BR-05: jumlah empat hasil = qty. Bila ada kolom NULL, CHECK lolos (belum diverifikasi)
        Skema::check(
            'return_claim_lines',
            'chk_rcl_hasil',
            '(`qty_baik` >= 0 AND `qty_rusak` >= 0 AND `qty_hilang` >= 0 AND `qty_ditolak` >= 0 '
            . 'AND `qty_baik` + `qty_rusak` + `qty_hilang` + `qty_ditolak` = `qty`)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('return_claim_lines');
    }
};