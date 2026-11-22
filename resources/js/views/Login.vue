<template>
  <div class="login-screen">
    <div class="login-card">
      <div class="login-logo">
        <span class="logo-icon">🔧</span>
        <div>
          <h1 class="login-title">FARIO</h1>
          <p class="login-sub">Sistema de Gestión Ferretería</p>
        </div>
      </div>

      <h2>Iniciar sesión</h2>
      <p class="login-hint">Ingresa con tu correo o nombre de usuario</p>

      <div v-if="auth.error" class="alert-error">{{ auth.error }}</div>

      <div class="form-group">
        <label>Usuario</label>
        <input
          v-model="usuario"
          type="text"
          placeholder="admin@fario.co"
          @keyup.enter="ingresar"
        />
      </div>

      <div class="form-group">
        <label>Contraseña</label>
        <input
          v-model="password"
          type="password"
          placeholder="••••••••"
          @keyup.enter="ingresar"
        />
      </div>

      <button class="btn-login" @click="ingresar" :disabled="cargando">
        {{ cargando ? 'Ingresando...' : 'Entrar' }}
      </button>

      
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth     = useAuthStore()
const router   = useRouter()
const usuario  = ref('')
const password = ref('')
const cargando = ref(false)

async function ingresar() {
  if (!usuario.value || !password.value) return
  cargando.value = true
  const ok = await auth.login(usuario.value, password.value)
  cargando.value = false
  if (ok) router.push('/')
}
</script>