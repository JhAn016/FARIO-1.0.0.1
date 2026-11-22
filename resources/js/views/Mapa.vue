<template>
  <div>
    <!-- Toggle de modo + acciones -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem">
      <div class="modo-toggle">
        <button :class="{active: modo==='alquileres'}" @click="cambiarModo('alquileres')">🔧 Herramientas alquiladas</button>
        <button :class="{active: modo==='clientes'}" @click="cambiarModo('clientes')">👥 Clientes</button>
      </div>
      <button class="btn-ghost" @click="recargar" :disabled="cargando" style="font-size:.82rem;display:flex;align-items:center;gap:.4rem">
        <span :style="cargando?'animation:spin .7s linear infinite;display:inline-block':''">⟳</span>
        {{ cargando ? 'Actualizando…' : 'Actualizar mapa' }}
      </button>
    </div>

    <!-- KPIs modo Alquileres -->
    <div v-if="modo==='alquileres'" class="kpi-grid" style="margin-bottom:1rem">
      <div class="kpi-card">
        <div class="kpi-icon">🔧</div>
        <div class="kpi-body">
          <span class="kpi-label">Alquileres activos</span>
          <span class="kpi-value">{{ alquileres.length }}</span>
          <span class="kpi-sub">en la calle ahora mismo</span>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon">📦</div>
        <div class="kpi-body">
          <span class="kpi-label">Herramientas alquiladas</span>
          <span class="kpi-value">{{ totalHerramientas }}</span>
          <span class="kpi-sub">unidades en la calle</span>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon">⚠️</div>
        <div class="kpi-body">
          <span class="kpi-label">En mora</span>
          <span class="kpi-value">{{ enMoraAlq.length }}</span>
          <span class="kpi-sub">alquileres vencidos</span>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon">💰</div>
        <div class="kpi-body">
          <span class="kpi-label">Valor en la calle</span>
          <span class="kpi-value" style="font-size:1.05rem">{{ fmt(valorEnAlquiler) }}</span>
          <span class="kpi-sub">total de alquileres activos</span>
        </div>
      </div>
    </div>

    <!-- KPIs modo Clientes -->
    <div v-else class="kpi-grid" style="margin-bottom:1rem">
      <div class="kpi-card">
        <div class="kpi-icon">👥</div>
        <div class="kpi-body">
          <span class="kpi-label">Clientes en mapa</span>
          <span class="kpi-value">{{ clientes.length }}</span>
          <span class="kpi-sub">clientes activos</span>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon">⭐</div>
        <div class="kpi-body">
          <span class="kpi-label">Clientes frecuentes</span>
          <span class="kpi-value">{{ frecuentes }}</span>
          <span class="kpi-sub">5+ compras o $1M+</span>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon">🔄</div>
        <div class="kpi-body">
          <span class="kpi-label">Clientes regulares</span>
          <span class="kpi-value">{{ regulares }}</span>
          <span class="kpi-sub">2-4 compras</span>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon">⚠️</div>
        <div class="kpi-body">
          <span class="kpi-label">Con mora</span>
          <span class="kpi-value">{{ enMora.length }}</span>
          <span class="kpi-sub">alquileres vencidos</span>
        </div>
      </div>
    </div>

    <div class="mapa-grid">

      <!-- MAPA -->
      <div class="card mapa-card">

        <!-- Barra de búsqueda tipo Google Maps -->
        <div class="msearch-wrap">
          <div class="msearch-box">
            <span class="msearch-icon">🔍</span>
            <input
              ref="searchInput"
              v-model="busqDireccion"
              @input="onInputDireccion"
              @keyup.enter="buscarDireccion"
              @keyup.escape="cerrarResultados"
              placeholder="Buscar dirección o lugar…"
              class="msearch-input"
              autocomplete="off"
            />
            <button v-if="busqDireccion" class="msearch-clear" @click="limpiarBusqueda">✕</button>
            <span v-if="buscando" class="msearch-spinner">⟳</span>
          </div>
          <div v-if="resultados.length" class="msearch-results">
            <div
              v-for="r in resultados" :key="r.place_id"
              class="msearch-result"
              @click="irALugar(r)"
            >
              <span class="msr-pin">📍</span>
              <div class="msr-info">
                <span class="msr-title">{{ r.display_name.split(',')[0] }}</span>
                <span class="msr-sub">{{ r.display_name.split(',').slice(1,3).join(',').trim() }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Leyenda -->
        <div class="mapa-leyenda" v-if="modo==='alquileres'">
          <span><span class="punto" style="background:#f59e0b"></span> Alquiler activo</span>
          <span><span class="punto" style="background:#ef4444"></span> En mora</span>
        </div>
        <div class="mapa-leyenda" v-else>
          <span><span class="punto" style="background:#10b981"></span> Frecuente</span>
          <span><span class="punto" style="background:#3b82f6"></span> Regular</span>
          <span><span class="punto" style="background:#8892a4"></span> Nuevo</span>
          <span><span class="punto" style="background:#ef4444"></span> En mora</span>
        </div>

        <!-- Contenedor del mapa -->
        <div ref="mapContainer" class="mapa-contenedor"></div>
        <div v-if="cargando && primeraCarga" class="mapa-loading-overlay">
          <span style="animation:spin .8s linear infinite;display:inline-block;font-size:1.4rem">⟳</span>
          <span>Ubicando direcciones en el mapa…</span>
        </div>
      </div>

      <!-- Panel lateral: modo Alquileres -->
      <div class="mapa-panel" v-if="modo==='alquileres'">
        <div class="card">
          <div class="card-header"><h4>🔍 Buscar alquiler</h4></div>
          <input
            class="search-input" style="width:100%"
            v-model="busquedaAlq"
            placeholder="Cliente, barrio o ciudad..."
          />
          <div class="list-mini" style="margin-top:.5rem;max-height:220px;overflow-y:auto">
            <div v-if="!alquileresFiltrados.length" style="text-align:center;color:var(--text3);padding:.75rem;font-size:.82rem">
              {{ alquileres.length ? 'Sin resultados' : 'No hay alquileres activos con dirección ubicable' }}
            </div>
            <div
              v-for="a in alquileresFiltrados" :key="a.id"
              class="list-mini-item" style="cursor:pointer"
              @click="centrarEnAlquiler(a)"
            >
              <span class="lm-name">{{ a.cliente || 'Cliente sin nombre' }}</span>
              <span class="lm-tag" :class="a.en_mora ? 'badge-red' : 'badge-orange'">{{ a.en_mora ? 'Mora' : 'Activo' }}</span>
            </div>
          </div>
        </div>

        <!-- Detalle alquiler seleccionado -->
        <div class="card" v-if="alquilerSel">
          <div class="card-header">
            <h4>📋 Detalle del alquiler</h4>
            <button class="btn-icon" @click="alquilerSel=null">✕</button>
          </div>
          <div class="detalle-rows">
            <div class="detalle-row"><span>Cliente</span><strong>{{ alquilerSel.cliente || '—' }}</strong></div>
            <div class="detalle-row"><span>Teléfono</span><span>{{ alquilerSel.telefono || '—' }}</span></div>
            <div class="detalle-row"><span>Ciudad</span><span>{{ alquilerSel.ciudad || '—' }}</span></div>
            <div v-if="alquilerSel.direccion" class="detalle-row" style="align-items:flex-start">
              <span style="flex-shrink:0">Dirección</span>
              <span style="text-align:right;font-size:.8rem">{{ alquilerSel.direccion }}{{ alquilerSel.barrio ? ', ' + alquilerSel.barrio : '' }}</span>
            </div>
            <div class="detalle-row"><span>Vence</span><span>{{ alquilerSel.fecha_final || '—' }}</span></div>
            <div class="detalle-row">
              <span>Total</span>
              <span style="color:var(--accent);font-weight:700">{{ fmt(alquilerSel.total) }}</span>
            </div>
            <div v-if="alquilerSel.items && alquilerSel.items.length" style="margin-top:.25rem">
              <span style="font-size:.78rem;color:var(--text2);display:block;margin-bottom:.3rem">Herramientas alquiladas</span>
              <div v-for="(it, i) in alquilerSel.items" :key="i" style="display:flex;justify-content:space-between;font-size:.8rem;padding:.15rem 0">
                <span>{{ it.nombre }}</span>
                <span class="mono">x{{ it.cantidad }}</span>
              </div>
            </div>
            <div v-if="alquilerSel.en_mora"
              style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:6px;padding:.5rem;color:#fca5a5;font-size:.8rem;text-align:center;margin-top:.25rem">
              ⚠️ Este alquiler está en mora
            </div>
          </div>
        </div>

        <!-- Próximos a vencer -->
        <div class="card">
          <div class="card-header"><h4>📅 Vencen pronto</h4></div>
          <div class="list-mini">
            <div v-if="!proximosVencer.length" style="text-align:center;color:var(--text3);padding:.5rem;font-size:.82rem">
              Sin alquileres próximos a vencer
            </div>
            <div
              v-for="a in proximosVencer" :key="a.id"
              class="list-mini-item" style="cursor:pointer"
              @click="centrarEnAlquiler(a)"
            >
              <span class="lm-name">{{ a.cliente || 'Cliente sin nombre' }}</span>
              <span class="lm-val">{{ a.fecha_final }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Panel lateral: modo Clientes -->
      <div class="mapa-panel" v-else>

        <!-- Buscar cliente (en lista) -->
        <div class="card">
          <div class="card-header"><h4>🔍 Buscar cliente</h4></div>
          <input
            class="search-input" style="width:100%"
            v-model="busqueda"
            placeholder="Nombre del cliente..."
          />
          <div class="list-mini" style="margin-top:.5rem;max-height:180px;overflow-y:auto">
            <div v-if="!clientesFiltrados.length" style="text-align:center;color:var(--text3);padding:.75rem;font-size:.82rem">
              Sin resultados
            </div>
            <div
              v-for="c in clientesFiltrados" :key="c.id"
              class="list-mini-item" style="cursor:pointer"
              @click="centrarEnCliente(c)"
            >
              <span class="lm-name">{{ c.nombre }}</span>
              <span class="lm-tag" :class="tagColor(c)">{{ c.tipo }}</span>
            </div>
          </div>
        </div>

        <!-- Detalle cliente seleccionado -->
        <div class="card" v-if="clienteSel">
          <div class="card-header">
            <h4>📋 Detalle del cliente</h4>
            <button class="btn-icon" @click="clienteSel=null">✕</button>
          </div>
          <div class="detalle-rows">
            <div class="detalle-row">
              <span>Nombre</span><strong>{{ clienteSel.nombre }}</strong>
            </div>
            <div class="detalle-row">
              <span>Teléfono</span><span>{{ clienteSel.telefono }}</span>
            </div>
            <div class="detalle-row">
              <span>Ciudad</span><span>{{ clienteSel.ciudad || '—' }}</span>
            </div>
            <div v-if="clienteSel.direccion" class="detalle-row" style="align-items:flex-start">
              <span style="flex-shrink:0">Dirección</span>
              <span style="text-align:right;font-size:.8rem">{{ clienteSel.direccion }}</span>
            </div>
            <div class="detalle-row">
              <span>Compras</span><span class="badge badge-blue">{{ clienteSel.total_compras }}</span>
            </div>
            <div class="detalle-row">
              <span>Total gastado</span>
              <span style="color:var(--accent);font-weight:700">{{ fmt(clienteSel.total_gastado) }}</span>
            </div>
            <div class="detalle-row">
              <span>Tipo</span><span class="badge" :class="tagColor(clienteSel)">{{ clienteSel.tipo }}</span>
            </div>
            <div v-if="enMora.includes(clienteSel.id)"
              style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:6px;padding:.5rem;color:#fca5a5;font-size:.8rem;text-align:center;margin-top:.25rem">
              ⚠️ Este cliente tiene alquileres en mora
            </div>
          </div>
        </div>

        <!-- Top clientes -->
        <div class="card">
          <div class="card-header"><h4>🏆 Top clientes</h4></div>
          <div class="list-mini">
            <div
              v-for="(c, idx) in top5" :key="c.id"
              class="list-mini-item" style="cursor:pointer"
              @click="centrarEnCliente(c)"
            >
              <span class="lm-name">
                <span style="color:var(--accent);font-weight:800;margin-right:.4rem">{{ idx+1 }}.</span>
                {{ c.nombre }}
              </span>
              <span class="lm-val">{{ fmt(c.total_gastado) }}</span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { api } from '../services/api'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl:       'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl:     'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
})

const CENTRO = { lat: 3.5394, lng: -76.3026 }

const mapContainer = ref(null)
const searchInput  = ref(null)

const modo         = ref('alquileres') // 'alquileres' | 'clientes'
const cargando     = ref(false)
const primeraCarga = ref(true)

// Clientes
const clientes     = ref([])
const enMora       = ref([])
const busqueda     = ref('')
const clienteSel   = ref(null)

// Alquileres
const alquileres   = ref([])
const enMoraAlq    = ref([])
const busquedaAlq  = ref('')
const alquilerSel  = ref(null)

const busqDireccion = ref('')
const buscando      = ref(false)
const resultados    = ref([])
let   timerBusq     = null
let   mapaInst      = null
let   markerBusq    = null
const markers       = []

const fmt = n => new Intl.NumberFormat('es-CO', { style:'currency', currency:'COP', maximumFractionDigits:0 }).format(n||0)

const frecuentes = computed(() => clientes.value.filter(c => c.tipo==='frecuente').length)
const regulares  = computed(() => clientes.value.filter(c => c.tipo==='regular').length)

const clientesFiltrados = computed(() => {
  const s = busqueda.value.toLowerCase()
  return clientes.value.filter(c => c.nombre.toLowerCase().includes(s)).slice(0, 10)
})

const top5 = computed(() =>
  [...clientes.value].sort((a,b) => b.total_gastado - a.total_gastado).slice(0,5)
)

const totalHerramientas = computed(() =>
  alquileres.value.reduce((sum, a) => sum + (a.items || []).reduce((s, it) => s + (Number(it.cantidad) || 0), 0), 0)
)

const valorEnAlquiler = computed(() => alquileres.value.reduce((s, a) => s + (Number(a.total) || 0), 0))

const alquileresFiltrados = computed(() => {
  const s = busquedaAlq.value.toLowerCase()
  return alquileres.value.filter(a =>
    (a.cliente || '').toLowerCase().includes(s) ||
    (a.barrio || '').toLowerCase().includes(s) ||
    (a.ciudad || '').toLowerCase().includes(s)
  ).slice(0, 15)
})

const proximosVencer = computed(() =>
  [...alquileres.value]
    .filter(a => a.fecha_final && !a.en_mora)
    .sort((a,b) => new Date(a.fecha_final) - new Date(b.fecha_final))
    .slice(0, 6)
)

function tagColor(c) {
  if (enMora.value.includes(c.id)) return 'badge-red'
  if (c.tipo==='frecuente') return 'badge-green'
  if (c.tipo==='regular')   return 'badge-blue'
  return 'badge-gray'
}

function colorMarker(c) {
  if (enMora.value.includes(c.id)) return '#ef4444'
  if (c.tipo==='frecuente') return '#10b981'
  if (c.tipo==='regular')   return '#3b82f6'
  return '#8892a4'
}

function crearIcono(color, size=12) {
  return L.divIcon({
    className: '',
    html: `<div style="width:${size}px;height:${size}px;border-radius:50%;background:${color};border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,.4);cursor:pointer"></div>`,
    iconSize:   [size, size],
    iconAnchor: [size/2, size/2],
    popupAnchor:[0, -size/2],
  })
}

function crearIconoHerramienta(color, size=26) {
  return L.divIcon({
    className: '',
    html: `<div style="
      width:${size}px;height:${size}px;border-radius:50% 50% 50% 0;
      background:${color};border:2px solid white;
      box-shadow:0 3px 8px rgba(0,0,0,.4);
      transform:rotate(-45deg);
      display:flex;align-items:center;justify-content:center;cursor:pointer
    "><span style="transform:rotate(45deg);font-size:${size*0.5}px;line-height:1">🔧</span></div>`,
    iconSize:   [size, size],
    iconAnchor: [size/2, size],
    popupAnchor:[0, -size],
  })
}

function centrarEnCliente(c) {
  clienteSel.value = c
  if (mapaInst) mapaInst.flyTo([c.lat, c.lng], 16, { animate:true, duration:.8 })
}

function centrarEnAlquiler(a) {
  alquilerSel.value = a
  if (mapaInst) mapaInst.flyTo([a.lat, a.lng], 16, { animate:true, duration:.8 })
}

/* ─── Búsqueda de dirección ─── */

function onInputDireccion() {
  clearTimeout(timerBusq)
  if (busqDireccion.value.length < 3) { resultados.value = []; return }
  timerBusq = setTimeout(buscarDireccion, 500)
}

async function buscarDireccion() {
  const q = busqDireccion.value.trim()
  if (q.length < 3) return
  buscando.value  = true
  resultados.value = []
  try {
    const encoded = encodeURIComponent(q + ', Valle del Cauca, Colombia')
    const res  = await fetch(
      `https://nominatim.openstreetmap.org/search?q=${encoded}&format=json&limit=5&countrycodes=co&addressdetails=1&viewbox=-77.5,2.8,-75.5,5.0&bounded=1`,
      { headers: { 'Accept-Language': 'es' } }
    )
    resultados.value = await res.json()
  } catch(e) {
    console.error('Nominatim error', e)
  } finally {
    buscando.value = false
  }
}

function irALugar(r) {
  const lat = parseFloat(r.lat)
  const lng = parseFloat(r.lon)

  if (markerBusq) { mapaInst.removeLayer(markerBusq); markerBusq = null }

  const icono = L.divIcon({
    className: '',
    html: `<div style="
      width:20px;height:20px;border-radius:50% 50% 50% 0;
      background:#f59e0b;border:2px solid white;
      box-shadow:0 3px 10px rgba(0,0,0,.4);
      transform:rotate(-45deg);
    "></div>`,
    iconSize:   [20,20],
    iconAnchor: [10,20],
    popupAnchor:[0,-20],
  })

  markerBusq = L.marker([lat, lng], { icon: icono }).addTo(mapaInst)
  markerBusq.bindPopup(`
    <div style="font-family:sans-serif;max-width:220px">
      <strong style="font-size:13px">${r.display_name.split(',')[0]}</strong><br>
      <span style="color:#888;font-size:11px">${r.display_name}</span>
    </div>
  `).openPopup()

  mapaInst.flyTo([lat, lng], 16, { animate:true, duration:1 })

  busqDireccion.value = r.display_name.split(',')[0]
  resultados.value    = []
}

function limpiarBusqueda() {
  busqDireccion.value = ''
  resultados.value    = []
  if (markerBusq) { mapaInst?.removeLayer(markerBusq); markerBusq = null }
  searchInput.value?.focus()
}

function cerrarResultados() {
  resultados.value = []
}

/* ─── Mapa ─── */

function iniciarMapa() {
  mapaInst = L.map(mapContainer.value, {
    center: [CENTRO.lat, CENTRO.lng],
    zoom:   13,
    zoomControl: true,
    attributionControl: false,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
}).addTo(mapaInst)
  mapaInst.on('click', () => { resultados.value = [] })
}

function limpiarMarcadores() {
  markers.forEach(m => mapaInst?.removeLayer(m))
  markers.length = 0
}

function dibujarClientes() {
  clientes.value.forEach(c => {
    const color  = colorMarker(c)
    const size   = c.tipo==='frecuente' ? 16 : c.tipo==='regular' ? 13 : 10
    const marker = L.marker([c.lat, c.lng], { icon: crearIcono(color, size) })

    marker.bindPopup(`
      <div style="font-family:sans-serif;min-width:180px">
        <strong style="font-size:13px">${c.nombre}</strong><br>
        <span style="color:#888;font-size:11px">📍 ${c.ciudad||'Colombia'}</span>
        ${c.direccion ? `<span style="color:#aaa;font-size:10px;display:block;margin-top:2px">${c.direccion}</span>` : ''}
        <br>
        <span style="font-size:11px">📦 Compras: <strong>${c.total_compras}</strong></span><br>
        <span style="font-size:11px">💰 Total: <strong>${fmt(c.total_gastado)}</strong></span><br>
        <span style="font-size:11px">📱 Tel: ${c.telefono}</span>
        ${enMora.value.includes(c.id) ? '<br><span style="color:#ef4444;font-size:11px;font-weight:700">⚠️ En mora</span>' : ''}
      </div>
    `, { maxWidth: 240 })

    marker.on('click', () => { clienteSel.value = c })
    marker.addTo(mapaInst)
    markers.push(marker)
  })
}

function dibujarAlquileres() {
  alquileres.value.forEach(a => {
    const color  = a.en_mora ? '#ef4444' : '#f59e0b'
    const marker = L.marker([a.lat, a.lng], { icon: crearIconoHerramienta(color) })

    const itemsHtml = (a.items || [])
      .map(it => `<span style="font-size:11px;display:block">• ${it.nombre} x${it.cantidad}</span>`)
      .join('')

    marker.bindPopup(`
      <div style="font-family:sans-serif;min-width:190px">
        <strong style="font-size:13px">${a.cliente || 'Cliente'}</strong><br>
        <span style="color:#888;font-size:11px">📍 ${a.direccion ? a.direccion + ', ' : ''}${a.barrio ? a.barrio + ', ' : ''}${a.ciudad||'Colombia'}</span>
        <div style="margin-top:4px">${itemsHtml || '<span style="font-size:11px;color:#888">Sin detalle de herramientas</span>'}</div>
        <br>
        <span style="font-size:11px">📅 Vence: <strong>${a.fecha_final || '—'}</strong></span><br>
        <span style="font-size:11px">💰 Total: <strong>${fmt(a.total)}</strong></span>
        ${a.en_mora ? '<br><span style="color:#ef4444;font-size:11px;font-weight:700">⚠️ En mora</span>' : ''}
      </div>
    `, { maxWidth: 250 })

    marker.on('click', () => { alquilerSel.value = a })
    marker.addTo(mapaInst)
    markers.push(marker)
  })
}

function redibujar() {
  limpiarMarcadores()
  if (modo.value === 'alquileres') dibujarAlquileres()
  else dibujarClientes()
}

async function cargarClientes() {
  const res  = await api.getClientesMapa()
  const data = res.data.data
  clientes.value = data.clientes || []
  enMora.value   = data.enMora   || []
}

async function cargarAlquileres() {
  const res  = await api.getAlquileresMapa()
  const data = res.data.data
  alquileres.value = data.alquileres || []
  enMoraAlq.value  = data.enMora     || []
}

async function recargar() {
  cargando.value = true
  try {
    if (modo.value === 'alquileres') await cargarAlquileres()
    else await cargarClientes()
    redibujar()
  } catch(e) {
    console.error(e)
  } finally {
    cargando.value  = false
    primeraCarga.value = false
  }
}

async function cambiarModo(m) {
  if (modo.value === m) return
  modo.value = m
  clienteSel.value  = null
  alquilerSel.value = null
  const yaCargado = m === 'alquileres' ? alquileres.value.length > 0 : clientes.value.length > 0
  if (yaCargado) {
    redibujar()
  } else {
    await recargar()
  }
}

onMounted(async () => {
  iniciarMapa()
  await recargar()
})

onUnmounted(() => {
  clearTimeout(timerBusq)
  limpiarMarcadores()
  if (mapaInst) { mapaInst.remove(); mapaInst = null }
})
</script>

<style scoped>
.mapa-grid {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 1rem;
}

/* ── Toggle de modo ── */
.modo-toggle {
  display: inline-flex; background: var(--card2, rgba(255,255,255,.06));
  border-radius: 10px; padding: 3px; gap: 2px;
}
.modo-toggle button {
  border: none; background: transparent; color: var(--text2);
  padding: .45rem .85rem; border-radius: 8px; font-size: .82rem;
  cursor: pointer; transition: all .15s; font-family: 'Sora', sans-serif;
}
.modo-toggle button.active {
  background: var(--accent); color: #0a0e14; font-weight: 600;
}

/* ── Mapa ── */
.mapa-card {
  padding: 0; overflow: hidden; position: relative;
}

.mapa-contenedor {
  height: 540px; width: 100%;
}

.mapa-loading-overlay {
  position: absolute; inset: 0; z-index: 900;
  background: rgba(10,12,18,.55);
  display: flex; align-items: center; justify-content: center; gap: .6rem;
  color: #fff; font-size: .85rem; pointer-events: none;
}

/* ── Leyenda ── */
.mapa-leyenda {
  position: absolute; bottom: 12px; left: 12px; z-index: 1000;
  display: flex; gap: .65rem; flex-wrap: wrap;
  background: rgba(15,17,23,.85); border-radius: 8px;
  padding: .4rem .75rem; font-size: .72rem; color: #e8eaf0;
  backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,.1);
}
.punto {
  width: 10px; height: 10px; border-radius: 50%;
  display: inline-block; margin-right: .3rem; vertical-align: middle;
  border: 1px solid rgba(255,255,255,.4);
}

.badge-orange { background: rgba(245,158,11,.15); color: #f59e0b; }

/* ── Barra de búsqueda tipo Google Maps ── */
.msearch-wrap {
  position: absolute; top: 12px; left: 12px; z-index: 1000;
  width: min(380px, calc(100% - 24px));
}

.msearch-box {
  display: flex; align-items: center; gap: .5rem;
  background: #fff; border-radius: 10px;
  padding: .6rem 1rem;
  box-shadow: 0 4px 24px rgba(0,0,0,.3), 0 1px 4px rgba(0,0,0,.15);
}
.msearch-icon { font-size: 1rem; flex-shrink: 0; }
.msearch-input {
  flex: 1; border: none; outline: none;
  font-size: .9rem; color: #1a202c; background: transparent;
  font-family: 'Sora', sans-serif;
}
.msearch-input::placeholder { color: #94a3b8; }
.msearch-clear {
  background: none; border: none; color: #94a3b8;
  cursor: pointer; font-size: .9rem; padding: 2px 4px;
  border-radius: 50%; line-height: 1; transition: all .15s;
}
.msearch-clear:hover { background: #f1f5f9; color: #475569; }
.msearch-spinner {
  color: #f59e0b; font-size: 1rem;
  animation: spin .7s linear infinite; flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

.msearch-results {
  background: #fff; border-radius: 10px; margin-top: 6px;
  box-shadow: 0 8px 32px rgba(0,0,0,.25);
  overflow: hidden; max-height: 300px; overflow-y: auto;
}
.msearch-result {
  display: flex; align-items: flex-start; gap: .75rem;
  padding: .65rem 1rem; cursor: pointer;
  border-bottom: 1px solid #f1f5f9;
  transition: background .12s;
}
.msearch-result:hover  { background: #f8fafc; }
.msearch-result:last-child { border-bottom: none; }
.msr-pin  { font-size: 1rem; flex-shrink: 0; margin-top: .1rem; }
.msr-info { display: flex; flex-direction: column; gap: .08rem; min-width: 0; }
.msr-title {
  font-size: .87rem; font-weight: 600; color: #1a202c;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.msr-sub {
  font-size: .75rem; color: #64748b;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* ── Panel lateral ── */
.mapa-panel {
  display: flex; flex-direction: column; gap: 1rem;
  max-height: 600px; overflow-y: auto;
}

.detalle-rows { display: flex; flex-direction: column; gap: .45rem; font-size: .85rem; }
.detalle-row  {
  display: flex; justify-content: space-between; align-items: center; gap: .5rem;
}
.detalle-row > span:first-child { color: var(--text2); flex-shrink: 0; }

@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 900px) {
  .mapa-grid { grid-template-columns: 1fr; }
  .mapa-panel { max-height: none; }
}
</style>
