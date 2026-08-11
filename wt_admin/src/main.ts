import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { router } from './router'
import { setUnauthorizedHandler } from './api/token'
import { useAuthStore } from './stores/auth'
import './styles/base.css'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(router)

// On a 401 the http interceptor clears the token; here we sync the store and route
// to the login screen, preserving where the user was headed.
setUnauthorizedHandler(() => {
  useAuthStore(pinia).onUnauthorized()
  const current = router.currentRoute.value
  if (!current.meta.public) {
    router.replace({ name: 'login', query: { redirect: current.fullPath } })
  }
})

app.mount('#app')
