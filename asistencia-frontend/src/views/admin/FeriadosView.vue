<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Días Feriados</h2>
      <button @click="openModal()" class="btn-primary flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        <span>Nuevo Feriado</span>
      </button>
    </div>

    <!-- Error/Loading Messages -->
    <div v-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg">{{ error }}</div>
    
    <!-- Calendar Header Controls -->
    <div class="flex items-center justify-between bg-white p-4 rounded-xl shadow-sm border border-slate-100">
      <button @click="changeMonth(-1)" class="p-2 text-slate-500 hover:bg-slate-100 rounded-lg">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
      </button>
      <h3 class="text-lg font-bold text-slate-800 capitalize">{{ currentMonthName }} {{ currentYear }}</h3>
      <button @click="changeMonth(1)" class="p-2 text-slate-500 hover:bg-slate-100 rounded-lg">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </button>
    </div>

    <div v-if="loading" class="text-center py-10 text-slate-500">Cargando feriados...</div>

    <!-- Calendar Grid -->
    <div v-if="!loading" class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
      <!-- Days of Week -->
      <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50">
        <div v-for="day in ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']" :key="day" class="py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider border-r border-slate-200 last:border-r-0">
          {{ day }}
        </div>
      </div>
      
      <!-- Days Grid -->
      <div class="grid grid-cols-7 auto-rows-fr">
        <!-- Empty padding days -->
        <div v-for="blank in blankDays" :key="'blank-'+blank" class="min-h-[120px] bg-slate-50/50 border-r border-b border-slate-200"></div>
        
        <!-- Actual days -->
        <div v-for="date in daysInMonth" :key="date.day" 
             @click="openModalForDate(date.fullDateStr)"
             class="min-h-[120px] p-2 border-r border-b border-slate-200 last:border-r-0 cursor-pointer hover:bg-slate-50 transition-colors group relative"
             :class="{'bg-teal-50/30': date.isToday}">
          
          <div class="flex justify-between items-start">
            <span class="text-sm font-medium" :class="date.isToday ? 'text-teal-600 bg-teal-100 w-6 h-6 rounded-full flex items-center justify-center' : 'text-slate-700'">
              {{ date.day }}
            </span>
          </div>

          <!-- Feriado Badges -->
          <div class="mt-2 space-y-1">
            <div v-for="feriado in getFeriadosForDate(date.fullDateStr)" :key="feriado.id" 
                 @click.stop="openModal(feriado)"
                 class="px-2 py-1 text-xs font-medium rounded truncate shadow-sm hover:shadow"
                 :class="feriado.tipo === 'NACIONAL' ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-indigo-100 text-indigo-800 border border-indigo-200'">
              {{ feriado.nombre || feriado.motivo }}
            </div>
          </div>
          
          <!-- Hover Icon Add -->
          <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity bg-black/5">
            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <div v-if="isModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-xl">
        <h3 class="text-lg font-bold text-slate-800 mb-4">{{ isEditing ? 'Editar Feriado' : 'Nuevo Feriado' }}</h3>
        <form @submit.prevent="saveItem" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Fecha</label>
            <input v-model="form.fecha" type="date" required class="input-field">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre / Motivo</label>
            <input v-model="form.nombre" type="text" required class="input-field" placeholder="Ej: Fiestas Patrias">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Tipo Feriado</label>
            <select v-model="form.tipo" class="input-field" required>
              <option value="NACIONAL">Nacional</option>
              <option value="LOCAL">Local / Regional</option>
              <option value="EMPRESA">De Empresa</option>
            </select>
          </div>
          <!-- TODO: Select for Sede if EMPRESA is selected -->
          <div v-if="form.tipo === 'EMPRESA'">
            <label class="block text-sm font-medium text-slate-700 mb-1">ID de Sede (Opcional)</label>
            <input v-model="form.sede_id" type="number" class="input-field" placeholder="ID de la sede">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Estado</label>
            <select v-model="form.estado" class="input-field">
              <option value="1">Activo</option>
              <option value="0">Inactivo</option>
            </select>
          </div>
          <div class="flex justify-between space-x-3 pt-4 border-t border-slate-100">
            <div>
              <button v-if="isEditing" type="button" @click="deleteItem(form.id)" class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg">Eliminar</button>
            </div>
            <div class="flex space-x-2">
              <button type="button" @click="closeModal" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
              <button type="submit" class="btn-primary" :disabled="saving">
                {{ saving ? 'Guardando...' : 'Guardar' }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import api from '@/api/axios'

const feriados = ref([])
const loading = ref(true)
const error = ref(null)

// Calendar State
const currentDate = ref(new Date())

// Form & Modal State
const isModalOpen = ref(false)
const saving = ref(false)
const isEditing = ref(false)

const form = reactive({
  id: null,
  fecha: '',
  nombre: '',
  tipo: 'NACIONAL',
  sede_id: '',
  estado: 1
})

const fetchFeriados = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.get('/v1/web/feriados')
    feriados.value = response.data.data || response.data
  } catch (err) {
    error.value = 'Error al obtener feriados'
  } finally {
    loading.value = false
  }
}

// Calendar Logic
const currentYear = computed(() => currentDate.value.getFullYear())
const currentMonthNumber = computed(() => currentDate.value.getMonth())
const currentMonthName = computed(() => {
  return currentDate.value.toLocaleString('es-ES', { month: 'long' })
})

const daysInMonth = computed(() => {
  const year = currentYear.value
  const month = currentMonthNumber.value
  const daysInThisMonth = new Date(year, month + 1, 0).getDate()
  
  const days = []
  const today = new Date()
  
  for (let i = 1; i <= daysInThisMonth; i++) {
    const d = new Date(year, month, i)
    // String in YYYY-MM-DD local format
    const fullDateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`
    
    days.push({
      day: i,
      fullDateStr: fullDateStr,
      isToday: d.getDate() === today.getDate() && d.getMonth() === today.getMonth() && d.getFullYear() === today.getFullYear()
    })
  }
  return days
})

const blankDays = computed(() => {
  const year = currentYear.value
  const month = currentMonthNumber.value
  // Day of week of the 1st of the month (0 = Sun, 1 = Mon ... 6 = Sat)
  let firstDayOfWeek = new Date(year, month, 1).getDay()
  // Adjust so Monday is 0 and Sunday is 6
  firstDayOfWeek = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1
  return firstDayOfWeek
})

const changeMonth = (step) => {
  currentDate.value = new Date(currentYear.value, currentMonthNumber.value + step, 1)
}

const getFeriadosForDate = (dateStr) => {
  return feriados.value.filter(f => f.fecha === dateStr && (f.activo === 1 || f.estado === 'activo' || f.activo === true))
}

const openModalForDate = (dateStr) => {
  isEditing.value = false
  Object.assign(form, { id: null, fecha: dateStr, nombre: '', tipo: 'NACIONAL', sede_id: '', estado: 1 })
  isModalOpen.value = true
}

const openModal = (item = null) => {
  if (item) {
    isEditing.value = true
    Object.assign(form, {
      id: item.id,
      fecha: item.fecha,
      nombre: item.nombre || item.motivo || '',
      tipo: item.tipo || 'NACIONAL',
      sede_id: item.sede_id || '',
      estado: item.activo !== undefined ? item.activo : 1
    })
  } else {
    isEditing.value = false
    const todayStr = new Date().toISOString().split('T')[0]
    Object.assign(form, { id: null, fecha: todayStr, nombre: '', tipo: 'NACIONAL', sede_id: '', estado: 1 })
  }
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
}

const saveItem = async () => {
  saving.value = true
  try {
    const payload = { ...form }
    if (payload.tipo !== 'EMPRESA') delete payload.sede_id
    payload.activo = payload.estado
    
    if (isEditing.value) {
      await api.put(`/v1/web/feriados/${form.id}`, payload)
    } else {
      await api.post('/v1/web/feriados', payload)
    }
    closeModal()
    fetchFeriados()
  } catch (err) {
    alert(err.response?.data?.error || err.response?.data?.message || 'Error al guardar el feriado')
  } finally {
    saving.value = false
  }
}

const deleteItem = async (id) => {
  if (!confirm('¿Seguro que deseas eliminar este feriado?')) return
  try {
    await api.delete(`/v1/web/feriados/${id}`)
    closeModal()
    fetchFeriados()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al eliminar')
  }
}

onMounted(() => {
  fetchFeriados()
})
</script>
