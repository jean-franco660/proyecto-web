<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Solicitudes de Justificaciones</h2>
      
      <div class="flex space-x-2">
         <select v-model="filterEstado" @change="fetchJustificaciones" class="input-field py-1.5 px-3 text-sm min-w-[150px]">
           <option value="">Todos los Estados</option>
           <option value="pendiente">Pendientes</option>
           <option value="aprobado">Aprobados</option>
           <option value="rechazado">Rechazados</option>
         </select>
      </div>
    </div>

    <!-- Error/Loading Messages -->
    <div v-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg">{{ error }}</div>
    <div v-if="loading" class="text-center py-10 text-slate-500">Cargando justificaciones...</div>

    <!-- Grid de Tarjetas -->
    <div v-if="!loading && justificaciones.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
      <div v-for="just in justificaciones" :key="just.id" class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm flex flex-col hover:border-indigo-200 transition-colors">
        
        <div class="flex justify-between items-start mb-4">
          <div>
             <h3 class="font-bold text-slate-800">{{ just.usuario?.nombres }} {{ just.usuario?.apellidos }}</h3>
             <p class="text-xs text-slate-500">{{ just.usuario?.numero_documento }}</p>
          </div>
          <span class="px-2 py-1 text-xs font-bold rounded-full uppercase tracking-wide" 
               :class="{
                 'bg-amber-100 text-amber-800': just.estado === 'pendiente',
                 'bg-green-100 text-green-800': just.estado === 'aprobado',
                 'bg-red-100 text-red-800': just.estado === 'rechazado'
               }">
             {{ just.estado }}
          </span>
        </div>
        
        <div class="flex-1 space-y-3">
          <div class="flex justify-between text-sm">
            <span class="text-slate-500">Fecha Ausencia/Falta:</span>
            <span class="font-medium text-slate-800">{{ just.fecha_justificar }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-slate-500">Motivo Corto:</span>
            <span class="font-medium text-slate-800">{{ just.motivo_tipo || 'Salud / Otro' }}</span>
          </div>
          <div>
            <span class="text-xs text-slate-500 block mb-1">Descripción:</span>
            <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-100 italic line-clamp-3" :title="just.descripcion">
              "{{ just.descripcion }}"
            </p>
          </div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100 space-y-3">
           <div v-if="just.archivo_adjunto" class="flex items-center text-sm text-indigo-600">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
              <a :href="getImageUrl(just.archivo_adjunto)" target="_blank" class="hover:underline">Ver Documento Adjunto</a>
           </div>

           <!-- Action buttons for Pendientes -->
           <div v-if="just.estado === 'pendiente'" class="grid grid-cols-2 gap-2 mt-4">
              <button @click="openModal(just, 'aprobar')" class="py-2 bg-green-50 hover:bg-green-100 text-green-700 font-medium rounded-lg text-sm border border-green-200 transition-colors">Aprobar</button>
              <button @click="openModal(just, 'rechazar')" class="py-2 bg-red-50 hover:bg-red-100 text-red-700 font-medium rounded-lg text-sm border border-red-200 transition-colors">Rechazar</button>
           </div>
           
           <!-- Observación admin info -->
           <div v-if="just.observacion_admin && just.estado !== 'pendiente'" class="mt-2 text-xs bg-slate-50 p-2 rounded border border-slate-100 text-slate-600">
             <strong>Respuesta Adm:</strong> {{ just.observacion_admin }}
           </div>
        </div>
      </div>
    </div>

    <div v-else-if="!loading && !justificaciones.length" class="text-center py-10 bg-white rounded-xl border border-slate-100 text-slate-500">
      No hay solicitudes de justificación.
    </div>

    <!-- Eval Modal -->
    <div v-if="isModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-bold mb-2 flex items-center" :class="evalType === 'aprobar' ? 'text-green-700' : 'text-red-700'">
           <svg v-if="evalType === 'aprobar'" class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
           <svg v-else class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
           Confirmar {{ evalType === 'aprobar' ? 'Aprobación' : 'Rechazo' }}
        </h3>
        <p class="text-sm text-slate-600 mb-4">Ingresa una nota o justificación opcional para el trabajador.</p>
        
        <textarea v-model="observacion_admin" class="input-field min-h-[100px] mb-4" placeholder="Observaciones (opcional)..."></textarea>

        <div class="flex justify-end space-x-3 pt-2">
          <button @click="closeModal" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
          <button @click="submitEval" class="px-6 py-2 text-white font-medium rounded-lg transition-colors shadow-sm"
                  :class="evalType === 'aprobar' ? 'bg-green-600 hover:bg-green-700 shadow-green-600/30' : 'bg-red-600 hover:bg-red-700 shadow-red-600/30'"
                  :disabled="saving">
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

const justificaciones = ref([])
const loading = ref(true)
const error = ref(null)

const filterEstado = ref('pendiente') // Por defecto mostrar pendientes para revisión rápida

const isModalOpen = ref(false)
const saving = ref(false)
const selectedItem = ref(null)
const evalType = ref('aprobar')
const observacion_admin = ref('')

const fetchJustificaciones = async () => {
  loading.value = true
  error.value = null
  try {
    const params = filterEstado.value ? { estado: filterEstado.value } : {}
    const response = await api.get('/v1/web/justificaciones', { params })
    justificaciones.value = response.data.data || response.data
  } catch (err) {
    error.value = 'Error al obtener justificaciones'
  } finally {
    loading.value = false
  }
}

const getImageUrl = (path) => {
  if (!path) return '#'
  if (path.startsWith('http')) return path
  // Usar BASE_URL del API para componer la URL completa de la imagen si viene de storage
  return `${api.defaults.baseURL.replace('/v1/web', '')}/${path}`
}

const openModal = (item, type) => {
  selectedItem.value = item
  evalType.value = type
  observacion_admin.value = ''
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
  selectedItem.value = null
}

const submitEval = async () => {
  saving.value = true
  try {
    const url = `/v1/web/justificaciones/${selectedItem.value.id}/${evalType.value}`
    await api.post(url, { observacion_admin: observacion_admin.value })
    closeModal()
    fetchJustificaciones()
  } catch (err) {
    alert(err.response?.data?.error || `Error al ${evalType.value} la justificación`)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchJustificaciones()
})
</script>
