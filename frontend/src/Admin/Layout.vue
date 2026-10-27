<script setup>
import { useRouter } from 'vue-router'
import { useAuth } from '../stores/auth'

const auth = useAuth()
const router = useRouter()

async function keluar() {
  await auth.logout()
  router.push({ name: 'admin-login' })
}
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <header class="flex items-center justify-between bg-white px-6 py-3 shadow">
      <nav class="flex gap-4">
        <RouterLink :to="{ name: 'admin-beranda' }" class="font-semibold">Beranda</RouterLink>
        <RouterLink :to="{ name: 'admin-barang' }" class="font-semibold">Barang</RouterLink>
      </nav>
      <div class="flex items-center gap-3 text-sm">
        <span>{{ auth.user?.name }}</span>
        <button class="rounded border px-3 py-1" @click="keluar">Keluar</button>
      </div>
    </header>

    <main class="mx-auto max-w-6xl p-6">
      <RouterView />
    </main>
  </div>
</template>