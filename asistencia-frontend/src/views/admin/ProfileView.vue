<template>
  <div class="space-y-6 max-w-lg mx-auto">
    <div class="pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Mi Perfil</h2>
      <p class="text-sm text-slate-500 mt-1">Actualiza tu información personal y contraseña.</p>
    </div>

    <!-- Alert Messages -->
    <div v-if="message" :class="message.type === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'" class="p-4 rounded-xl border flex items-center space-x-2 transition-all">
      <span class="text-sm font-medium">{{ message.text }}</span>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
      <form @submit.prevent="updateProfile" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nombre Completo</label>
          <input v-model="form.nombre" type="text" required class="input-field w-full" placeholder="Ej: Juan Pérez">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Correo Electrónico</label>
          <input v-model="form.email" type="email" required class="input-field w-full" placeholder="ejemplo@correo.com">
        </div>

        <div class="border-t border-slate-100 pt-4 mt-6">
          <h3 class="text-sm font-bold text-slate-700 mb-2">Cambiar Contraseña</h3>
          <p class="text-xs text-slate-400 mb-3">Deja estos campos en blanco si no deseas cambiar tu contraseña actual.</p>
          
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Nueva Contraseña</label>
              <input v-model="form.password" type="password" class="input-field w-full" placeholder="••••••••">
            </div>

            <div v-if="form.password && form.password.trim() !== ''" class="transition-all duration-300">
              <label class="block text-sm font-medium text-slate-700 mb-1 text-primary-600 font-semibold">Contraseña Actual (Requerido)</label>
              <input v-model="form.current_password" type="password" required class="input-field w-full border-primary-300 focus:border-primary-500 focus:ring-primary-500" placeholder="Ingresa tu contraseña actual">
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-100 mt-6">
          <button type="submit" class="btn-primary flex items-center space-x-2" :disabled="saving">
            <svg v-if="saving" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>{{ saving ? 'Guardando...' : 'Guardar Cambios' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '@/store/auth'
import api from '@/api/axios'

const authStore = useAuthStore()
const saving = ref(false)
const message = ref(null)

const form = reactive({
  nombre: '',
  email: '',
  password: '',
  current_password: ''
})

const loadProfile = () => {
  if (authStore.user) {
    form.nombre = authStore.user.nombre || authStore.user.nombres || ''
    form.email = authStore.user.email || ''
    form.password = ''
    form.current_password = ''
  }
}

const showMessage = (text, type = 'success') => {
  message.value = { text, type }
  setTimeout(() => {
    message.value = null
  }, 5000)
}

const updateProfile = async () => {
  const nombreTrim = form.nombre.trim()
  const emailTrim = form.email.trim().toLowerCase()
  const passwordTrim = form.password.trim()
  const currentPasswordTrim = form.current_password.trim()

  if (!nombreTrim) {
    showMessage('El nombre completo es requerido', 'error')
    return
  }
  if (!emailTrim) {
    showMessage('El correo electrónico es requerido', 'error')
    return
  }

  const payload = {
    nombre: nombreTrim,
    email: emailTrim
  }

  if (passwordTrim !== '') {
    if (passwordTrim.length < 8) {
      showMessage('La nueva contraseña debe tener al menos 8 caracteres', 'error')
      return
    }
    if (!currentPasswordTrim) {
      showMessage('Debes proporcionar tu contraseña actual para realizar el cambio', 'error')
      return
    }
    payload.password = passwordTrim
    payload.current_password = currentPasswordTrim
  }

  saving.value = true
  message.value = null

  try {
    await api.put('/v1/web/profile', payload)
    
    // Actualizar datos del usuario localmente en el store de Pinia
    authStore.updateUser({
      nombre: nombreTrim,
      email: emailTrim
    })

    // Limpiar campos de contraseña
    form.password = ''
    form.current_password = ''

    showMessage('Perfil actualizado correctamente', 'success')
  } catch (err) {
    const errMsg = err.response?.data?.error || err.response?.data?.message || 'Error al actualizar el perfil'
    showMessage(errMsg, 'error')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadProfile()
})
</script>
