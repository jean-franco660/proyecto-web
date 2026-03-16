<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Historial de Asistencias</h2>
      
      <!-- Filters -->
      <div class="flex space-x-3 items-center">
        <input v-model="filters.fecha_inicio" type="date" class="input-field py-1.5 px-3 text-sm" placeholder="Desde">
        <span class="text-slate-400">-</span>
        <input v-model="filters.fecha_fin" type="date" class="input-field py-1.5 px-3 text-sm" placeholder="Hasta">
        <button @click="fetchAsistencias" class="btn-primary py-1.5 text-sm">Filtrar</button>
      </div>
    </div>

    <!-- Error/Loading Messages -->
    <div v-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg">{{ error }}</div>
    <div v-if="loading" class="text-center py-10 text-slate-500">Cargando historial...</div>

    <!-- Table -->
    <div v-if="!loading && asistencias.length" class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Fecha / Trabajador</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Marcaciones</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Sede</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Estado de Tardanza</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Revisión</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
          <tr v-for="asistencia in asistencias" :key="asistencia.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-bold text-slate-800">{{ asistencia.fecha }}</div>
              <div class="text-sm text-slate-500">{{ asistencia.usuario?.nombres }} {{ asistencia.usuario?.apellidos }}</div>
              <div class="text-xs text-slate-400">{{ asistencia.usuario?.numero_documento }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
              <div>Entrada: <span class="font-medium" :class="asistencia.hora_entrada ? 'text-slate-800' : 'text-red-400'">{{ asistencia.hora_entrada || '--:--' }}</span></div>
              <div>Salida:   <span class="font-medium" :class="asistencia.hora_salida ? 'text-slate-800' : 'text-slate-400'">{{ asistencia.hora_salida || '--:--' }}</span></div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
               {{ asistencia.sede_entrada?.nombre || 'Fuera de Sede' }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
               <span v-if="asistencia.estado_tardanza === 'temprano' || asistencia.estado_tardanza === 'puntual'" class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold capitalize">Puntual</span>
               <span v-else-if="asistencia.estado_tardanza === 'tarde'" class="px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-bold capitalize">Tarde
                 <span v-if="asistencia.minutos_tarde > 0"> ({{ asistencia.minutos_tarde }}m)</span>
               </span>
               <span v-else class="text-slate-400 text-xs italic">Falta / Otro</span>
            </td>
             <td class="px-6 py-4 whitespace-nowrap text-sm">
                <div v-if="asistencia.revisado_por_admin" class="flex items-center text-green-600 font-medium">
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                  <span>Revisado</span>
                </div>
                <div v-else class="text-slate-400 italic">Pendiente</div>
             </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <button @click="openModal(asistencia)" class="text-indigo-600 hover:text-indigo-900 mr-3">Ver Detalle / Revisar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="!loading && !asistencias.length" class="text-center py-10 bg-white rounded-xl border border-slate-100 text-slate-500">
      No se encontraron registros en este periodo.
    </div>

    <!-- Review Modal -->
    <div v-if="isModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-xl">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Revisión de Asistencia</h3>
        
        <div v-if="selectedAsistencia" class="space-y-4">
           <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-lg border border-slate-100">
             <div>
               <p class="text-xs text-slate-500">Trabajador</p>
               <p class="font-medium text-slate-800">{{ selectedAsistencia.usuario?.nombres }}</p>
             </div>
             <div>
               <p class="text-xs text-slate-500">Fecha</p>
               <p class="font-medium text-slate-800">{{ selectedAsistencia.fecha }}</p>
             </div>
             <div>
               <p class="text-xs text-slate-500">Hora de Entrada Oficial</p>
               <p class="font-medium text-slate-800">{{ selectedAsistencia.horario?.hora_entrada || 'N/A' }}</p>
             </div>
             <div>
               <p class="text-xs text-slate-500">Marcación Real Entrada</p>
               <p class="font-medium" :class="selectedAsistencia.estado_tardanza === 'tarde' ? 'text-red-600' : 'text-green-600'">{{ selectedAsistencia.hora_entrada }}</p>
             </div>
           </div>

           <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Notas del Administrador</label>
              <textarea v-model="formReview.observacion_admin" class="input-field min-h-[100px]" placeholder="Motivos, justificaciones verificadas o comentarios de auditoría..."></textarea>
           </div>
           
           <div class="flex items-center space-x-2 pt-2">
            <input v-model="formReview.revisado_por_admin" type="checkbox" id="revisado" class="w-5 h-5 text-primary-600 border-slate-300 rounded focus:ring-primary-500">
            <label for="revisado" class="text-sm font-medium text-slate-700">Marcar como revisado conformemente</label>
          </div>

          <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
            <button type="button" @click="closeModal" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
            <button type="button" @click="saveReview" class="btn-primary" :disabled="saving">
              {{ saving ? 'Guardando...' : 'Guardar Revisión' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/api/axios'

const asistencias = ref([])
const loading = ref(true)
const error = ref(null)
const isModalOpen = ref(false)
const saving = ref(false)
const selectedAsistencia = ref(null)

// Traer datos del mes en curso por defecto
const today = new Date()
const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)
const formatDateISOLocal = (date) => {
   const offset = date.getTimezoneOffset()
   const dateObj = new Date(date.getTime() - (offset*60*1000))
   return dateObj.toISOString().split('T')[0]
}

const filters = reactive({
  fecha_inicio: formatDateISOLocal(startOfMonth),
  fecha_fin: formatDateISOLocal(today)
})

const formReview = reactive({
  revisado_por_admin: false,
  observacion_admin: ''
})

const fetchAsistencias = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.get('/v1/web/asistencias', { params: filters })
    asistencias.value = response.data.data || response.data
  } catch (err) {
    error.value = 'Error al obtener asistencias'
  } finally {
    loading.value = false
  }
}

const openModal = (item) => {
  selectedAsistencia.value = item
  formReview.revisado_por_admin = !!item.revisado_por_admin
  formReview.observacion_admin = item.observacion_admin || ''
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
  selectedAsistencia.value = null
}

const saveReview = async () => {
  saving.value = true
  try {
    const payload = { ...formReview, revisado_por_admin: formReview.revisado_por_admin ? 1 : 0 }
    await api.put(`/v1/web/asistencias/${selectedAsistencia.value.id}/review`, payload)
    closeModal()
    fetchAsistencias()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al guardar la revisión')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchAsistencias()
})
</script>
