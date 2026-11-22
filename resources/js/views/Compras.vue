<template>
  <div>
    <div class="view-toolbar">
      <button class="btn-primary" @click="abrirModal()">+ Nueva compra</button>
      <input class="search-input" v-model="busqueda" placeholder="Buscar compra…" />
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Fecha</th><th>Proveedor</th><th>Total</th><th>Observaciones</th><th>Acciones</th></tr>
        </thead>
        <tbody>
          <tr v-if="!filtradas.length">
            <td colspan="6" style="text-align:center;color:var(--text3);padding:2rem">Sin compras</td>
          </tr>
          <tr v-for="c in filtradas" :key="c.id_compra">
            <td class="mono">#{{ c.id_compra }}</td>
            <td>{{ fmtD(c.fecha) }}</td>
            <td>{{ c.proveedor || '—' }}</td>
            <td class="mono">{{ fmt(c.total) }}</td>
            <td>{{ c.observaciones || '—' }}</td>
            <td class="actions">
              <button class="btn-icon view" @click="verDetalle(c)" title="Ver detalle">🧾</button>
              <button class="btn-icon" @click="generarPDFCompra(c, config)" title="Descargar PDF" style="color:var(--red)">📄</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal nueva compra -->
    <div class="modal-overlay" :class="{ open: modalAbierto }" @click.self="modalAbierto = false">
      <div class="modal-box">
        <button class="modal-close" @click="modalAbierto = false">✕</button>
        <h3 class="modal-title">Nueva Compra</h3>
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label>Proveedor</label>
              <select v-model="form.id_proveedor">
                <option value="">— Seleccionar —</option>
                <option v-for="p in proveedores" :key="p.id_proveedor" :value="p.id_proveedor">
                  {{ p.nombre }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Observaciones</label>
              <input v-model="form.observaciones" placeholder="Opcional" />
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
              <label>Precio compra</label>
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
                  <td class="mono">{{ it.cantidad }}</td>
                  <td class="mono">{{ fmt(it.precio) }}</td>
                  <td class="mono">{{ fmt(it.sub) }}</td>
                  <td><button class="btn-icon del" @click="cart.splice(idx,1)">✕</button></td>
                </tr>
              </tbody>
            </table>
            <div style="text-align:right;font-weight:800;color:var(--accent);margin-top:.5rem">
              Total: {{ fmt(totalCart) }}
            </div>
          </div>
          <p v-else class="muted" style="text-align:center;padding:.5rem">Sin ítems</p>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="modalAbierto = false">Cancelar</button>
          <button class="btn-primary" @click="guardar" :disabled="guardando">
            {{ guardando ? 'Guardando...' : 'Registrar compra' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal detalle -->
    <div class="modal-overlay" :class="{ open: detalleAbierto }" @click.self="detalleAbierto = false">
      <div class="modal-box" v-if="compraDetalle">
        <button class="modal-close" @click="detalleAbierto = false">✕</button>
        <h3 class="modal-title">Compra #{{ compraDetalle.id_compra }}</h3>
        <div class="modal-body">
          <p style="margin-bottom:.75rem;color:var(--text2)">
            Proveedor: <strong>{{ compraDetalle.proveedor }}</strong> |
            Fecha: <strong>{{ fmtD(compraDetalle.fecha) }}</strong>
          </p>
          <table class="data-table">
            <thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Sub.</th></tr></thead>
            <tbody>
              <tr v-for="(it, idx) in compraDetalle.items" :key="idx">
                <td>{{ it.nombre }}</td>
                <td class="mono">{{ it.cantidad }}</td>
                <td class="mono">{{ fmt(it.precio) }}</td>
                <td class="mono">{{ fmt(it.sub) }}</td>
              </tr>
            </tbody>
          </table>
          <div style="text-align:right;font-size:1.1rem;font-weight:800;color:var(--accent);margin-top:.5rem">
            Total: {{ fmt(compraDetalle.total) }}
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="detalleAbierto = false">Cerrar</button>
          <button class="btn-primary" @click="generarPDFCompra(compraDetalle, config)">📄 Descargar PDF</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { api } from '../services/api'
import { generarPDFCompra } from '../services/pdf'

const auth           = useAuthStore()
const compras        = ref([])
const proveedores    = ref([])
const productos      = ref([])
const config         = ref({})
const busqueda       = ref('')
const modalAbierto   = ref(false)
const detalleAbierto = ref(false)
const compraDetalle  = ref(null)
const guardando      = ref(false)
const cart           = ref([])
const prodSel        = ref('')
const cantSel        = ref(1)
const precioSel      = ref(0)
const form           = ref({ id_proveedor:'', observaciones:'' })

const fmt  = n => new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0}).format(n||0)
const fmtD = d => d ? new Date(d).toLocaleDateString('es-CO') : '—'
const totalCart = computed(() => cart.value.reduce((s,i) => s + i.sub, 0))

const filtradas = computed(() => {
  const s = busqueda.value.toLowerCase()
  return (compras.value || []).filter(c =>
    String(c.id_compra).includes(s) ||
    (c.proveedor || '').toLowerCase().includes(s)
  )
})

async function cargar() {
  const [c, p, pr, cfg] = await Promise.all([
    api.getCompras(),
    api.getProductos(),
    api.getProveedores(),
    api.getConfig(),
  ])
  compras.value     = c.data.data || []
  productos.value   = p.data.data || []
  proveedores.value = pr.data.data || []
  config.value      = cfg.data.data || {}
}

function abrirModal() {
  cart.value      = []
  form.value      = { id_proveedor:'', observaciones:'' }
  prodSel.value   = ''
  cantSel.value   = 1
  precioSel.value = 0
  modalAbierto.value = true
}

function verDetalle(c) {
  compraDetalle.value  = c
  detalleAbierto.value = true
}

function agregarItem() {
  if (!prodSel.value) return alert('Selecciona un producto')
  const cantidad = cantSel.value || 1
  const precio   = precioSel.value || prodSel.value.pcompra || 0
  cart.value.push({
    id:       prodSel.value.id,
    nombre:   prodSel.value.nombre,
    cantidad,
    precio,
    sub: cantidad * precio,
  })
  prodSel.value   = ''
  cantSel.value   = 1
  precioSel.value = 0
}

async function guardar() {
  if (!cart.value.length) return alert('Agrega al menos un producto')
  guardando.value = true
  try {
    await api.crearCompra({
      id_proveedor:  form.value.id_proveedor,
      observaciones: form.value.observaciones,
      items:         cart.value,
      usuarioId:     auth.user.id_usuario,
    })
    modalAbierto.value = false
    await cargar()
  } catch(e) {
    alert(e.response?.data?.msg || 'Error al guardar')
  } finally {
    guardando.value = false
  }
}

onMounted(cargar)
</script>