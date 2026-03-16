<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Sedes</h2>
      <button @click="openModal()" class="btn-primary flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        <span>Nueva Sede</span>
      </button>
    </div>

    <!-- Error/Loading Messages -->
    <div v-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg">{{ error }}</div>
    <div v-if="loading" class="text-center py-10 text-slate-500">Cargando sedes...</div>

    <!-- Table -->
    <div v-if="!loading && sedes.length" class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Nombre</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Dirección</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Tolerancia (min)</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Estado</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
          <tr v-for="sede in sedes" :key="sede.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-700">{{ sede.nombre }}</td>
            <td class="px-6 py-4 text-sm text-slate-600 max-w-xs truncate" :title="sede.direccion_text || ''">{{ sede.direccion_text || 'N/A' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">{{ sede.minutos_tolerancia }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="sede.estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                {{ sede.estado }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <button @click="openModal(sede)" class="text-indigo-600 hover:text-indigo-900 mr-3">Editar</button>
              <button @click="deleteSede(sede.id)" class="text-red-600 hover:text-red-900">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="!loading && !sedes.length" class="text-center py-10 bg-white rounded-xl border border-slate-100 text-slate-500">
      No hay sedes registradas.
    </div>

    <!-- Modal Form -->
    <div v-if="isModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-bold text-slate-800 mb-4">{{ isEditing ? 'Editar Sede' : 'Nueva Sede' }}</h3>
        <form @submit.prevent="saveSede" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre</label>
            <input v-model="form.nombre" type="text" required class="input-field" placeholder="Nombre de la sede">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Dirección Referencial</label>
            <textarea v-model="form.direccion_text" class="input-field min-h-[80px]" placeholder="Dirección referencial"></textarea>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Latitud</label>
              <input v-model="form.latitud" type="number" step="any" required class="input-field" placeholder="Ej: -12.0463">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Longitud</label>
              <input v-model="form.longitud" type="number" step="any" required class="input-field" placeholder="Ej: -77.0427">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Tolerancia (min)</label>
              <input v-model="form.minutos_tolerancia" type="number" class="input-field" placeholder="0">
            </div>
             <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Radio Geolocalización</label>
               <select v-model="form.config_radio" class="input-field">
                 <option value="50">50m</option>
                 <option value="100">100m</option>
                 <option value="500">500m</option>
                 <option value="1000">1km</option>
               </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Estado</label>
            <select v-model="form.estado" class="input-field">
              <option value="activo">Activo</option>
              <option value="inactivo">Inactivo</option>
            </select>
          </div>
          <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
            <button type="button" @click="closeModal" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Guardando...' : 'Guardar Sede' }}
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

const sedes = ref([])
const loading = ref(true)
const error = ref(null)
const isModalOpen = ref(false)
const saving = ref(false)
const isEditing = ref(false)

const form = reactive({
  id: null,
  nombre: '',
  direccion_text: '',
  latitud: '',
  longitud: '',
  minutos_tolerancia: 0,
  config_radio: 100,
  estado: 'activo'
})

const fetchSedes = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.get('/v1/web/sedes')
    sedes.value = response.data.data || response.data
  } catch (err) {
    error.value = 'Error al obtener sedes'
  } finally {
    loading.value = false
  }
}

const openModal = (sede = null) => {
  if (sede) {
    isEditing.value = true
    Object.assign(form, sede)
  } else {
    isEditing.value = false
    Object.assign(form, { id: null, nombre: '', direccion_text: '', latitud: '', longitud: '', minutos_tolerancia: 0, config_radio: 100, estado: 'activo' })
  }
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
}

const saveSede = async () => {
  saving.value = true
  try {
    if (isEditing.value) {
      await api.put(`/v1/web/sedes/${form.id}`, form)
    } else {
      await api.post('/v1/web/sedes', form)
    }
    closeModal()
    fetchSedes()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al guardar la sede')
  } finally {
    saving.value = false
  }
}

const deleteSede = async (id) => {
  if (!confirm('¿Seguro que deseas eliminar esta sede?')) return
  try {
    await api.delete(`/v1/web/sedes/${id}`)
    fetchSedes()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al eliminar')
  }
}

onMounted(() => {
  fetchSedes()
})
</script>
