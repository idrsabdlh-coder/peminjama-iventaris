<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

// State Data
const barangs = ref([])
const kategoris = ref([])
const divisis = ref([])
const loading = ref(true)
const searchQuery = ref('')
const selectedKategori = ref('')

// State Form Peminjaman
const form = ref({
  nama_peminjam: '',
  divisi_id: '',
  tipe_peminjam: 'karyawan',
  telepon: '',
  tanggal_pinjam: new Date().toISOString().split('T')[0],
  durasi_hari: 3,
  barang_id: '',
  jumlah: 1
})

const successMessage = ref('')
const errorMessage = ref('')
const isSubmitting = ref(false)

// Ambil data dari API Laravel
const fetchData = async () => {
  try {
    const response = await axios.get('/api/kiosk')
    // Menyesuaikan struktur JSON dari KioskController
    barangs.value = response.data.data.barangs || []
    kategoris.value = response.data.data.kategoris || []
    divisis.value = response.data.data.divisis || []
  } catch (error) {
    console.error('Gagal memuat data kiosk:', error)
    errorMessage.value = 'Gagal memuat data dari server.'
  } finally {
    loading.value = false
  }
}

// Filter barang berdasarkan pencarian dan kategori
const filteredBarangs = computed(() => {
  return barangs.value.filter(barang => {
    const matchName = barang.nama_barang.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchCategory = selectedKategori.value === '' || barang.kategori_id == selectedKategori.value
    return matchName && matchCategory
  })
})

// Kirim data peminjaman
const submitPeminjaman = async () => {
  isSubmitting.value = true
  successMessage.value = ''
  errorMessage.value = ''

  try {
    const response = await axios.post('/api/kiosk/store', form.value)
    successMessage.value = `Peminjaman berhasil! Kode Pinjam Anda: ${response.data.data.kode_peminjaman}`
    
    // Reset form setelah berhasil
    form.value.nama_peminjam = ''
    form.value.telepon = ''
    form.value.barang_id = ''
    form.value.jumlah = 1
    
    // Refresh data barang agar stok terupdate
    fetchData()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Terjadi kesalahan saat memproses peminjaman.'
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="max-w-7xl mx-auto p-6">
    <!-- Header -->
    <header class="mb-8 text-center">
      <h1 class="text-3xl font-bold text-gray-800">Kiosk Peminjaman Inventaris</h1>
      <p class="text-gray-600 mt-2">Silakan pilih barang yang ingin dipinjam dan isi formulir di bawah.</p>
    </header>

    <!-- Notifikasi Sukses/Error -->
    <div v-if="successMessage" class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
      {{ successMessage }}
    </div>
    <div v-if="errorMessage" class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
      {{ errorMessage }}
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-500">
      Memuat katalog barang...
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Sisi Kiri: Katalog Barang (2 Kolom) -->
      <div class="lg:col-span-2 space-y-4">
        <div class="bg-white p-4 rounded-xl shadow-sm flex flex-col md:flex-row gap-4 justify-between items-center">
          <!-- Pencarian -->
          <input 
            type="text" 
            v-model="searchQuery" 
            placeholder="Cari nama barang..." 
            class="w-full md:w-1/2 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
          <!-- Filter Kategori -->
          <select 
            v-model="selectedKategori" 
            class="w-full md:w-1/2 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Semua Kategori</option>
            <option v-for="kat in kategoris" :key="kat.id" :value="kat.id">{{ kat.nama_kategori }}</option>
          </select>
        </div>

        <!-- Grid Daftar Barang -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div 
            v-for="barang in filteredBarangs" 
            :key="barang.id" 
            class="bg-white p-4 rounded-xl shadow-sm border hover:shadow-md transition flex flex-col justify-between"
          >
            <div>
              <h3 class="font-semibold text-lg text-gray-800">{{ barang.nama_barang }}</h3>
              <p class="text-sm text-gray-500 mb-2">Stok Tersedia: <span class="font-bold text-blue-600">{{ barang.tersedia }}</span></p>
            </div>
            <button 
              @click="form.barang_id = barang.id" 
              class="mt-4 w-full py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition"
              :class="{ 'bg-green-600 hover:bg-green-700': form.barang_id === barang.id }"
            >
              {{ form.barang_id === barang.id ? 'Barang Dipilih ✓' : 'Pilih Barang Ini' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Sisi Kanan: Form Peminjaman (1 Kolom) -->
      <div class="bg-white p-6 rounded-xl shadow-sm h-fit sticky top-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Formulir Peminjaman</h2>
        
        <form @submit.prevent="submitPeminjaman" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Peminjam</label>
            <input type="text" v-model="form.nama_peminjam" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Divisi / Unit</label>
            <select v-model="form.divisi_id" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
              <option value="" disabled>Pilih Divisi</option>
              <option v-for="div in divisis" :key="div.id" :value="div.id">{{ div.nama_divisi }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon / WhatsApp</label>
            <input type="text" v-model="form.telepon" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">ID Barang Terpilih</label>
            <input type="text" v-model="form.barang_id" readonly placeholder="Klik 'Pilih Barang' di kiri" class="w-full px-3 py-2 bg-gray-50 border rounded-lg text-gray-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Pinjam</label>
            <input type="number" v-model="form.durasi_hari" min="1" value="1" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
          </div>

          <button 
            type="submit" 
            :disabled="isSubmitting || !form.barang_id"
            class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 text-white font-semibold rounded-lg transition"
          >
            {{ isSubmitting ? 'Memproses...' : 'Ajukan Peminjaman' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>