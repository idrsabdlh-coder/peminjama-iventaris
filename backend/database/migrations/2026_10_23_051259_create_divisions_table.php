<?php

use App\Support\Skema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama', 100)->unique();
            $table->boolean('aktif')->default(true);
        });
        Skema::check('divisions', 'chk_divisions_aktif', '`aktif` IN (0, 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};