<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Solicitudes de Justificaciones</h2>

      <div class="flex space-x-2">
         <select v-model="filterEstado" @change="fetchJustificaciones" class="input-field py-1.5 px-3 text-sm min-w-[160px]">
           <option value="">Todos los Estados</option>
           <option value="PENDIENTE">Pendientes</option>
           <option value="APROBADO">Aprobados</option>
           <option value="RECHAZADO">Rechazados</option>
         </select>
      </div>
    </div>

    <!-- Skeleton Loader (grid de cards) -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
      <div v-for="i in 6" :key="i" class="bg-white rounded-xl border border-slate-200 p-6 animate-pulse">
        <div class="flex justify-between mb-4">
          <div class="space-y-2">
            <div class="h-3 bg-slate-200 rounded w-32"></div>
            <div class="h-2 bg-slate-100 rounded w-20"></div>
          </div>
          <div class="h-5 bg-slate-200 rounded-full w-20"></div>
        </div>
        <div class="space-y-3">
          <div class="h-2 bg-slate-100 rounded w-full"></div>
          <div class="h-2 bg-slate-100 rounded w-3/4"></div>
          <div class="h-16 bg-slate-100 rounded"></div>
        </div>
        <div class="mt-6 pt-4 border-t border-slate-100 grid grid-cols-2 gap-2">
          <div class="h-8 bg-slate-100 rounded-lg"></div>
          <div class="h-8 bg-slate-100 rounded-lg"></div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-8 text-center">
      <svg class="w-10 h-10 text-red-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
      </svg>
      <p class="text-red-700 font-medium mb-1">No se pudo cargar las justificaciones</p>
      <p class="text-red-500 text-sm mb-4">{{ error }}</p>
      <button @click="fetchJustificaciones" class="btn-primary text-sm">Reintentar</button>
    </div>

    <!-- Grid de Tarjetas -->
    <div v-if="!loading && justificaciones.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
      <div v-for="just in justificaciones" :key="just.id"
        class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm flex flex-col hover:border-indigo-200 transition-colors">

        <div class="flex justify-between items-start mb-4">
          <div>
             <!-- Campos planos del backend: nombres, apellido_paterno, codigo_empleado -->
             <h3 class="font-bold text-slate-800">{{ just.nombres }} {{ just.apellido_paterno }}</h3>
             <p class="text-xs text-slate-500 font-mono">{{ just.codigo_empleado }}</p>
          </div>
          <span class="px-2 py-1 text-xs font-bold rounded-full uppercase tracking-wide"
               :class="{
                 'bg-amber-100 text-amber-800': just.estado === 'PENDIENTE',
                 'bg-green-100 text-green-800': just.estado === 'APROBADO',
                 'bg-red-100 text-red-800': just.estado === 'RECHAZADO'
               }">
             {{ just.estado }}
          </span>
        </div>

        <div class="flex-1 space-y-3">
          <div class="flex justify-between text-sm">
            <span class="text-slate-500">Fechas:</span>
            <span class="font-medium text-slate-800">{{ just.fecha_inicio }} → {{ just.fecha_fin }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-slate-500">Tipo:</span>
            <span class="font-medium text-slate-800">{{ formatTipo(just.tipo) }}</span>
          </div>
          <div>
            <span class="text-xs text-slate-500 block mb-1">Motivo:</span>
            <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-100 italic line-clamp-3" :title="just.motivo">
              "{{ just.motivo }}"
            </p>
          </div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100 space-y-3">
           <!-- Acción buttons para Pendientes -->
           <div v-if="just.estado === 'PENDIENTE'" class="grid grid-cols-2 gap-2">
              <button @click="openModal(just, 'aprobar')"
                class="py-2 bg-green-50 hover:bg-green-100 text-green-700 font-medium rounded-lg text-sm border border-green-200 transition-colors">
                Aprobar
              </button>
              <button @click="openModal(just, 'rechazar')"
                class="py-2 bg-red-50 hover:bg-red-100 text-red-700 font-medium rounded-lg text-sm border border-red-200 transition-colors">
                Rechazar
              </button>
           </div>

           <!-- Info revisión -->
           <div v-if="just.estado !== 'PENDIENTE' && just.motivo" class="text-xs bg-slate-50 p-2 rounded border border-slate-100 text-slate-500 line-clamp-2">
             {{ just.motivo }}
           </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!justificaciones.length" class="text-center py-12 bg-white rounded-xl border border-slate-100">
      <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <p class="text-slate-500 font-medium">
        No hay justificaciones
        <span v-if="filterEstado === 'PENDIENTE'"> pendientes de revisión</span>
        <span v-else-if="filterEstado === 'APROBADO'"> aprobadas</span>
        <span v-else-if="filterEstado === 'RECHAZADO'"> rechazadas</span>
        <span v-else> en este filtro</span>
      </p>
      <p class="text-slate-400 text-sm mt-1">Los trabajadores envían solicitudes desde la app móvil</p>
    </div>

    <!-- Eval Modal -->
    <div v-if="isModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-bold mb-2 flex items-center"
          :class="evalType === 'aprobar' ? 'text-green-700' : 'text-red-700'">
           <svg v-if="evalType === 'aprobar'" class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
           <svg v-else class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
           Confirmar {{ evalType === 'aprobar' ? 'Aprobación' : 'Rechazo' }}
        </h3>
        <p class="text-sm text-slate-500 mb-1">
          Trabajador: <strong>{{ selectedItem?.nombres }} {{ selectedItem?.apellido_paterno }}</strong>
        </p>
        <p class="text-sm text-slate-500 mb-4">
          Período: <strong>{{ selectedItem?.fecha_inicio }} → {{ selectedItem?.fecha_fin }}</strong>
        </p>

        <!-- El backend usa 'observaciones' como campo en aprobar/rechazar -->
        <textarea v-model="observaciones" class="input-field min-h-[100px] mb-1"
          :placeholder="evalType === 'rechazar' ? 'Motivo del rechazo (requerido)...' : 'Observaciones opcionales...'">
        </textarea>
        <p v-if="evalType === 'rechazar' && !observaciones.trim()" class="text-xs text-red-500 mb-2">
          Las observaciones son requeridas al rechazar.
        </p>

        <div class="flex justify-end space-x-3 pt-2">
          <button @click="closeModal" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
          <button @click="submitEval"
            class="px-6 py-2 text-white font-medium rounded-lg transition-colors shadow-sm"
            :class="evalType === 'aprobar' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
            :disabled="saving || (evalType === 'rechazar' && !observaciones.trim())">
            {{ saving ? 'Aplicando...' : (evalType === 'aprobar' ? 'Aprobar' : 'Rechazar') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/axios'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const justificaciones = ref([])
const loading = ref(true)
const error = ref(null)

// Estado en MAYÚSCULAS para coincidir con la BD: PENDIENTE, APROBADO, RECHAZADO
const filterEstado = ref('PENDIENTE')

const isModalOpen = ref(false)
const saving = ref(false)
const selectedItem = ref(null)
const evalType = ref('aprobar')
const observaciones = ref('')

// Mapeo de tipos de justificación para mostrar en español amigable
const tipoLabels = {
  ENFERMEDAD: 'Enfermedad',
  PERMISO_PERSONAL: 'Permiso Personal',
  LICENCIA: 'Licencia',
  COMISION_SERVICIO: 'Comisión de Servicio',
  CAPACITACION: 'Capacitación',
  DUELO: 'Duelo',
  MATERNIDAD: 'Maternidad',
  PATERNIDAD: 'Paternidad',
  OLVIDO_MARCACION: 'Olvido de Marcación',
  OTRO: 'Otro'
}

const formatTipo = (tipo) => tipoLabels[tipo] || tipo

const fetchJustificaciones = async () => {
  loading.value = true
  error.value = null
  try {
    // El backend filtra por ?estado=PENDIENTE (mayúsculas)
    const params = filterEstado.value ? { estado: filterEstado.value } : {}
    const response = await api.get('/v1/web/justificaciones', { params })
    justificaciones.value = response.data.data || response.data
  } catch (err) {
    error.value = 'Error al obtener justificaciones'
  } finally {
    loading.value = false
  }
}

const openModal = (item, type) => {
  selectedItem.value = item
  evalType.value = type
  observaciones.value = ''
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
  selectedItem.value = null
}

const submitEval = async () => {
  if (evalType.value === 'rechazar' && !observaciones.value.trim()) return
  saving.value = true
  try {
    // El backend usa el campo 'observaciones' (ver aprobar/rechazar en JustificacionWebController)
    const url = `/v1/web/justificaciones/${selectedItem.value.id}/${evalType.value}`
    await api.post(url, { observaciones: observaciones.value })
    closeModal()
    fetchJustificaciones()
  } catch (err) {
    toast.error(err.response?.data?.error || `Error al ${evalType.value} la justificación`)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchJustificaciones()
})
</script>
