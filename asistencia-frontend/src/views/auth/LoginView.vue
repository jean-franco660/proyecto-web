<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-900 relative overflow-hidden">
    <!-- Background Decorators -->
    <div class="absolute w-[500px] h-[500px] bg-primary-600/30 rounded-full blur-3xl -top-20 -left-20 pointer-events-none"></div>
    <div class="absolute w-[400px] h-[400px] bg-indigo-600/20 rounded-full blur-3xl bottom-0 right-0 pointer-events-none"></div>
    
    <div class="w-full max-w-md p-8 relative z-10">
      <div class="bg-white/10 backdrop-blur-xl border border-white/20 p-8 rounded-2xl shadow-2xl">
        <div class="text-center mb-8">
          <div class="w-16 h-16 bg-primary-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-primary-500/50">
             <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"></path></svg>
          </div>
          <h2 class="text-3xl font-bold text-white tracking-tight">Bienvenido</h2>
          <p class="text-slate-300 mt-2 text-sm">Ingresa a tu cuenta de administración</p>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-1">Correo Electrónico</label>
            <input 
              v-model="form.email"
              type="email" 
              required
              class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
              placeholder="admin@ejemplo.com"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-1">Contraseña</label>
            <input 
              v-model="form.password"
              type="password" 
              required
              class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
              placeholder="••••••••"
            />
          </div>

          <!-- Error Alert -->
          <div v-if="authStore.error" class="bg-red-500/10 border border-red-500/20 rounded-xl p-3 flex items-start space-x-3 text-red-200 text-sm">
             <svg class="w-5 h-5 flex-shrink-0 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
             <span>{{ authStore.error }}</span>
          </div>
          
          <button 
            type="submit" 
            :disabled="authStore.loading"
            class="w-full py-3 px-4 bg-primary-600 hover:bg-primary-500 text-white font-semibold rounded-xl shadow-lg shadow-primary-600/30 transition-all focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
          >
            <svg v-if="authStore.loading" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ authStore.loading ? 'Ingresando...' : 'Iniciar Sesión' }}</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
  email: '',
  password: ''
})

const handleLogin = async () => {
  const success = await authStore.login(form)
  if (success) {
    router.push('/')
  }
}
</script>
