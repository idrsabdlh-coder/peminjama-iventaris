<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import api from '../services/api'

const daftar = ref([])
const meta = reactive({ halaman: 1, terakhir: 1, total: 0 })
const kategoris = ref([])

const cari = ref('')
const kategoriId = ref('')
const memuat = ref(true)
const galat = ref('')

const dialog = ref(false)
const editId = ref(null)
const form = reactive({ nama: '', deskripsi: '', kategori_id: '', total: 0, boleh_diperpanjang: true })
const errors = ref({})
const menyimpan = ref(false)

async function muat(halaman = 1) {
  memuat.value = true
  galat.value = ''
  try {
    const { data } = await api.get('/admin/items', {
      params: {
        page: halaman,
        cari: cari.value || undefined,
        kategori_id: kategoriId.value || undefined,
      },
    })
    daftar.value = data.data
    meta.halaman = data.current_page
    meta.terakhir = data.last_page
    meta.total = data.total
  } catch {
    galat.value = 'Gagal memuat daftar barang.'
  } finally {
    memuat.value = false
  }
}

onMounted(async () => {
  const { data } = await api.get('/kiosk/kategori')
  kategoris.value = data.data
  muat()
})

let timer
watch([cari, kategoriId], () => {
  clearTimeout(timer)
  timer = setTimeout(() => muat(1), 300)
})

function bukaTambah() {
  editId.value = null
  Object.assign(form, { nama: '', deskripsi: '', kategori_id: '', total: 0, boleh_diperpanjang: true })
  errors.value = {}
  dialog.value = true
}

function bukaUbah(b) {
  editId.value = b.id
  Object.assign(form, {
    nama: b.nama,
    deskripsi: b.deskripsi ?? '',
    kategori_id: b.kategori.id,
    total: b.total,
    boleh_diperpanjang: b.boleh_diperpanjang,
  })
  errors.value = {}
  dialog.value = true
}

async function simpan() {
  menyimpan.value = true
  errors.value = {}
  const payload = {
    nama: form.nama,
    deskripsi: form.deskripsi || null,
    kategori_id: form.kategori_id,
    total: form.total,
    boleh_diperpanjang: form.boleh_diperpanjang,
  }
  try {
    if (editId.value) await api.put(`/admin/items/${editId.value}`, payload)
    else await api.post('/admin/items', payload)
    dialog.value = false
    muat(editId.value ? meta.halaman : 1)
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors ?? {}
    else errors.value = { umum: ['Gagal menyimpan. Coba lagi.'] }
  } finally {
    menyimpan.value = false
  }
}

async function hapus(b) {
  if (!confirm(`Hapus "${b.nama}"?`)) return
  galat.value = ''
  try {
    await api.delete(`/admin/items/${b.id}`)
    muat(daftar.value.length === 1 && meta.halaman > 1 ? meta.halaman - 1 : meta.halaman)
  } catch (e) {
    if (e.response?.status === 422) {
      galat.value = Object.values(e.response.data.errors ?? {})[0]?.[0] ?? 'Barang tidak bisa dihapus.'
    } else {
      galat.value = 'Gagal menghapus barang.'
    }
  }
}
</script>

<template>
  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-2xl font-bold">Barang</h1>
    <button class="rounded bg-blue-600 px-4 py-2 text-white" @click="bukaTambah">Tambah barang</button>
  </div>

  <p v-if="galat" class="mb-3 rounded bg-red-50 p-3 text-red-700">{{ galat }}</p>

  <div class="mb-3 flex gap-2">
    <input v-model="cari" placeholder="Cari nama barang..." class="w-full rounded border p-2" />
    <select v-model="kategoriId" class="rounded border p-2">
      <option value="">Semua kategori</option>
      <option v-for="k in kategoris" :key="k.id" :value="k.id">{{ k.nama }}</option>
    </select>
  </div>

  <div class="overflow-x-auto rounded bg-white shadow">
    <table class="w-full text-left text-sm">
      <thead class="border-b bg-gray-50">
        <tr>
          <th class="p-3">Nama</th>
          <th class="p-3">Kategori</th>
          <th class="p-3 text-right">Total</th>
          <th class="p-3 text-right">Tersedia</th>
          <th class="p-3 text-right">Kondisi</th>
          <th class="p-3 text-right">Di luar</th>
          <th class="p-3"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="b in daftar" :key="b.id" class="border-b">
          <td class="p-3 font-medium">{{ b.nama }}</td>
          <td class="p-3">{{ b.kategori.nama }}</td>
          <td class="p-3 text-right">{{ b.total }}</td>
          <td class="p-3 text-right">{{ b.tersedia }}</td>
          <td class="p-3 text-right">{{ b.kondisi }}</td>
          <td class="p-3 text-right">{{ b.di_luar }}</td>
          <td class="space-x-2 p-3 text-right">
            <button class="text-blue-600 underline" @click="bukaUbah(b)">Ubah</button>
            <button class="text-red-600 underline" @click="hapus(b)">Hapus</button>
          </td>
        </tr>
        <tr v-if="!memuat && !daftar.length">
          <td colspan="7" class="p-6 text-center text-gray-500">Belum ada barang.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="mt-3 flex items-center justify-between text-sm">
    <span>{{ meta.total }} barang</span>
    <span class="flex items-center gap-2">
      <button :disabled="meta.halaman <= 1" class="rounded border px-3 py-1 disabled:opacity-40" @click="muat(meta.halaman - 1)">Sebelumnya</button>
      Halaman {{ meta.halaman }} / {{ meta.terakhir }}
      <button :disabled="meta.halaman >= meta.terakhir" class="rounded border px-3 py-1 disabled:opacity-40" @click="muat(meta.halaman + 1)">Berikutnya</button>
    </span>
  </div>

  <div v-if="dialog" class="fixed inset-0 flex items-center justify-center bg-black/40 p-4">
    <form class="w-full max-w-md space-y-3 rounded bg-white p-6 shadow" @submit.prevent="simpan">
      <h2 class="text-lg font-semibold">{{ editId ? 'Ubah barang' : 'Tambah barang' }}</h2>

      <input v-model="form.nama" placeholder="Nama barang" class="w-full rounded border p-2" />
      <textarea v-model="form.deskripsi" placeholder="Deskripsi (opsional)" class="w-full rounded border p-2"></textarea>

      <select v-model="form.kategori_id" class="w-full rounded border p-2">
        <option value="">Pilih kategori</option>
        <option v-for="k in kategoris" :key="k.id" :value="k.id">{{ k.nama }}</option>
      </select>

      <label class="block text-sm text-gray-600">
        Total unit
        <input v-model.number="form.total" type="number" min="0" class="mt-1 w-full rounded border p-2" />
      </label>

      <label class="flex items-center gap-2 text-sm">
        <input v-model="form.boleh_diperpanjang" type="checkbox" />
        Boleh diperpanjang
      </label>

      <ul v-if="Object.keys(errors).length" class="text-sm text-red-600">
        <li v-for="(pesan, kolom) in errors" :key="kolom">{{ pesan[0] }}</li>
      </ul>

      <div class="flex justify-end gap-2">
        <button type="button" class="rounded border px-4 py-2" @click="dialog = false">Batal</button>
        <button :disabled="menyimpan" class="rounded bg-blue-600 px-4 py-2 text-white disabled:bg-gray-300">
          {{ menyimpan ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </div>
    </form>
  </div>
</template>