import { createRouter, createWebHistory } from 'vue-router'
import { getToken } from '@/api/token'

const AdminLayout = () => import('@/layouts/AdminLayout.vue')

/**
 * Every screen, every tab and every entity has its own URL.
 *
 * Two consequences that are the whole point:
 *  - the browser Back button goes to the previous PAGE, not the previous tab click, because a tab
 *    switch is a `replace`, not a `push` (see UserDetailView);
 *  - a link can be shared. `/users/:id/dialogs` and `/logs?purpose=generation&status_class=5xx`
 *    open exactly what the sender was looking at.
 *
 * The layout is the parent route, so moving between children swaps only the content — the sidebar
 * never remounts and never flashes.
 */
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
        // The tab lives in the path. `/users/:id` alone redirects to the first tab, so there is
        // exactly one URL per view and every tab is linkable.
        {
          path: 'users/:id',
          redirect: (to) => ({ name: 'user', params: { id: to.params.id, tab: 'plan' } }),
        },
        {
          path: 'users/:id/:tab(plan|reviews|collections|modes|dialogs|generations|logs)',
          name: 'user',
          component: () => import('@/views/UserDetailView.vue'),
          props: true,
        },

        // The live ladder. Its whole state (learner, filters, page) lives in the query string, so a
        // view of one session is a link like any other page here.
        { path: 'ladder', name: 'ladder', component: () => import('@/views/LadderView.vue') },

        { path: 'collections', name: 'collections', component: () => import('@/views/CollectionsView.vue') },
        {
          path: 'collections/:id',
          name: 'collection',
          component: () => import('@/views/CollectionDetailView.vue'),
          props: true,
        },

        // «Здоровье контента»: три уровня на одном маршруте, уровень — в query string
        // (?collection=…&term=…), как на экране лестницы. Ссылка на паспорт термина открывает
        // ровно его, а Back поднимает на уровень выше, а не выкидывает из раздела.
        { path: 'content', name: 'content', component: () => import('@/views/ContentView.vue') },

        { path: 'terms', name: 'terms', component: () => import('@/views/TermsView.vue') },
        { path: 'terms/:id', name: 'term', component: () => import('@/views/TermDetailView.vue'), props: true },

        {
          path: 'exercise-modes',
          name: 'exercise-modes',
          component: () => import('@/views/ExerciseModesView.vue'),
        },
        // The full row per trainer (on/off, position, ladder threshold) — separate from the toggles
        // screen above, whose state (learner in the query string) it mirrors.
        {
          path: 'mode-settings',
          name: 'mode-settings',
          component: () => import('@/views/ModeSettingsMatrixView.vue'),
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
