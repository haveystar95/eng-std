<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useMocks } from '@/api'
import PaperCard from '@/components/PaperCard.vue'
import PaperButton from '@/components/PaperButton.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')

async function submit() {
  const ok = await auth.login(email.value, password.value)
  if (ok) {
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    router.replace(redirect)
  }
}
</script>

<template>
  <div class="login">
    <PaperCard class="card">
      <div class="head">
        <div class="brand serif">Слова</div>
        <div class="sub">панель администратора</div>
      </div>
      <form @submit.prevent="submit">
        <label class="fld">
          <span class="section-label">Email</span>
          <input v-model="email" type="email" autocomplete="username" required />
        </label>
        <label class="fld">
          <span class="section-label">Пароль</span>
          <input v-model="password" type="password" autocomplete="current-password" required />
        </label>
        <p v-if="auth.error" class="err">{{ auth.error }}</p>
        <PaperButton type="submit" block :disabled="auth.loading">
          {{ auth.loading ? 'Вход…' : 'Войти' }}
        </PaperButton>
      </form>
      <p v-if="useMocks" class="hint faint">
        Демо-режим: бэкенд не подключён, войти можно с любыми данными.
      </p>
    </PaperCard>
  </div>
</template>

<style scoped>
.login {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--s22);
}
.card {
  width: 360px;
  max-width: 100%;
  padding: var(--s26);
}
.head {
  text-align: center;
  margin-bottom: var(--s22);
}
.brand {
  font-family: var(--font-serif);
  font-size: 34px;
  font-weight: 500;
}
.sub {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--tertiary);
}
form {
  display: flex;
  flex-direction: column;
  gap: var(--s16);
}
.fld {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.fld input {
  background: var(--field);
  border: 1px solid var(--hairline);
  border-radius: var(--r-field);
  padding: 11px 14px;
  font-size: 15px;
}
.fld input:focus {
  outline: none;
  border-color: var(--tertiary);
}
.err {
  margin: 0;
  color: var(--destructive);
  font-size: 13px;
}
.hint {
  margin: var(--s16) 0 0;
  font-size: 12px;
  text-align: center;
}
</style>
