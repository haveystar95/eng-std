import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api'
import { clearToken, getToken, setToken } from '@/api/token'

// Admin session. Token lives in src/api/token (memory + localStorage); this store
// owns the identity and the login/logout flow. A valid token on boot counts as
// authenticated (the 401 interceptor demotes it if the server disagrees).
export const useAuthStore = defineStore('auth', () => {
  const email = ref<string | null>(null)
  const authenticated = ref<boolean>(!!getToken())
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function login(inputEmail: string, password: string): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      const res = await api.login(inputEmail, password)
      setToken(res.token)
      email.value = res.admin.email
      authenticated.value = true
      return true
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Не удалось войти'
      authenticated.value = false
      return false
    } finally {
      loading.value = false
    }
  }

  function logout() {
    void api.logout().catch(() => undefined)
    clearToken()
    authenticated.value = false
    email.value = null
  }

  // Called by the 401 interceptor.
  function onUnauthorized() {
    authenticated.value = false
    email.value = null
  }

  return { email, authenticated, loading, error, login, logout, onUnauthorized }
})
