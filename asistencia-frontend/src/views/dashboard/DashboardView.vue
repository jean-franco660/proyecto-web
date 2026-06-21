<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Dashboard General</h2>
      <div class="flex items-center space-x-3">
        <!-- Selector de Sedes -->
        <div class="flex items-center space-x-2">
          <label for="sede-selector" class="text-sm font-medium text-slate-500 hidden sm:inline">Sede:</label>
          <select 
            id="sede-selector"
            v-model="selectedSede" 
            @change="loadStats" 
            class="px-3 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg shadow-sm hover:bg-slate-50 transition-colors focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-medium min-w-[180px] outline-none"
            :disabled="loading"
          >
            <option value="">
              {{ authStore.user?.rol === 'administrador' ? 'Todas las sedes' : 'Todas mis sedes' }}
            </option>
            <option v-for="sede in sedes" :key="sede.id" :value="sede.id">
              {{ sede.nombre }}
            </option>
          </select>
        </div>

        <button @click="loadStats" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg shadow-sm hover:bg-slate-50 transition-colors flex items-center space-x-2 text-sm font-medium" :disabled="loading">
          <svg :class="{ 'animate-spin': loading }" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
          <span>Actualizar</span>
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-md">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-red-700">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Skeleton Loader -->
    <div v-if="loading && !stats" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div v-for="i in 4" :key="i" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm animate-pulse">
        <div class="h-4 bg-slate-200 rounded w-1/2 mb-4"></div>
        <div class="h-8 bg-slate-200 rounded w-3/4"></div>
      </div>
    </div>

    <!-- Stats Grid -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <!-- Total Trabajadores -->
      <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm relative overflow-hidden group hover:border-blue-100 transition-colors">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-50 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
        <div class="relative z-10">
          <p class="text-sm font-medium text-slate-500 mb-1">Trabajadores Activos</p>
          <div class="flex items-end justify-between">
            <h3 class="text-3xl font-bold text-slate-800">{{ stats.usuariosApp ?? 0 }}</h3>
            <div class="p-2 bg-blue-100 text-blue-600 rounded-lg">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Asistencias Hoy -->
      <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm relative overflow-hidden group hover:border-green-100 transition-colors">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-green-50 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
        <div class="relative z-10">
          <p class="text-sm font-medium text-slate-500 mb-1">Asistencias Hoy</p>
          <div class="flex items-end justify-between">
            <h3 class="text-3xl font-bold text-slate-800">{{ stats.asistenciasHoy ?? 0 }}</h3>
            <div class="p-2 bg-green-100 text-green-600 rounded-lg">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
          </div>
          <p class="text-xs text-slate-400 mt-1">{{ stats.presentes ?? 0 }} puntual · {{ stats.tardanzas ?? 0 }} tardanza</p>
        </div>
      </div>

      <!-- Sedes Activas -->
      <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm relative overflow-hidden group hover:border-purple-100 transition-colors">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-50 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
        <div class="relative z-10">
          <p class="text-sm font-medium text-slate-500 mb-1">Sedes Activas</p>
          <div class="flex items-end justify-between">
            <h3 class="text-3xl font-bold text-slate-800">{{ stats.sedes ?? 0 }}</h3>
            <div class="p-2 bg-purple-100 text-purple-600 rounded-lg">
               <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Justificaciones Pendientes -->
      <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm relative overflow-hidden group hover:border-amber-100 transition-colors">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-amber-50 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
        <div class="relative z-10">
          <p class="text-sm font-medium text-slate-500 mb-1">Justificaciones Pendientes</p>
          <div class="flex items-end justify-between">
            <h3 class="text-3xl font-bold text-slate-800">{{ stats.justificacionesPendientes ?? 0 }}</h3>
             <div class="p-2 bg-amber-100 text-amber-600 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Resumen de hoy -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
        <h3 class="text-base font-semibold text-slate-700 mb-4">Resumen del Día — {{ stats.fecha }}</h3>
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
              <span class="text-sm text-slate-600">Presentes</span>
            </div>
            <span class="text-sm font-semibold text-slate-800">{{ stats.presentes ?? 0 }}</span>
          </div>
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <span class="w-2.5 h-2.5 rounded-full bg-orange-400"></span>
              <span class="text-sm text-slate-600">Tardanzas</span>
            </div>
            <span class="text-sm font-semibold text-slate-800">{{ stats.tardanzas ?? 0 }}</span>
          </div>
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
              <span class="text-sm text-slate-600">Faltas</span>
            </div>
            <span class="text-sm font-semibold text-slate-800">{{ stats.faltas ?? 0 }}</span>
          </div>
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>
              <span class="text-sm text-slate-600">Justificados</span>
            </div>
            <span class="text-sm font-semibold text-slate-800">{{ stats.justificados ?? 0 }}</span>
          </div>
          <div class="flex items-center justify-between border-t border-slate-100 pt-2">
            <div class="flex items-center space-x-2">
              <span class="w-2.5 h-2.5 rounded-full bg-slate-300"></span>
              <span class="text-sm text-slate-500">Marcaciones observadas pendientes</span>
            </div>
            <span class="text-sm font-semibold text-amber-600">{{ stats.observadas_pendientes ?? 0 }}</span>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col justify-between">
        <h3 class="text-base font-semibold text-slate-700 mb-4">Accesos Rápidos</h3>
        <div class="grid grid-cols-2 gap-3">
          <router-link to="/asistencias" class="flex items-center space-x-2 p-3 bg-slate-50 hover:bg-indigo-50 rounded-xl transition-colors group">
            <svg class="w-5 h-5 text-slate-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            <span class="text-sm text-slate-600 group-hover:text-indigo-700 font-medium">Asistencias</span>
          </router-link>
          <router-link to="/justificaciones" class="flex items-center space-x-2 p-3 bg-slate-50 hover:bg-amber-50 rounded-xl transition-colors group">
            <svg class="w-5 h-5 text-slate-400 group-hover:text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span class="text-sm text-slate-600 group-hover:text-amber-700 font-medium">Justificaciones</span>
          </router-link>
          <router-link to="/usuarios-app" class="flex items-center space-x-2 p-3 bg-slate-50 hover:bg-blue-50 rounded-xl transition-colors group">
            <svg class="w-5 h-5 text-slate-400 group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            <span class="text-sm text-slate-600 group-hover:text-blue-700 font-medium">Trabajadores</span>
          </router-link>
          <router-link to="/feriados" class="flex items-center space-x-2 p-3 bg-slate-50 hover:bg-green-50 rounded-xl transition-colors group">
            <svg class="w-5 h-5 text-slate-400 group-hover:text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <span class="text-sm text-slate-600 group-hover:text-green-700 font-medium">Feriados</span>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/axios'
import { useAuthStore } from '@/store/auth'

const authStore = useAuthStore()

const stats = ref(null)
const loading = ref(true)
const error = ref(null)

const sedes = ref([])
const selectedSede = ref('')

const fetchSedes = async () => {
  try {
    const response = await api.get('/v1/web/sedes/mis-sedes')
    sedes.value = response.data.data || response.data
  } catch (err) {
    console.error('Error al obtener sedes para el selector', err)
  }
}

const loadStats = async () => {
  loading.value = true
  error.value = null
  try {
    const params = {}
    if (selectedSede.value) {
      params.sede_id = selectedSede.value
    }
    const response = await api.get('/v1/web/stats', { params })
    // El backend devuelve: usuariosApp, sedes, asistenciasHoy, presentes, tardanzas,
    // faltas, justificados, justificacionesPendientes, observadas_pendientes, fecha
    stats.value = response.data.data || response.data
  } catch (err) {
    console.error('Error cargando stats', err)
    if (err.response?.status === 403) {
      error.value = err.response.data?.error || 'Sin acceso a esta sede.'
    } else {
      error.value = 'No se pudieron cargar las estadísticas. Verifica la conexión con el servidor.'
    }
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await fetchSedes()
  await loadStats()
})
</script>
