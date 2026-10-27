<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import api from '../services/api'

const barangs = ref([])
const kategoris = ref([])
const divisis = ref([])
const loading = ref(true)
const galat = ref('')

const cari = ref('')
const kategoriId = ref('')

// keranjang: { [itemId]: { id, nama, qty, maks } }
const keranjang = reactive({})
const daftarKeranjang = computed(() => Object.values(keranjang))
const fmt = (d) => d.toLocaleDateString('en-CA') // YYYY-MM-DD, zona waktu lokal
function tambahHari(tgl, n) {
  const d = new Date(`${tgl}T12:00:00`)
  d.setDate(d.getDate() + n)
  return fmt(d)
}
const hariIni = fmt(new Date())

const form = reactive({
  nama_peminjam: '',
  division_id: '',
  tipe_peminjam: 'karyawan',
  telepon: '',
  tanggal_pinjam: new Date().toISOString().split('T')[0],
  durasi_hari: 3,
})

const mengirim = ref(false)
const errors = ref({})
const hasil = ref(null)
let idempotencyKey = crypto.randomUUID()

async function muatKatalog() {
  try {
    const { data } = await api.get('/kiosk/katalog', {
      params: { cari: cari.value || undefined, kategori_id: kategoriId.value || undefined },
    })
    barangs.value = data.data
    galat.value = ''
  } catch {
    galat.value = 'Gagal memuat katalog.'
  } finally {
    loading.value = false
  }
}

async function muatPilihan() {
  const [k, d] = await Promise.all([api.get('/kiosk/kategori'), api.get('/kiosk/divisi')])
  kategoris.value = k.data.data
  divisis.value = d.data.data
}

let timer
watch([cari, kategoriId], () => {
  clearTimeout(timer)
  timer = setTimeout(muatKatalog, 300)
})

onMounted(async () => {
  try {
    await Promise.all([muatKatalog(), muatPilihan()])
  } catch {
    galat.value = 'Gagal memuat data.'
    loading.value = false
  }
})

function tambah(b) {
  const ada = keranjang[b.id]
  if (ada) {
    if (ada.qty < b.tersedia) ada.qty++
  } else {
    keranjang[b.id] = { id: b.id, nama: b.nama, qty: 1, maks: b.tersedia }
  }
}

function ubahQty(it, delta) {
  const baru = it.qty + delta
  if (baru < 1) delete keranjang[it.id]
  else if (baru <= it.maks) it.qty = baru
}

// SESUAIKAN dengan PeminjamanStoreRequest bila nama field-nya berbeda
function susunPayload() {
  return {
    nama_peminjam: form.nama_peminjam,
    division_id: form.division_id,
    tipe_peminjam: form.tipe_peminjam,
    telepon: form.telepon || null,
    tanggal_pinjam: form.tanggal_pinjam,
    batas_kembali: form.batas_kembali,
    items: daftarKeranjang.value.map((it) => ({ item_id: it.id, qty: it.qty })),
  }
}

async function kirim() {
  errors.value = {}
  galat.value = ''
  mengirim.value = true
  try {
    const { data } = await api.post('/kiosk/peminjaman', susunPayload(), {
      headers: { 'Idempotency-Key': idempotencyKey },
    })
    hasil.value = data.data
    Object.keys(keranjang).forEach((k) => delete keranjang[k])
    idempotencyKey = crypto.randomUUID()
    muatKatalog()
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors ?? {}
    else if (e.response?.status === 429) galat.value = 'Terlalu banyak permintaan, coba lagi sebentar.'
    else galat.value = 'Peminjaman gagal dikirim.'
  } finally {
    mengirim.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-6xl p-6">
    <h1 class="mb-4 text-2xl font-bold">Katalog Peminjaman Inventaris</h1>
    <RouterLink to="/pengembalian" class="mb-4 inline-block text-blue-600 underline">Kembalikan barang</RouterLink>

    <p v-if="galat" class="mb-3 rounded bg-red-50 p-3 text-red-700">{{ galat }}</p>

    <div v-if="hasil" class="mb-4 rounded bg-green-50 p-4 text-green-800">
  <p class="font-semibold">
    {{ hasil.pemesanan ? 'Pemesanan' : 'Peminjaman' }} berhasil dicatat.
  </p>
  <p class="mt-1">Kode Anda: <span class="font-mono text-lg font-bold">{{ hasil.kode_pinjam }}</span></p>
  <p class="text-sm">Simpan kode ini untuk pengembalian. Batas kembali: {{ hasil.batas_kembali }}</p>
  <ul class="mt-2 text-sm">
    <li v-for="i in hasil.items" :key="i.nama">{{ i.nama }} × {{ i.dipinjam }}</li>
  </ul>
  <button class="mt-2 underline" @click="hasil = null">Tutup</button>
</div>

    <div v-if="loading">Memuat data barang...</div>

    <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <div class="rounded bg-white p-4 shadow md:col-span-2">
        <div class="mb-3 flex gap-2">
          <input v-model="cari" placeholder="Cari barang..." class="w-full rounded border p-2" />
          <select v-model="kategoriId" class="rounded border p-2">
            <option value="">Semua kategori</option>
            <option v-for="k in kategoris" :key="k.id" :value="k.id">{{ k.nama }}</option>
          </select>
        </div>

        <ul>
          <li v-for="b in barangs" :key="b.id" class="flex items-center justify-between border-b py-2">
            <div>
              <p class="font-medium">{{ b.nama }}</p>
              <p class="text-sm text-gray-500">{{ b.kategori.nama }} | Tersedia: {{ b.tersedia }}</p>
            </div>
            <button
              :disabled="!b.boleh_dipilih"
              class="rounded bg-blue-600 px-3 py-1 text-white disabled:bg-gray-300"
              @click="tambah(b)"
            >
              Pilih
            </button>
          </li>
          <li v-if="!barangs.length" class="py-4 text-gray-500">Tidak ada barang.</li>
        </ul>
      </div>

      <div class="rounded bg-white p-4 shadow">
        <h2 class="mb-3 text-lg font-semibold">Form Peminjaman</h2>

        <ul class="mb-3">
          <li v-for="it in daftarKeranjang" :key="it.id" class="flex items-center justify-between py-1">
            <span>{{ it.nama }}</span>
            <span class="flex items-center gap-2">
              <button class="rounded border px-2" @click="ubahQty(it, -1)">-</button>
              {{ it.qty }}
              <button class="rounded border px-2" @click="ubahQty(it, 1)">+</button>
            </span>
          </li>
          <li v-if="!daftarKeranjang.length" class="text-sm text-gray-500">Belum ada barang dipilih.</li>
        </ul>

        <div class="space-y-2">
          <input v-model="form.nama_peminjam" placeholder="Nama peminjam" class="w-full rounded border p-2" />
          <select v-model="form.division_id" class="w-full rounded border p-2">
            <option value="">Pilih divisi</option>
            <option v-for="d in divisis" :key="d.id" :value="d.id">{{ d.nama }}</option>
          </select>
          <select v-model="form.tipe_peminjam" class="w-full rounded border p-2">
            <option value="karyawan">Karyawan</option>
            <option value="magang">Magang</option>
          </select>
          <input v-model="form.telepon" placeholder="Telepon (opsional)" class="w-full rounded border p-2" />
          <input v-model="form.tanggal_pinjam" type="date" class="w-full rounded border p-2" />
          <label class="block text-sm text-gray-600">Batas kembali</label>
<input v-model="form.batas_kembali" type="date" :min="form.tanggal_pinjam" class="w-full rounded border p-2" />
        </div>

        <ul v-if="Object.keys(errors).length" class="mt-2 text-sm text-red-600">
          <li v-for="(pesan, kolom) in errors" :key="kolom">{{ pesan[0] }}</li>
        </ul>

        <button
          :disabled="mengirim || !daftarKeranjang.length"
          class="mt-3 w-full rounded bg-green-600 py-2 text-white disabled:bg-gray-300"
          @click="kirim"
        >
          {{ mengirim ? 'Mengirim...' : 'Pinjam' }}
        </button>
      </div>
    </div>
  </div>
</template>