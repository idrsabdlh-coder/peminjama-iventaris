<?php

namespace App\Http\Controllers\Api\Admin;

class DivisionController extends MasterController
{
    protected function tabel(): string { return 'divisions'; }

    protected function entitas(): string { return 'divisi'; }

    protected function label(): string { return 'Divisi'; }

    protected function pemakai(): array { return ['loans', 'division_id', 'peminjaman']; }
}