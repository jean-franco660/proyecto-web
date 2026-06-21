<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Departamentos / Áreas</h2>
      <button v-if="isAdmin" @click="openModal()" class="btn-primary flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        <span>Nuevo Departamento</span>
      </button>
    </div>

    <div v-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg">{{ error }}</div>
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="i in 6" :key="i" class="bg-white rounded-xl border border-slate-200 p-6 animate-pulse">
        <div class="h-4 bg-slate-200 rounded w-3/4 mb-3"></div>
        <div class="h-3 bg-slate-100 rounded w-1/2"></div>
      </div>
    </div>

    <!-- Grid de departamentos -->
    <div v-if="!loading && departamentos.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="dep in departamentos" :key="dep.id"
        class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-all group">
        <div class="flex justify-between items-start">
          <div class="flex items-start space-x-3">
            <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-slate-800 group-hover:text-indigo-700 transition-colors">{{ dep.nombre }}</h3>
              <p v-if="dep.descripcion" class="text-xs text-slate-500 mt-1 line-clamp-2">{{ dep.descripcion }}</p>
            </div>
          </div>
          <span class="px-2 py-1 text-xs font-medium rounded-full flex-shrink-0 ml-2"
            :class="dep.activo == 1 ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
            {{ dep.activo == 1 ? 'Activo' : 'Inactivo' }}
          </span>
        </div>
        <div v-if="isAdmin" class="flex justify-end space-x-2 mt-4 pt-4 border-t border-slate-100">
          <button @click="openModal(dep)" class="px-3 py-1.5 text-sm text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Editar</button>
          <button @click="deleteItem(dep.id)" class="px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors">Desactivar</button>
        </div>
      </div>
    </div>

    <div v-else-if="!loading && !departamentos.length" class="text-center py-16 bg-white rounded-xl border border-slate-100">
      <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
      </svg>
      <p class="text-slate-500">No hay departamentos registrados.</p>
    </div>

    <!-- Modal -->
    <div v-if="isModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-bold text-slate-800 mb-4">{{ isEditing ? 'Editar Departamento' : 'Nuevo Departamento' }}</h3>
        <form @submit.prevent="saveItem" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre <span class="text-red-500">*</span></label>
            <input v-model="form.nombre" type="text" required class="input-field" placeholder="Ej: Recursos Humanos">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
            <textarea v-model="form.descripcion" class="input-field" rows="3" placeholder="Descripción opcional del área..."></textarea>
          </div>
          <div v-if="isEditing">
            <label class="block text-sm font-medium text-slate-700 mb-1">Estado</label>
            <select v-model="form.activo" class="input-field">
              <option :value="1">Activo</option>
              <option :value="0">Inactivo</option>
            </select>
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
import { ref, reactive, onMounted, computed } from 'vue'
import api from '@/api/axios'
import { useAuthStore } from '@/store/auth'

const authStore = useAuthStore()
const isAdmin = computed(() => authStore.user?.rol === 'administrador')

const departamentos = ref([])
const loading = ref(true)
const error = ref(null)
const isModalOpen = ref(false)
const saving = ref(false)
const isEditing = ref(false)

const form = reactive({ id: null, nombre: '', descripcion: '', activo: 1 })

const fetchData = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await api.get('/v1/web/departamentos')
    departamentos.value = res.data.data ?? res.data
  } catch {
    error.value = 'Error al cargar departamentos'
  } finally {
    loading.value = false
  }
}

const openModal = (item = null) => {
  if (item) {
    isEditing.value = true
    Object.assign(form, { id: item.id, nombre: item.nombre, descripcion: item.descripcion || '', activo: item.activo })
  } else {
    isEditing.value = false
    Object.assign(form, { id: null, nombre: '', descripcion: '', activo: 1 })
  }
  isModalOpen.value = true
}

const closeModal = () => { isModalOpen.value = false }

const saveItem = async () => {
  form.nombre = form.nombre ? form.nombre.trim() : ''
  form.descripcion = form.descripcion ? form.descripcion.trim() : ''

  if (!form.nombre) {
    alert('El nombre del departamento es requerido')
    return
  }

  saving.value = true
  try {
    if (isEditing.value) {
      await api.put(`/v1/web/departamentos/${form.id}`, { nombre: form.nombre, descripcion: form.descripcion, activo: form.activo })
    } else {
      await api.post('/v1/web/departamentos', { nombre: form.nombre, descripcion: form.descripcion })
    }
    closeModal()
    fetchData()
  } catch (err) {
    alert(err.response?.data?.error || err.response?.data?.message || 'Error al guardar')
  } finally {
    saving.value = false
  }
}

const deleteItem = async (id) => {
  if (!confirm('¿Desactivar este departamento? (No se puede si tiene empleados activos asignados)')) return
  try {
    await api.delete(`/v1/web/departamentos/${id}`)
    fetchData()
  } catch (err) {
    alert(err.response?.data?.error || err.response?.data?.message || 'Error al desactivar')
  }
}

onMounted(() => { fetchData() })
</script>
