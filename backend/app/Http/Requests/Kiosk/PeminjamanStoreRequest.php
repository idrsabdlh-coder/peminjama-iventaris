<?php

namespace App\Http\Requests\Kiosk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PeminjamanStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // BR-20: kunci idempotensi dikirim lewat header Idempotency-Key.
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'nama_peminjam' => ['required', 'string', 'min:2', 'max:100'],
            'division_id' => ['required', 'integer', Rule::exists('divisions', 'id')->where('aktif', true)],
            'tipe_peminjam' => ['required', Rule::in(['karyawan', 'magang'])],
            'telepon' => ['nullable', 'regex:/^\d{8,15}$/'],
            'tanggal_pinjam' => ['required', 'date_format:Y-m-d'],
            'batas_kembali' => ['required', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'Permintaan tidak lengkap. Muat ulang halaman lalu coba lagi.',
            'idempotency_key.uuid' => 'Permintaan tidak lengkap. Muat ulang halaman lalu coba lagi.',
            'items.required' => 'Pilih minimal satu barang.',
            'items.min' => 'Pilih minimal satu barang.',
            'items.*.item_id.distinct' => 'Barang yang sama tidak boleh muncul dua kali.',
            'telepon.regex' => 'Telepon harus berupa 8 sampai 15 digit angka.',
        ];
    }

    // BR-23: aturan tanggal, zona Asia/Jakarta (BR-21).
    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->hasAny(['tanggal_pinjam', 'batas_kembali'])) {
                return;
            }

            $hariIni = Carbon::now('Asia/Jakarta')->startOfDay();
            $pinjam = Carbon::createFromFormat('Y-m-d', $this->input('tanggal_pinjam'), 'Asia/Jakarta')->startOfDay();
            $kembali = Carbon::createFromFormat('Y-m-d', $this->input('batas_kembali'), 'Asia/Jakarta')->startOfDay();

            $maksBooking = (int) config('inventaris.maks_booking_hari');
            $maksDurasi = (int) config('inventaris.maks_durasi_awal_hari');

            if ($pinjam->lt($hariIni)) {
                $v->errors()->add('tanggal_pinjam', 'Tanggal pinjam tidak boleh sebelum hari ini.');
            } elseif ($pinjam->gt($hariIni->copy()->addDays($maksBooking))) {
                $v->errors()->add('tanggal_pinjam', "Pemesanan paling jauh {$maksBooking} hari ke depan.");
            }

            if ($kembali->lt($pinjam)) {
                $v->errors()->add('batas_kembali', 'Batas kembali tidak boleh sebelum tanggal pinjam.');
            } elseif ($kembali->gt($pinjam->copy()->addDays($maksDurasi))) {
                $v->errors()->add('batas_kembali', "Lama peminjaman paling lama {$maksDurasi} hari.");
            }
        }];
    }
}