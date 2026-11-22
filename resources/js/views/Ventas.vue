<template>
  <div class="ventas-root">

    <!-- Tabs -->
    <div class="filter-tabs" style="margin-bottom:.9rem">
      <button :class="['tab-btn', tab==='pos'?'active':'']"       @click="tab='pos'">🧾 Nueva venta</button>
      <button :class="['tab-btn', tab==='historial'?'active':'']" @click="tab='historial'">📋 Historial</button>
    </div>

    <!-- ══════════ POS ══════════ -->
    <div v-if="tab==='pos'" class="pos-layout">

      <!-- LEFT: productos -->
      <div class="pos-left">

        <!-- Cliente -->
        <div class="pos-cliente-card">
          <div class="pos-cli-avatar">{{ (clienteSel?.nombre||'C')[0].toUpperCase() }}</div>
          <div class="pos-cli-info">
            <span class="pos-cli-nombre">{{ clienteSel?.nombre || 'Consumidor Final' }}</span>
            <span class="pos-cli-sub">
              {{ clienteSel?.documento ? clienteSel.documento + ' · ' : '' }}{{ clienteSel?.tipo || 'Público general' }}
            </span>
          </div>
          <button class="btn-sm" @click="modalCliente=true">Cambiar</button>
        </div>

        <!-- Buscador -->
        <div class="pos-search-wrap">
          <span class="pos-search-icon">🔍</span>
          <input class="pos-search" v-model="busqProd"
            placeholder="Buscar producto por nombre, código o categoría" />
        </div>

        <!-- Grid de productos -->
        <div class="pos-prod-grid">
          <div v-if="!productosFiltrados.length"
            style="grid-column:1/-1;text-align:center;color:var(--text3);padding:2rem">
            Sin productos disponibles
          </div>
          <div
            v-for="p in productosFiltrados" :key="p.id"
            class="pos-prod-card"
            @click="agregarAlCarrito(p)"
          >
            <div class="pos-prod-nombre">{{ p.nombre }}</div>
            <div class="pos-prod-cat">{{ p.cat || 'General' }}</div>
            <div class="pos-prod-precio">{{ fmt(p[tablaPrec] || p.p1) }}</div>
            <div class="pos-prod-stock">Stock: {{ p.stock }} und</div>
          </div>
        </div>
      </div>

      <!-- RIGHT: carrito -->
      <div class="pos-right">

        <div class="pos-cart-header">
          <span class="pos-cart-title">CARRITO</span>
          <span class="pos-cart-badge">{{ cart.length }} {{ cart.length===1?'producto':'productos' }}</span>
        </div>

        <!-- Tabla de precio -->
        <div class="pos-tabla-row">
          <select v-model="tablaPrec" class="select-filter" style="font-size:.78rem;padding:.35rem .6rem;width:100%">
            <option value="p1">P1 — Precio público</option>
            <option value="p2">P2 — Mayorista</option>
            <option value="p3">P3 — Especial</option>
          </select>
        </div>

        <!-- Items -->
        <div class="pos-cart-items">
          <div v-if="!cart.length" class="pos-cart-empty">
            Carrito vacío — haz clic en un producto
          </div>
          <div v-for="(it, idx) in cart" :key="idx" class="pos-cart-item">
            <div class="pos-cart-item-top">
              <span class="pos-cart-item-nombre">{{ it.nombre }}</span>
              <span class="pos-cart-item-sub">{{ fmt(it.sub) }}</span>
            </div>
            <div class="pos-cart-item-bot">
              <span class="pos-cart-item-uprice">{{ fmt(it.precio) }} × und</span>
              <div class="pos-qty-ctrl">
                <button @click="cambiarCant(idx,-1)">−</button>
                <span>{{ it.cant }}</span>
                <button @click="cambiarCant(idx,+1)">+</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Resumen -->
        <div class="pos-cart-summary">
          <div class="pos-sum-row">
            <span>Subtotal</span>
            <span>{{ fmt(subtotal) }}</span>
          </div>
          <div class="pos-sum-row">
            <span>Descuento</span>
            <div class="pos-desc-inp">
              <span>−&nbsp;$</span>
              <input v-model.number="descuento" type="number" min="0" placeholder="0" />
            </div>
          </div>
          <div class="pos-sum-row">
            <span>IVA (19%)</span>
            <span>{{ fmt(iva) }}</span>
          </div>
          <div class="pos-sum-total">
            <span>Total</span>
            <span>{{ fmt(total) }}</span>
          </div>
        </div>

        <!-- Footer -->
        <div class="pos-footer">
          <select v-model="form.pago" class="pos-pago-sel">
            <option>Efectivo</option>
            <option>Transferencia</option>
            <option>Nequi</option>
            <option>Daviplata</option>
          </select>
          <button
            class="btn-primary" style="flex:1"
            @click="guardar"
            :disabled="guardando||!cart.length"
          >
            {{ guardando ? 'Guardando…' : '✓ Registrar venta' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ══════════ HISTORIAL ══════════ -->
    <div v-if="tab==='historial'">
      <div class="view-toolbar">
        <input class="search-input" v-model="busqueda" placeholder="Buscar venta…" />
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Factura</th><th>Fecha</th><th>Cliente</th>
              <th>Total</th><th>Pago</th><th>Estado</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!ventasFiltradas.length">
              <td colspan="7" style="text-align:center;color:var(--text3);padding:2rem">Sin ventas</td>
            </tr>
            <tr v-for="v in ventasFiltradas" :key="v.id_venta">
              <td class="mono">{{ v.numero_factura || ('#'+v.id_venta) }}</td>
              <td>{{ fmtD(v.fecha) }}</td>
              <td>{{ v.cliNom || 'Consumidor Final' }}</td>
              <td class="mono">{{ fmt(v.total) }}</td>
              <td>{{ v.pago }}</td>
              <td>
                <span class="badge" :class="v.estado==='anulada'?'badge-red':'badge-green'">
                  {{ v.estado || 'activa' }}
                </span>
              </td>
              <td class="actions">
                <button class="btn-icon" @click="verDetalle(v)" title="Ver detalle">🧾</button>
                <button class="btn-icon" @click="generarPDFVenta(v, config)" title="PDF" style="color:var(--red)">📄</button>
                <button class="btn-icon del" v-if="v.estado!=='anulada'" @click="anular(v.id_venta)" title="Anular">✕</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ MODAL: Seleccionar / crear cliente ══ -->
    <div class="modal-overlay" :class="{open:modalCliente}" @click.self="cerrarModalCliente">
      <div class="modal-box" style="max-width:500px">
        <button class="modal-close" @click="cerrarModalCliente">✕</button>
        <h3 class="modal-title">Cliente</h3>
        <div class="modal-body">

          <!-- Formulario nuevo cliente -->
          <div class="nuevo-cli-toggle" @click="mostrarFormNuevo=!mostrarFormNuevo">
            <span>{{ mostrarFormNuevo ? '▾' : '▸' }} Agregar nuevo cliente</span>
          </div>

          <div v-if="mostrarFormNuevo" class="nuevo-cli-form">
            <div class="form-row">
              <div class="form-group">
                <label>Nombre *</label>
                <input v-model="nuevoCliente.nombre" placeholder="Nombre completo o empresa" />
              </div>
              <div class="form-group">
                <label>Tipo</label>
                <select v-model="nuevoCliente.tipo">
                  <option>Final</option>
                  <option>Empresa</option>
                  <option>Mayorista</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Documento / NIT</label>
                <input v-model="nuevoCliente.cedula" placeholder="Cédula o NIT" />
              </div>
              <div class="form-group">
                <label>Teléfono</label>
                <input v-model="nuevoCliente.telefono" placeholder="Teléfono" />
              </div>
            </div>

            <!-- Dirección + geocodificación -->
            <div class="form-group" style="grid-column:1/-1">
              <label>Dirección</label>
              <div style="display:flex;gap:.4rem">
                <input
                  v-model="nuevoCliente.direccion"
                  placeholder="Ej: Calle 30 #25-10, Palmira"
                  @keyup.enter="geocodificarDireccion"
                  style="flex:1"
                />
                <button type="button" class="btn-ghost"
                  @click="geocodificarDireccion"
                  :disabled="geocodificando"
                  style="flex-shrink:0;padding:.45rem .75rem;font-size:.82rem">
                  {{ geocodificando ? '…' : '📍 Ubicar' }}
                </button>
              </div>
              <div v-if="geoError" style="color:var(--red);font-size:.75rem;margin-top:.2rem">{{ geoError }}</div>
              <div v-if="geoResultados.length" class="geo-list">
                <div v-for="r in geoResultados" :key="r.place_id" class="geo-item" @click="selGeoResultado(r)">
                  📍 {{ r.display_name }}
                </div>
              </div>
              <div v-if="nuevoCliente.lat" style="font-size:.73rem;color:var(--green);margin-top:.25rem">
                ✓ Ubicado: {{ nuevoCliente.lat.toFixed(5) }}, {{ nuevoCliente.lng.toFixed(5) }}
              </div>
              <button type="button" class="btn-ghost"
                @click="mapaNuevoCliente = !mapaNuevoCliente"
                style="margin-top:.4rem;width:100%;padding:.4rem;font-size:.8rem">
                {{ mapaNuevoCliente ? '🗺️ Cerrar mapa' : '🗺️ Marcar ubicación en mapa' }}
              </button>
              <MapaPicker v-if="mapaNuevoCliente" style="margin-top:.4rem"
                v-model:lat="nuevoCliente.lat"
                v-model:lng="nuevoCliente.lng"
                @ubicado="onUbicadoEnMapa" />
            </div>

            <div class="form-group">
              <label>Ciudad</label>
              <input v-model="nuevoCliente.ciudad" placeholder="Ciudad" />
            </div>

            <button
              class="btn-primary" style="width:100%"
              @click="crearYSeleccionar"
              :disabled="guardandoCliente||!nuevoCliente.nombre.trim()"
            >
              {{ guardandoCliente ? 'Guardando…' : '+ Crear y seleccionar' }}
            </button>
          </div>

          <!-- Separador -->
          <div class="cli-separator">o selecciona uno existente</div>

          <!-- Búsqueda -->
          <input class="search-input" v-model="busqCliente"
            placeholder="Buscar cliente…"
            style="width:100%;margin-bottom:.65rem" />

          <!-- Lista -->
          <div class="list-mini" style="max-height:280px;overflow-y:auto">
            <div class="list-mini-item" @click="selCliente(null)" style="cursor:pointer">
              <span class="lm-name">Consumidor Final</span>
              <span class="lm-tag">Público general</span>
            </div>
            <div
              v-for="c in clientesFiltrados" :key="c.id_cliente"
              class="list-mini-item" style="cursor:pointer"
              @click="selCliente(c)"
            >
              <span class="lm-name">{{ c.nombre }}</span>
              <span class="lm-tag" :class="c.tipo==='Empresa'?'orange':''">{{ c.tipo||'Final' }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ MODAL: Detalle venta ══ -->
    <div class="modal-overlay" :class="{open:detalleAbierto}" @click.self="detalleAbierto=false">
      <div class="modal-box" v-if="ventaDetalle">
        <button class="modal-close" @click="detalleAbierto=false">✕</button>
        <h3 class="modal-title">{{ ventaDetalle.numero_factura || ('Venta #'+ventaDetalle.id_venta) }}</h3>
        <div class="modal-body">
          <p style="margin-bottom:.75rem;color:var(--text2)">
            Cliente: <strong>{{ ventaDetalle.cliNom||'Consumidor Final' }}</strong> |
            Pago: <strong>{{ ventaDetalle.pago }}</strong>
          </p>
          <table class="data-table">
            <thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Sub.</th></tr></thead>
            <tbody>
              <tr v-for="(it,idx) in ventaDetalle.items" :key="idx">
                <td>{{ it.nombre }}</td>
                <td class="mono">{{ it.cant }}</td>
                <td class="mono">{{ fmt(it.precio) }}</td>
                <td class="mono">{{ fmt(it.sub) }}</td>
              </tr>
            </tbody>
          </table>
          <div style="text-align:right;font-size:1.1rem;font-weight:800;color:var(--accent);margin-top:.5rem">
            Total: {{ fmt(ventaDetalle.total) }}
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="detalleAbierto=false">Cerrar</button>
          <button class="btn-primary" @click="generarPDFVenta(ventaDetalle, config)">📄 Descargar PDF</button>
        </div>
      </div>
    </div>

    <!-- ══ MODAL: Alertas stock ══ -->
    <div class="modal-overlay" :class="{open:alertasStock.length>0}" @click.self="alertasStock=[]">
      <div class="modal-box" style="max-width:460px">
        <button class="modal-close" @click="alertasStock=[]">✕</button>
        <h3 class="modal-title" style="color:#f59e0b">⚠ Alerta de stock</h3>
        <div class="modal-body">
          <p style="margin-bottom:.75rem;color:var(--text2)">Los siguientes productos tienen stock bajo:</p>
          <div v-for="(a, idx) in alertasStock" :key="idx" class="list-mini-item" :style="{
            background: a.agotado?'rgba(239,68,68,.1)':'rgba(249,115,22,.1)',
            borderLeft: '3px solid '+(a.agotado?'var(--red)':'var(--orange)')
          }">
            <span class="lm-name" :style="{color:a.agotado?'#fca5a5':'#fdba74'}">
              {{ a.agotado?'📦 AGOTADO':'⚠ Stock bajo' }} — {{ a.nombre }}
            </span>
            <span style="font-size:.8rem;color:var(--text2)">{{ a.stock }} / mín {{ a.minimo }}</span>
          </div>
        </div>
        <div class="modal-footer">
          <router-link to="/inventario" class="btn-primary" @click="alertasStock=[]">Ver inventario</router-link>
          <button class="btn-ghost" @click="alertasStock=[]">Cerrar</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { api } from '../services/api'
import { generarPDFVenta } from '../services/pdf'
import MapaPicker from '../components/MapaPicker.vue'

const auth      = useAuthStore()
const ventas    = ref([])
const clientes  = ref([])
const productos = ref([])
const config    = ref({})

const tab          = ref('pos')
const busqueda     = ref('')
const busqProd     = ref('')
const busqCliente  = ref('')
const tablaPrec    = ref('p1')
const descuento    = ref(0)
const guardando    = ref(false)
const modalCliente    = ref(false)
const mostrarFormNuevo = ref(false)
const guardandoCliente = ref(false)
const detalleAbierto  = ref(false)
const ventaDetalle    = ref(null)
const alertasStock    = ref([])
const clienteSel      = ref(null)
const cart            = ref([])
const form            = ref({ pago: 'Efectivo' })

const nuevoClienteVacio = () => ({ nombre: '', tipo: 'Final', cedula: '', telefono: '', direccion: '', ciudad: '', lat: null, lng: null })
const nuevoCliente    = ref(nuevoClienteVacio())
const geocodificando  = ref(false)
const geoResultados   = ref([])
const geoError        = ref('')
const mapaNuevoCliente = ref(false)

function onUbicadoEnMapa({ ciudad, direccion }) {
  if (ciudad) nuevoCliente.value.ciudad = ciudad
  if (!nuevoCliente.value.direccion && direccion) nuevoCliente.value.direccion = direccion
}

async function geocodificarDireccion() {
  if (!nuevoCliente.value.direccion.trim()) return
  geocodificando.value = true
  geoError.value       = ''
  geoResultados.value  = []
  try {
    const q   = encodeURIComponent(nuevoCliente.value.direccion + ', Valle del Cauca, Colombia')
    const res = await fetch(`https://nominatim.openstreetmap.org/search?q=${q}&format=json&limit=4&countrycodes=co&addressdetails=1&viewbox=-77.5,2.8,-75.5,5.0&bounded=1`)
    const data = await res.json()
    geoResultados.value = data
    if (!data.length) geoError.value = 'No se encontró la dirección'
  } catch {
    geoError.value = 'Error al buscar dirección'
  } finally {
    geocodificando.value = false
  }
}

function selGeoResultado(r) {
  nuevoCliente.value.lat  = parseFloat(r.lat)
  nuevoCliente.value.lng  = parseFloat(r.lon)
  const addr = r.address || {}
  nuevoCliente.value.ciudad = addr.city || addr.town || addr.municipality || addr.county || nuevoCliente.value.ciudad
  geoResultados.value = []
}

const fmt  = n => new Intl.NumberFormat('es-CO', { style:'currency', currency:'COP', maximumFractionDigits:0 }).format(n||0)
const fmtD = d => d ? new Date(d).toLocaleString('es-CO', { day:'2-digit', month:'2-digit', year:'2-digit', hour:'2-digit', minute:'2-digit' }) : '—'

const subtotal = computed(() => cart.value.reduce((s,i) => s+i.sub, 0))
const iva      = computed(() => Math.round((subtotal.value - (descuento.value||0)) * 0.19))
const total    = computed(() => subtotal.value - (descuento.value||0) + iva.value)

const productosFiltrados = computed(() => {
  const s = busqProd.value.toLowerCase()
  return productos.value.filter(p =>
    !s ||
    (p.nombre||'').toLowerCase().includes(s) ||
    (p.cat||'').toLowerCase().includes(s) ||
    (p.ref||'').toLowerCase().includes(s)
  )
})

const ventasFiltradas = computed(() => {
  const s = busqueda.value.toLowerCase()
  return (ventas.value||[]).filter(v =>
    String(v.id_venta).includes(s) ||
    (v.cliNom||'').toLowerCase().includes(s)
  )
})

const clientesFiltrados = computed(() => {
  const s = busqCliente.value.toLowerCase()
  return clientes.value.filter(c =>
    !s || c.nombre.toLowerCase().includes(s) || (c.documento||'').includes(s)
  )
})

function cerrarModalCliente() {
  modalCliente.value     = false
  mostrarFormNuevo.value = false
  busqCliente.value      = ''
  nuevoCliente.value     = nuevoClienteVacio()
  mapaNuevoCliente.value = false
}

function selCliente(c) {
  clienteSel.value = c
  cerrarModalCliente()
}

async function crearYSeleccionar() {
  if (!nuevoCliente.value.nombre.trim()) return
  guardandoCliente.value = true
  try {
    const res = await api.crearCliente(nuevoCliente.value)
    if (!res.data.ok) {
      alert(res.data.msg || 'Error al crear cliente')
      return
    }
    const nombreGuardado = nuevoCliente.value.nombre.trim()
    await cargar()
    const id    = Number(res.data.data?.id)
    const nuevo = clientes.value.find(c => Number(c.id_cliente) === id) ||
                  clientes.value.find(c => c.nombre?.trim() === nombreGuardado)
    if (nuevo) {
      selCliente(nuevo)
    } else {
      // El cliente se guardó pero no se encontró en la lista — seleccionar manualmente
      selCliente({ id_cliente: id, nombre: nombreGuardado, tipo: nuevoCliente.value.tipo || 'Final', documento: nuevoCliente.value.cedula || '' })
    }
  } catch(e) {
    alert(e.response?.data?.msg || e.message || 'Error al guardar el cliente')
  } finally {
    guardandoCliente.value = false
  }
}

function agregarAlCarrito(p) {
  const precio = p[tablaPrec.value] || p.p1 || 0
  const idx = cart.value.findIndex(i => i.id === p.id)
  if (idx >= 0) {
    cart.value[idx].cant++
    cart.value[idx].sub = cart.value[idx].cant * cart.value[idx].precio
  } else {
    cart.value.push({ id: p.id, nombre: p.nombre, cant: 1, precio, sub: precio })
  }
}

function cambiarCant(idx, delta) {
  const it    = cart.value[idx]
  const nueva = it.cant + delta
  if (nueva <= 0) { cart.value.splice(idx, 1); return }
  it.cant = nueva
  it.sub  = nueva * it.precio
}

function verDetalle(v) {
  ventaDetalle.value   = v
  detalleAbierto.value = true
}

async function guardar() {
  if (!cart.value.length) return
  guardando.value = true
  try {
    const res = await api.crearVenta({
      cliId:     clienteSel.value?.id_cliente || 1,
      pago:      form.value.pago,
      items:     cart.value,
      usuarioId: auth.user.id_usuario,
    })
    cart.value       = []
    descuento.value  = 0
    clienteSel.value = null
    tab.value = 'historial'
    await cargar()
    if (res.data.data?.alertas?.length) alertasStock.value = res.data.data.alertas
  } catch(e) {
    alert(e.response?.data?.msg || 'Error al guardar')
  } finally {
    guardando.value = false
  }
}

async function anular(id) {
  if (!confirm(`¿Anular venta #${id}?`)) return
  await api.anularVenta(id)
  await cargar()
}

async function cargar() {
  const [v, c, p, cfg] = await Promise.all([
    api.getVentas(), api.getClientes(), api.getProductos(), api.getConfig(),
  ])
  ventas.value    = v.data.data || []
  clientes.value  = c.data.data || []
  productos.value = (p.data.data || []).filter(x => x.stock > 0)
  config.value    = cfg.data.data || {}
}

onMounted(cargar)
</script>

<style scoped>
.ventas-root { display:flex; flex-direction:column; }

/* ── POS layout ── */
.pos-layout {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 1rem;
  height: calc(100vh - var(--topbar-h) - 3rem - 50px);
  min-height: 540px;
}

/* LEFT */
.pos-left {
  display: flex; flex-direction: column; gap: .65rem;
  min-height: 0; overflow: hidden;
}

/* Tarjeta cliente */
.pos-cliente-card {
  display: flex; align-items: center; gap: .75rem;
  padding: .8rem 1rem;
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: var(--radius); box-shadow: var(--shadow);
  flex-shrink: 0;
}
.pos-cli-avatar {
  width: 38px; height: 38px; border-radius: 50%;
  background: var(--accent); opacity: .9;
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: .95rem; color: #fff; flex-shrink: 0;
}
.pos-cli-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .06rem; }
.pos-cli-nombre {
  font-weight: 700; font-size: .92rem; color: var(--text);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pos-cli-sub { font-size: .73rem; color: var(--text3); }

/* Buscador */
.pos-search-wrap {
  display: flex; align-items: center; gap: .6rem;
  padding: .6rem 1rem;
  background: var(--bg2); border: 1px solid var(--border2);
  border-radius: var(--radius); box-shadow: var(--shadow);
  flex-shrink: 0;
}
.pos-search-icon { color: var(--accent); font-size: 1rem; flex-shrink: 0; }
.pos-search {
  flex: 1; border: none; outline: none;
  font-size: .88rem; color: var(--text); background: transparent;
}
.pos-search::placeholder { color: var(--text3); }

/* Grid productos */
.pos-prod-grid {
  flex: 1; overflow-y: auto; min-height: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: .6rem; padding: .05rem;
}
.pos-prod-card {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: var(--radius); padding: .9rem;
  cursor: pointer; transition: all var(--trans);
  display: flex; flex-direction: column; gap: .2rem;
  box-shadow: var(--shadow);
}
.pos-prod-card:hover {
  border-color: var(--accent);
  transform: translateY(-2px);
  box-shadow: var(--shadow2);
  background: var(--bg3);
}
.pos-prod-nombre { font-weight: 700; font-size: .84rem; color: var(--text); line-height: 1.3; }
.pos-prod-cat    { font-size: .71rem; color: var(--text3); margin-bottom: .1rem; }
.pos-prod-precio { font-size: 1.05rem; font-weight: 800; color: var(--accent); font-family: 'JetBrains Mono', monospace; }
.pos-prod-stock  { font-size: .72rem; color: var(--text3); }

/* RIGHT: carrito */
.pos-right {
  display: flex; flex-direction: column;
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: var(--radius); box-shadow: var(--shadow);
  overflow: hidden; min-height: 0;
}

.pos-cart-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: .75rem 1rem; border-bottom: 1px solid var(--border);
  background: var(--bg3); flex-shrink: 0;
}
.pos-cart-title { font-size: .68rem; font-weight: 700; color: var(--text2); text-transform: uppercase; letter-spacing: .12em; }
.pos-cart-badge {
  font-size: .72rem; font-weight: 700;
  background: var(--text); color: var(--bg2);
  padding: 2px 10px; border-radius: 20px;
}

.pos-tabla-row {
  padding: .5rem .85rem; border-bottom: 1px solid var(--border); flex-shrink: 0;
}

.pos-cart-items {
  flex: 1; overflow-y: auto; min-height: 0;
  padding: .6rem .85rem; display: flex; flex-direction: column; gap: .5rem;
}
.pos-cart-empty { color: var(--text3); font-size: .84rem; text-align: center; padding: 2.5rem .5rem; }

.pos-cart-item {
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  padding: .6rem .75rem; display: flex; flex-direction: column; gap: .3rem;
  background: var(--bg3);
}
.pos-cart-item-top { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; }
.pos-cart-item-nombre { font-weight: 700; font-size: .84rem; color: var(--text); flex: 1; }
.pos-cart-item-sub    { font-weight: 800; font-size: .9rem; color: var(--text); white-space: nowrap; font-family: 'JetBrains Mono', monospace; }
.pos-cart-item-bot    { display: flex; justify-content: space-between; align-items: center; }
.pos-cart-item-uprice { font-size: .73rem; color: var(--text3); }

.pos-qty-ctrl {
  display: flex; align-items: center;
  border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden;
}
.pos-qty-ctrl button {
  width: 28px; height: 28px; background: var(--bg2);
  border: none; font-size: 1.05rem; font-weight: 700;
  color: var(--text2); cursor: pointer; transition: background var(--trans);
  display: flex; align-items: center; justify-content: center;
}
.pos-qty-ctrl button:hover { background: var(--accent); color: #fff; }
.pos-qty-ctrl span {
  font-size: .88rem; font-weight: 700;
  min-width: 28px; text-align: center; color: var(--text);
}

/* Resumen */
.pos-cart-summary {
  border-top: 1px solid var(--border);
  padding: .8rem 1rem;
  display: flex; flex-direction: column; gap: .45rem;
  flex-shrink: 0; background: var(--bg2);
}
.pos-sum-row {
  display: flex; justify-content: space-between; align-items: center;
  font-size: .85rem; color: var(--text2);
}
.pos-sum-total {
  display: flex; justify-content: space-between; align-items: center;
  font-size: 1.15rem; font-weight: 800; color: var(--text);
  padding-top: .5rem; border-top: 2px solid var(--border);
  font-family: 'JetBrains Mono', monospace;
}
.pos-desc-inp {
  display: flex; align-items: center; gap: .2rem;
  border: 1px solid var(--border); border-radius: 4px;
  padding: 3px 7px; font-size: .8rem; color: var(--text3);
  background: var(--bg3);
}
.pos-desc-inp input {
  width: 72px; border: none; outline: none;
  font-size: .82rem; color: var(--text); background: transparent; text-align: right;
}

/* Footer */
.pos-footer {
  border-top: 1px solid var(--border);
  padding: .75rem .85rem;
  display: flex; gap: .5rem; align-items: center;
  flex-shrink: 0; background: var(--bg3);
}
.pos-pago-sel {
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  padding: .5rem .65rem; font-size: .82rem; color: var(--text);
  background: var(--bg2); cursor: pointer; flex-shrink: 0;
}

/* Geocodificación */
.geo-list {
  margin-top: .3rem; border: 1px solid var(--border);
  border-radius: var(--radius-sm); overflow: hidden;
}
.geo-item {
  padding: .5rem .75rem; font-size: .78rem; color: var(--text);
  cursor: pointer; border-bottom: 1px solid var(--border);
  transition: background var(--trans); line-height: 1.4;
}
.geo-item:last-child { border-bottom: none; }
.geo-item:hover { background: var(--bg3); color: var(--accent); }

/* Modal: nuevo cliente */
.nuevo-cli-toggle {
  font-size: .82rem; font-weight: 700; color: var(--accent);
  cursor: pointer; padding: .4rem 0; margin-bottom: .5rem;
  user-select: none;
}
.nuevo-cli-toggle:hover { opacity: .8; }
.nuevo-cli-form {
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius-sm); padding: .85rem;
  margin-bottom: .75rem; display: flex; flex-direction: column; gap: .5rem;
}
.cli-separator {
  text-align: center; font-size: .73rem; color: var(--text3);
  margin: .6rem 0; position: relative;
}
.cli-separator::before, .cli-separator::after {
  content: ''; position: absolute; top: 50%;
  width: 35%; height: 1px; background: var(--border);
}
.cli-separator::before { left: 0; }
.cli-separator::after  { right: 0; }

@media (max-width: 900px) {
  .pos-layout { grid-template-columns: 1fr; height: auto; }
  .pos-right   { min-height: 420px; }
  .pos-prod-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
}
</style>
