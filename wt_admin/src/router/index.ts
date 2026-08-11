import { createRouter, createWebHistory } from 'vue-router'
import { getToken } from '@/api/token'

const AdminLayout = () => import('@/layouts/AdminLayout.vue')

export const router = createRouter({
  history: createWebHistory('/'),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { public: true },
    },
    {
      path: '/',
      component: AdminLayout,
      children: [
        { path: '', redirect: { name: 'dashboard' } },
        { path: 'dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },
        { path: 'users', name: 'users', component: () => import('@/views/UsersView.vue') },
        { path: 'users/:id', name: 'user', component: () => import('@/views/UserDetailView.vue'), props: true },
        { path: 'collections', name: 'collections', component: () => import('@/views/CollectionsView.vue') },
        {
          path: 'collections/:id',
          name: 'collection',
          component: () => import('@/views/CollectionDetailView.vue'),
          props: true,
        },
        { path: 'terms', name: 'terms', component: () => import('@/views/TermsView.vue') },
        { path: 'terms/:id', name: 'term', component: () => import('@/views/TermDetailView.vue'), props: true },
        {
          path: 'exercise-modes',
          name: 'exercise-modes',
          component: () => import('@/views/ExerciseModesView.vue'),
        },
        { path: 'generations', name: 'generations', component: () => import('@/views/GenerationsView.vue') },
        { path: 'logs', name: 'logs', component: () => import('@/views/LogsView.vue') },
      ],
    },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue') },
  ],
})

// Auth guard: everything except `public` routes needs a token.
router.beforeEach((to) => {
  if (to.meta.public) return true
  if (!getToken()) return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : undefined }
  return true
})
