<?php

namespace App\Http\Requests\Kiosk;

use Illuminate\Foundation\Http\FormRequest;

class PembatalanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kode_pinjam' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('kode_pinjam'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'kode_pinjam' => ['required', 'string', 'max:20'],
            'alasan' => ['nullable', 'string', 'max:500'],
        ];
    }
}