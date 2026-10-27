import { defineStore } from 'pinia'
import api, { csrf } from '../services/api'

export const useAuth = defineStore('auth', {
  state: () => ({ user: null, loaded: false }),
  actions: {
    async fetchMe() {
      try {
        this.user = (await api.get('/auth/me')).data.data
      } catch {
        this.user = null
      } finally {
        this.loaded = true
      }
    },
    async login(email, password) {
      await csrf()
      this.user = (await api.post('/auth/login', { email, password })).data.data
    },
    async logout() {
      await api.post('/auth/logout')
      this.user = null
    },
  },
})