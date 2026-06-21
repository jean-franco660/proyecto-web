<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Definición de Horarios</h2>
      <button @click="openModal()" class="btn-primary flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        <span>Nuevo Horario</span>
      </button>
    </div>

    <div v-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg">{{ error }}</div>
    <div v-if="loading" class="text-center py-10 text-slate-500">Cargando horarios...</div>

    <div v-if="!loading && horarios.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="horario in horarios" :key="horario.id" class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h3 class="font-bold text-lg text-slate-800">{{ horario.nombre }}</h3>
            <p class="text-xs text-slate-400">{{ getSedeName(horario.sede_id) }}</p>
          </div>
          <span class="px-2 py-1 text-xs font-medium rounded-full"
            :class="horario.activo == 1 ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-700'">
            {{ horario.activo == 1 ? 'Activo' : 'Inactivo' }}
          </span>
        </div>
        <div class="space-y-2 mb-4">
          <div class="flex justify-between text-sm">
            <span class="text-slate-500">Entrada:</span>
            <span class="font-medium text-slate-800">{{ horario.hora_entrada }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-slate-500">Salida:</span>
            <span class="font-medium text-slate-800">{{ horario.hora_salida }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-slate-500">Tolerancia entrada:</span>
            <span class="font-medium text-slate-800">{{ horario.tolerancia_entrada }} min</span>
          </div>
          <div class="flex justify-between text-sm border-t border-slate-100 pt-2">
            <span class="text-slate-500">Días:</span>
            <span class="font-medium text-slate-700">{{ formatDias(horario.dias_semana) }}</span>
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
      <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-slate-800 mb-4">{{ isEditing ? 'Editar Horario' : 'Nuevo Horario' }}</h3>
        <form @submit.prevent="saveItem" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Sede <span class="text-red-500">*</span></label>
            <select v-model="form.sede_id" class="input-field" required :disabled="isEditing">
              <option value="">Seleccione sede</option>
              <option v-for="s in sedesList" :key="s.id" :value="s.id">{{ s.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre del Turno <span class="text-red-500">*</span></label>
            <input v-model="form.nombre" type="text" required class="input-field" placeholder="Ej: Turno Mañana">
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Hora Entrada <span class="text-red-500">*</span></label>
              <input v-model="form.hora_entrada" type="time" required class="input-field">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Hora Salida <span class="text-red-500">*</span></label>
              <input v-model="form.hora_salida" type="time" required class="input-field">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Tolerancia Entrada (min)</label>
              <input v-model="form.tolerancia_entrada" type="number" min="0" class="input-field" placeholder="0">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Tolerancia Salida (min)</label>
              <input v-model="form.tolerancia_salida" type="number" min="0" class="input-field" placeholder="0">
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Días de la Semana <span class="text-red-500">*</span></label>
            <div class="flex flex-wrap gap-2">
              <label v-for="dia in diasOpciones" :key="dia.value"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border cursor-pointer transition-colors text-sm font-medium select-none"
                :class="form.dias.includes(dia.value)
                  ? 'bg-primary-600 text-white border-primary-600'
                  : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50'">
                <input type="checkbox" :value="dia.value" v-model="form.dias" class="sr-only">
                {{ dia.label }}
              </label>
            </div>
            <p v-if="form.dias.length === 0" class="text-xs text-red-500 mt-1">Selecciona al menos un día.</p>
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
            <button type="submit" class="btn-primary" :disabled="saving || form.dias.length === 0">
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

const diasOpciones = [
  { value: 'L', label: 'Lun' },
  { value: 'M', label: 'Mar' },
  { value: 'X', label: 'Mié' },
  { value: 'J', label: 'Jue' },
  { value: 'V', label: 'Vie' },
  { value: 'S', label: 'Sáb' },
  { value: 'D', label: 'Dom' },
]

const charToNum = { L: 1, M: 2, X: 3, J: 4, V: 5, S: 6, D: 7 }
const numToChar = { 1: 'L', 2: 'M', 3: 'X', 4: 'J', 5: 'V', 6: 'S', 7: 'D' }

const form = reactive({
  id: null,
  sede_id: '',
  nombre: '',
  hora_entrada: '',
  hora_salida: '',
  tolerancia_entrada: 0,
  tolerancia_salida: 0,
  activo: 1,
  dias: ['L', 'M', 'X', 'J', 'V']
})

const formatDias = (diasNumArr) => {
  if (!diasNumArr || !diasNumArr.length) return '—'
  const map = { 1: 'Lun', 2: 'Mar', 3: 'Mié', 4: 'Jue', 5: 'Vie', 6: 'Sáb', 7: 'Dom' }
  return diasNumArr.map(d => map[d] || d).join(', ')
}

const getSedeName = (sedeId) => {
  const s = sedesList.value.find(x => x.id == sedeId)
  return s ? s.nombre : '—'
}

const fetchInitialData = async () => {
  loading.value = true
  error.value = null
  try {
    const [resHorarios, resSedes] = await Promise.all([
      api.get('/v1/web/horarios'),
      api.get('/v1/web/sedes')
    ])
    horarios.value = resHorarios.data.data ?? resHorarios.data
    sedesList.value = resSedes.data.data ?? resSedes.data
  } catch {
    error.value = 'Error al obtener datos'
  } finally {
    loading.value = false
  }
}

const openModal = (item = null) => {
  if (item) {
    isEditing.value = true
    const dias = Array.isArray(item.dias_semana)
      ? item.dias_semana.map(d => numToChar[d])
      : ['L', 'M', 'X', 'J', 'V']
    Object.assign(form, {
      id: item.id,
      sede_id: item.sede_id,
      nombre: item.nombre,
      hora_entrada: item.hora_entrada.substring(0, 5),
      hora_salida: item.hora_salida.substring(0, 5),
      tolerancia_entrada: item.tolerancia_entrada ?? 0,
      tolerancia_salida: item.tolerancia_salida ?? 0,
      activo: item.activo,
      dias
    })
  } else {
    isEditing.value = false
    Object.assign(form, {
      id: null,
      sede_id: '',
      nombre: '',
      hora_entrada: '',
      hora_salida: '',
      tolerancia_entrada: 0,
      tolerancia_salida: 0,
      activo: 1,
      dias: ['L', 'M', 'X', 'J', 'V']
    })
  }
  isModalOpen.value = true
}

const closeModal = () => { isModalOpen.value = false }

const saveItem = async () => {
  form.nombre = form.nombre ? form.nombre.trim() : ''
  if (!form.sede_id) {
    alert('Debe seleccionar una sede')
    return
  }
  if (!form.nombre) {
    alert('El nombre del turno es requerido')
    return
  }
  if (!form.hora_entrada) {
    alert('La hora de entrada es requerida')
    return
  }
  if (!form.hora_salida) {
    alert('La hora de salida es requerida')
    return
  }
  if (form.tolerancia_entrada < 0 || form.tolerancia_salida < 0) {
    alert('Las tolerancias no pueden ser valores negativos')
    return
  }
  if (form.dias.length === 0) {
    alert('Debe seleccionar al menos un día de la semana')
    return
  }

  saving.value = true
  try {
    const diasSemanaNums = form.dias.map(d => charToNum[d])
    const payload = {
      sede_id: form.sede_id,
      nombre: form.nombre,
      hora_entrada: form.hora_entrada,
      hora_salida: form.hora_salida,
      tolerancia_entrada: Number(form.tolerancia_entrada || 0),
      tolerancia_salida: Number(form.tolerancia_salida || 0),
      activo: form.activo,
      dias_semana: diasSemanaNums
    }
    
    if (isEditing.value) {
      await api.put(`/v1/web/horarios/${form.id}`, payload)
    } else {
      await api.post('/v1/web/horarios', payload)
    }
    closeModal()
    fetchInitialData()
  } catch (err) {
    alert(err.response?.data?.error || err.response?.data?.message || 'Error al guardar')
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

onMounted(() => { fetchInitialData() })
</script>
