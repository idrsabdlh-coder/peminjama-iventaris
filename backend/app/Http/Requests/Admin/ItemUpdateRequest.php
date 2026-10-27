<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nama')) {
            $this->merge(['nama' => trim((string) $this->input('nama'))]);
        }
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'nama'              => ['sometimes', 'required', 'string', 'max:150',
                Rule::unique('items', 'nama_aktif')->ignore($id)],
            // tipe: spesifikasi/versi barang, contoh: "Epson EB-X51", "HP EliteBook 840 G8"
            'tipe'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'deskripsi'         => ['sometimes', 'nullable', 'string', 'max:2000'],
            'kategori_id'       => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')],
            'total'             => ['sometimes', 'required', 'integer', 'min:0', 'max:100000'],
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
