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
          component: () => import('@/views/admin/SedesView.vue')
        },
        {
          path: 'horarios',
          name: 'horarios',
          component: () => import('@/views/admin/HorariosView.vue')
        },
        {
          path: 'feriados',
          name: 'feriados',
          component: () => import('@/views/admin/FeriadosView.vue')
        },
        {
          path: 'usuarios-web',
          name: 'usuarios-web',
          component: () => import('@/views/admin/UsuariosWebView.vue')
        },
        {
          path: 'supervisores',
          name: 'supervisores',
          component: () => import('@/views/admin/SupervisoresView.vue')
        },
        {
          path: 'usuarios-app',
          name: 'usuarios-app',
          component: () => import('@/views/admin/UsuariosAppView.vue')
        },
        {
          path: 'asistencias',
          name: 'asistencias',
          component: () => import('@/views/operaciones/AsistenciasView.vue')
        },
        {
          path: 'justificaciones',
          name: 'justificaciones',
          component: () => import('@/views/operaciones/JustificacionesView.vue')
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
  } else if (to.meta.requiresGuest && isAuthenticated) {
    next({ name: 'dashboard' })
  } else {
    next()
  }
})

export default router
