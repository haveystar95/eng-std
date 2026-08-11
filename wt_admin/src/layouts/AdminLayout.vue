<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { LayoutDashboard, Users, Library, Type, Sparkles, ScrollText, LogOut } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useMocks } from '@/api'

const auth = useAuthStore()
const router = useRouter()

const nav = [
  { to: { name: 'dashboard' }, label: 'Обзор', icon: LayoutDashboard },
  { to: { name: 'users' }, label: 'Пользователи', icon: Users },
  { to: { name: 'collections' }, label: 'Коллекции', icon: Library },
  { to: { name: 'terms' }, label: 'Термины', icon: Type },
  { to: { name: 'generations' }, label: 'Генерации', icon: Sparkles },
  { to: { name: 'logs' }, label: 'Логи', icon: ScrollText },
]

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
      <nav class="nav">
        <RouterLink
          v-for="item in nav"
          :key="item.label"
          :to="item.to"
          class="nav-link"
          active-class="active"
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
