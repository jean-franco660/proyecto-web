import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useToastStore } from '../toast'

describe('Toast Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.useFakeTimers()
  })

  it('should initialize with empty toasts', () => {
    const store = useToastStore()
    expect(store.toasts).toEqual([])
  })

  it('should add toast and remove it after duration', () => {
    const store = useToastStore()
    
    const id = store.addToast('Test message', 'success', 2000)
    
    expect(store.toasts).toHaveLength(1)
    expect(store.toasts[0]).toEqual({
      id,
      message: 'Test message',
      type: 'success',
      visible: true
    })

    // Fast-forward time
    vi.advanceTimersByTime(2000)
    expect(store.toasts).toHaveLength(0)
  })

  it('should remove toast manually', () => {
    const store = useToastStore()
    
    const id = store.addToast('Test message', 'info', 0) // duration 0 means no auto-remove
    expect(store.toasts).toHaveLength(1)

    store.removeToast(id)
    expect(store.toasts).toHaveLength(0)
  })
})
