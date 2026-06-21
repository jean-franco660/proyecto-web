<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Sedes</h2>
      <div class="flex space-x-2">
        <button @click="openImportModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 bg-white hover:bg-slate-50 transition-colors font-medium shadow-sm flex items-center space-x-2 text-sm">
          <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
          <span>Importar Excel</span>
        </button>
        <button @click="openModal()" class="btn-primary flex items-center space-x-2 text-sm">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          <span>Nueva Sede</span>
        </button>
      </div>
    </div>

    <!-- Error/Loading Messages -->
    <div v-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg">{{ error }}</div>
    <div v-if="loading" class="text-center py-10 text-slate-500">Cargando sedes...</div>

    <!-- Table -->
    <div v-if="!loading && sedes.length" class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Código / Nombre</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Dirección</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Radio GPS</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Estado</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
          <tr v-for="sede in sedes" :key="sede.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-bold text-slate-800">{{ sede.nombre }}</div>
              <div class="text-xs text-slate-400 font-mono">{{ sede.codigo }}</div>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600 max-w-xs truncate" :title="sede.direccion || ''">
              {{ sede.direccion || 'N/A' }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">{{ sede.radio_metros }} m</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                :class="sede.activo == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                {{ sede.activo == 1 ? 'Activa' : 'Inactiva' }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <button @click="openModal(sede)" class="text-indigo-600 hover:text-indigo-900 mr-3">Editar</button>
              <button @click="deleteSede(sede.id)" class="text-red-600 hover:text-red-900">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="!loading && !sedes.length" class="text-center py-10 bg-white rounded-xl border border-slate-100 text-slate-500">
      No hay sedes registradas.
    </div>

    <!-- Modal Form -->
    <div v-if="isModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-slate-800 mb-4">{{ isEditing ? 'Editar Sede' : 'Nueva Sede' }}</h3>
        <form @submit.prevent="saveSede" class="space-y-4">
          <!-- Código solo al crear -->
          <div v-if="!isEditing">
            <label class="block text-sm font-medium text-slate-700 mb-1">Código de Sede <span class="text-red-500">*</span></label>
            <input v-model="form.codigo" type="text" required class="input-field" placeholder="Ej: SEDE-004">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre <span class="text-red-500">*</span></label>
            <input v-model="form.nombre" type="text" required class="input-field" placeholder="Nombre de la sede">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Dirección Referencial</label>
            <textarea v-model="form.direccion" class="input-field min-h-[70px]" placeholder="Dirección referencial"></textarea>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Latitud <span class="text-red-500">*</span></label>
              <input v-model="form.latitud" type="number" step="any" required class="input-field" placeholder="-12.0463">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Longitud <span class="text-red-500">*</span></label>
              <input v-model="form.longitud" type="number" step="any" required class="input-field" placeholder="-77.0427">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Radio GPS (metros) <span class="text-red-500">*</span></label>
            <input v-model="form.radio" type="number" min="10" max="5000" required class="input-field" placeholder="100">
          </div>
          <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
            <button type="button" @click="closeModal" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Guardando...' : 'Guardar Sede' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Importación Excel (Sedes) -->
    <div v-if="isImportModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl flex flex-col">
        <div class="flex justify-between items-center pb-3 border-b border-slate-100">
          <h3 class="text-lg font-bold text-slate-800">Importar Sedes desde Excel (CSV)</h3>
          <button @click="isImportModalOpen = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <div class="py-4 space-y-4 flex-1">
          <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 text-xs text-slate-600 space-y-1">
            <p class="font-bold">Estructura requerida del archivo CSV:</p>
            <p class="font-mono bg-white p-1.5 rounded border border-slate-100 overflow-x-auto select-all">codigo,nombre,direccion,latitud,longitud,radio_metros</p>
            <p class="mt-1">* Nota: Guarda tu hoja de cálculo Excel en formato CSV (delimitado por comas o punto y coma) antes de subir.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Seleccionar archivo</label>
            <input type="file" ref="fileInput" accept=".csv" class="input-field py-1.5 text-sm">
          </div>

          <!-- Resultados de importación -->
          <div v-if="importResults" class="p-3 rounded-lg border text-sm max-h-[200px] overflow-y-auto" :class="importResults.errores.length ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-green-50 border-green-200 text-green-800'">
            <p class="font-bold">Resultados:</p>
            <p>Procesados: {{ importResults.total_procesados }} | Exitosos: {{ importResults.exitosos }} | Errores: {{ importResults.errores.length }}</p>
            <ul v-if="importResults.errores.length" class="list-disc list-inside mt-2 text-xs space-y-1 text-red-600">
              <li v-for="(err, idx) in importResults.errores" :key="idx">{{ err }}</li>
            </ul>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex justify-end space-x-2">
          <button @click="isImportModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium">Cancelar</button>
          <button @click="uploadFile" class="btn-primary" :disabled="importing">
            {{ importing ? 'Procesando...' : 'Importar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/api/axios'

const sedes = ref([])
const loading = ref(true)
const error = ref(null)
const isModalOpen = ref(false)
const saving = ref(false)
const isEditing = ref(false)

const isImportModalOpen = ref(false)
const fileInput = ref(null)
const importing = ref(false)
const importResults = ref(null)

// Campos reales de la BD: codigo, nombre, direccion, latitud, longitud, radio_metros, activo
const form = reactive({
  id: null,
  codigo: '',
  nombre: '',
  direccion: '',
  latitud: '',
  longitud: '',
  radio: 100
})

const fetchSedes = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.get('/v1/web/sedes')
    sedes.value = response.data.data || response.data
  } catch (err) {
    error.value = 'Error al obtener sedes'
  } finally {
    loading.value = false
  }
}

const openModal = (sede = null) => {
  if (sede) {
    isEditing.value = true
    Object.assign(form, {
      id: sede.id,
      codigo: sede.codigo,
      nombre: sede.nombre,
      direccion: sede.direccion || '',
      latitud: sede.latitud,
      longitud: sede.longitud,
      radio: sede.radio_metros
    })
  } else {
    isEditing.value = false
    Object.assign(form, { id: null, codigo: '', nombre: '', direccion: '', latitud: '', longitud: '', radio: 100 })
  }
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
}

const saveSede = async () => {
  form.nombre = form.nombre ? form.nombre.trim() : ''
  form.codigo = form.codigo ? form.codigo.trim() : ''
  const nombre = form.nombre
  const codigo_sede = form.codigo
  const latitud = parseFloat(form.latitud)
  const longitud = parseFloat(form.longitud)
  const radio = parseInt(form.radio)

  if (!isEditing.value && !codigo_sede) {
    alert('El código de la sede es requerido')
    return
  }
  if (!nombre) {
    alert('El nombre de la sede es requerido')
    return
  }
  if (isNaN(latitud)) {
    alert('Ingrese una latitud válida')
    return
  }
  if (isNaN(longitud)) {
    alert('Ingrese una longitud válida')
    return
  }
  if (isNaN(radio) || radio < 10) {
    alert('El radio GPS debe ser un número entero mayor o igual a 10 metros')
    return
  }

  saving.value = true
  try {
    if (isEditing.value) {
      // Al editar no se envía codigo_sede (no es editable)
      const payload = {
        nombre,
        direccion: form.direccion ? form.direccion.trim() : '',
        latitud,
        longitud,
        radio_metros: radio
      }
      await api.put(`/v1/web/sedes/${form.id}`, payload)
    } else {
      const payload = {
        codigo: codigo_sede,
        nombre,
        direccion: form.direccion ? form.direccion.trim() : '',
        latitud,
        longitud,
        radio_metros: radio
      }
      await api.post('/v1/web/sedes', payload)
    }
    closeModal()
    fetchSedes()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al guardar la sede')
  } finally {
    saving.value = false
  }
}

const deleteSede = async (id) => {
  if (!confirm('¿Seguro que deseas eliminar esta sede?')) return
  try {
    await api.delete(`/v1/web/sedes/${id}`)
    fetchSedes()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al eliminar')
  }
}

const openImportModal = () => {
  importResults.value = null
  if (fileInput.value) fileInput.value.value = ''
  isImportModalOpen.value = true
}

const uploadFile = async () => {
  const file = fileInput.value.files[0]
  if (!file) {
    alert('Por favor seleccione un archivo CSV.')
    return
  }

  const formData = new FormData()
  formData.append('file', file)

  importing.value = true
  importResults.value = null
  try {
    const res = await api.post('/v1/web/sedes/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    importResults.value = res.data.data
    fetchSedes()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al importar sedes')
  } finally {
    importing.value = false
  }
}

onMounted(() => {
  fetchSedes()
})
</script>
