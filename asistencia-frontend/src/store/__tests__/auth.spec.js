import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '../auth'
import api from '@/api/axios'

vi.mock('@/api/axios', () => {
  return {
    default: {
      post: vi.fn()
    }
  }
})

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
  })

  it('should initialize with default state', () => {
    const store = useAuthStore()
    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
    expect(store.isAuthenticated).toBe(false)
    expect(store.isAdmin).toBe(false)
    expect(store.isSupervisor).toBe(false)
  })

  it('should return correct role getters', () => {
    const store = useAuthStore()
    
    store.user = { rol: 'administrador' }
    store.token = 'some-token'
    expect(store.isAuthenticated).toBe(true)
    expect(store.isAdmin).toBe(true)
    expect(store.isSupervisor).toBe(false)

    store.user = { rol: 'supervisor' }
    expect(store.isAdmin).toBe(false)
    expect(store.isSupervisor).toBe(true)
  })

  it('should handle successful login without 2FA', async () => {
    const store = useAuthStore()
    const mockUserData = {
      token: 'jwt-token-xyz',
      usuario: { id: 1, email: 'admin@test.com', rol: 'administrador' }
    }

    api.post.mockResolvedValueOnce({
      data: { data: mockUserData }
    })

    const result = await store.login({ email: 'admin@test.com', password: 'password123' })

    expect(result.success).toBe(true)
    expect(store.token).toBe('jwt-token-xyz')
    expect(store.user).toEqual(mockUserData.usuario)
    expect(localStorage.getItem('token')).toBe('jwt-token-xyz')
    expect(JSON.parse(localStorage.getItem('user'))).toEqual(mockUserData.usuario)
  })

  it('should handle login requiring 2FA', async () => {
    const store = useAuthStore()
    api.post.mockResolvedValueOnce({
      data: {
        data: {
          requires_2fa: true,
          temp_token: 'temp-token-123',
          message: 'Código 2FA enviado'
        }
      }
    })

    const result = await store.login({ email: 'admin@test.com', password: 'password123' })

    expect(result.requires2FA).toBe(true)
    expect(store.tempToken).toBe('temp-token-123')
    expect(store.token).toBeNull()
  })

  it('should handle login failure', async () => {
    const store = useAuthStore()
    api.post.mockRejectedValueOnce({
      response: {
        data: { error: 'Credenciales inválidas' }
      }
    })

    const result = await store.login({ email: 'admin@test.com', password: 'wrong' })

    expect(result.success).toBe(false)
    expect(store.error).toBe('Credenciales inválidas')
    expect(store.token).toBeNull()
  })

  it('should verify 2FA successfully', async () => {
    const store = useAuthStore()
    store.tempToken = 'temp-token-123'

    const mockUserData = {
      token: 'jwt-token-xyz',
      usuario: { id: 1, email: 'admin@test.com', rol: 'administrador' }
    }

    api.post.mockResolvedValueOnce({
      data: { data: mockUserData }
    })

    const result = await store.verify2FA('123456')

    expect(result).toBe(true)
    expect(store.token).toBe('jwt-token-xyz')
    expect(store.tempToken).toBeNull()
  })

  it('should handle logout', async () => {
    const store = useAuthStore()
    store.token = 'some-token'
    store.user = { id: 1, email: 'admin@test.com' }
    localStorage.setItem('token', 'some-token')
    localStorage.setItem('user', JSON.stringify({ id: 1 }))

    api.post.mockResolvedValueOnce({})

    await store.logout()

    expect(store.token).toBeNull()
    expect(store.user).toBeNull()
    expect(localStorage.getItem('token')).toBeNull()
    expect(localStorage.getItem('user')).toBeNull()
  })
})
