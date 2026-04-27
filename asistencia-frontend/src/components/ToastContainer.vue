<template>
  <Teleport to="body">
    <div
      aria-live="polite"
      aria-atomic="false"
      class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-3 pointer-events-none"
    >
      <TransitionGroup name="toast" tag="div" class="flex flex-col gap-3">
        <div
          v-for="toast in toastStore.toasts"
          :key="toast.id"
          class="flex items-start gap-3 min-w-[300px] max-w-[420px] px-4 py-3 rounded-xl shadow-xl pointer-events-auto"
          :class="toastClass(toast.type)"
          role="alert"
        >
          <!-- Icono -->
          <div class="flex-shrink-0 mt-0.5">
            <!-- Success -->
            <svg v-if="toast.type === 'success'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <!-- Error -->
            <svg v-else-if="toast.type === 'error'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <!-- Warning -->
            <svg v-else-if="toast.type === 'warning'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <!-- Info -->
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>

          <!-- Mensaje -->
          <p class="flex-1 text-sm font-medium leading-snug">{{ toast.message }}</p>

          <!-- Botón de cierre -->
          <button
            @click="toastStore.removeToast(toast.id)"
            class="flex-shrink-0 opacity-70 hover:opacity-100 transition-opacity ml-1 mt-0.5"
            aria-label="Cerrar notificación"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { useToastStore } from '@/store/toast'

const toastStore = useToastStore()

const toastClass = (type) => ({
  'bg-emerald-600 text-white':  type === 'success',
  'bg-red-600 text-white':      type === 'error',
  'bg-amber-500 text-white':    type === 'warning',
  'bg-slate-700 text-white':    type === 'info',
})
</script>

<style scoped>
/* Animaciones de entrada y salida */
.toast-enter-active {
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toast-leave-active {
  transition: all 0.25s ease-in;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(80px) scale(0.9);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(80px) scale(0.9);
}
.toast-move {
  transition: transform 0.3s ease;
}
</style>
