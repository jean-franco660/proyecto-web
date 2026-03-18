<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Administradores Sistema</h2>
      <button @click="openModal()" class="btn-primary flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
        <span>Nuevo Admin</span>
      </button>
    </div>

    <!-- Error/Loading Messages -->
    <div v-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg">{{ error }}</div>
    <div v-if="loading" class="text-center py-10 text-slate-500">Cargando usuarios...</div>

    <!-- Table -->
    <div v-if="!loading && usuarios.length" class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Nombre Completo</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Email</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Rol</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Estado</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
          <tr v-for="user in usuarios.filter(u => u.rol === 'administrador')" :key="user.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="flex-shrink-0 h-8 w-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold">
                  {{ user.nombre ? user.nombre.charAt(0).toUpperCase() : 'U' }}
                </div>
                <div class="ml-3">
                  <div class="text-sm font-medium text-slate-800">{{ user.nombre }}</div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">{{ user.email }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 capitalize">{{ user.rol }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
               <button @click="toggleEstado(user)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full focus:outline-none" :class="(user.estado || '').toLowerCase() === 'activo' ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200'" title="Click para cambiar estado">
                {{ (user.estado || '').toLowerCase() }}
               </button>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <button @click="openModal(user)" class="text-indigo-600 hover:text-indigo-900 mr-3">Editar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Form -->
    <div v-if="isModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-bold text-slate-800 mb-4">{{ isEditing ? 'Editar Administrador' : 'Nuevo Administrador' }}</h3>
        <form @submit.prevent="saveItem" class="space-y-4">
          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Nombre Completo</label>
              <input v-model="form.nombre" type="text" required class="input-field" placeholder="Ej: Juan Pérez">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Correo Electrónico</label>
            <input v-model="form.email" type="email" required class="input-field" placeholder="admin@ejemplo.com">
          </div>
          <div v-if="!isEditing || form.password !== undefined">
             <label class="block text-sm font-medium text-slate-700 mb-1">
                Contraseña <span v-if="isEditing" class="text-xs text-slate-400 font-normal">(Dejar en blanco para no cambiar)</span>
             </label>
             <input v-model="form.password" type="password" :required="!isEditing" class="input-field" placeholder="••••••••">
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="hidden">
              <label class="block text-sm font-medium text-slate-700 mb-1">Rol</label>
              <select v-model="form.rol" class="input-field cursor-not-allowed bg-slate-50" disabled>
                <option value="administrador">Administrador</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Estado</label>
              <select v-model="form.estado" class="input-field">
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
            <button type="button" @click="closeModal" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/api/axios'

const usuarios = ref([])
const loading = ref(true)
const error = ref(null)
const isModalOpen = ref(false)
const saving = ref(false)
const isEditing = ref(false)

const form = reactive({
  id: null,
  nombre: '',
  email: '',
  password: '',
  rol: 'administrador',
  estado: 'activo'
})

const fetchUsuarios = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.get('/v1/web/usuarios-web')
    usuarios.value = response.data.data || response.data
  } catch (err) {
    error.value = 'Error al obtener usuarios web'
  } finally {
    loading.value = false
  }
}

const openModal = (item = null) => {
  if (item) {
    isEditing.value = true
    Object.assign(form, item)
    form.password = '' // Don't show hashed password, start empty
    form.estado = (item.estado || '').toLowerCase()
  } else {
    isEditing.value = false
    Object.assign(form, { id: null, nombre: '', email: '', password: '', rol: 'administrador', estado: 'activo' })
  }
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
}

const saveItem = async () => {
  saving.value = true
  try {
    const payload = { ...form }
    if (isEditing.value && !payload.password) {
      delete payload.password // No enviar si no la cambia
    }

    if (isEditing.value) {
      await api.put(`/v1/web/usuarios-web/${form.id}`, payload)
    } else {
      await api.post('/v1/web/usuarios-web', payload)
    }
    closeModal()
    fetchUsuarios()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al guardar el usuario')
  } finally {
    saving.value = false
  }
}

const toggleEstado = async (user) => {
  const nuevoEstado = (user.estado || '').toLowerCase() === 'activo' ? 'INACTIVO' : 'ACTIVO'
  try {
    await api.patch(`/v1/web/usuarios-web/${user.id}/estado`, { estado: nuevoEstado })
    user.estado = nuevoEstado // Optimistic update
  } catch (err) {
    alert(err.response?.data?.error || 'Error al cambiar estado')
  }
}

onMounted(() => {
  fetchUsuarios()
})
</script>
