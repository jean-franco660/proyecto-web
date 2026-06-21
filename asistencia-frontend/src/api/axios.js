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

    if (!error.response) {
      // Error de red o servidor caído
      _toastStore?.addToast('Error de conexión. No se pudo establecer comunicación con el servidor.', 'error')
      return Promise.reject(error)
    }

    if (status === 401) {
      // Si el error 401 viene del login (credenciales incorrectas), no hacemos logout
      if (error.config?.url?.includes('/login')) {
        return Promise.reject(error)
      }

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

// ── Control Proactivo de Expiración de Sesión ──────────────────────────────
let expirationInterval = null
let warnedExpiring = false

function startExpirationCheck() {
  if (expirationInterval) return

  expirationInterval = setInterval(() => {
    const token = localStorage.getItem('token')
    if (!token) {
      warnedExpiring = false
      return
    }

    try {
      const base64Url = token.split('.')[1]
      const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/')
      const jsonPayload = decodeURIComponent(atob(base64).split('').map(c => {
        return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
      }).join(''))
      const payload = JSON.parse(jsonPayload)

      if (payload && payload.exp) {
        const timeLeft = payload.exp - (Math.floor(Date.now() / 1000))
        if (timeLeft <= 300 && timeLeft > 0) { // 5 minutos o menos
          if (!warnedExpiring) {
            _toastStore?.addToast('Tu sesión está a punto de expirar en menos de 5 minutos. Por favor, guarda tus cambios.', 'warning')
            warnedExpiring = true
          }
        } else if (timeLeft <= 0) {
          warnedExpiring = false
          if (_authStore) {
            _authStore.logout()
          } else {
            localStorage.removeItem('token')
            localStorage.removeItem('user')
          }
          if (_router && _router.currentRoute.value?.name !== 'login') {
            _router.push('/login')
          }
        } else {
          // Resetear flag si el token se extendió o renovó
          warnedExpiring = false
        }
      }
    } catch (e) {
      // Ignorar fallos de decodificación
    }
  }, 30000) // Verificar cada 30 segundos
}

// Iniciar chequeo de expiración
startExpirationCheck()

export default api
