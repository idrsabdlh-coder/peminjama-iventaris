<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

// State data
const barangs = ref([])
const kategoris = ref([])
const divisis = ref([])
const loading = ref(true)

// Form State peminjaman
const form = ref({
  nama_peminjam: '',
  divisi_id: '',
  tipe_peminjam: 'karyawan',
  telepon: '',
  tanggal_pinjam: new Date().toISOString().split('T')[0],
  durasi_hari: 3,
  items: [] // Berisi barang yang dipilih
})

// Ambil data dari Backend Laravel
const fetchData = async () => {
  try {
    const response = await axios.get('http://127.0.0.1:8000/api/kiosk')
    barangs.value = response.data.data.barangs
    kategoris.value = response.data.data.kategoris
    divisis.value = response.data.data.divisis
  } catch (error) {
    console.error('Gagal memuat data:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Katalog Peminjaman Inventaris</h1>
    
    <div v-if="loading">Memuat data barang...</div>
    
    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Daftar Barang -->
      <div class="col-span-2 bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-3">Daftar Barang Tersedia</h2>
        <ul>
          <li v-for="barang in barangs" :key="barang.id" class="border-b py-2 flex justify-between items-center">
            <div>
              <p class="font-medium">{{ barang.nama_barang }}</p>
              <p class="text-sm text-gray-500">Kategori: {{ barang.kategori }} | Tersedia: {{ barang.tersedia }}</p>
            </div>
            <!-- Tombol pilih barang bisa ditambahkan di sini -->
          </li>
        </ul>
      </div>

      <!-- Form Peminjaman -->
      <div class="bg-white p-4 shadow rounded">
        <h2 class="text-lg font-semibold mb-3">Form Peminjaman</h2>
        <!-- Form input peminjam & tombol submit -->
      </div>
    </div>
  </div>
</template>