import { defineStore } from 'pinia'
import { ref } from 'vue'

let nextId = 0

/**
 * Store global de notificaciones toast.
 * Uso: const toast = useToast() → toast.success('Guardado correctamente')
 */
export const useToastStore = defineStore('toast', () => {
  const toasts = ref([])

  /**
   * Agregar un toast. Se elimina automáticamente después de `duration` ms.
   * @param {string} message - Texto del toast
   * @param {'success'|'error'|'warning'|'info'} type - Tipo visual
   * @param {number} duration - Duración en ms (default: 4000)
   */
  function addToast(message, type = 'info', duration = 4000) {
    const id = ++nextId
    toasts.value.push({ id, message, type, visible: true })

    if (duration > 0) {
      setTimeout(() => removeToast(id), duration)
    }
    return id
  }

  function removeToast(id) {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  return { toasts, addToast, removeToast }
})
