import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { setAuthStore, setRouter, setToastStore } from './api/axios.js'
import { useAuthStore } from './store/auth.js'
import { useToastStore } from './store/toast.js'

const app = createApp(App)
const pinia = createPinia()

// Pinia debe estar activo antes de llamar a useAuthStore/useToastStore
app.use(pinia)

// Inyectar stores y router en axios DESPUÉS de inicializar Pinia
// Esto evita la dependencia circular: axios.js ↔ auth.js
setAuthStore(useAuthStore())
setToastStore(useToastStore())
setRouter(router)

// Manejo global de errores de Vue
app.config.errorHandler = (err, instance, info) => {
  console.error('Unhandled Vue Error:', err, info)
  const toastStore = useToastStore()
  toastStore.addToast('Ha ocurrido un error inesperado en la aplicación.', 'error')
}

app.use(router)
app.mount('#app')

