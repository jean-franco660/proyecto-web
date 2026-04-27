<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Trabajadores</h2>
      <div class="flex space-x-3">
         <button @click="openModal()" class="btn-primary flex items-center space-x-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
          <span>Nuevo Trabajador</span>
        </button>
      </div>
    </div>

    <!-- Skeleton Loader -->
    <div v-if="loading" class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="min-w-full">
        <div class="bg-slate-50 px-6 py-3 flex gap-4">
          <div class="h-3 bg-slate-200 rounded animate-pulse w-32"></div>
          <div class="h-3 bg-slate-200 rounded animate-pulse w-24"></div>
          <div class="h-3 bg-slate-200 rounded animate-pulse w-20"></div>
        </div>
        <div v-for="i in 5" :key="i" class="px-6 py-4 border-t border-slate-100 flex items-center gap-4 animate-pulse">
          <div class="flex-1 space-y-2">
            <div class="h-3 bg-slate-200 rounded w-40"></div>
            <div class="h-2 bg-slate-100 rounded w-24"></div>
          </div>
          <div class="h-3 bg-slate-200 rounded w-28"></div>
          <div class="h-3 bg-slate-200 rounded w-20"></div>
          <div class="h-5 bg-slate-200 rounded-full w-14"></div>
          <div class="h-3 bg-slate-200 rounded w-24"></div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-8 text-center">
      <svg class="w-10 h-10 text-red-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
      </svg>
      <p class="text-red-700 font-medium mb-1">No se pudo cargar la lista de trabajadores</p>
      <p class="text-red-500 text-sm mb-4">{{ error }}</p>
      <button @click="fetchInicial()" class="btn-primary text-sm">Reintentar</button>
    </div>

    <!-- Table -->
    <div v-if="!loading && usuarios.length" class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Identidad</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Sede</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Cargo</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Horario</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Estado</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 bg-white">
            <tr v-for="user in usuarios" :key="user.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="ml-0">
                    <div class="text-sm font-bold text-slate-800">{{ user.nombres }} {{ user.apellido_paterno }} {{ user.apellido_materno }}</div>
                    <div class="text-xs text-slate-500">DNI: {{ user.dni }}</div>
                    <div class="text-xs text-slate-500">{{ user.email }}</div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">{{ user.instituciones?.[0]?.nombre || 'No asignado' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">{{ user.instituciones?.[0]?.pivot?.cargo || 'No asignado' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                <div v-if="user.instituciones?.[0]" class="font-medium text-indigo-600">{{ user.instituciones[0].pivot?.hora_inicio || '—' }}</div>
                <div v-if="user.instituciones?.[0]" class="text-xs">{{ user.instituciones[0].pivot?.fecha_inicio || '' }} </div>
                <span v-else class="text-slate-400 italic">Sin horario</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <button @click="toggleEstado(user)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full focus:outline-none" :class="user.estado === 'ACTIVO' ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200'" title="Click para cambiar estado">
                  {{ user.estado }}
                </button>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button @click="openHorarioModal(user)" class="text-teal-600 hover:text-teal-900 mr-3" title="Asignar Horario">
                  <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </button>
                <button @click="openModal(user)" class="text-indigo-600 hover:text-indigo-900 mr-3">Editar</button>
                <button @click="deleteItem(user.id)" class="text-red-600 hover:text-red-900">Eliminar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Paginación -->
      <div class="bg-slate-50 px-6 py-3 border-t border-slate-200 flex items-center justify-between">
        <div class="text-sm text-slate-500">
          Mostrando página <span class="font-medium">{{ currentPage }}</span> de <span class="font-medium">{{ totalPages }}</span> ({{ totalItems }} registros)
        </div>
        <div class="flex space-x-2">
          <button @click="goToPage(currentPage - 1)" :disabled="currentPage <= 1" class="px-3 py-1 border border-slate-300 rounded text-sm text-slate-600 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed">Anterior</button>
          <button @click="goToPage(currentPage + 1)" :disabled="currentPage >= totalPages" class="px-3 py-1 border border-slate-300 rounded text-sm text-slate-600 hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed">Siguiente</button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!usuarios.length" class="bg-white rounded-xl border border-slate-100 p-12 text-center">
      <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      <p class="text-slate-500 font-medium">No hay trabajadores registrados</p>
      <p class="text-slate-400 text-sm mt-1">Crea el primero usando el botón "Nuevo Trabajador"</p>
    </div>
    <div v-if="isModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-2xl shadow-xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-slate-800 mb-4">{{ isEditing ? 'Editar Trabajador' : 'Nuevo Trabajador' }}</h3>
        <form @submit.prevent="saveItem" class="space-y-4">
           <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Nombres</label>
              <input v-model="form.nombres" type="text" required class="input-field" placeholder="Nombres">
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Ap. Paterno</label>
                <input v-model="form.apellido_paterno" type="text" required class="input-field" placeholder="Paterno">
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Ap. Materno</label>
                <input v-model="form.apellido_materno" type="text" class="input-field" placeholder="Materno">
              </div>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">DNI</label>
              <input v-model="form.dni" type="text" required class="input-field" placeholder="Nro de Documento">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Código Modular</label>
              <input v-model="form.codigo_modular" type="text" required class="input-field" placeholder="Emp-xxx">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Correo Electrónico</label>
            <input v-model="form.email" type="email" class="input-field" placeholder="trabajador@ejemplo.com">
          </div>
          <div v-if="!isEditing || form.password !== undefined">
             <label class="block text-sm font-medium text-slate-700 mb-1">
                Contraseña <span v-if="isEditing" class="text-xs text-slate-400 font-normal">(Vacío para no cambiar)</span>
             </label>
             <input v-model="form.password" type="password" :required="!isEditing" class="input-field" placeholder="••••••••">
          </div>
          <div class="grid grid-cols-2 gap-4">
             <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Cargo</label>
              <input v-model="form.cargo" type="text" class="input-field" placeholder="Inspector, Médico, etc.">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Condición Laboral</label>
              <input v-model="form.condicion_laboral" type="text" class="input-field" placeholder="CAS, Nombrado, etc.">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Sede asignada</label>
            <select v-model="form.sede_id" class="input-field" required>
              <option value="">Seleccione sede</option>
              <option v-for="s in sedes" :key="s.id" :value="s.id">{{ s.nombre }}</option>
            </select>
          </div>
          <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
            <button type="button" @click="closeModal" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Form (Asignar Horario) -->
    <div v-if="isHorarioModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
        <h3 class="text-lg font-bold text-slate-800 mb-2">Asignar Horario</h3>
        <p class="text-sm text-slate-500 mb-4">Trabajador: <strong>{{ selectedUser?.nombres }} {{ selectedUser?.apellidos }}</strong></p>
        
        <form @submit.prevent="saveHorarioAssign" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Seleccionar Horario</label>
            <select v-model="horarioForm.horario_sede_id" class="input-field" required>
               <option :value="null">Ninguno / Quitar Horario</option>
               <option v-for="h in horariosList" :key="h.id" :value="h.id">{{ h.nombre_turno }} ({{h.hora_entrada}} - {{h.hora_salida}})</option>
            </select>
          </div>

          <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
            <button type="button" @click="closeHorarioModal" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Aplicando...' : 'Aplicar' }}
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
import { useToast } from '@/composables/useToast'

const toast = useToast()

const usuarios = ref([])
const horariosList = ref([])
const sedes = ref([])
const selectedUser = ref(null)

const loading = ref(true)
const error = ref(null)
const saving = ref(false)

const isModalOpen = ref(false)
const isHorarioModalOpen = ref(false)
const isEditing = ref(false)

const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const perPage = ref(20)

const form = reactive({
  id: null,
  nombres: '',
  apellido_paterno: '',
  apellido_materno: '',
  dni: '',
  codigo_modular: '',
  email: '',
  password: '',
  estado: 'ACTIVO',
  cargo: '',
  condicion_laboral: '',
  sede_id: null
})

const horarioForm = reactive({
  horario_sede_id: null,
  sede_id: null
})

const fetchInicial = async (page = 1) => {
  loading.value = true
  error.value = null
  currentPage.value = page
  try {
    const [resUsuarios, resHorarios, resSedes] = await Promise.all([
       api.get(`/v1/web/usuarios-app?page=${currentPage.value}&per_page=${perPage.value}`),
       api.get('/v1/web/horarios'),
       api.get('/v1/web/sedes')
    ])
    const result = resUsuarios.data.data || resUsuarios.data;
    if (result.current_page) {
      usuarios.value = result.data || []
      currentPage.value = result.current_page
      totalPages.value = result.last_page
      totalItems.value = result.total
    } else {
      usuarios.value = result || []
    }
    horariosList.value = resHorarios.data.data || resHorarios.data;
    sedes.value = resSedes.data.data || resSedes.data  } catch (err) {
    error.value = 'Error al obtener registros'
  } finally {
    loading.value = false
  }
}

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    fetchInicial(page)
  }
}

const openModal = (item = null) => {
  if (item) {
    isEditing.value = true
    Object.assign(form, item)
    form.password = ''
    form.codigo_modular = item.codigo // Map from 'codigo' payload property
  } else {
    isEditing.value = false
    Object.assign(form, { id: null, nombres: '', apellido_paterno: '', apellido_materno: '', dni: '', codigo_modular: '', email: '', password: '', estado: 'ACTIVO', cargo: '', condicion_laboral: '' })
  }
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
}

const openHorarioModal = (user) => {
  selectedUser.value = user
  horarioForm.horario_sede_id = null
  horarioForm.sede_id = user.instituciones?.[0]?.id || null
  isHorarioModalOpen.value = true
}

const closeHorarioModal = () => {
  isHorarioModalOpen.value = false
  selectedUser.value = null
}

const saveItem = async () => {
  saving.value = true
  try {
    const payload = { ...form }
    if (isEditing.value && !payload.password) delete payload.password

    if (isEditing.value) {
      const updatePayload = {
        ...payload,
        asignaciones: form.sede_id ? [{ institucion_id: form.sede_id, cargo: form.cargo || 'DOCENTE', estado: 'ACTIVO' }] : []
      }
      await api.put(`/v1/web/usuarios-app/${form.id}`, updatePayload)
    } else {
      const createPayload = {
        ...payload,
        asignaciones: form.sede_id ? [{ institucion_id: form.sede_id, cargo: form.cargo || 'DOCENTE', estado: 'ACTIVO' }] : []
      }
      await api.post('/v1/web/usuarios-app', createPayload)
    }
    closeModal()
    fetchInicial()
  } catch (err) {
    toast.error(err.response?.data?.error || err.response?.data?.message || 'Error al guardar')
  } finally {
    saving.value = false
  }
}

const saveHorarioAssign = async () => {
  saving.value = true
  try {
    if (!horarioForm.sede_id) {
      toast.warning('El trabajador no tiene sede asignada. Edítalo primero.')
      saving.value = false
      return
    }
    // El backend espera: sede_id y horario_sede_id (puede ser null para quitar horario)
    await api.patch(`/v1/web/usuarios-app/${selectedUser.value.id}/horario`, {
      sede_id: horarioForm.sede_id,
      horario_sede_id: horarioForm.horario_sede_id
    })
    closeHorarioModal()
    fetchInicial()
  } catch (err) {
    toast.error(err.response?.data?.error || 'Error al asignar horario')
  } finally {
    saving.value = false
  }
}

const toggleEstado = async (user) => {
  const nuevoEstado = user.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'
  try {
    await api.patch(`/v1/web/usuarios-app/${user.id}/estado`, { estado: nuevoEstado })
    user.estado = nuevoEstado
  } catch (err) {
    toast.error(err.response?.data?.error || 'Error al cambiar estado')
  }
}

const deleteItem = async (id) => {
  if (!confirm('¿Seguro que deseas eliminar este trabajador? Todo su historial podría perderse.')) return
  try {
    await api.delete(`/v1/web/usuarios-app/${id}`)
    fetchInicial()
  } catch (err) {
    toast.error(err.response?.data?.error || 'Error al eliminar')
  }
}

onMounted(() => {
  fetchInicial()
})
</script>
