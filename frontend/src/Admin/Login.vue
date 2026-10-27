<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../stores/auth'

const auth = useAuth()
const router = useRouter()

const email = ref('')
const password = ref('')
const galat = ref('')
const proses = ref(false)

async function masuk() {
  galat.value = ''
  proses.value = true
  try {
    await auth.login(email.value, password.value)
    router.push({ name: 'admin-beranda' })
  } catch (e) {
    const s = e.response?.status
    if (s === 422) galat.value = e.response.data.errors?.email?.[0] ?? 'Email atau password salah.'
    else if (s === 429) galat.value = e.response.data.message
    else if (s === 419) galat.value = 'Sesi kedaluwarsa, muat ulang halaman lalu coba lagi.'
    else galat.value = 'Gagal masuk. Coba lagi.'
  } finally {
    proses.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-100 p-4">
    <form class="w-full max-w-sm space-y-3 rounded bg-white p-6 shadow" @submit.prevent="masuk">
      <h1 class="text-xl font-bold">Masuk Admin</h1>

      <p v-if="galat" class="rounded bg-red-50 p-2 text-sm text-red-700">{{ galat }}</p>

      <input v-model="email" type="email" placeholder="Email" autocomplete="username" class="w-full rounded border p-2" />
      <input v-model="password" type="password" placeholder="Password" autocomplete="current-password" class="w-full rounded border p-2" />

      <button :disabled="proses || !email || !password" class="w-full rounded bg-blue-600 py-2 text-white disabled:bg-gray-300">
        {{ proses ? 'Memproses...' : 'Masuk' }}
      </button>

      <RouterLink to="/" class="block text-center text-sm text-blue-600 underline">Ke halaman kiosk</RouterLink>
    </form>
  </div>
</template>