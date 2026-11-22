<template>
  <div>
    <div class="view-toolbar">
      <button class="btn-primary" @click="abrirModal()">+ Nuevo producto</button>
      <input class="search-input" v-model="busqueda" placeholder="Buscar producto…" />
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Ref.</th><th>Producto</th><th>Categoría</th>
            <th>P1</th><th>P2</th><th>P3</th>
            <th>Stock</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!productosFiltrados.length">
            <td colspan="8" style="text-align:center;color:var(--text3);padding:2rem">
              Sin productos
            </td>
          </tr>
          <tr v-for="p in productosFiltrados" :key="p.id">
            <td class="mono">{{ p.ref }}</td>
            <td><strong>{{ p.nombre }}</strong></td>
            <td>{{ p.cat }}</td>
            <td class="mono">{{ fmt(p.p1) }}</td>
            <td class="mono">{{ fmt(p.p2) }}</td>
            <td class="mono">{{ fmt(p.p3) }}</td>
            <td>
              <span class="stock-chip" :class="chipColor(p)">{{ p.stock }}</span>
            </td>
            <td class="actions">
              <button class="btn-icon" @click="verMovimientos(p)" title="Historial de movimientos" style="color:#3b82f6">📋</button>
              <button class="btn-icon edit" @click="abrirModal(p)">✏️</button>
              <button class="btn-icon del"  @click="eliminar(p.id)">🗑</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal historial de movimientos -->
    <div class="modal-overlay" :class="{ open: historialAbierto }" @click.self="historialAbierto = false">
      <div class="modal-box" style="max-width:620px">
        <button class="modal-close" @click="historialAbierto = false">✕</button>
        <h3 class="modal-title">📋 Movimientos — {{ productoHistorial?.nombre }}</h3>
        <div class="modal-body">
          <div v-if="cargandoHistorial" style="text-align:center;padding:2rem;color:var(--text3)">Cargando…</div>
          <div v-else-if="!movimientos.length" style="text-align:center;padding:2rem;color:var(--text3)">Sin movimientos registrados</div>
          <div v-else class="table-wrap" style="max-height:420px;overflow-y:auto">
            <table class="data-table">
              <thead>
                <tr><th>Fecha</th><th>Tipo</th><th>Referencia</th><th>Cant.</th></tr>
              </thead>
              <tbody>
                <tr v-for="(m, i) in movimientos" :key="i">
                  <td style="font-size:.78rem;white-space:nowrap">{{ fmtD(m.fecha) }}</td>
                  <td>
                    <span class="badge" :class="m.signo === '+' ? 'badge-green' : 'badge-red'">
                      {{ m.signo === '+' ? '▲ Entrada' : '▼ Salida' }}
                    </span>
                  </td>
                  <td style="font-size:.82rem">{{ m.referencia }}</td>
                  <td class="mono" :style="{ color: m.signo === '+' ? 'var(--green)' : 'var(--red)' }">
                    {{ m.signo }}{{ m.cantidad }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="historialAbierto = false">Cerrar</button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div class="modal-overlay" :class="{ open: modalAbierto }" @click.self="modalAbierto = false">
      <div class="modal-box">
        <button class="modal-close" @click="modalAbierto = false">✕</button>
        <h3 class="modal-title">{{ editId ? 'Editar' : 'Nuevo' }} Producto</h3>
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label>Referencia</label>
              <input v-model="form.ref" placeholder="REF-001" />
            </div>
            <div class="form-group">
              <label>Nombre *</label>
              <input v-model="form.nombre" />
            </div>
          </div>
          <div class="form-group">
            <label>Descripción</label>
            <input v-model="form.desc" />
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Categoría</label>
              <input v-model="form.cat" placeholder="Ej: Herramientas Manuales" />
            </div>
            <div class="form-group">
              <label>Precio compra</label>
              <input v-model.number="form.pcompra" type="number" min="0" />
            </div>
          </div>
          <div class="form-row cols3">
            <div class="form-group">
              <label>Precio P1</label>
              <input v-model.number="form.p1" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Precio P2</label>
              <input v-model.number="form.p2" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Precio P3</label>
              <input v-model.number="form.p3" type="number" min="0" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Precio alquiler/día</label>
              <input v-model.number="form.palq" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Stock actual</label>
              <input v-model.number="form.stock" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Stock mínimo</label>
              <input v-model.number="form.smin" type="number" min="0" />
            </div>
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
import { api } from '../services/api'

const historialAbierto  = ref(false)
const productoHistorial = ref(null)
const movimientos       = ref([])
const cargandoHistorial = ref(false)

const fmtD = d => d ? new Date(d).toLocaleDateString('es-CO', { day:'2-digit', month:'2-digit', year:'2-digit' }) : '—'

async function verMovimientos(p) {
  productoHistorial.value = p
  historialAbierto.value  = true
  cargandoHistorial.value = true
  movimientos.value = []
  try {
    const res = await api.getMovimientosProducto(p.id)
    movimientos.value = res.data.data || []
  } catch(e) {
    console.error(e)
  } finally {
    cargandoHistorial.value = false
  }
}

const productos    = ref([])
const busqueda     = ref('')
const modalAbierto = ref(false)
const guardando    = ref(false)
const editId       = ref(null)

const formVacio = {
  ref:'', nombre:'', desc:'', cat:'',
  pcompra:0, p1:0, p2:0, p3:0, palq:0, stock:0, smin:0
}
const form = ref({ ...formVacio })

const fmt = n => new Intl.NumberFormat('es-CO', {
  style:'currency', currency:'COP', maximumFractionDigits:0
}).format(n || 0)

const chipColor = p =>
  p.stock <= 0 ? 'stock-out' : p.stock <= p.smin ? 'stock-low' : 'stock-ok'

const productosFiltrados = computed(() => {
  const s = busqueda.value.toLowerCase()
  return productos.value.filter(p =>
    (p.nombre||'').toLowerCase().includes(s) ||
    (p.ref || '').toLowerCase().includes(s)
  )
})

async function cargar() {
  try {
    const res = await api.getProductos()
    productos.value = res.data.data
  } catch (e) {
    alert(e.response?.data?.msg || 'No se pudieron cargar los productos')
  }
}

function abrirModal(p = null) {
  editId.value = p ? p.id : null
  form.value   = p ? { ...p } : { ...formVacio }
  modalAbierto.value = true
}

async function guardar() {
  if (!form.value.nombre) return alert('El nombre es obligatorio')
  guardando.value = true
  try {
    if (editId.value) {
      await api.actualizarProducto(editId.value, form.value)
    } else {
      await api.crearProducto(form.value)
    }
    modalAbierto.value = false
    await cargar()
  } catch(e) {
    alert(e.response?.data?.msg || 'Error al guardar')
  } finally {
    guardando.value = false
  }
}

async function eliminar(id) {
  if (!confirm('¿Eliminar producto?')) return
  await api.eliminarProducto(id)
  await cargar()
}

onMounted(cargar)
</script>