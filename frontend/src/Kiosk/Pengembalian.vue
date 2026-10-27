<script setup>
import { ref, reactive, computed } from 'vue'
import api from '../services/api'

const kode = ref('')
const kartu = ref(null)
const galat = ref('')
const errors = ref({})
const mencari = ref(false)
const mengirim = ref(false)
const hasil = ref(null)
const catatan = ref('')
const input = reactive({}) // { [item_id]: { baik, rusak, hilang } }
let kunci = crypto.randomUUID()

const KONDISI = [
  ['baik', 'Baik'],
  ['rusak', 'Rusak'],
  ['hilang', 'Hilang'],
]

async function cek() {
  galat.value = ''
  hasil.value = null
  mencari.value = true
  try {
    const { data } = await api.post('/kiosk/peminjaman/cek', { kode: kode.value })
    kartu.value = data.data
    Object.keys(input).forEach((k) => delete input[k])
    for (const it of kartu.value.items) input[it.item_id] = { baik: 0, rusak: 0, hilang: 0 }
  } catch (e) {
    kartu.value = null
    galat.value = e.response?.data?.message ?? 'Gagal memeriksa kode.'
  } finally {
    mencari.value = false
  }
}

const jumlahDilaporkan = (it) => {
  const v = input[it.item_id]
  return v ? Number(v.baik || 0) + Number(v.rusak || 0) + Number(v.hilang || 0) : 0
}

const kelebihan = computed(() => kartu.value?.items.some((it) => jumlahDilaporkan(it) > it.sisa))
const totalLapor = computed(() => kartu.value?.items.reduce((n, it) => n + jumlahDilaporkan(it), 0) ?? 0)

async function kirim() {
  galat.value = ''
  errors.value = {}
  mengirim.value = true

  const baris = []
  for (const it of kartu.value.items) {
    for (const [k] of KONDISI) {
      const qty = Number(input[it.item_id][k] || 0)
      if (qty > 0) baris.push({ item_id: it.item_id, kondisi: k, qty })
    }
  }

  try {
    const { data } = await api.post('/kiosk/pengembalian', {
      kode_pinjam: kartu.value.kode_pinjam,
      catatan_peminjam: catatan.value || null,
      idempotency_key: kunci,
      baris,
    })
    hasil.value = data.data
    kunci = crypto.randomUUID()
    kartu.value = null
    kode.value = ''
    catatan.value = ''
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors ?? {}
    else galat.value = e.response?.data?.message ?? 'Klaim pengembalian gagal dikirim.'
  } finally {
    mengirim.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl p-6">
    <RouterLink to="/" class="text-blue-600 underline">&larr; Kembali ke katalog</RouterLink>
    <h1 class="my-4 text-2xl font-bold">Pengembalian Barang</h1>

    <p v-if="galat" class="mb-3 rounded bg-red-50 p-3 text-red-700">{{ galat }}</p>

    <div v-if="hasil" class="mb-4 rounded bg-green-50 p-4 text-green-800">
      <p class="font-semibold">Klaim pengembalian terkirim.</p>
      <p class="mt-1">Kode klaim: <span class="font-mono font-bold">{{ hasil.kode_klaim }}</span></p>
      <p class="text-sm">Serahkan barang ke petugas untuk diverifikasi. Stok baru diperbarui setelah verifikasi.</p>
      <p v-if="hasil.hari_terlambat > 0" class="text-sm">Terlambat {{ hasil.hari_terlambat }} hari.</p>
    </div>

    <form class="mb-4 flex gap-2" @submit.prevent="cek">
      <input v-model="kode" placeholder="Kode pinjam (PJM-XXXXXX)" class="w-full rounded border p-2 font-mono uppercase" />
      <button :disabled="mencari || !kode" class="rounded bg-blue-600 px-4 text-white disabled:bg-gray-300">
        {{ mencari ? 'Mencari...' : 'Cek' }}
      </button>
    </form>

    <div v-if="kartu" class="rounded bg-white p-4 shadow">
      <p class="font-semibold">{{ kartu.nama_peminjam }} · {{ kartu.divisi }}</p>
      <p class="text-sm text-gray-600">
        Status: {{ kartu.status.replace('_', ' ') }} · Batas kembali {{ kartu.batas_kembali }}
        <span v-if="kartu.hari_terlambat > 0">· terlambat {{ kartu.hari_terlambat }} hari</span>
      </p>

      <p v-if="!kartu.boleh_klaim" class="mt-3 rounded bg-yellow-50 p-3 text-yellow-800">
        <template v-if="kartu.status === 'menunggu_verifikasi'">Pengembalian sedang menunggu verifikasi petugas.</template>
        <template v-else-if="kartu.status === 'selesai'">Semua barang sudah dikembalikan.</template>
        <template v-else-if="kartu.status === 'dipesan'">Peminjaman belum dimulai.</template>
        <template v-else>Peminjaman ini tidak bisa diklaim pengembaliannya.</template>
      </p>

      <div v-else class="mt-3 space-y-3">
        <div v-for="it in kartu.items" :key="it.item_id" class="border-b pb-3">
          <p class="font-medium">{{ it.nama }}</p>
          <p class="text-sm text-gray-500">
            Dipinjam {{ it.dipinjam }} · belum dikembalikan {{ it.sisa }}
            <span v-if="it.menunggu">· menunggu verifikasi {{ it.menunggu }}</span>
          </p>
          <div v-if="it.sisa > 0" class="mt-2 flex gap-3">
            <label v-for="[k, label] in KONDISI" :key="k" class="text-sm">
              {{ label }}
              <input v-model.number="input[it.item_id][k]" type="number" min="0" :max="it.sisa"
                     class="ml-1 w-16 rounded border p-1" />
            </label>
          </div>
          <p v-if="jumlahDilaporkan(it) > it.sisa" class="mt-1 text-sm text-red-600">Melebihi sisa ({{ it.sisa }}).</p>
        </div>

        <textarea v-model="catatan" placeholder="Catatan (opsional)" maxlength="500" class="w-full rounded border p-2"></textarea>

        <ul v-if="Object.keys(errors).length" class="text-sm text-red-600">
          <li v-for="(pesan, kolom) in errors" :key="kolom">{{ pesan[0] }}</li>
        </ul>

        <button :disabled="mengirim || totalLapor === 0 || kelebihan"
                class="w-full rounded bg-green-600 py-2 text-white disabled:bg-gray-300" @click="kirim">
          {{ mengirim ? 'Mengirim...' : 'Kirim klaim pengembalian' }}
        </button>
      </div>
    </div>
  </div>
</template>