<template>
  <div class="min-h-screen bg-slate-50 flex">
    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col hidden md:flex">
      <div class="h-16 flex items-center justify-center border-b border-slate-800">
        <h1 class="text-xl font-bold tracking-wider text-primary-400">PANEL WEB</h1>
      </div>
      
      <nav class="flex-1 px-4 py-6 space-y-2">
        <router-link to="/" class="flex items-center space-x-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors" active-class="bg-primary-600 hover:bg-primary-600 text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
          <span>Dashboard</span>
        </router-link>
        
        <div class="pt-4 pb-2">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Administración</p>
        </div>
        
        <router-link to="/usuarios-web" class="flex items-center space-x-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors text-slate-300" active-class="bg-primary-600 text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
          <span>Admin Sistema</span>
        </router-link>
        <router-link to="/supervisores" class="flex items-center space-x-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors text-slate-300" active-class="bg-primary-600 text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
          <span>Supervisores</span>
        </router-link>
        <router-link to="/usuarios-app" class="flex items-center space-x-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors text-slate-300" active-class="bg-primary-600 text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
          <span>Trabajadores (App)</span>
        </router-link>
        <router-link to="/sedes" class="flex items-center space-x-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors text-slate-300" active-class="bg-primary-600 text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
          <span>Sedes</span>
        </router-link>
        <router-link to="/horarios" class="flex items-center space-x-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors text-slate-300" active-class="bg-primary-600 text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span>Horarios</span>
        </router-link>
         <router-link to="/feriados" class="flex items-center space-x-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors text-slate-300" active-class="bg-primary-600 text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
          <span>Feriados</span>
        </router-link>
        
        <div class="pt-4 pb-2">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Operaciones</p>
        </div>
        
        <router-link to="/asistencias" class="flex items-center space-x-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors text-slate-300" active-class="bg-primary-600 text-white">
           <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
          <span>Asistencias</span>
        </router-link>
        <router-link to="/justificaciones" class="flex items-center space-x-3 px-3 py-2 rounded-lg hover:bg-slate-800 transition-colors text-slate-300" active-class="bg-primary-600 text-white">
           <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span>Justificaciones</span>
        </router-link>
      </nav>
      
      <div class="p-4 border-t border-slate-800">
        <button @click="handleLogout" class="flex items-center space-x-3 px-3 py-2 w-full rounded-lg hover:bg-red-500/10 text-slate-300 hover:text-red-500 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
          <span>Cerrar Sesión</span>
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0">
      <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10">
        <button class="md:hidden text-slate-500 hover:text-slate-700">
           <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>
        <div class="flex-1"></div>
        <div class="flex items-center space-x-4">
           <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-bold">
             {{ userInitial }}
           </div>
           <span class="text-sm font-medium text-slate-700 hidden sm:block">{{ userName }}</span>
        </div>
      </header>
      
      <main class="flex-1 overflow-auto p-6">
        <router-view></router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth'

const router = useRouter()
const authStore = useAuthStore()

const userName = computed(() => authStore.user?.nombres || 'Usuario')
const userInitial = computed(() => userName.value.charAt(0).toUpperCase())

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}
</script>
