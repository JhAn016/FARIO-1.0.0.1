<template>
  <div>
    <div class="view-toolbar">
      <button class="btn-primary" @click="abrirModal()">+ Nueva devolución</button>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Venta</th><th>Fecha</th><th>Motivo</th><th>Usuario</th><th>Total</th></tr>
        </thead>
        <tbody>
          <tr v-if="!devoluciones.length">
            <td colspan="6" style="text-align:center;color:var(--text3);padding:2rem">Sin devoluciones</td>
          </tr>
          <tr v-for="d in devoluciones" :key="d.id">
            <td class="mono">#{{ d.id }}</td>
            <td class="mono">#{{ d.id_venta }}</td>
            <td>{{ fmtD(d.fecha) }}</td>
            <td>{{ d.motivo || '—' }}</td>
            <td>{{ d.usuario || '—' }}</td>
            <td class="mono">{{ fmt(d.total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal nueva devolución -->
    <div class="modal-overlay" :class="{ open: modalAbierto }" @click.self="modalAbierto = false">
      <div class="modal-box">
        <button class="modal-close" @click="modalAbierto = false">✕</button>
        <h3 class="modal-title">Nueva Devolución</h3>
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label>Número de venta</label>
              <select v-model="ventaSel" @change="cargarItemsVenta">
                <option value="">— Seleccionar venta —</option>
                <option v-for="v in ventas" :key="v.id_venta" :value="v">
                  #{{ v.id_venta }} — {{ v.cliNom || 'Consumidor Final' }} — {{ fmt(v.total) }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Motivo</label>
              <input v-model="form.motivo" placeholder="Motivo de la devolución" />
            </div>
          </div>

          <div v-if="itemsVenta.length" style="margin-top:.75rem">
            <p style="font-size:.82rem;color:var(--text2);margin-bottom:.5rem">
              Selecciona los productos a devolver:
            </p>
            <table class="data-table">
              <thead><tr><th>Producto</th><th>Cant. vendida</th><th>Cant. a devolver</th><th>Precio</th></tr></thead>
              <tbody>
                <tr v-for="(it, idx) in itemsVenta" :key="idx">
                  <td>{{ it.nombre }}</td>
                  <td class="mono">{{ it.cant }}</td>
                  <td>
                    <input
                      type="number" min="0" :max="it.cant"
                      v-model.number="it.cantDev"
                      style="width:70px;background:var(--bg3);border:1px solid var(--border2);border-radius:4px;color:var(--text);padding:4px 8px"
                    />
                  </td>
                  <td class="mono">{{ fmt(it.precio) }}</td>
                </tr>
              </tbody>
            </table>
            <div style="text-align:right;font-weight:800;color:var(--accent);margin-top:.5rem">
              Total a devolver: {{ fmt(totalDevolucion) }}
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-ghost" @click="modalAbierto = false">Cancelar</button>
          <button class="btn-primary" @click="guardar" :disabled="guardando">
            {{ guardando ? 'Guardando...' : 'Registrar devolución' }}
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

const auth         = useAuthStore()
const devoluciones = ref([])
const ventas       = ref([])
const ventaSel     = ref('')
const itemsVenta   = ref([])
const modalAbierto = ref(false)
const guardando    = ref(false)
const form         = ref({ motivo:'' })

const fmt  = n => new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0}).format(n||0)
const fmtD = d => d ? new Date(d).toLocaleDateString('es-CO') : '—'

const totalDevolucion = computed(() =>
  itemsVenta.value.reduce((s,i) => s + (i.cantDev || 0) * i.precio, 0)
)

function cargarItemsVenta() {
  if (!ventaSel.value) { itemsVenta.value = []; return }
  itemsVenta.value = (ventaSel.value.items || []).map(i => ({ ...i, cantDev: 0 }))
}

async function cargar() {
  const [d, v] = await Promise.all([api.getDevoluciones(), api.getVentas()])
  devoluciones.value = d.data.data || []
  ventas.value       = (v.data.data || []).filter(v => v.estado !== 'anulada')
}

function abrirModal() {
  ventaSel.value  = ''
  itemsVenta.value = []
  form.value = { motivo:'' }
  modalAbierto.value = true
}

async function guardar() {
  const itemsADevolver = itemsVenta.value.filter(i => i.cantDev > 0)
  if (!itemsADevolver.length) return alert('Selecciona al menos un producto a devolver')
  if (!form.value.motivo) return alert('Ingresa el motivo de la devolución')

  guardando.value = true
  try {
    await api.crearDevolucion({
      ventaId: ventaSel.value.id_venta,
      motivo:  form.value.motivo,
      usuario: auth.user.nombre,
      total:   totalDevolucion.value,
      items:   itemsADevolver.map(i => ({
        id:       i.id,
        nombre:   i.nombre,
        cantidad: i.cantDev,
        precio:   i.precio,
      })),
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