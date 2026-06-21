<template>
  <div class="space-y-6">
    <div class="pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Centro de Reportes</h2>
      <p class="text-sm text-slate-500 mt-1">
        Filtre y exporte reportes en formato Excel (.xls) interpretado nativamente para control, auditoría y asistencia.
      </p>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
      <p class="text-red-700 font-medium">{{ error }}</p>
      <button @click="fetchFiltersData" class="btn-primary mt-2 text-xs py-1.5">Reintentar Cargar Filtros</button>
    </div>

    <!-- Loading State for Filters -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div v-for="i in 4" :key="i" class="bg-white rounded-xl border border-slate-200 p-6 space-y-4 animate-pulse">
        <div class="h-6 bg-slate-200 rounded w-2/3"></div>
        <div class="h-4 bg-slate-100 rounded w-full"></div>
        <div class="space-y-2 pt-2">
          <div class="h-8 bg-slate-100 rounded"></div>
          <div class="h-8 bg-slate-100 rounded"></div>
        </div>
        <div class="h-10 bg-slate-200 rounded w-1/3 pt-4"></div>
      </div>
    </div>

    <!-- Reports Cards Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
      
      <!-- Card 1: Reporte Consolidado de Asistencias -->
      <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md hover:border-slate-300 transition-all flex flex-col justify-between">
        <div class="space-y-4">
          <div class="flex items-center space-x-3">
            <div class="p-2.5 bg-indigo-50 text-indigo-600 rounded-xl">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-bold text-slate-800">Reporte Consolidado de Asistencias</h3>
              <p class="text-xs text-slate-400">Resumen detallado de ingresos y salidas en un período específico.</p>
            </div>
          </div>
          
          <div class="space-y-3 pt-2">
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Sede de Asistencia</label>
              <select v-model="formConsolidado.sede_id" class="input-field py-2 text-sm">
                <option value="">Todas las Sedes</option>
                <option v-for="s in sedes" :key="s.id" :value="s.id">{{ s.nombre }} ({{ s.codigo }})</option>
              </select>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Fecha Inicio</label>
                <input v-model="formConsolidado.fecha_inicio" type="date" class="input-field py-2 text-sm" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Fecha Fin</label>
                <input v-model="formConsolidado.fecha_fin" type="date" class="input-field py-2 text-sm" />
              </div>
            </div>
          </div>
        </div>

        <div class="pt-6 border-t border-slate-100 mt-6 flex justify-end">
          <button @click="downloadReport('consolidado')" class="btn-primary w-full sm:w-auto py-2 px-5 text-sm flex items-center justify-center space-x-2 font-semibold">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>Descargar Excel</span>
          </button>
        </div>
      </div>

      <!-- Card 2: Hoja de Asistencia Individual -->
      <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md hover:border-slate-300 transition-all flex flex-col justify-between">
        <div class="space-y-4">
          <div class="flex items-center space-x-3">
            <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-xl">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-bold text-slate-800">Hoja de Asistencia Individual</h3>
              <p class="text-xs text-slate-400">Historial completo, tardanzas y marcaciones de un trabajador.</p>
            </div>
          </div>
          
          <div class="space-y-3 pt-2">
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Seleccionar Trabajador</label>
              <select v-model="formIndividual.usuario_id" class="input-field py-2 text-sm">
                <option value="">-- Seleccione un trabajador --</option>
                <option v-for="t in trabajadores" :key="t.id" :value="t.id">
                  {{ t.nombre_completo || (t.nombres + ' ' + t.apellidos) }} ({{ t.codigo || t.dni }})
                </option>
              </select>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Fecha Inicio</label>
                <input v-model="formIndividual.fecha_inicio" type="date" class="input-field py-2 text-sm" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Fecha Fin</label>
                <input v-model="formIndividual.fecha_fin" type="date" class="input-field py-2 text-sm" />
              </div>
            </div>
          </div>
        </div>

        <div class="pt-6 border-t border-slate-100 mt-6 flex justify-end">
          <button @click="downloadReport('individual')" class="btn-primary w-full sm:w-auto py-2 px-5 text-sm flex items-center justify-center space-x-2 font-semibold bg-emerald-600 hover:bg-emerald-700 border-emerald-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>Descargar Excel</span>
          </button>
        </div>
      </div>

      <!-- Card 3: Reporte Comparativo por Sedes -->
      <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md hover:border-slate-300 transition-all flex flex-col justify-between">
        <div class="space-y-4">
          <div class="flex items-center space-x-3">
            <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-bold text-slate-800">Reporte Comparativo por Sedes</h3>
              <p class="text-xs text-slate-400">Datos agrupados por establecimiento para comparar niveles de puntualidad.</p>
            </div>
          </div>
          
          <div class="space-y-3 pt-2">
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Filtrar por Sede</label>
              <select v-model="formSedes.sede_id" class="input-field py-2 text-sm">
                <option value="">Todas las Sedes</option>
                <option v-for="s in sedes" :key="s.id" :value="s.id">{{ s.nombre }} ({{ s.codigo }})</option>
              </select>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Fecha Inicio</label>
                <input v-model="formSedes.fecha_inicio" type="date" class="input-field py-2 text-sm" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Fecha Fin</label>
                <input v-model="formSedes.fecha_fin" type="date" class="input-field py-2 text-sm" />
              </div>
            </div>
          </div>
        </div>

        <div class="pt-6 border-t border-slate-100 mt-6 flex justify-end">
          <button @click="downloadReport('sedes')" class="btn-primary w-full sm:w-auto py-2 px-5 text-sm flex items-center justify-center space-x-2 font-semibold bg-blue-600 hover:bg-blue-700 border-blue-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>Descargar Excel</span>
          </button>
        </div>
      </div>

      <!-- Card 4: Reporte Mensual General de Control -->
      <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md hover:border-slate-300 transition-all flex flex-col justify-between">
        <div class="space-y-4">
          <div class="flex items-center space-x-3">
            <div class="p-2.5 bg-amber-50 text-amber-600 rounded-xl">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-bold text-slate-800">Reporte Mensual General de Control</h3>
              <p class="text-xs text-slate-400">Consolidado general de marcaciones agrupadas por un mes calendario.</p>
            </div>
          </div>
          
          <div class="space-y-3 pt-2">
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Filtrar por Sede</label>
              <select v-model="formMensual.sede_id" class="input-field py-2 text-sm">
                <option value="">Todas las Sedes</option>
                <option v-for="s in sedes" :key="s.id" :value="s.id">{{ s.nombre }} ({{ s.codigo }})</option>
              </select>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Mes</label>
                <select v-model="formMensual.mes" class="input-field py-2 text-sm">
                  <option v-for="m in meses" :key="m.valor" :value="m.valor">{{ m.nombre }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Año</label>
                <select v-model="formMensual.anio" class="input-field py-2 text-sm">
                  <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="pt-6 border-t border-slate-100 mt-6 flex justify-end">
          <button @click="downloadReport('mensual')" class="btn-primary w-full sm:w-auto py-2 px-5 text-sm flex items-center justify-center space-x-2 font-semibold bg-amber-600 hover:bg-amber-700 border-amber-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>Descargar Excel</span>
          </button>
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

const sedes = ref([])
const trabajadores = ref([])
const loading = ref(true)
const error = ref(null)

const today = new Date()
const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)

const formatDateLocal = (date) => {
  const offset = date.getTimezoneOffset()
  const d = new Date(date.getTime() - (offset * 60 * 1000))
  return d.toISOString().split('T')[0]
}

const formConsolidado = reactive({
  fecha_inicio: formatDateLocal(startOfMonth),
  fecha_fin: formatDateLocal(today),
  sede_id: ''
})

const formIndividual = reactive({
  fecha_inicio: formatDateLocal(startOfMonth),
  fecha_fin: formatDateLocal(today),
  usuario_id: ''
})

const formSedes = reactive({
  fecha_inicio: formatDateLocal(startOfMonth),
  fecha_fin: formatDateLocal(today),
  sede_id: ''
})

const formMensual = reactive({
  mes: today.getMonth() + 1,
  anio: today.getFullYear(),
  sede_id: ''
})

const meses = [
  { valor: 1, nombre: 'Enero' },
  { valor: 2, nombre: 'Febrero' },
  { valor: 3, nombre: 'Marzo' },
  { valor: 4, nombre: 'Abril' },
  { valor: 5, nombre: 'Mayo' },
  { valor: 6, nombre: 'Junio' },
  { valor: 7, nombre: 'Julio' },
  { valor: 8, nombre: 'Agosto' },
  { valor: 9, nombre: 'Septiembre' },
  { valor: 10, nombre: 'Octubre' },
  { valor: 11, nombre: 'Noviembre' },
  { valor: 12, nombre: 'Diciembre' }
]

const anios = ref([])
const currentYear = today.getFullYear()
for (let y = currentYear; y >= currentYear - 3; y--) {
  anios.value.push(y)
}

const fetchFiltersData = async () => {
  loading.value = true
  error.value = null
  try {
    const [resSedes, resTrabajadores] = await Promise.all([
      api.get('/v1/web/sedes/mis-sedes'),
      api.get('/v1/web/usuarios-app?per_page=1000')
    ])
    sedes.value = resSedes.data.data || resSedes.data || []
    
    const resTrabData = resTrabajadores.data.data || resTrabajadores.data
    trabajadores.value = resTrabData.data || resTrabData || []
  } catch (err) {
    console.error('Error al cargar filtros para reportes:', err)
    error.value = 'No se pudieron cargar los datos de sedes o trabajadores para filtrar.'
  } finally {
    loading.value = false
  }
}

const downloadReport = (type) => {
  const token = localStorage.getItem('token')
  const baseUrl = api.defaults.baseURL || 'http://localhost/Asistencia-Backend-php/public'
  
  let endpoint = ''
  let params = new URLSearchParams()
  
  if (type === 'consolidado') {
    if (!formConsolidado.fecha_inicio || !formConsolidado.fecha_fin) {
      toast.error('Las fechas de inicio y fin son requeridas')
      return
    }
    if (new Date(formConsolidado.fecha_inicio) > new Date(formConsolidado.fecha_fin)) {
      toast.error('La fecha de inicio no puede ser posterior a la fecha de fin')
      return
    }
    endpoint = '/v1/web/reportes/consolidado'
    params.append('fecha_inicio', formConsolidado.fecha_inicio)
    params.append('fecha_fin', formConsolidado.fecha_fin)
    if (formConsolidado.sede_id) params.append('sede_id', formConsolidado.sede_id)
  }
  else if (type === 'individual') {
    if (!formIndividual.usuario_id) {
      toast.error('Debe seleccionar un trabajador')
      return
    }
    if (!formIndividual.fecha_inicio || !formIndividual.fecha_fin) {
      toast.error('Las fechas de inicio y fin son requeridas')
      return
    }
    if (new Date(formIndividual.fecha_inicio) > new Date(formIndividual.fecha_fin)) {
      toast.error('La fecha de inicio no puede ser posterior a la fecha de fin')
      return
    }
    endpoint = '/v1/web/reportes/individual'
    params.append('usuario_id', formIndividual.usuario_id)
    params.append('fecha_inicio', formIndividual.fecha_inicio)
    params.append('fecha_fin', formIndividual.fecha_fin)
  }
  else if (type === 'sedes') {
    if (!formSedes.fecha_inicio || !formSedes.fecha_fin) {
      toast.error('Las fechas de inicio y fin son requeridas')
      return
    }
    if (new Date(formSedes.fecha_inicio) > new Date(formSedes.fecha_fin)) {
      toast.error('La fecha de inicio no puede ser posterior a la fecha de fin')
      return
    }
    endpoint = '/v1/web/reportes/sedes'
    params.append('fecha_inicio', formSedes.fecha_inicio)
    params.append('fecha_fin', formSedes.fecha_fin)
    if (formSedes.sede_id) params.append('sede_id', formSedes.sede_id)
  }
  else if (type === 'mensual') {
    if (!formMensual.mes || !formMensual.anio) {
      toast.error('El mes y año son requeridos')
      return
    }
    endpoint = '/v1/web/reportes/mensual'
    params.append('mes', formMensual.mes)
    params.append('anio', formMensual.anio)
    if (formMensual.sede_id) params.append('sede_id', formMensual.sede_id)
  }
  
  params.append('token', token)
  
  const downloadUrl = `${baseUrl}${endpoint}?${params.toString()}`
  window.open(downloadUrl, '_blank')
}

onMounted(() => {
  fetchFiltersData()
})
</script>
