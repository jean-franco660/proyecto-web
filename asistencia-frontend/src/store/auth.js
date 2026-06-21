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
    isAdmin: (state) => state.user?.rol === 'administrador',
    isSupervisor: (state) => state.user?.rol === 'supervisor'
  },
  actions: {
    async login(credentials) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post('/v1/web/login', credentials)
        const data = response.data.data

        if (data.requires_2fa) {
          this.tempToken = data.temp_token
          return { requires2FA: true, message: data.message }
        }

        const { token, usuario: user } = data
        this.setAuthData(token, user)
        return { success: true }
      } catch (err) {
        this.error = err.response?.data?.error || 'Error al iniciar sesión'
        return { success: false }
      } finally {
        this.loading = false
      }
    },
    async verify2FA(code) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post('/v1/web/verify-2fa', {
          temp_token: this.tempToken,
          code: code
        })
        const { token, usuario: user } = response.data.data
        
        this.setAuthData(token, user)
        this.tempToken = null // Limpiar token temporal
        return true
      } catch (err) {
        this.error = err.response?.data?.error || 'Código incorrecto o expirado'
        return false
      } finally {
        this.loading = false
      }
    },
    setAuthData(token, user) {
      this.token = token
      this.user = user
      localStorage.setItem('token', token)
      localStorage.setItem('user', JSON.stringify(user))
    },
    updateUser(updatedUser) {
      this.user = { ...this.user, ...updatedUser }
      localStorage.setItem('user', JSON.stringify(this.user))
    },
    async logout() {
      try {
        await api.post('/v1/web/logout')
      } catch (err) {
        if (err.response?.status !== 401) {
          console.error('Logout request falló', err)
        }
      } finally {
        this.token = null
        this.user = null
        localStorage.removeItem('token')
        localStorage.removeItem('user')
      }
    }
  }
})
