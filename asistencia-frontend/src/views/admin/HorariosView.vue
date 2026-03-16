<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Definición de Horarios</h2>
      <button @click="openModal()" class="btn-primary flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        <span>Nuevo Horario</span>
      </button>
    </div>

    <!-- Error/Loading Messages -->
    <div v-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg">{{ error }}</div>
    <div v-if="loading" class="text-center py-10 text-slate-500">Cargando horarios...</div>

    <!-- Table -->
    <div v-if="!loading && horarios.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="horario in horarios" :key="horario.id" class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex justify-between items-start mb-4">
          <h3 class="font-bold text-lg text-slate-800">{{ horario.nombre }}</h3>
           <span class="px-2 py-1 text-xs font-medium rounded-full" :class="horario.estado === 'activo' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-700'">
             {{ horario.estado }}
           </span>
        </div>
        <div class="space-y-3 mb-6">
          <div class="flex justify-between text-sm">
            <span class="text-slate-500">Hora Entrada:</span>
            <span class="font-medium text-slate-800">{{ horario.hora_entrada }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-slate-500">Hora Salida:</span>
            <span class="font-medium text-slate-800">{{ horario.hora_salida }}</span>
          </div>
          <div class="flex justify-between text-sm border-t border-slate-100 pt-3 text-slate-600">
             <span>Sede asociadas: <span v-if="horario.sede?.nombre">{{ horario.sede.nombre }}</span><span v-else>X</span></span>
          </div>
        </div>
        <div class="flex justify-end space-x-2">
          <button @click="openModal(horario)" class="px-3 py-1.5 text-sm text-indigo-600 hover:bg-indigo-50 rounded-lg">Editar</button>
          <button @click="deleteItem(horario.id)" class="px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 rounded-lg">Eliminar</button>
        </div>
      </div>
    </div>

    <div v-else-if="!loading && !horarios.length" class="text-center py-10 bg-white rounded-xl border border-slate-100 text-slate-500">
      No hay horarios registrados.
    </div>

    <!-- Modal Form -->
    <div v-if="isModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-xl">
        <h3 class="text-lg font-bold text-slate-800 mb-4">{{ isEditing ? 'Editar Horario' : 'Nuevo Horario' }}</h3>
        <form @submit.prevent="saveItem" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre Descriptivo</label>
            <input v-model="form.nombre" type="text" required class="input-field" placeholder="Ej: Lunes a Viernes Mañana">
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Hora Entrada</label>
              <input v-model="form.hora_entrada" type="time" required class="input-field">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Hora Salida</label>
              <input v-model="form.hora_salida" type="time" required class="input-field">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
             <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Hora Inicio Descanso</label>
              <input v-model="form.hora_inicio_descanso" type="time" class="input-field">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Hora Fin Descanso</label>
              <input v-model="form.hora_fin_descanso" type="time" class="input-field">
            </div>
          </div>
          <div>
             <label class="block text-sm font-medium text-slate-700 mb-1">Sede (Opcional si es global)</label>
             <select v-model="form.sede_id" class="input-field">
                <option :value="null">Todas (Global)</option>
                <option v-for="sede in sedesList" :key="sede.id" :value="sede.id">{{ sede.nombre }}</option>
             </select>
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
              {{ saving ? 'Guardando...' : 'Guardar Horario' }}
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

const horarios = ref([])
const sedesList = ref([])
const loading = ref(true)
const error = ref(null)
const isModalOpen = ref(false)
const saving = ref(false)
const isEditing = ref(false)

const form = reactive({
  id: null,
  nombre: '',
  hora_entrada: '',
  hora_salida: '',
  hora_inicio_descanso: '',
  hora_fin_descanso: '',
  sede_id: null,
  estado: 'activo'
})

const fetchInitialData = async () => {
  loading.value = true
  error.value = null
  try {
    const [resHorarios, resSedes] = await Promise.all([
      api.get('/v1/web/horarios'),
      api.get('/v1/web/sedes')
    ])
    horarios.value = resHorarios.data.data || resHorarios.data
    sedesList.value = resSedes.data.data || resSedes.data
  } catch (err) {
    error.value = 'Error al obtener datos'
  } finally {
    loading.value = false
  }
}

const openModal = (item = null) => {
  if (item) {
    isEditing.value = true
    Object.assign(form, item)
    // Corregir nulls en inputs si los previene vue
    if (!form.hora_inicio_descanso) form.hora_inicio_descanso = ''
    if (!form.hora_fin_descanso) form.hora_fin_descanso = ''
  } else {
    isEditing.value = false
    Object.assign(form, { id: null, nombre: '', hora_entrada: '', hora_salida: '', hora_inicio_descanso: '', hora_fin_descanso: '', sede_id: null, estado: 'activo' })
  }
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
}

const saveItem = async () => {
  saving.value = true
  try {
    // Limpiar vacios
    const payload = { ...form }
    if (!payload.hora_inicio_descanso) payload.hora_inicio_descanso = null
    if (!payload.hora_fin_descanso) payload.hora_fin_descanso = null

    if (isEditing.value) {
      await api.put(`/v1/web/horarios/${form.id}`, payload)
    } else {
      await api.post('/v1/web/horarios', payload)
    }
    closeModal()
    fetchInitialData()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al guardar')
  } finally {
    saving.value = false
  }
}

const deleteItem = async (id) => {
  if (!confirm('¿Seguro que deseas eliminar este horario?')) return
  try {
    await api.delete(`/v1/web/horarios/${id}`)
    fetchInitialData()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al eliminar')
  }
}

onMounted(() => {
  fetchInitialData()
})
</script>
