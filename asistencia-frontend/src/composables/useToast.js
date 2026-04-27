import { useToastStore } from '@/store/toast'

/**
 * Composable helper para usar el sistema de toasts desde cualquier View.
 *
 * @example
 * import { useToast } from '@/composables/useToast'
 * const toast = useToast()
 * toast.success('Trabajador guardado correctamente')
 * toast.error('Error al conectar con el servidor')
 */
export function useToast() {
  const toastStore = useToastStore()

  return {
    success: (message, duration) => toastStore.addToast(message, 'success', duration),
    error:   (message, duration) => toastStore.addToast(message, 'error', duration),
    warning: (message, duration) => toastStore.addToast(message, 'warning', duration),
    info:    (message, duration) => toastStore.addToast(message, 'info', duration),
  }
}
