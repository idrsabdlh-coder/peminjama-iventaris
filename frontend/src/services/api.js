import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// Wajib dipanggil sebelum login agar cookie XSRF-TOKEN ada
export async function csrf() {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

api.interceptors.response.use(
  (res) => res,
  (err) => {
    const p = location.pathname
    const cekMe = err.config?.url?.includes('/auth/me')
    if (err.response?.status === 401 && !cekMe && p.startsWith('/admin') && p !== '/admin/login') {
      location.href = '/admin/login'
    }
    return Promise.reject(err)
  },
)

export default api