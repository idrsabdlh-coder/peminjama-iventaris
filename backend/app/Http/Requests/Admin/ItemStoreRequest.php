<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // akses sudah dijaga middleware auth:sanctum + admin.aktif
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nama' => trim((string) $this->input('nama'))]);
    }

    public function rules(): array
    {
        return [
            // BR-14: nama hanya harus unik di antara barang aktif (kolom nama_aktif).
            'nama'              => ['required', 'string', 'max:150', Rule::unique('items', 'nama_aktif')],
            // tipe: spesifikasi/versi barang, contoh: "Dell Latitude 5420 Core i5-1135G7"
            'tipe'              => ['nullable', 'string', 'max:255'],
            'deskripsi'         => ['nullable', 'string', 'max:2000'],
            'kategori_id'       => ['required', 'integer', Rule::exists('categories', 'id')],
            'total'             => ['required', 'integer', 'min:0', 'max:100000'],
            'boleh_diperpanjang'=> ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.unique'        => 'Nama barang sudah dipakai barang lain.',
            'kategori_id.exists' => 'Kategori tidak ditemukan.',
        ];
    }
}
