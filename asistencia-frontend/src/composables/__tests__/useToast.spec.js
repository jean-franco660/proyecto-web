import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useToast } from '../useToast'
import { useToastStore } from '@/store/toast'

describe('useToast Composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('should call toastStore addToast with correct parameters', () => {
    const store = useToastStore()
    const spy = vi.spyOn(store, 'addToast')

    const toast = useToast()

    toast.success('Success message', 1000)
    expect(spy).toHaveBeenLastCalledWith('Success message', 'success', 1000)

    toast.error('Error message', 2000)
    expect(spy).toHaveBeenLastCalledWith('Error message', 'error', 2000)

    toast.warning('Warning message')
    expect(spy).toHaveBeenLastCalledWith('Warning message', 'warning', undefined)

    toast.info('Info message')
    expect(spy).toHaveBeenLastCalledWith('Info message', 'info', undefined)
  })
})
