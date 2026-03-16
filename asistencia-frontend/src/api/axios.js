import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost/Asistencia-Backend-php/public',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Request Interceptor
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
}, error => {
  return Promise.reject(error)
})

// Response Interceptor
api.interceptors.response.use(response => {
  return response
}, error => {
  if (error.response && error.response.status === 401) {
    // Manejar expiración del token
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    // Redirigir usando window.location para desconectar la sesión sin importar el ciclo de vue (o lanzar evento)
    if (window.location.pathname !== '/login') {
      window.location.href = '/login'
    }
  }
  return Promise.reject(error)
})

export default api
