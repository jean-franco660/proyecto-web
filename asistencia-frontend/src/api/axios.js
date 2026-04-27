import axios from 'axios'

/**
 * Cliente HTTP centralizado.
 *
 * ⚠️  DEPENDENCIA CIRCULAR PREVENIDA:
 * axios.js NO importa authStore ni router directamente.
 * En su lugar, main.js llama a setAuthStore() y setRouter() una vez
 * que Pinia y el router están inicializados. Esto evita el ciclo:
 *   axios.js → auth.js → axios.js
 */

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost/Asistencia-Backend-php/public',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Referencias internas que se rellenan desde main.js
let _authStore = null
let _router    = null
let _toastStore = null

/** Llamar desde main.js después de createPinia() */
export function setAuthStore(store) {
  _authStore = store
}

/** Llamar desde main.js después de createRouter() */
export function setRouter(router) {
  _router = router
}

/** Llamar desde main.js después de montar el app (opcional, para toasts en errores HTTP) */
export function setToastStore(store) {
  _toastStore = store
}

// ── Request Interceptor ────────────────────────────────────────────────────
api.interceptors.request.use(
  config => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  error => Promise.reject(error)
)

// ── Response Interceptor ───────────────────────────────────────────────────
api.interceptors.response.use(
  response => response,
  error => {
    const status = error.response?.status

    if (status === 401) {
      // Evitar bucle infinito si la petición original ya era un logout
      const isLogoutReq = error.config?.url?.includes('/logout')

      // Token expirado o inválido → limpiar sesión y redirigir
      if (_authStore && !isLogoutReq) {
        _authStore.logout()
      } else {
        // Fallback si el store aún no fue inyectado o ya estamos en logout
        localStorage.removeItem('token')
        localStorage.removeItem('user')
      }
      
      if (_router && _router.currentRoute.value?.name !== 'login') {
        _router.push('/login')
      } else if (!_router && window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    } else if (status === 403) {
      _toastStore?.addToast('Sin permisos para realizar esta acción', 'warning')
    } else if (status >= 500) {
      _toastStore?.addToast(
        error.response?.data?.message || 'Error interno del servidor. Intente de nuevo.',
        'error'
      )
    }

    return Promise.reject(error)
  }
)

export default api
