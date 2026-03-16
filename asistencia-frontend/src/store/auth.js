import { defineStore } from 'pinia'
import api from '@/api/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user')) || null,
    token: localStorage.getItem('token') || null,
    loading: false,
    error: null
  }),
  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdmin: (state) => state.user?.rol === 'administrador'
  },
  actions: {
    async login(credentials) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post('/v1/web/login', credentials)
        
        // Asume estructura { token: '...', usuario: {...} } y lo alinea
        // a la variable local `user` de auth
        const { token, usuario: user } = response.data.data !== undefined ? response.data.data : response.data
        
        this.token = token
        this.user = user
        localStorage.setItem('token', token)
        localStorage.setItem('user', JSON.stringify(user))
        
        return true
      } catch (err) {
        this.error = err.response?.data?.error || 'Error al iniciar sesión'
        return false
      } finally {
        this.loading = false
      }
    },
    async logout() {
      try {
        await api.post('/v1/web/logout')
      } catch (err) {
        console.error('Logout request falló', err)
      } finally {
        this.token = null
        this.user = null
        localStorage.removeItem('token')
        localStorage.removeItem('user')
      }
    }
  }
})
