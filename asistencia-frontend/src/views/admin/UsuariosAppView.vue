<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center pb-4 border-b border-slate-200">
      <h2 class="text-2xl font-bold text-slate-800">Trabajadores</h2>
      <div class="flex space-x-3">
         <button v-if="isAdmin" @click="openResetsModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 bg-white hover:bg-slate-50 transition-colors font-medium shadow-sm flex items-center space-x-2 text-sm">
          <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
          <span>Solicitudes de Contraseña</span>
         </button>
         <button @click="openImportModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 bg-white hover:bg-slate-50 transition-colors font-medium shadow-sm flex items-center space-x-2 text-sm">
          <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
          <span>Importar Excel</span>
         </button>
         <button @click="openModal()" class="btn-primary flex items-center space-x-2 text-sm">
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

    <!-- Modal (Solicitudes de Recuperación de Contraseña) -->
    <div v-if="isResetsModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-2xl shadow-xl max-h-[85vh] flex flex-col">
        <div class="flex justify-between items-center pb-3 border-b border-slate-100">
          <h3 class="text-lg font-bold text-slate-800">Solicitudes de Recuperación de Contraseña (App)</h3>
          <button @click="isResetsModalOpen = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto py-4">
          <!-- Alerta de Contraseña Temporal Generada -->
          <div v-if="tempPasswordGenerated" class="mb-4 bg-green-50 border border-green-200 rounded-xl p-4 text-green-800">
            <div class="flex items-center space-x-2">
              <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              <h4 class="font-bold">¡Clave Temporal Generada con Éxito!</h4>
            </div>
            <p class="text-sm mt-1">Trabajador: <strong>{{ tempPasswordUser }}</strong></p>
            <div class="mt-3 bg-white p-3 rounded-lg border border-green-100 flex items-center justify-between">
              <span class="font-mono text-xl font-bold tracking-wider text-green-700">{{ tempPasswordGenerated }}</span>
              <span class="text-xs text-slate-400">Entrega este código de 6 dígitos al trabajador</span>
            </div>
          </div>

          <div v-if="loadingResets" class="text-center py-8">
            <div class="animate-spin inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full mb-2"></div>
            <p class="text-sm text-slate-500">Cargando solicitudes...</p>
          </div>

          <div v-else-if="!resetsList.length" class="text-center py-8 text-slate-400">
            <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            <p class="text-sm font-medium">No hay solicitudes de recuperación pendientes</p>
          </div>

          <div v-else class="border border-slate-100 rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase">Trabajador</th>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase">DNI / Código</th>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase">Fecha</th>
                  <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-for="reset in resetsList" :key="reset.id" class="hover:bg-slate-50 transition-colors">
                  <td class="px-4 py-2.5">
                    <p class="text-sm font-medium text-slate-800">{{ reset.nombres }} {{ reset.apellidos }}</p>
                  </td>
                  <td class="px-4 py-2.5 text-xs text-slate-500">
                    <p>DNI: {{ reset.dni }}</p>
                    <p>Cod: {{ reset.codigo_empleado }}</p>
                  </td>
                  <td class="px-4 py-2.5 text-xs text-slate-500">
                    {{ new Date(reset.created_at).toLocaleDateString('es-PE', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) }}
                  </td>
                  <td class="px-4 py-2.5 text-right space-x-2">
                    <button @click="aprobarReset(reset)" class="px-2 py-1 text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                      Aprobar
                    </button>
                    <button @click="rechazarReset(reset)" class="px-2 py-1 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                      Rechazar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex justify-end">
          <button @click="isResetsModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium">
            Cerrar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Importación Excel (Trabajadores) -->
    <div v-if="isImportModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center pb-3 border-b border-slate-100">
          <h3 class="text-lg font-bold text-slate-800">Importar Trabajadores desde Excel (.xlsx)</h3>
          <button @click="isImportModalOpen = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <div class="py-4 space-y-4 flex-1 overflow-y-auto">
          <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 text-xs text-slate-600 space-y-1">
            <p class="font-bold">Estructura requerida de las columnas de Excel:</p>
            <p class="font-mono bg-white p-1.5 rounded border border-slate-100 overflow-x-auto select-all">codigo_empleado, nombres, apellidos, dni, email, telefono, password, cargo, condicion_laboral, sede_codigo, horario_nombre</p>
            <p class="mt-1">* Nota: Los campos codigo_empleado, nombres, apellidos y DNI son requeridos. El correo se autogenera si se deja vacío. Si se define un codigo de sede y nombre de horario válidos, se creará la asignación automática.</p>
            <p class="mt-1">* Nota: Se recomienda descargar la plantilla estructurada para evitar errores de importación.</p>
          </div>

          <div>
            <div class="flex justify-between items-center mb-1">
              <label class="block text-sm font-medium text-slate-700">Seleccionar archivo Excel</label>
              <button @click="downloadTemplate" type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center space-x-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                <span>Descargar Plantilla (.xlsx)</span>
              </button>
            </div>
            <input type="file" ref="fileInput" accept=".xlsx" class="input-field py-1.5 text-sm">
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
import { ref, reactive, onMounted, computed } from 'vue'
import api from '@/api/axios'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/store/auth'

const toast = useToast()
const authStore = useAuthStore()
const isAdmin = computed(() => authStore.isAdmin)
const isSupervisor = computed(() => authStore.isSupervisor)

const isResetsModalOpen = ref(false)
const resetsList = ref([])
const loadingResets = ref(false)
const tempPasswordGenerated = ref(null)
const tempPasswordUser = ref(null)

const isImportModalOpen = ref(false)
const fileInput = ref(null)
const importing = ref(false)
const importResults = ref(null)

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
  form.nombres = form.nombres ? form.nombres.trim() : ''
  form.apellido_paterno = form.apellido_paterno ? form.apellido_paterno.trim() : ''
  form.apellido_materno = form.apellido_materno ? form.apellido_materno.trim() : ''
  form.dni = form.dni ? form.dni.trim() : ''
  form.codigo_modular = form.codigo_modular ? form.codigo_modular.trim() : ''
  form.email = form.email ? form.email.trim() : ''
  form.password = form.password ? form.password.trim() : ''
  form.cargo = form.cargo ? form.cargo.trim() : ''
  form.condicion_laboral = form.condicion_laboral ? form.condicion_laboral.trim() : ''

  if (!form.nombres) {
    toast.error('El nombre es requerido')
    return
  }
  if (!form.apellido_paterno) {
    toast.error('El apellido paterno es requerido')
    return
  }
  if (!form.dni || isNaN(form.dni) || form.dni.length < 8) {
    toast.error('El DNI debe tener al menos 8 dígitos numéricos')
    return
  }
  if (!form.codigo_modular) {
    toast.error('El código modular es requerido')
    return
  }
  if (form.email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(form.email)) {
      toast.error('Ingrese un correo electrónico válido')
      return
    }
  }
  if (!form.sede_id) {
    toast.error('Debe seleccionar una sede')
    return
  }
  if (!isEditing.value) {
    if (!form.password) {
      toast.error('La contraseña es requerida')
      return
    }
    if (form.password.length < 6) {
      toast.error('La contraseña debe tener al menos 6 caracteres')
      return
    }
  } else {
    if (form.password && form.password.length < 6) {
      toast.error('La nueva contraseña debe tener al menos 6 caracteres')
      return
    }
  }

  saving.value = true
  try {
    const payload = { 
      ...form,
      nombres: form.nombres,
      apellido_paterno: form.apellido_paterno,
      apellido_materno: form.apellido_materno,
      dni: form.dni,
      codigo_modular: form.codigo_modular,
      email: form.email,
      password: form.password || undefined,
      cargo: form.cargo,
      condicion_laboral: form.condicion_laboral
    }
    if (isEditing.value && !form.password) delete payload.password

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

const openResetsModal = () => {
  tempPasswordGenerated.value = null
  tempPasswordUser.value = null
  isResetsModalOpen.value = true
  fetchPasswordResets()
}

const fetchPasswordResets = async () => {
  loadingResets.value = true
  try {
    const res = await api.get('/v1/web/password-resets-app?estado=PENDIENTE')
    resetsList.value = res.data.data ?? res.data
  } catch (err) {
    toast.error('Error al cargar solicitudes de contraseña')
  } finally {
    loadingResets.value = false
  }
}

const aprobarReset = async (reset) => {
  try {
    const res = await api.post(`/v1/web/password-resets-app/${reset.id}/aprobar`)
    tempPasswordGenerated.value = res.data.data.temp_password
    tempPasswordUser.value = `${reset.nombres} ${reset.apellidos}`
    fetchPasswordResets()
  } catch (err) {
    toast.error(err.response?.data?.error || 'Error al aprobar la solicitud')
  }
}

const rechazarReset = async (reset) => {
  if (!confirm(`¿Rechazar solicitud de recuperación de contraseña de ${reset.nombres}?`)) return
  try {
    await api.post(`/v1/web/password-resets-app/${reset.id}/rechazar`)
    toast.success('Solicitud rechazada')
    fetchPasswordResets()
  } catch (err) {
    toast.error(err.response?.data?.error || 'Error al rechazar la solicitud')
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
    alert('Por favor seleccione un archivo Excel (.xlsx).')
    return
  }

  const formData = new FormData()
  formData.append('file', file)

  importing.value = true
  importResults.value = null
  try {
    const res = await api.post('/v1/web/usuarios-app/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    importResults.value = res.data.data
    fetchInicial()
  } catch (err) {
    alert(err.response?.data?.error || 'Error al importar trabajadores')
  } finally {
    importing.value = false
  }
}

const downloadTemplate = async () => {
  try {
    const response = await api.get('/v1/web/usuarios-app/import/template', {
      responseType: 'blob'
    })
    const blob = new Blob([response.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'plantilla_trabajadores.xlsx')
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch (err) {
    toast.error('Error al descargar la plantilla')
  }
}

onMounted(() => {
  fetchInicial()
})
</script>
