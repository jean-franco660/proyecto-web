<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Historial de Asistencias</h2>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2 items-center">
        <input v-model="filters.fecha_inicio" type="date" class="input-field py-1.5 px-3 text-sm w-36">
        <span class="text-slate-400">—</span>
        <input v-model="filters.fecha_fin" type="date" class="input-field py-1.5 px-3 text-sm w-36">
        <select v-model="filters.estado_marcacion" class="input-field py-1.5 px-3 text-sm">
          <option value="">Todas las marcaciones</option>
          <option value="VALIDA">Válidas</option>
          <option value="OBSERVADA">Observadas</option>
        </select>
        <button @click="fetchAsistencias" class="btn-primary py-1.5 text-sm">Filtrar</button>
        <button @click="exportarCSV" class="px-4 py-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium flex items-center space-x-1 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
          <span>Exportar</span>
        </button>
      </div>
    </div>

    <!-- Skeleton Loader -->
    <div v-if="loading" class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="bg-slate-50 px-6 py-3 grid grid-cols-6 gap-4 animate-pulse">
        <div class="h-3 bg-slate-200 rounded col-span-2"></div>
        <div class="h-3 bg-slate-200 rounded"></div>
        <div class="h-3 bg-slate-200 rounded col-span-2"></div>
        <div class="h-3 bg-slate-200 rounded"></div>
      </div>
      <div v-for="i in 6" :key="i" class="px-6 py-4 border-t border-slate-100 grid grid-cols-6 gap-4 animate-pulse">
        <div class="col-span-2 space-y-2">
          <div class="h-3 bg-slate-200 rounded w-24"></div>
          <div class="h-2 bg-slate-100 rounded w-36"></div>
        </div>
        <div class="h-5 bg-slate-200 rounded-full w-16"></div>
        <div class="h-3 bg-slate-200 rounded col-span-2 w-28"></div>
        <div class="h-5 bg-slate-200 rounded-full w-16"></div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-8 text-center">
      <svg class="w-10 h-10 text-red-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
      </svg>
      <p class="text-red-700 font-medium mb-1">No se pudo cargar el historial de asistencias</p>
      <p class="text-red-500 text-sm mb-4">{{ error }}</p>
      <button @click="fetchAsistencias" class="btn-primary text-sm">Reintentar</button>
    </div>

    <!-- Table -->
    <div v-if="!loading && asistencias.length" class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Fecha / Trabajador</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Tipo</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Marcada en</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Sede</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Estado Marcación</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Revisión</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
          <tr v-for="item in asistencias" :key="item.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-bold text-slate-800">{{ item.fecha }}</div>
              <div class="text-sm text-slate-500">{{ item.nombres }} {{ item.apellido_paterno }}</div>
              <div class="text-xs text-slate-400 font-mono">{{ item.codigo_empleado }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 py-0.5 rounded text-xs font-semibold"
                :class="item.tipo === 'ENTRADA' ? 'bg-blue-100 text-blue-700' : 'bg-indigo-100 text-indigo-700'">
                {{ item.tipo }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
              {{ item.marcada_en ? item.marcada_en.replace('T', ' ').substring(0, 16) : '—' }}
              <div class="text-xs text-slate-400">{{ item.distancia_metros != null ? item.distancia_metros + ' m' : '' }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">{{ item.sede_nombre }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span v-if="item.estado_marcacion === 'VALIDA'" class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">Válida</span>
              <span v-else class="px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-bold">Observada</span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <span v-if="item.estado_revision === 'APROBADA'" class="flex items-center text-green-600 font-medium text-xs">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Aprobada
              </span>
              <span v-else-if="item.estado_revision === 'MANTENER_OBSERVADA'" class="text-orange-500 text-xs font-medium">Mantener Obs.</span>
              <span v-else class="text-slate-400 italic text-xs">Pendiente</span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <button v-if="item.estado_marcacion === 'OBSERVADA'" @click="openModal(item)"
                class="text-indigo-600 hover:text-indigo-900">Revisar</button>
              <span v-else class="text-slate-300 text-xs">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <div v-else-if="!asistencias.length" class="text-center py-12 bg-white rounded-xl border border-slate-100">
      <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      <p class="text-slate-500 font-medium">No hay registros en este período</p>
      <p class="text-slate-400 text-sm mt-1">Ajusta el rango de fechas o cambia el filtro de estado</p>
    </div>

    <!-- Review Modal -->
    <div v-if="isModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-xl">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Revisión de Marcación Observada</h3>

        <div v-if="selectedItem" class="space-y-4">
           <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-lg border border-slate-100">
             <div>
               <p class="text-xs text-slate-500">Trabajador</p>
               <p class="font-medium text-slate-800">{{ selectedItem.nombres }} {{ selectedItem.apellido_paterno }}</p>
             </div>
             <div>
               <p class="text-xs text-slate-500">Fecha</p>
               <p class="font-medium text-slate-800">{{ selectedItem.fecha }}</p>
             </div>
             <div>
               <p class="text-xs text-slate-500">Tipo de Marcación</p>
               <p class="font-medium text-slate-800">{{ selectedItem.tipo }}</p>
             </div>
             <div>
               <p class="text-xs text-slate-500">Marcada En</p>
               <p class="font-medium text-slate-800">{{ selectedItem.marcada_en }}</p>
             </div>
             <div class="col-span-2">
               <p class="text-xs text-slate-500">Motivo de Observación</p>
               <p class="text-sm text-orange-700 font-medium">{{ selectedItem.motivo_observacion || 'Sin detalle' }}</p>
             </div>
           </div>

           <div>
             <label class="block text-sm font-medium text-slate-700 mb-1">Decisión de revisión</label>
             <select v-model="formReview.estado_revision" class="input-field">
               <option value="APROBADA">Aprobar — Marcación válida a pesar de la observación</option>
               <option value="MANTENER_OBSERVADA">Mantener Observada — Se confirma la irregularidad</option>
             </select>
           </div>

           <div>
             <label class="block text-sm font-medium text-slate-700 mb-1">Notas del Administrador</label>
             <textarea v-model="formReview.observacion" class="input-field min-h-[80px]"
               placeholder="Comentarios de auditoría o justificación..."></textarea>
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
import { useToast } from '@/composables/useToast'

const toast = useToast()

const asistencias = ref([])
const loading = ref(true)
const error = ref(null)
const isModalOpen = ref(false)
const saving = ref(false)
const selectedItem = ref(null)

const today = new Date()
const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)
const formatDateLocal = (date) => {
  const offset = date.getTimezoneOffset()
  const d = new Date(date.getTime() - (offset * 60 * 1000))
  return d.toISOString().split('T')[0]
}

const filters = reactive({
  fecha_inicio: formatDateLocal(startOfMonth),
  fecha_fin: formatDateLocal(today),
  estado_marcacion: ''
})

// El backend devuelve: estado_revision: 'APROBADA' | 'MANTENER_OBSERVADA' | 'PENDIENTE'
// y observacion como campo de texto
const formReview = reactive({
  estado_revision: 'APROBADA',
  observacion: ''
})

const fetchAsistencias = async () => {
  loading.value = true
  error.value = null
  try {
    const params = {}
    if (filters.fecha_inicio) params.fecha_inicio = filters.fecha_inicio
    if (filters.fecha_fin) params.fecha_fin = filters.fecha_fin
    if (filters.estado_marcacion) params.estado_marcacion = filters.estado_marcacion

    const response = await api.get('/v1/web/asistencias', { params })
    asistencias.value = response.data.data || response.data
  } catch (err) {
    error.value = 'Error al obtener asistencias'
  } finally {
    loading.value = false
  }
}

const openModal = (item) => {
  selectedItem.value = item
  formReview.estado_revision = item.estado_revision === 'MANTENER_OBSERVADA' ? 'MANTENER_OBSERVADA' : 'APROBADA'
  formReview.observacion = item.motivo_observacion || ''
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
  selectedItem.value = null
}

const saveReview = async () => {
  saving.value = true
  try {
    // El endpoint espera: estado_revision y observacion
    await api.put(`/v1/web/asistencias/${selectedItem.value.id}/review`, { ...formReview })
    closeModal()
    fetchAsistencias()
  } catch (err) {
    toast.error(err.response?.data?.error || 'Error al guardar la revisión')
  } finally {
    saving.value = false
  }
}

// Exportar — llama al endpoint /v1/web/asistencias/exportar con los mismos filtros
const exportarCSV = async () => {
  try {
    const params = {}
    if (filters.fecha_inicio) params.fecha_inicio = filters.fecha_inicio
    if (filters.fecha_fin) params.fecha_fin = filters.fecha_fin

    const response = await api.get('/v1/web/asistencias/exportar', { params })
    const registros = response.data.data?.registros || []
    if (!registros.length) { toast.warning('No hay registros para exportar'); return }

    // Generar CSV básico
    const headers = Object.keys(registros[0]).join(',')
    const rows = registros.map(r =>
      Object.values(r).map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')
    )
    const csv = [headers, ...rows].join('\n')
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `asistencias_${filters.fecha_inicio}_${filters.fecha_fin}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    toast.error(err.response?.data?.error || 'Error al exportar el archivo')
  }
}

onMounted(() => {
  fetchAsistencias()
})
</script>
