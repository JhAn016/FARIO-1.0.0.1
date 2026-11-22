<template>
  <div v-if="auth.user" class="app-shell">

    <!-- Sidebar -->
    <aside class="sidebar" :class="{ collapsed: sidebarCollapsed }">
      <div class="sidebar-header">
        <div class="sidebar-logo">
          <span class="logo-icon">🔧</span>
          <span class="sidebar-brand">FARIO</span>
        </div>
        <button class="btn-toggle" @click="sidebarCollapsed = !sidebarCollapsed">☰</button>
      </div>

      <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <router-link to="/"              class="nav-item">🏠 <span>Dashboard</span></router-link>
        <router-link to="/ventas"        class="nav-item" v-if="auth.tienePermiso('ventas')">💰 <span>Ventas</span></router-link>
        <router-link to="/cotizaciones"  class="nav-item" v-if="auth.tienePermiso('cotizaciones')">📋 <span>Cotizaciones</span></router-link>
        <router-link to="/alquileres"    class="nav-item" v-if="auth.tienePermiso('alquileres')">🔧 <span>Alquileres</span></router-link>

        <div class="nav-section">Inventario</div>
        <router-link to="/inventario"    class="nav-item" v-if="auth.tienePermiso('inventario')">📦 <span>Inventario</span></router-link>
        <router-link to="/compras"       class="nav-item" v-if="auth.tienePermiso('compras')">🛒 <span>Compras</span></router-link>
        <router-link to="/devoluciones"  class="nav-item" v-if="auth.tienePermiso('devoluciones')">↩️ <span>Devoluciones</span></router-link>

        <div class="nav-section">Administración</div>
        <router-link to="/clientes"      class="nav-item" v-if="auth.tienePermiso('clientes')">👥 <span>Clientes</span></router-link>
        <router-link to="/proveedores"   class="nav-item" v-if="auth.tienePermiso('proveedores')">🏭 <span>Proveedores</span></router-link>
        <router-link to="/caja"          class="nav-item" v-if="auth.tienePermiso('caja')">🏦 <span>Caja</span></router-link>

        <div class="nav-section">Reportes y análisis</div>
        <router-link to="/reportes"      class="nav-item" v-if="auth.tienePermiso('reportes')">📊 <span>Reportes</span></router-link>
        <router-link to="/analisis"      class="nav-item" v-if="auth.tienePermiso('reportes')">📉 <span>Análisis de ventas</span></router-link>
        <router-link to="/mapa"          class="nav-item" v-if="auth.tienePermiso('mapa')">🗺️ <span>Mapa clientes</span></router-link>

        <div class="nav-section">Sistema</div>
        <router-link to="/usuarios"      class="nav-item" v-if="auth.tienePermiso('usuarios')">🔑 <span>Usuarios</span></router-link>
        <router-link to="/configuracion" class="nav-item" v-if="auth.tienePermiso('configuracion')">⚙️ <span>Configuración</span></router-link>
      </nav>

      <div class="sidebar-user">
        <div class="user-avatar">{{ auth.user.nombre[0].toUpperCase() }}</div>
        <div class="user-info">
          <span class="user-name">{{ auth.user.nombre }}</span>
          <span class="user-role">{{ auth.user.rol }}</span>
        </div>
        <button class="btn-logout" @click="cerrarSesion" title="Cerrar sesión">⏻</button>
      </div>
    </aside>

    <!-- Contenido principal -->
    <main class="main-content">
      <header class="topbar">
        <h2 class="page-title">{{ rutaTitulo }}</h2>
        <div class="topbar-right">
  <span class="status-dot" :class="{ online: online }"></span>
  <span class="topbar-user">{{ auth.user.nombre }}</span>
  <button @click="toggleTema" class="btn-tema" :title="modoOscuro ? 'Modo claro' : 'Modo oscuro'">
    {{ modoOscuro ? '☀️' : '🌙' }}
  </button>
  <button
    @click="cerrarSesion" 
    style="
      background:rgba(239,68,68,.15);
      color:#fca5a5;
      border:1px solid rgba(239,68,68,.3);
      border-radius:6px;
      padding:.35rem .8rem;
      font-size:.78rem;
      font-weight:600;
      cursor:pointer;
      transition:all .18s;
    "
    onmouseover="this.style.background='rgba(239,68,68,.25)'"
    onmouseout="this.style.background='rgba(239,68,68,.15)'"
  >
    ⏻ Cerrar sesión
  </button>
</div>
      </header>
      <div class="view-container">
        <router-view />
      </div>
    </main>

  </div>

  <router-view v-else />
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { api } from './services/api'

const auth             = useAuthStore()
const router           = useRouter()
const route            = useRoute()
const sidebarCollapsed = ref(false)
const online           = ref(false)
const modoOscuro       = ref(localStorage.getItem('fario_tema') === 'oscuro')

function aplicarTema() {
  if (modoOscuro.value) document.documentElement.classList.add('dark')
  else document.documentElement.classList.remove('dark')
}
function toggleTema() {
  modoOscuro.value = !modoOscuro.value
  localStorage.setItem('fario_tema', modoOscuro.value ? 'oscuro' : 'claro')
  aplicarTema()
}

const titulos = {
  '/':              '🏠 Dashboard',
  '/ventas':        '💰 Ventas',
  '/cotizaciones':  '📋 Cotizaciones',
  '/alquileres':    '🔧 Alquileres',
  '/inventario':    '📦 Inventario',
  '/compras':       '🛒 Compras',
  '/devoluciones':  '↩️ Devoluciones',
  '/clientes':      '👥 Clientes',
  '/proveedores':   '🏭 Proveedores',
  '/usuarios':      '🔑 Usuarios',
  '/caja':          '🏦 Caja',
  '/reportes':      '📊 Reportes',
  '/configuracion': '⚙️ Configuración',
  '/analisis': '📉 Análisis de ventas',
  '/mapa':     '🗺️ Mapa de clientes',
}

const rutaTitulo = computed(() => titulos[route.path] || 'FARIO')

async function cerrarSesion() {
  auth.logout()
  router.push('/login')
}

async function checkStatus() {
  try {
    await api.ping()
    online.value = true
  } catch {
    online.value = false
  }
}

onMounted(() => {
  aplicarTema()
  checkStatus()
  setInterval(checkStatus, 30000)
})
</script>