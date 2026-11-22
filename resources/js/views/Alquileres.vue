<template>
  <div>
    <div class="view-toolbar">
      <button class="btn-primary" @click="abrirModal">+ Nuevo alquiler</button>
      <div class="filter-tabs">
        <button class="tab-btn" :class="{ active: filtro === 'todos' }"    @click="filtro = 'todos'">Todos</button>
        <button class="tab-btn" :class="{ active: filtro === 'En curso' }" @click="filtro = 'En curso'">En curso</button>
        <button class="tab-btn" :class="{ active: filtro === 'En mora' }"  @click="filtro = 'En mora'">En mora</button>
        <button class="tab-btn" :class="{ active: filtro === 'Vencido' }"  @click="filtro = 'Vencido'">Vencidos</button>
        <button class="tab-btn" :class="{ active: filtro === 'Devuelto' }" @click="filtro = 'Devuelto'">Devueltos</button>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Cliente</th><th>Cédula</th><th>Inicio</th><th>Fin</th><th>Total</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
          <tr v-if="!filtrados.length">
            <td colspan="8" style="text-align:center;color:var(--text3);padding:2rem">Sin alquileres</td>
          </tr>
          <tr v-for="a in filtrados" :key="a.id_alquiler">
            <td class="mono">#{{ a.id_alquiler }}</td>
            <td>{{ a.cliente || '—' }}</td>
            <td class="mono">{{ a.cedula || '—' }}</td>
            <td>{{ fmtD(a.ini) }}</td>
            <td>{{ fmtD(a.fin) }}</td>
            <td class="mono">{{ fmt(a.total) }}</td>
            <td>
              <span class="badge" :class="badgeAlq(a.estado)">{{ a.estado }}</span>
            </td>
            <td class="actions">
              <button class="btn-icon view" @click="verDetalle(a)" title="Ver detalle">🧾</button>
              <button class="btn-icon" @click="descargarPDF(a)" title="Descargar PDF" style="color:var(--red)">📄</button>
              <button class="btn-icon del" v-if="a.estado !== 'Devuelto'" @click="devolver(a.id_alquiler)" title="Devolver">↩️</button>
              <button class="btn-icon" v-if="a.estado !== 'Devuelto'" @click="abrirProrroga(a)" title="Prorrogar" style="color:var(--accent)">📅</button>
              <button class="btn-icon edit" @click="editarEstado(a)" title="Cambiar estado">🔄</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal nuevo alquiler -->
    <div class="modal-overlay" :class="{ open: modalAbierto }" @click.self="cerrarModalAlquiler">
      <div class="modal-box" style="max-width:680px">
        <button class="modal-close" @click="cerrarModalAlquiler">✕</button>
        <h3 class="modal-title">Nuevo Alquiler</h3>
        <div class="modal-body">

          <div class="form-row">
            <div class="form-group">
              <label>Nombre cliente</label>
              <input v-model="form.cliente" placeholder="Nombre completo" />
            </div>
            <div class="form-group">
              <label>Cédula</label>
              <input v-model="form.cedula" placeholder="Número de cédula" />
            </div>
            <div class="form-group">
              <label>Teléfono</label>
              <input v-model="form.tel" placeholder="Teléfono" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Fecha inicio</label>
              <input v-model="form.ini" type="date" />
            </div>
            <div class="form-group">
              <label>Fecha fin</label>
              <input v-model="form.fin" type="date" />
            </div>
            <div class="form-group">
              <label>Días</label>
              <input :value="diasCalculados" readonly style="background:var(--bg2);cursor:default" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Herramienta</label>
              <select v-model="prodSel">
                <option value="">— Seleccionar —</option>
                <option v-if="!disponibles.length" value="" disabled>No hay herramientas disponibles</option>
                <option v-for="p in disponibles" :key="p.id" :value="p">
                  {{ p.nombre }} (disponibles: {{ disponibleReal(p) }})
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Cantidad</label>
              <input v-model.number="cantSel" type="number" min="1" />
            </div>
            <div class="form-group">
              <label>Precio / día</label>
              <input v-model.number="precioDiaSel" type="number" min="0" />
            </div>
          </div>

          <button class="btn-primary" style="width:100%;margin-bottom:1rem" @click="agregarItem">
            + Agregar herramienta
          </button>

          <div v-if="cart.length">
            <table class="data-table">
              <thead>
                <tr><th>Herramienta</th><th>Cant.</th><th>$/día</th><th>Subtotal</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="(it, idx) in cart" :key="idx">
                  <td>{{ it.nombre }}</td>
                  <td class="mono">{{ it.cant }}</td>
                  <td class="mono">{{ fmt(it.pd) }}</td>
                  <td class="mono">{{ fmt(it.cant * it.pd * diasCalculados) }}</td>
                  <td><button class="btn-icon del" @click="cart.splice(idx, 1)">✕</button></td>
                </tr>
              </tbody>
            </table>
            <div style="text-align:right;font-weight:800;color:var(--accent);margin-top:.5rem">
              Total: {{ fmt(totalCart) }}
            </div>
          </div>
          <p v-else class="muted" style="text-align:center;padding:.5rem">Sin herramientas agregadas</p>

          <!-- SECCIÓN DIRECCIÓN + MAPA -->
          <div class="form-group" style="margin-top:1rem">
            <label>
              Dirección de entrega
              <span v-if="form.lat" style="font-weight:400;color:#10b981;font-size:.75rem;margin-left:.4rem">
                ✓ Ubicación marcada en mapa
              </span>
            </label>
            <input v-model="form.direccion" placeholder="Ej: Calle 5 #25-10" />
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Barrio</label>
              <input v-model="form.barrio" placeholder="Opcional" />
            </div>
            <div class="form-group">
              <label>Ciudad</label>
              <input v-model="form.ciudad" placeholder="Ej: Palmira" />
            </div>
          </div>

          <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem">
            <button type="button" class="btn-ghost" style="font-size:.82rem;padding:.4rem .9rem" @click="toggleMapa">
              {{ mapaAbierto ? '🗺️ Cerrar mapa' : '🗺️ Marcar ubicación en mapa' }}
            </button>
            <button
              v-if="form.lat" type="button" class="btn-ghost"
              style="font-size:.82rem;padding:.4rem .9rem;color:#ef4444;border-color:rgba(239,68,68,.4)"
              @click="quitarUbicacion"
            >✕ Quitar ubicación</button>
            <span v-if="form.lat" style="font-size:.75rem;color:var(--text3)">
              {{ form.lat.toFixed(5) }}, {{ form.lng.toFixed(5) }}
            </span>
          </div>

          <div v-show="mapaAbierto" style="border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:.75rem">
            <div style="padding:.5rem .75rem;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;gap:.5rem;position:relative">
              <input
                v-model="busquedaCiudad"
                @input="onBuscarCiudad"
                placeholder="Busca una ciudad o barrio para navegar al mapa..."
                style="flex:1;font-size:.82rem"
                autocomplete="off"
              />
              <span v-if="buscandoCiudad" style="font-size:.75rem;color:var(--text3);align-self:center">Buscando…</span>
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
              👆 Haz clic en el mapa para marcar el punto exacto de entrega
            </div>
            <div ref="mapaPickerEl" class="mapa-picker" style="height:300px;width:100%"></div>
          </div>

          <div class="form-group">
            <label>Observaciones</label>
            <input v-model="form.notas" placeholder="Opcional" />
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="cerrarModalAlquiler">Cancelar</button>
          <button class="btn-primary" @click="guardar" :disabled="guardando">
            {{ guardando ? 'Guardando...' : 'Registrar alquiler' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal detalle -->
    <div class="modal-overlay" :class="{ open: detalleAbierto }" @click.self="detalleAbierto = false">
      <div class="modal-box" v-if="alqDetalle">
        <button class="modal-close" @click="detalleAbierto = false">✕</button>
        <h3 class="modal-title">Alquiler #{{ alqDetalle.id_alquiler }}</h3>
        <div class="modal-body">
          <div class="form-row" style="margin-bottom:.75rem;gap:1.5rem">
            <div><span style="color:var(--text2)">Cliente: </span><strong>{{ alqDetalle.cliente }}</strong></div>
            <div><span style="color:var(--text2)">Cédula: </span><strong>{{ alqDetalle.cedula || '—' }}</strong></div>
            <div><span style="color:var(--text2)">Teléfono: </span><strong>{{ alqDetalle.tel || '—' }}</strong></div>
          </div>
          <div class="form-row" style="margin-bottom:.75rem;gap:1.5rem">
            <div><span style="color:var(--text2)">Inicio: </span><strong>{{ fmtD(alqDetalle.ini) }}</strong></div>
            <div><span style="color:var(--text2)">Fin: </span><strong>{{ fmtD(alqDetalle.fin) }}</strong></div>
            <div>
              <span style="color:var(--text2)">Estado: </span>
              <span class="badge" :class="badgeAlq(alqDetalle.estado)">{{ alqDetalle.estado }}</span>
            </div>
          </div>
          <div v-if="alqDetalle.direccion || alqDetalle.lat" style="margin-bottom:.75rem;font-size:.85rem;color:var(--text2)">
            📍 {{ [alqDetalle.direccion, alqDetalle.barrio, alqDetalle.ciudad].filter(Boolean).join(', ') || 'Ubicación marcada en mapa' }}
          </div>
          <table class="data-table">
            <thead><tr><th>Herramienta</th><th>Cant.</th><th>$/día</th><th>Subtotal</th></tr></thead>
            <tbody>
              <tr v-for="(it, idx) in alqDetalle.items" :key="idx">
                <td>{{ it.prodNom }}</td>
                <td class="mono">{{ it.cant }}</td>
                <td class="mono">{{ fmt(it.pd) }}</td>
                <td class="mono">{{ fmt(it.cant * it.pd) }}</td>
              </tr>
            </tbody>
          </table>
          <div style="text-align:right;font-size:1.1rem;font-weight:800;color:var(--accent);margin-top:.5rem">
            Total: {{ fmt(alqDetalle.total) }}
          </div>
          <p v-if="alqDetalle.notas" style="margin-top:.5rem;color:var(--text2)">
            Observaciones: {{ alqDetalle.notas }}
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="detalleAbierto = false">Cerrar</button>
          <button class="btn-primary" @click="descargarPDF(alqDetalle)">📄 Descargar PDF</button>
        </div>
      </div>
    </div>

    <!-- Modal prórroga -->
    <div class="modal-overlay" :class="{ open: prorrogaModal }" @click.self="prorrogaModal = false">
      <div class="modal-box" style="max-width:380px" v-if="alqProrroga">
        <button class="modal-close" @click="prorrogaModal = false">✕</button>
        <h3 class="modal-title">Prorrogar alquiler</h3>
        <div class="modal-body">
          <p style="margin-bottom:1rem;color:var(--text2)">
            Alquiler #{{ alqProrroga.id_alquiler }} —
            Fin actual: <strong>{{ fmtD(alqProrroga.fin) }}</strong>
          </p>
          <div class="form-group">
            <label>Nueva fecha de fin</label>
            <input v-model="nuevaFecha" type="date" :min="alqProrroga.fin" />
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="prorrogaModal = false">Cancelar</button>
          <button class="btn-primary" @click="confirmarProrroga">Guardar</button>
        </div>
      </div>
    </div>

    <!-- Modal cambiar estado -->
    <div class="modal-overlay" :class="{ open: estadoModal }" @click.self="estadoModal = false">
      <div class="modal-box" style="max-width:380px" v-if="alqEstado">
        <button class="modal-close" @click="estadoModal = false">✕</button>
        <h3 class="modal-title">Cambiar estado</h3>
        <div class="modal-body">
          <p style="margin-bottom:1rem;color:var(--text2)">
            Alquiler #{{ alqEstado.id_alquiler }} —
            Estado actual: <strong>{{ alqEstado.estado }}</strong>
          </p>
          <div class="form-group">
            <label>Nuevo estado</label>
            <select v-model="nuevoEstado">
              <option value="Automatico">Automático (En curso → Vencido según la fecha)</option>
              <option value="En curso">En curso</option>
              <option value="Vencido">Vencido</option>
              <option value="En mora">En mora</option>
              <option value="Devuelto">Devuelto (regresa las herramientas al stock)</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="estadoModal = false">Cancelar</button>
          <button class="btn-primary" @click="confirmarEstado">Guardar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useAuthStore } from '../stores/auth'
import { api } from '../services/api'
import { generarPDFAlquiler } from '../services/pdf'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl:       'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl:     'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
})

const auth           = useAuthStore()
const alquileres     = ref([])
const productos      = ref([])
const config         = ref({})
const filtro         = ref('todos')
const modalAbierto   = ref(false)
const detalleAbierto = ref(false)
const estadoModal    = ref(false)
const prorrogaModal  = ref(false)
const alqDetalle     = ref(null)
const alqEstado      = ref(null)
const alqProrroga    = ref(null)
const nuevoEstado    = ref('En curso')
const nuevaFecha     = ref('')
const guardando      = ref(false)
const cart           = ref([])
const prodSel        = ref('')
const cantSel        = ref(1)
const precioDiaSel   = ref(0)
const formVacio      = { cliente: '', cedula: '', tel: '', ini: '', fin: '', notas: '', direccion: '', barrio: '', ciudad: '', lat: null, lng: null }
const form           = ref({ ...formVacio })

// ─── Mapa picker (dirección de entrega) ─────────────────────────────────────
const mapaAbierto       = ref(false)
const mapaPickerEl      = ref(null)
const busquedaCiudad    = ref('')
const buscandoCiudad    = ref(false)
const sugerenciasCiudad = ref([])
let   mapaPicker        = null
let   marcadorActual    = null
let   debounceCity      = null
let   abortCtrl         = null

const fmt  = n => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n || 0)
const fmtD = d => d ? new Date(d).toLocaleDateString('es-CO') : '—'

const diasCalculados = computed(() => {
  if (!form.value.ini || !form.value.fin) return 1
  const dias = Math.ceil((new Date(form.value.fin) - new Date(form.value.ini)) / 86400000)
  return dias > 0 ? dias : 1
})

const totalCart = computed(() =>
  cart.value.reduce((s, i) => s + i.cant * i.pd * diasCalculados.value, 0)
)

// Herramientas con unidades libres para alquilar: descuenta lo que ya está
// en el carrito de este mismo alquiler, no solo el stock guardado en BD.
function disponibleReal(p) {
  const enCarrito = cart.value.filter(i => i.prodId === p.id).reduce((s, i) => s + i.cant, 0)
  return p.stock - enCarrito
}

const disponibles = computed(() => productos.value.filter(p => disponibleReal(p) > 0))

const badgeAlq = e => ({
  'En curso': 'badge-green',
  'Devuelto': 'badge-gray',
  'En mora':  'badge-red',
  'Vencido':  'badge-orange',
}[e] || 'badge-gray')

const filtrados = computed(() => {
  const lista = alquileres.value || []
  return filtro.value === 'todos' ? lista : lista.filter(a => a.estado === filtro.value)
})

watch(prodSel, (p) => {
  if (p) precioDiaSel.value = p.palq || 0
})

function abrirModal() {
  cart.value          = []
  form.value          = { ...formVacio }
  prodSel.value       = ''
  cantSel.value       = 1
  precioDiaSel.value  = 0
  mapaAbierto.value   = false
  busquedaCiudad.value = ''
  sugerenciasCiudad.value = []
  modalAbierto.value  = true
}

function cerrarModalAlquiler() {
  modalAbierto.value = false
  mapaAbierto.value  = false
  destroyMapaPicker()
}

// ─── Mapa picker (dirección de entrega) ─────────────────────────────────────
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

  const lat  = form.value.lat || 3.5394
  const lng  = form.value.lng || -76.3026
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

  if (form.value.lat) {
    marcadorActual = L.marker([form.value.lat, form.value.lng]).addTo(mapaPicker)
  }

  mapaPicker.on('click', async (e) => {
    const { lat, lng } = e.latlng

    if (marcadorActual) mapaPicker.removeLayer(marcadorActual)
    marcadorActual = L.marker([lat, lng]).addTo(mapaPicker)
      .bindPopup('📍 Punto de entrega').openPopup()

    form.value.lat = lat
    form.value.lng = lng

    try {
      const res  = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=es`, {
        headers: { 'User-Agent': 'FARIO-App/1.0' }
      })
      const data = await res.json()
      if (data && data.address) {
        const a = data.address
        form.value.ciudad = a.city || a.town || a.village || a.municipality || a.county || ''
        if (!form.value.barrio) form.value.barrio = a.neighbourhood || a.suburb || ''
        if (!form.value.direccion) {
          form.value.direccion = a.road ? (a.road + (a.house_number ? ' #' + a.house_number : '')) : ''
        }
      }
    } catch(e) {
      // Si falla la geocodificación inversa, las coordenadas ya quedaron guardadas igual
    }
  })
}

function destroyMapaPicker() {
  if (mapaPicker) { mapaPicker.remove(); mapaPicker = null }
  marcadorActual = null
}

function quitarUbicacion() {
  form.value.lat = null
  form.value.lng = null
  if (marcadorActual && mapaPicker) {
    mapaPicker.removeLayer(marcadorActual)
    marcadorActual = null
  }
}

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

function verDetalle(a) {
  alqDetalle.value     = a
  detalleAbierto.value = true
}

function descargarPDF(a) {
  generarPDFAlquiler(a, config.value)
}

async function devolver(id) {
  if (!confirm('¿Confirmar devolución de herramientas?')) return
  await api.devolverAlquiler(id)
  await cargar()
}

function abrirProrroga(a) {
  alqProrroga.value  = a
  nuevaFecha.value   = a.fin || ''
  prorrogaModal.value = true
}

async function confirmarProrroga() {
  if (!nuevaFecha.value) return alert('Selecciona una fecha')
  try {
    await api.prorrogarAlquiler(alqProrroga.value.id_alquiler, { nuevaFecha: nuevaFecha.value })
    prorrogaModal.value = false
    await cargar()
  } catch (e) {
    alert('Error al prorrogar el alquiler')
  }
}

function editarEstado(a) {
  alqEstado.value   = a
  nuevoEstado.value = a.estado
  estadoModal.value = true
}

async function confirmarEstado() {
  try {
    await api.cambiarEstadoAlquiler(alqEstado.value.id_alquiler, { estado: nuevoEstado.value })
    estadoModal.value = false
    await cargar()
  } catch (e) {
    alert(e.response?.data?.msg || 'Error al cambiar estado')
  }
}

function agregarItem() {
  if (!prodSel.value) return alert('Selecciona una herramienta')
  const cant = cantSel.value || 1
  const libres = disponibleReal(prodSel.value)
  if (cant > libres) return alert(`Solo quedan ${libres} unidad(es) disponibles de «${prodSel.value.nombre}»`)
  const pd   = precioDiaSel.value || prodSel.value.palq || 0
  cart.value.push({ prodId: prodSel.value.id, nombre: prodSel.value.nombre, cant, pd })
  prodSel.value      = ''
  cantSel.value      = 1
  precioDiaSel.value = 0
}

async function guardar() {
  if (!form.value.cliente) return alert('Ingresa el nombre del cliente')
  if (!form.value.ini || !form.value.fin) return alert('Selecciona las fechas de inicio y fin')
  if (!cart.value.length) return alert('Agrega al menos una herramienta')
  guardando.value = true
  try {
    await api.crearAlquiler({
      cliente:   form.value.cliente,
      cedula:    form.value.cedula,
      tel:       form.value.tel,
      ini:       form.value.ini,
      fin:       form.value.fin,
      notas:     form.value.notas,
      direccion: form.value.direccion,
      barrio:    form.value.barrio,
      ciudad:    form.value.ciudad,
      lat:       form.value.lat,
      lng:       form.value.lng,
      total:     totalCart.value,
      usuarioId: auth.user?.id_usuario ?? 1,
      items:     cart.value.map(i => ({ prodId: i.prodId, cant: i.cant, pd: i.pd })),
    })
    cerrarModalAlquiler()
    await cargar()
  } catch (e) {
    alert(e.response?.data?.msg || 'Error al guardar el alquiler')
  } finally {
    guardando.value = false
  }
}

async function cargar() {
  const [a, p, cfg] = await Promise.all([
    api.getAlquileres(),
    api.getProductos(),
    api.getConfig(),
  ])
  alquileres.value = a.data.data || []
  productos.value  = p.data.data || []
  config.value     = cfg.data.data || {}
}

onMounted(cargar)
</script>

<style scoped>
.mapa-picker :deep(.leaflet-tile-pane) {
  filter: invert(92%) hue-rotate(180deg) brightness(0.95) contrast(0.9);
}
</style>
