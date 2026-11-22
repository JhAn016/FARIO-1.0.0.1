<template>
  <div>
    <div class="view-toolbar">
      <button class="btn-primary" @click="abrirModal()">+ Nuevo cliente</button>
      <input class="search-input" v-model="busqueda" placeholder="Buscar cliente…" />
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Nombre</th><th>Cédula</th><th>Teléfono</th><th>Correo</th><th>Tipo</th><th>Ubicación</th><th style="width:110px">Acciones</th></tr>
        </thead>
        <tbody>
          <tr v-if="!filtrados.length">
            <td colspan="8" style="text-align:center;color:var(--text3);padding:2rem">Sin clientes</td>
          </tr>
          <tr v-for="c in filtrados" :key="c.id_cliente">
            <td class="mono">{{ c.id_cliente }}</td>
            <td><strong>{{ c.nombre }}</strong></td>
            <td class="mono">{{ c.documento || '—' }}</td>
            <td>{{ c.telefono || '—' }}</td>
            <td>{{ c.correo || '—' }}</td>
            <td><span class="badge badge-blue">{{ c.tipo || 'Final' }}</span></td>
            <td>
              <span v-if="c.lat" style="color:#10b981;font-size:.78rem">📍 {{ c.ciudad || 'Ubicado' }}</span>
              <span v-else style="color:var(--text3);font-size:.78rem">Sin ubicación</span>
            </td>
            <td class="actions">
              <button class="btn-icon" @click="verHistorial(c)" title="Historial del cliente" style="color:#3b82f6">📋</button>
              <button class="btn-icon edit" @click="abrirModal(c)">✏️</button>
              <button class="btn-icon del" @click="eliminar(c.id_cliente)">🗑</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal historial del cliente -->
    <div class="modal-overlay" :class="{ open: historialAbierto }" @click.self="historialAbierto = false">
      <div class="modal-box" style="max-width:700px">
        <button class="modal-close" @click="historialAbierto = false">✕</button>
        <h3 class="modal-title">📋 Historial — {{ clienteHistorial?.nombre }}</h3>
        <div class="modal-body">
          <div v-if="cargandoHistorial" style="text-align:center;padding:2rem;color:var(--text3)">Cargando…</div>
          <template v-else>
            <!-- KPIs resumen -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:1rem">
              <div class="kpi-card" style="--accent:#f59e0b;padding:.75rem">
                <div class="kpi-body">
                  <span class="kpi-label">Compras</span>
                  <span class="kpi-value" style="font-size:1.2rem">{{ historial.resumen?.total_ventas ?? 0 }}</span>
                </div>
              </div>
              <div class="kpi-card" style="--accent:#10b981;padding:.75rem">
                <div class="kpi-body">
                  <span class="kpi-label">Total gastado</span>
                  <span class="kpi-value" style="font-size:1.1rem">{{ fmt(historial.resumen?.total_gastado) }}</span>
                </div>
              </div>
              <div class="kpi-card" style="--accent:#3b82f6;padding:.75rem">
                <div class="kpi-body">
                  <span class="kpi-label">Alquileres</span>
                  <span class="kpi-value" style="font-size:1.2rem">{{ historial.resumen?.total_alquileres ?? 0 }}</span>
                </div>
              </div>
            </div>

            <!-- Ventas -->
            <h4 style="margin-bottom:.5rem;color:var(--text2)">Ventas</h4>
            <div v-if="!historial.ventas?.length" style="color:var(--text3);font-size:.82rem;margin-bottom:1rem">Sin compras</div>
            <div v-else class="table-wrap" style="max-height:200px;overflow-y:auto;margin-bottom:1rem">
              <table class="data-table">
                <thead><tr><th>Factura</th><th>Fecha</th><th>Total</th><th>Pago</th></tr></thead>
                <tbody>
                  <tr v-for="v in historial.ventas" :key="v.numero_factura">
                    <td class="mono">{{ v.numero_factura }}</td>
                    <td style="font-size:.78rem">{{ fmtD(v.fecha) }}</td>
                    <td class="mono">{{ fmt(v.total) }}</td>
                    <td>{{ v.pago }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Alquileres -->
            <h4 style="margin-bottom:.5rem;color:var(--text2)">Alquileres</h4>
            <div v-if="!historial.alquileres?.length" style="color:var(--text3);font-size:.82rem">Sin alquileres</div>
            <div v-else class="table-wrap" style="max-height:200px;overflow-y:auto">
              <table class="data-table">
                <thead><tr><th>Desde</th><th>Hasta</th><th>Estado</th><th>Total</th></tr></thead>
                <tbody>
                  <tr v-for="a in historial.alquileres" :key="a.fecha">
                    <td style="font-size:.78rem">{{ fmtD(a.fecha) }}</td>
                    <td style="font-size:.78rem">{{ fmtD(a.fin) }}</td>
                    <td><span class="badge" :class="a.estado === 'Devuelto' ? 'badge-green' : 'badge-blue'">{{ a.estado }}</span></td>
                    <td class="mono">{{ fmt(a.total) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="historialAbierto = false">Cerrar</button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div class="modal-overlay" :class="{ open: modalAbierto }" @click.self="cerrarModal">
      <div class="modal-box" style="max-width:660px">
        <button class="modal-close" @click="cerrarModal">✕</button>
        <h3 class="modal-title">{{ editId ? 'Editar' : 'Nuevo' }} Cliente</h3>
        <div class="modal-body">

          <div class="form-row">
            <div class="form-group"><label>Nombre *</label><input v-model="form.nombre" /></div>
            <div class="form-group">
              <label>Tipo</label>
              <select v-model="form.tipo">
                <option>Final</option><option>Empresa</option><option>Mayorista</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group"><label>Cédula/NIT</label><input v-model="form.cedula" /></div>
            <div class="form-group"><label>Teléfono</label><input v-model="form.telefono" /></div>
          </div>

          <div class="form-group">
            <label>Correo</label><input v-model="form.correo" type="email" />
          </div>

          <!-- SECCIÓN DIRECCIÓN + MAPA -->
          <div class="form-group">
            <label>
              Dirección
              <span v-if="form.lat" style="font-weight:400;color:#10b981;font-size:.75rem;margin-left:.4rem">
                ✓ Ubicación marcada en mapa
              </span>
            </label>
            <input v-model="form.direccion" placeholder="Ej: Calle 5 #25-10, Barrio Centro" />
          </div>

          <!-- Botón abrir mapa -->
          <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem">
            <button
              type="button"
              class="btn-ghost"
              style="font-size:.82rem;padding:.4rem .9rem"
              @click="toggleMapa"
            >
              {{ mapaAbierto ? '🗺️ Cerrar mapa' : '🗺️ Marcar ubicación en mapa' }}
            </button>
            <button
              v-if="form.lat"
              type="button"
              class="btn-ghost"
              style="font-size:.82rem;padding:.4rem .9rem;color:#ef4444;border-color:rgba(239,68,68,.4)"
              @click="quitarUbicacion"
            >✕ Quitar ubicación</button>
            <span v-if="form.lat" style="font-size:.75rem;color:var(--text3)">
              {{ form.lat.toFixed(5) }}, {{ form.lng.toFixed(5) }}
            </span>
          </div>

          <!-- Mapa interactivo -->
          <div v-show="mapaAbierto" style="border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:.75rem">

            <!-- Buscador de ciudad dentro del mapa -->
            <div style="padding:.5rem .75rem;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;gap:.5rem;position:relative">
              <input
                v-model="busquedaCiudad"
                @input="onBuscarCiudad"
                placeholder="Busca una ciudad o barrio para navegar al mapa..."
                style="flex:1;font-size:.82rem"
                autocomplete="off"
              />
              <span v-if="buscandoCiudad" style="font-size:.75rem;color:var(--text3);align-self:center">Buscando…</span>
              <!-- Sugerencias de ciudad -->
              <div v-if="sugerenciasCiudad.length" style="
                position:absolute;top:100%;left:.75rem;right:.75rem;
                background:var(--surface2);border:1px solid var(--border);
                border-radius:6px;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.5);
                overflow:hidden;
              ">
                <div
                  v-for="s in sugerenciasCiudad" :key="s.place_id"
                  @mousedown.prevent="navegarA(s)"
                  style="padding:.5rem .75rem;cursor:pointer;font-size:.81rem;border-bottom:1px solid var(--border)"
                  @mouseenter="e => e.currentTarget.style.background='var(--surface3)'"
                  @mouseleave="e => e.currentTarget.style.background=''"
                >📍 {{ s.display_name }}</div>
              </div>
            </div>

            <div style="padding:.4rem .75rem;background:var(--surface2);border-bottom:1px solid var(--border);font-size:.75rem;color:var(--text3)">
              👆 Haz clic en el mapa para marcar la ubicación exacta del cliente
            </div>

            <div ref="mapaPickerEl" class="mapa-picker" style="height:350px;width:100%"></div>
          </div>

          <div class="form-group">
            <label>Notas</label><textarea v-model="form.notas" rows="2"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-ghost" @click="cerrarModal">Cancelar</button>
          <button class="btn-primary" @click="guardar" :disabled="guardando">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { api } from '../services/api'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl:       'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl:     'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
})

// ─── Estado principal ────────────────────────────────────────────────────────
const clientes     = ref([])
const busqueda     = ref('')
const modalAbierto = ref(false)
const guardando    = ref(false)
const editId       = ref(null)

// ─── Historial de cliente ────────────────────────────────────────────────────
const historialAbierto  = ref(false)
const clienteHistorial  = ref(null)
const cargandoHistorial = ref(false)
const historial         = ref({ ventas: [], alquileres: [], resumen: {} })

const fmt  = n => new Intl.NumberFormat('es-CO', { style:'currency', currency:'COP', maximumFractionDigits:0 }).format(n || 0)
const fmtD = d => d ? new Date(d).toLocaleDateString('es-CO', { day:'2-digit', month:'2-digit', year:'2-digit' }) : '—'

async function verHistorial(c) {
  clienteHistorial.value  = c
  historialAbierto.value  = true
  cargandoHistorial.value = true
  historial.value = { ventas: [], alquileres: [], resumen: {} }
  try {
    const res = await api.getHistorialCliente(c.id_cliente)
    historial.value = res.data.data || {}
  } catch(e) {
    console.error(e)
  } finally {
    cargandoHistorial.value = false
  }
}

const formVacio = { nombre:'', tipo:'Final', cedula:'', telefono:'', correo:'', direccion:'', notas:'', lat:null, lng:null, ciudad:'' }
const form = ref({ ...formVacio })

// ─── Mapa picker ────────────────────────────────────────────────────────────
const mapaAbierto    = ref(false)
const mapaPickerEl   = ref(null)
const busquedaCiudad = ref('')
const buscandoCiudad = ref(false)
const sugerenciasCiudad = ref([])
let   mapaPicker     = null
let   marcadorActual = null
let   debounceCity   = null
let   abortCtrl      = null

// ─── Tabla ──────────────────────────────────────────────────────────────────
const filtrados = computed(() => {
  const s = busqueda.value.toLowerCase()
  return clientes.value.filter(c =>
    c.nombre.toLowerCase().includes(s) ||
    (c.documento||'').includes(s) ||
    (c.telefono||'').includes(s)
  )
})

async function cargar() {
  try {
    const res = await api.getClientes()
    clientes.value = res.data.data
  } catch (e) {
    alert(e.response?.data?.msg || 'No se pudieron cargar los clientes')
  }
}

// ─── Modal ──────────────────────────────────────────────────────────────────
function abrirModal(c = null) {
  editId.value = c ? c.id_cliente : null
  form.value = c
    ? {
        nombre: c.nombre, tipo: c.tipo||'Final', cedula: c.documento||'',
        telefono: c.telefono||'', correo: c.correo||'', direccion: c.direccion||'',
        notas: c.notas||'', ciudad: c.ciudad||'',
        lat: c.lat ? parseFloat(c.lat) : null,
        lng: c.lng ? parseFloat(c.lng) : null,
      }
    : { ...formVacio }
  mapaAbierto.value    = false
  busquedaCiudad.value = ''
  sugerenciasCiudad.value = []
  modalAbierto.value   = true
}

function cerrarModal() {
  modalAbierto.value = false
  mapaAbierto.value  = false
  destroyMapaPicker()
}

// ─── Mapa picker ────────────────────────────────────────────────────────────
function toggleMapa() {
  mapaAbierto.value = !mapaAbierto.value
  if (mapaAbierto.value) {
    nextTick(() => iniciarMapaPicker())
  } else {
    destroyMapaPicker()
  }
}

function iniciarMapaPicker() {
  if (!mapaPickerEl.value) return
  if (mapaPicker) { mapaPicker.remove(); mapaPicker = null }

  const lat = form.value.lat || 3.5394
  const lng = form.value.lng || -76.3026
  const zoom = form.value.lat ? 16 : 11

  mapaPicker = L.map(mapaPickerEl.value, {
    center: [lat, lng],
    zoom,
    zoomControl: true,
    attributionControl: false,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap',
  }).addTo(mapaPicker)

  // Si ya tiene ubicación, mostrar marcador
  if (form.value.lat) {
    marcadorActual = L.marker([form.value.lat, form.value.lng]).addTo(mapaPicker)
  }

  // Clic en el mapa → colocar marcador y geocodificar inverso
  mapaPicker.on('click', async (e) => {
    const { lat, lng } = e.latlng

    if (marcadorActual) mapaPicker.removeLayer(marcadorActual)
    marcadorActual = L.marker([lat, lng]).addTo(mapaPicker)
      .bindPopup('📍 Ubicación del cliente').openPopup()

    form.value.lat = lat
    form.value.lng = lng

    // Geocodificación inversa para obtener la dirección real
    try {
      const res  = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=es`, {
        headers: { 'User-Agent': 'FARIO-App/1.0' }
      })
      const data = await res.json()
      if (data && data.address) {
        const a = data.address
        form.value.ciudad   = a.city || a.town || a.village || a.municipality || a.county || ''
        if (!form.value.direccion) {
          const road = a.road ? (a.road + (a.house_number ? ' #' + a.house_number : '')) : ''
          const barrio = a.neighbourhood || a.suburb || ''
          form.value.direccion = [road, barrio, form.value.ciudad].filter(Boolean).join(', ')
        }
      }
    } catch(e) {
      // Si falla la geocodificación inversa, no pasa nada — las coordenadas ya quedaron guardadas
    }
  })
}

function destroyMapaPicker() {
  if (mapaPicker) { mapaPicker.remove(); mapaPicker = null }
  marcadorActual = null
}

function quitarUbicacion() {
  form.value.lat    = null
  form.value.lng    = null
  form.value.ciudad = ''
  if (marcadorActual && mapaPicker) {
    mapaPicker.removeLayer(marcadorActual)
    marcadorActual = null
  }
}

// ─── Buscador de ciudad en el mapa ──────────────────────────────────────────
function onBuscarCiudad() {
  sugerenciasCiudad.value = []
  clearTimeout(debounceCity)
  if (busquedaCiudad.value.trim().length < 3) return
  debounceCity = setTimeout(buscarCiudad, 500)
}

async function buscarCiudad() {
  const q = busquedaCiudad.value.trim()
  if (!q) return
  if (abortCtrl) abortCtrl.abort()
  abortCtrl = new AbortController()
  buscandoCiudad.value = true
  try {
    const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q + ' Valle del Cauca Colombia')}&format=json&limit=4&addressdetails=0&viewbox=-77.5,2.8,-75.5,5.0&bounded=1`
    const res  = await fetch(url, { signal: abortCtrl.signal, headers: { 'Accept-Language': 'es', 'User-Agent': 'FARIO-App/1.0' } })
    sugerenciasCiudad.value = await res.json()
  } catch(e) {
    if (e.name !== 'AbortError') console.error(e)
  } finally {
    buscandoCiudad.value = false
    abortCtrl = null
  }
}

function navegarA(s) {
  sugerenciasCiudad.value = []
  busquedaCiudad.value    = ''
  if (mapaPicker) {
    mapaPicker.setView([parseFloat(s.lat), parseFloat(s.lon)], 14, { animate: true })
  }
}

// ─── CRUD ───────────────────────────────────────────────────────────────────
async function guardar() {
  if (!form.value.nombre.trim()) return alert('El nombre es obligatorio')
  guardando.value = true
  try {
    if (editId.value) await api.actualizarCliente(editId.value, form.value)
    else              await api.crearCliente(form.value)
    cerrarModal()
    await cargar()
  } catch(e) {
    alert(e.response?.data?.msg || 'Error al guardar')
  } finally {
    guardando.value = false
  }
}

async function eliminar(id) {
  if (!confirm('¿Eliminar cliente?')) return
  await api.eliminarCliente(id)
  await cargar()
}

onMounted(cargar)
</script>

<style scoped>
.mapa-picker :deep(.leaflet-tile-pane) {
  filter: invert(92%) hue-rotate(180deg) brightness(0.95) contrast(0.9);
}
</style>
