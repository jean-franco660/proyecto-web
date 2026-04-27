import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/store/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { requiresGuest: true }
    },
    {
      path: '/',
      component: () => import('@/layouts/DashboardLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/dashboard/DashboardView.vue')
        },
        {
          path: 'sedes',
          name: 'sedes',
          component: () => import('@/views/admin/SedesView.vue'),
          meta: { roles: ['administrador', 'supervisor'] }
        },
        {
          path: 'horarios',
          name: 'horarios',
          component: () => import('@/views/admin/HorariosView.vue'),
          meta: { roles: ['administrador', 'supervisor'] }
        },
        {
          path: 'feriados',
          name: 'feriados',
          component: () => import('@/views/admin/FeriadosView.vue'),
          meta: { roles: ['administrador', 'supervisor'] }
        },
        {
          path: 'usuarios-web',
          name: 'usuarios-web',
          component: () => import('@/views/admin/UsuariosWebView.vue'),
          meta: { roles: ['administrador'] }
        },
        {
          path: 'supervisores',
          name: 'supervisores',
          component: () => import('@/views/admin/SupervisoresView.vue'),
          meta: { roles: ['administrador'] }
        },
        {
          path: 'usuarios-app',
          name: 'usuarios-app',
          component: () => import('@/views/admin/UsuariosAppView.vue'),
          meta: { roles: ['administrador', 'supervisor'] }
        },
        {
          path: 'asistencias',
          name: 'asistencias',
          component: () => import('@/views/operaciones/AsistenciasView.vue'),
          meta: { roles: ['administrador', 'supervisor'] }
        },
        {
          path: 'justificaciones',
          name: 'justificaciones',
          component: () => import('@/views/operaciones/JustificacionesView.vue'),
          meta: { roles: ['administrador', 'supervisor'] }
        },
        {
          path: 'solicitudes-ausencia',
          name: 'solicitudes-ausencia',
          component: () => import('@/views/operaciones/SolicitudesAusenciaView.vue'),
          meta: { roles: ['administrador', 'supervisor'] }
        },
        {
          path: 'departamentos',
          name: 'departamentos',
          component: () => import('@/views/admin/DepartamentosView.vue'),
          meta: { roles: ['administrador', 'supervisor'] }
        }
      ]
    }
  ]
})

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  const isAuthenticated = authStore.isAuthenticated

  if (to.meta.requiresAuth && !isAuthenticated) {
    next({ name: 'login' })
    return
  } else if (to.meta.requiresGuest && isAuthenticated) {
    next({ name: 'dashboard' })
    return
  }

  const role = authStore.user?.rol
  const allowedRoles = to.meta.roles
  if (allowedRoles && role && !allowedRoles.includes(role)) {
    next({ name: 'dashboard' })
    return
  }

  next()
})

export default router
