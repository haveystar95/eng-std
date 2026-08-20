<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { Activity, LayoutDashboard, Users, Library, Type, Sparkles,
  SlidersHorizontal, Table2, ScrollText, LogOut, HeartPulse } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useMocks } from '@/api'
import CommandPalette from '@/components/CommandPalette.vue'

const auth = useAuthStore()
const router = useRouter()
const palette = ref<InstanceType<typeof CommandPalette> | null>(null)

const nav = [
  { to: { name: 'dashboard' }, label: 'Обзор', icon: LayoutDashboard, section: '/dashboard' },
  { to: { name: 'users' }, label: 'Пользователи', icon: Users, section: '/users' },
  { to: { name: 'ladder' }, label: 'Прогресс обучения', icon: Activity, section: '/ladder' },
  { to: { name: 'collections' }, label: 'Коллекции', icon: Library, section: '/collections' },
  { to: { name: 'terms' }, label: 'Термины', icon: Type, section: '/terms' },
  { to: { name: 'content' }, label: 'Контент', icon: HeartPulse, section: '/content' },
  { to: { name: 'exercise-modes' }, label: 'Тренажёры', icon: SlidersHorizontal, section: '/exercise-modes' },
  { to: { name: 'mode-settings' }, label: 'Матрица режимов', icon: Table2, section: '/mode-settings' },
  { to: { name: 'generations' }, label: 'Генерации', icon: Sparkles, section: '/generations' },
  { to: { name: 'logs' }, label: 'Логи', icon: ScrollText, section: '/logs' },
]

// Highlight by SECTION, not by exact route: a detail page (/users/:id/dialogs) is still
// "Пользователи". RouterLink's own active-class can't do this — detail routes are siblings of the
// list route, not children of it.
const route = useRoute()
function isActive(section: string): boolean {
  return route.path === section || route.path.startsWith(section + '/')
}

function logout() {
  auth.logout()
  router.replace({ name: 'login' })
}
</script>

<template>
  <div class="shell">
    <aside class="sidebar">
      <div class="brand">
        <span class="brand-mark serif">Слова</span>
        <span class="brand-sub">админка</span>
      </div>
      <button class="search-trigger" @click="palette?.show()">
        <span>Поиск</span>
        <kbd>⌘K</kbd>
      </button>
      <nav class="nav">
        <RouterLink
          v-for="item in nav"
          :key="item.label"
          :to="item.to"
          class="nav-link"
          :class="{ active: isActive(item.section) }"
        >
          <component :is="item.icon" :size="18" class="nav-icon" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>
      <div class="foot">
        <div v-if="useMocks" class="mock-badge" title="Бэкенд admin API ещё не подключён — данные из моков по контракту">
          демо-данные (моки)
        </div>
        <div v-if="auth.email" class="who faint">{{ auth.email }}</div>
        <button class="logout" @click="logout">
          <LogOut :size="16" />
          <span>Выйти</span>
        </button>
      </div>
    </aside>
    <main class="content">
      <RouterView />
    </main>
    <CommandPalette ref="palette" />
  </div>
</template>

<style scoped>
.shell {
  display: grid;
  grid-template-columns: 236px 1fr;
  min-height: 100vh;
}
.sidebar {
  position: sticky;
  top: 0;
  align-self: start;
  height: 100vh;
  display: flex;
  flex-direction: column;
  gap: var(--s22);
  padding: var(--s22) var(--s16);
  background: var(--surface-raised);
  box-shadow: var(--shadow-card);
}
.brand {
  display: flex;
  flex-direction: column;
  padding: 0 var(--s8);
}
.brand-mark {
  font-family: var(--font-serif);
  font-size: 26px;
  font-weight: 500;
  letter-spacing: -0.01em;
}
.brand-sub {
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--tertiary);
}
.search-trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  margin-bottom: var(--s12);
  padding: 8px 12px;
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  background: var(--field);
  font-size: 13px;
  color: var(--secondary);
  cursor: pointer;
}
.search-trigger:hover {
  color: var(--ink);
}
.search-trigger kbd {
  font-family: inherit;
  font-size: 11px;
  opacity: 0.7;
}
.nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.nav-link {
  display: flex;
  align-items: center;
  gap: var(--s12);
  padding: 10px var(--s12);
  border-radius: var(--r-field);
  color: var(--secondary);
  font-weight: 600;
  font-size: 14.5px;
  transition: background 0.12s ease, color 0.12s ease;
}
.nav-link:hover {
  background: var(--faint-ink);
  color: var(--ink);
}
.nav-link.active {
  background: var(--ink);
  color: var(--paper);
}
.nav-icon {
  flex-shrink: 0;
}
.foot {
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: var(--s8);
  padding: 0 var(--s8);
}
.mock-badge {
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--verdict-unsure);
  border: 1px solid color-mix(in srgb, var(--verdict-unsure) 40%, transparent);
  border-radius: var(--r-pill);
  padding: 4px 10px;
  text-align: center;
}
.who {
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.logout {
  display: inline-flex;
  align-items: center;
  gap: var(--s8);
  background: transparent;
  border: none;
  color: var(--destructive);
  font-weight: 600;
  font-size: 13.5px;
  padding: 6px 0;
}
.logout:hover {
  text-decoration: underline;
}
.content {
  padding: var(--s26) var(--s26) 64px;
  max-width: 1520px;
  width: 100%;
}
@media (max-width: 760px) {
  .shell {
    grid-template-columns: 1fr;
  }
  .sidebar {
    position: static;
    height: auto;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s12);
  }
  .nav {
    flex-direction: row;
    flex-wrap: wrap;
  }
  .foot {
    margin: 0 0 0 auto;
    flex-direction: row;
    align-items: center;
  }
}
</style>
