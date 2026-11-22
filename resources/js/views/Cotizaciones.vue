<template>
  <div>
    <div class="view-toolbar">
      <button class="btn-primary" @click="abrirModal()">+ Nueva cotización</button>
      <input class="search-input" v-model="busqueda" placeholder="Buscar cotización…" />
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Fecha</th><th>Cliente</th><th>Teléfono</th><th>Total</th><th>Notas</th><th style="width:180px">Acciones</th></tr>
        </thead>
        <tbody>
          <tr v-if="!filtradas.length">
            <td colspan="7" style="text-align:center;color:var(--text3);padding:2rem">Sin cotizaciones</td>
          </tr>
          <tr v-for="c in filtradas" :key="c.id">
            <td class="mono">#{{ c.id }}</td>
            <td>{{ fmtD(c.fecha) }}</td>
            <td>{{ c.cliente || '—' }}</td>
            <td>{{ c.telefono || '—' }}</td>
            <td class="mono">{{ fmt(c.total) }}</td>
            <td>{{ c.notas || '—' }}</td>
            <td class="actions">
              <button class="btn-icon edit" @click="abrirModal(c)" title="Editar">✏️</button>
              <button class="btn-icon" @click="abrirConvertir(c)" title="Convertir en venta" style="color:#10b981">💸</button>
              <button class="btn-icon" @click="generarPDFCotizacion(c, config)" title="Descargar PDF" style="color:var(--red)">📄</button>
              <button class="btn-icon del" @click="eliminar(c.id)" title="Eliminar">🗑</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal convertir cotización a venta -->
    <div class="modal-overlay" :class="{ open: modalConvertir }" @click.self="modalConvertir = false">
      <div class="modal-box" style="max-width:400px" v-if="cotConvertir">
        <button class="modal-close" @click="modalConvertir = false">✕</button>
        <h3 class="modal-title">Convertir a venta</h3>
        <div class="modal-body">
          <p style="margin-bottom:1rem;color:var(--text2)">
            Cotización <strong>#{{ cotConvertir.id }}</strong> de <strong>{{ cotConvertir.cliente || 'Sin nombre' }}</strong>
            por <strong>{{ fmt(cotConvertir.total) }}</strong>
          </p>
          <div class="form-group">
            <label>Método de pago</label>
            <select v-model="pagoConvertir">
              <option>Efectivo</option>
              <option>Transferencia</option>
              <option>Nequi</option>
              <option>Daviplata</option>
            </select>
          </div>
          <p style="font-size:.78rem;color:var(--text3);margin-top:.5rem">
            Se creará una venta y se descountará el stock. La cotización será eliminada.
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="modalConvertir = false">Cancelar</button>
          <button class="btn-primary" @click="confirmarConvertir" :disabled="convirtiendo">
            {{ convirtiendo ? 'Convirtiendo...' : '✓ Confirmar venta' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal nueva cotización -->
    <div class="modal-overlay" :class="{ open: modalAbierto }" @click.self="modalAbierto = false">
      <div class="modal-box">
        <button class="modal-close" @click="modalAbierto = false">✕</button>
        <h3 class="modal-title">{{ editId ? `Editar Cotización #${editId}` : 'Nueva Cotización' }}</h3>
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label>Cliente</label>
              <input v-model="form.cliente" placeholder="Nombre del cliente" />
            </div>
            <div class="form-group">
              <label>Teléfono</label>
              <input v-model="form.tel" placeholder="300..." />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Producto</label>
              <select v-model="prodSel">
                <option value="">— Seleccionar —</option>
                <option v-for="p in productos" :key="p.id" :value="p">
                  {{ p.nombre }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Cantidad</label>
              <input v-model.number="cantSel" type="number" min="1" />
            </div>
            <div class="form-group">
              <label>Precio</label>
              <input v-model.number="precioSel" type="number" min="0" />
            </div>
          </div>

          <button class="btn-primary" style="width:100%;margin-bottom:1rem" @click="agregarItem">
            + Agregar producto
          </button>

          <div v-if="cart.length">
            <table class="data-table">
              <thead>
                <tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Sub.</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="(it, idx) in cart" :key="idx">
                  <td>{{ it.nombre }}</td>
                  <td>
                    <input v-model.number="it.cant" type="number" min="1" @input="recalcular(it)"
                      style="width:4.5rem;padding:.2rem .35rem" />
                  </td>
                  <td class="mono">{{ fmt(it.precio) }}</td>
                  <td class="mono">{{ fmt(it.sub) }}</td>
                  <td><button class="btn-icon del" @click="cart.splice(idx, 1)">✕</button></td>
                </tr>
              </tbody>
            </table>
            <div style="text-align:right;font-weight:800;color:var(--accent);margin-top:.5rem">
              Total: {{ fmt(totalCart) }}
            </div>
          </div>
          <p v-else class="muted" style="text-align:center;padding:.5rem">Sin ítems</p>

          <div class="form-group" style="margin-top:.75rem">
            <label>Notas</label>
            <textarea v-model="form.notas" rows="2" placeholder="Observaciones..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="modalAbierto = false">Cancelar</button>
          <button class="btn-primary" @click="guardar" :disabled="guardando">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { api } from '../services/api'
import { generarPDFCotizacion } from '../services/pdf'

const auth = useAuthStore()

const cotizaciones = ref([])
const productos    = ref([])
const config       = ref({})
const modalAbierto = ref(false)
const guardando    = ref(false)
const busqueda     = ref('')
const cart         = ref([])
const prodSel      = ref('')
const cantSel      = ref(1)
const precioSel    = ref(0)
const form         = ref({ cliente:'', tel:'', notas:'' })
const editId       = ref(null)

const modalConvertir = ref(false)
const cotConvertir   = ref(null)
const pagoConvertir  = ref('Efectivo')
const convirtiendo   = ref(false)

const fmt  = n => new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0}).format(n||0)
const fmtD = d => d ? new Date(d).toLocaleDateString('es-CO') : '—'
const totalCart = computed(() => cart.value.reduce((s,i) => s + i.sub, 0))

const filtradas = computed(() => {
  const s = busqueda.value.toLowerCase()
  return (cotizaciones.value || []).filter(c =>
    (c.cliente || '').toLowerCase().includes(s) ||
    (c.telefono || '').includes(s) ||
    String(c.id).includes(s)
  )
})

async function cargar() {
  const [c, p, cfg] = await Promise.all([
    api.getCotizaciones(),
    api.getProductos(),
    api.getConfig(),
  ])
  cotizaciones.value = c.data.data || []
  productos.value    = p.data.data || []
  config.value       = cfg.data.data || {}
}

function abrirModal(cot = null) {
  editId.value = cot?.id ?? null
  if (cot) {
    let items = []
    try { items = typeof cot.items === 'string' ? JSON.parse(cot.items) : (cot.items || []) } catch {}
    cart.value = items.map(i => ({ ...i, sub: (i.cant || 0) * (i.precio || 0) }))
    form.value = { cliente: cot.cliente || '', tel: cot.telefono || '', notas: cot.notas || '' }
  } else {
    cart.value = []
    form.value = { cliente:'', tel:'', notas:'' }
  }
  prodSel.value   = ''
  cantSel.value   = 1
  precioSel.value = 0
  modalAbierto.value = true
}

function recalcular(it) {
  it.sub = (it.cant || 0) * (it.precio || 0)
}

function agregarItem() {
  if (!prodSel.value) return alert('Selecciona un producto')
  const cant   = cantSel.value || 1
  const precio = precioSel.value || prodSel.value.p1 || 0

  // Si el producto ya está con el mismo precio, sumar cantidad en vez de duplicar la fila
  const existente = cart.value.find(i => i.id === prodSel.value.id && i.precio === precio)
  if (existente) {
    existente.cant += cant
    recalcular(existente)
    prodSel.value = ''; cantSel.value = 1; precioSel.value = 0
    return
  }

  cart.value.push({
    id:     prodSel.value.id,
    nombre: prodSel.value.nombre,
    cant,
    precio,
    sub: cant * precio,
  })
  prodSel.value   = ''
  cantSel.value   = 1
  precioSel.value = 0
}

async function guardar() {
  if (!cart.value.length) return alert('Agrega al menos un producto')
  if (cart.value.some(i => !(i.cant > 0))) return alert('Todas las cantidades deben ser mayores a 0')
  guardando.value = true
  try {
    const datos = {
      cliente: form.value.cliente,
      tel:     form.value.tel,
      items:   cart.value,
      total:   totalCart.value,
      notas:   form.value.notas,
    }
    if (editId.value) await api.actualizarCotizacion(editId.value, datos)
    else              await api.crearCotizacion(datos)
    modalAbierto.value = false
    await cargar()
  } catch(e) {
    alert(e.response?.data?.msg || 'Error al guardar')
  } finally {
    guardando.value = false
  }
}

async function eliminar(id) {
  if (!confirm('¿Eliminar cotización?')) return
  await api.eliminarCotizacion(id)
  await cargar()
}

function abrirConvertir(c) {
  cotConvertir.value  = c
  pagoConvertir.value = 'Efectivo'
  modalConvertir.value = true
}

async function confirmarConvertir() {
  convirtiendo.value = true
  try {
    const res = await api.convertirCotizacion(cotConvertir.value.id, {
      pago:      pagoConvertir.value,
      usuarioId: auth.user?.id_usuario ?? 1,
    })
    alert(`✓ ${res.data.msg}`)
    modalConvertir.value = false
    await cargar()
  } catch(e) {
    alert(e.response?.data?.msg || 'Error al convertir')
  } finally {
    convirtiendo.value = false
  }
}

onMounted(cargar)
</script>