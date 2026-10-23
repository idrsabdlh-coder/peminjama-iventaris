<?php

use App\Support\Skema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('loan_id');
            $table->unsignedInteger('item_id');
            $table->integer('qty_dipinjam');

            $table->unique(['loan_id', 'item_id']); // barang tidak ganda (BR-22)

            $table->foreign('loan_id')->references('id')->on('loans')->restrictOnDelete();
            $table->foreign('item_id')->references('id')->on('items')->restrictOnDelete();
        });

        Skema::check('loan_items', 'chk_loan_items_qty', '`qty_dipinjam` >= 1');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_items');
    }
};