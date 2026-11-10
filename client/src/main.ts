import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import PermissionGate from './components/auth/PermissionGate.vue'
import { vPermission } from './directives/permission'
import { useInstitution } from './composables/useInstitution'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

app.component('PermissionGate', PermissionGate)
app.directive('permission', vPermission)

// The institutional identity is part of the runtime configuration and is
// resolved once, before the first navigation renders the shell.
useInstitution().load()

router.isReady().then(() => {
  app.mount('#app')
})
