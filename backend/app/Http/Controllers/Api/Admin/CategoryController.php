<?php

namespace App\Http\Controllers\Api\Admin;

class CategoryController extends MasterController
{
    protected function tabel(): string { return 'categories'; }

    protected function entitas(): string { return 'kategori'; }

    protected function label(): string { return 'Kategori'; }

    protected function pemakai(): array { return ['items', 'kategori_id', 'barang']; }
}