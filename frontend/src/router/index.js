import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../stores/auth'
import Kiosk from '../Kiosk/View.vue'
import Pengembalian from '../Kiosk/Pengembalian.vue'
import AdminLogin from '../Admin/Login.vue'
import AdminLayout from '../Admin/Layout.vue'
import AdminBeranda from '../Admin/Beranda.vue'
import AdminBarang from '../Admin/Barang.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'kiosk', component: Kiosk },
    { path: '/pengembalian', name: 'pengembalian', component: Pengembalian },

    { path: '/admin/login', name: 'admin-login', component: AdminLogin },
    {
      path: '/admin',
      component: AdminLayout,
      meta: { admin: true },
      children: [
        { path: '', name: 'admin-beranda', component: AdminBeranda },
        { path: 'barang', name: 'admin-barang', component: AdminBarang },
        // halaman klaim, riwayat, barang, kategori, divisi, laporan ditambahkan di sini
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuth()
  if (!auth.loaded) await auth.fetchMe()

  if (to.meta.admin && !auth.user) return { name: 'admin-login' }
  if (to.name === 'admin-login' && auth.user) return { name: 'admin-beranda' }
})

export default router