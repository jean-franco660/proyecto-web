<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Solicitudes de Ausencia</h2>
      <!-- Filtros -->
      <div class="flex items-center gap-2 flex-wrap">
        <select v-model="filtroEstado" @change="fetchData" class="input-field text-sm py-1.5 w-40">
          <option value="">Todos</option>
          <option value="PENDIENTE">Pendientes</option>
          <option value="APROBADO">Aprobadas</option>
          <option value="RECHAZADO">Rechazadas</option>
        </select>
      </div>
    </div>

    <div v-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg">{{ error }}</div>

    <!-- Skeleton -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 5" :key="i" class="bg-white rounded-xl border border-slate-200 p-4 animate-pulse flex gap-4">
        <div class="w-20 h-5 bg-slate-200 rounded"></div>
        <div class="flex-1 h-5 bg-slate-100 rounded w-1/2"></div>
        <div class="w-24 h-5 bg-slate-200 rounded"></div>
      </div>
    </div>

    <!-- Tabla -->
    <div v-if="!loading" class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Trabajador</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Tipo</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Período</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Estado</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Fecha solicitud</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="!solicitudes.length">
            <td colspan="6" class="text-center py-12 text-slate-400">No hay solicitudes que mostrar</td>
          </tr>
          <tr v-for="s in solicitudes" :key="s.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3">
              <p class="text-sm font-medium text-slate-800">{{ s.trabajador || `#${s.usuario_id}` }}</p>
              <p class="text-xs text-slate-400">{{ s.codigo_trabajador }}</p>
            </td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ s.tipo_nombre || s.tipo_codigo }}</td>
            <td class="px-4 py-3 text-sm text-slate-700">
              <span class="font-medium">{{ s.fecha_inicio }}</span>
              <span v-if="s.fecha_fin !== s.fecha_inicio"> → {{ s.fecha_fin }}</span>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="{
                  'bg-amber-100 text-amber-800': s.estado === 'PENDIENTE',
                  'bg-green-100 text-green-800': s.estado === 'APROBADO',
                  'bg-red-100 text-red-800':    s.estado === 'RECHAZADO',
                }">
                {{ s.estado }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-slate-500">{{ formatDate(s.created_at) }}</td>
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end gap-2" v-if="s.estado === 'PENDIENTE'">
                <button @click="aprobar(s.id)" class="px-3 py-1 text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                  Aprobar
                </button>
                <button @click="openRechazar(s)" class="px-3 py-1 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                  Rechazar
                </button>
              </div>
              <span v-else class="text-xs text-slate-400">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Rechazar -->
    <div v-if="modalRechazar" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Rechazar Solicitud</h3>
        <p class="text-sm text-slate-600 mb-4">
          Solicitud de <strong>{{ solicitudActual?.trabajador }}</strong>
          ({{ solicitudActual?.tipo_nombre }}, {{ solicitudActual?.fecha_inicio }} → {{ solicitudActual?.fecha_fin }})
        </p>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Motivo del rechazo <span class="text-red-500">*</span></label>
          <textarea v-model="comentarioRechazo" class="input-field" rows="3" placeholder="Explica el motivo del rechazo..."></textarea>
        </div>
        <div class="flex justify-end space-x-3 pt-4 mt-4 border-t border-slate-100">
          <button @click="modalRechazar = false" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
          <button @click="confirmarRechazo" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700" :disabled="!comentarioRechazo || procesando">
            {{ procesando ? 'Procesando...' : 'Confirmar Rechazo' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/axios'

const solicitudes = ref([])
const loading = ref(true)
const error = ref(null)
const filtroEstado = ref('')

const modalRechazar = ref(false)
const solicitudActual = ref(null)
const comentarioRechazo = ref('')
const procesando = ref(false)

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('es-PE', { day: '2-digit', month: 'short', year: 'numeric' })
}

const fetchData = async () => {
  loading.value = true
  error.value = null
  try {
    const params = {}
    if (filtroEstado.value) params.estado = filtroEstado.value
    const res = await api.get('/v1/web/solicitudes-ausencia', { params })
    solicitudes.value = res.data.data ?? res.data
  } catch {
    error.value = 'Error al cargar solicitudes de ausencia'
  } finally {
    loading.value = false
  }
}

const aprobar = async (id) => {
  if (!confirm('¿Aprobar esta solicitud?')) return
  try {
    await api.post(`/v1/web/solicitudes-ausencia/${id}/aprobar`)
    fetchData()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al aprobar')
  }
}

const openRechazar = (s) => {
  solicitudActual.value = s
  comentarioRechazo.value = ''
  modalRechazar.value = true
}

const confirmarRechazo = async () => {
  if (!comentarioRechazo.value.trim()) return
  procesando.value = true
  try {
    await api.post(`/v1/web/solicitudes-ausencia/${solicitudActual.value.id}/rechazar`, {
      comentario_revision: comentarioRechazo.value
    })
    modalRechazar.value = false
    fetchData()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al rechazar')
  } finally {
    procesando.value = false
  }
}

onMounted(() => { fetchData() })
</script>
