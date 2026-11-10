import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import PermissionGate from './components/auth/PermissionGate.vue'
import { vPermission } from './directives/permission'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

app.component('PermissionGate', PermissionGate)
app.directive('permission', vPermission)

router.isReady().then(() => {
  app.mount('#app')
})
