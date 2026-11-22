<template>
  <div>
    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem">
      <button class="btn-primary" @click="descargarPDF">📄 Descargar Arqueo PDF</button>
    </div>

    <div class="caja-grid">
      <div class="card caja-estado">
        <h4>Estado de caja hoy</h4>
        <div class="caja-kpi">
          <span class="caja-kpi-label">Estado</span>
          <span class="badge" :class="descuadre.estado === 'abierta' ? 'badge-green' : 'badge-gray'">
            {{ descuadre.estado || 'Sin apertura' }}
          </span>
        </div>
        <div class="caja-kpi"><span class="caja-kpi-label">Apertura</span><span class="caja-kpi-val">{{ fmt(descuadre.montoApertura) }}</span></div>
        <div class="caja-kpi"><span class="caja-kpi-label">Ingresos efectivo</span><span class="caja-kpi-val">{{ fmt(descuadre.ingresosEfectivo) }}</span></div>
        <div class="caja-kpi"><span class="caja-kpi-label">Transferencias</span><span class="caja-kpi-val">{{ fmt(descuadre.transferencias) }}</span></div>
        <div class="caja-kpi"><span class="caja-kpi-label">Egresos</span><span class="caja-kpi-val" style="color:var(--red)">{{ fmt(descuadre.egresos) }}</span></div>
        <div class="caja-kpi"><span class="caja-kpi-label">Saldo teórico</span><span class="caja-kpi-val" style="font-size:1.2rem">{{ fmt(descuadre.saldoTeorico) }}</span></div>
      </div>

      <div class="card">
        <div class="card-header"><h4>Apertura / Cierre</h4></div>
        <div class="caja-form">
          <div class="form-group">
            <label>Operación</label>
            <select v-model="opTipo">
              <option value="apertura">Apertura</option>
              <option value="cierre">Cierre</option>
            </select>
          </div>
          <div class="form-group"><label>Monto ($)</label><input v-model.number="opMonto" type="number" min="0" /></div>
          <div class="form-group"><label>Observación</label><input v-model="opObs" /></div>
          <button class="btn-primary w-full" @click="operar">Registrar</button>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h4>Movimiento manual</h4></div>
        <div class="caja-form">
          <div class="form-group">
            <label>Tipo</label>
            <select v-model="movTipo">
              <option value="ingreso">Ingreso</option>
              <option value="egreso">Egreso</option>
            </select>
          </div>
          <div class="form-group"><label>Concepto</label><input v-model="movConcepto" /></div>
          <div class="form-group"><label>Monto ($)</label><input v-model.number="movMonto" type="number" min="0" /></div>
          <div class="form-group">
            <label>Método</label>
            <select v-model="movMetodo">
              <option>Efectivo</option>
              <option>Transferencia</option>
              <option>Nequi</option>
              <option>Daviplata</option>
            </select>
          </div>
          <button class="btn-primary w-full" @click="registrarMov">Registrar</button>
        </div>
      </div>
    </div>

    <div class="card mt-2">
      <div class="card-header"><h4>Últimos movimientos</h4></div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Tipo</th><th>Concepto</th><th>Monto</th><th>Método</th><th>Fecha</th></tr>
          </thead>
          <tbody>
            <tr v-if="!movimientos.length">
              <td colspan="5" style="text-align:center;color:var(--text3);padding:1.5rem">Sin movimientos</td>
            </tr>
            <tr v-for="m in movimientos" :key="m.id_movimiento">
              <td><span class="badge" :class="m.tipo === 'ingreso' ? 'badge-green' : 'badge-red'">{{ m.tipo }}</span></td>
              <td>{{ m.concepto || '—' }}</td>
              <td class="mono" :style="{ color: m.tipo === 'ingreso' ? 'var(--green)' : 'var(--red)' }">
                {{ m.tipo === 'ingreso' ? '+' : '-' }}{{ fmt(m.monto) }}
              </td>
              <td>{{ m.metodo_pago || '—' }}</td>
              <td>{{ fmtD(m.fecha) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { api } from '../services/api'
import { generarPDFCaja } from '../services/pdf'

const auth        = useAuthStore()
const descuadre   = ref({ estado:'', montoApertura:0, ingresosEfectivo:0, transferencias:0, egresos:0, saldoTeorico:0 })
const movimientos = ref([])
const config      = ref({})
const opTipo      = ref('apertura')
const opMonto     = ref(0)
const opObs       = ref('')
const movTipo     = ref('ingreso')
const movConcepto = ref('')
const movMonto    = ref(0)
const movMetodo   = ref('Efectivo')

const fmt  = n => new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0}).format(n||0)
const fmtD = d => d ? new Date(d).toLocaleString('es-CO') : '—'

async function cargar() {
  try {
    const [d, m, cfg] = await Promise.all([
      api.getDescuadre(),
      api.getMovimientos(),
      api.getConfig(),
    ])
    descuadre.value   = d.data.data || {}
    movimientos.value = (m.data.data || []).slice(0, 50)
    config.value      = cfg.data.data || {}
  } catch (e) {
    alert(e.response?.data?.msg || 'No se pudo cargar la información de caja')
  }
}

async function operar() {
  try {
    await api.operarCaja({ tipo: opTipo.value, monto: opMonto.value, obs: opObs.value, usuarioId: auth.user.id_usuario })
    opMonto.value = 0
    opObs.value   = ''
    await cargar()
  } catch (e) {
    alert(e.response?.data?.msg || 'No se pudo registrar la operación de caja')
  }
}

async function registrarMov() {
  if (!movConcepto.value) return alert('Ingresa el concepto')
  if (!movMonto.value)    return alert('Ingresa el monto')
  try {
    await api.crearMovimiento({ tipo: movTipo.value, concepto: movConcepto.value, monto: movMonto.value, metodoPago: movMetodo.value })
    movConcepto.value = ''
    movMonto.value    = 0
    await cargar()
  } catch (e) {
    alert(e.response?.data?.msg || 'No se pudo registrar el movimiento')
  }
}

function descargarPDF() {
  generarPDFCaja(descuadre.value, movimientos.value, config.value)
}

onMounted(cargar)
</script>