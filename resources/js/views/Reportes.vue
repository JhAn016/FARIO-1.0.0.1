<template>
  <div>
    <!-- Selector año y mes -->
    <div class="card" style="margin-bottom:1rem">
      <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <div class="form-group-inline">
          <label>Año</label>
          <select v-model="anio" @change="cargar" style="background:var(--bg3);border:1px solid var(--border2);border-radius:6px;color:var(--text);padding:.5rem .75rem">
            <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
        <div class="form-group-inline">
          <label>Mes detalle</label>
          <select v-model="mesSeleccionado" @change="cargar" style="background:var(--bg3);border:1px solid var(--border2);border-radius:6px;color:var(--text);padding:.5rem .75rem">
            <option v-for="(m, idx) in nombresMeses" :key="idx" :value="idx+1">{{ m }}</option>
          </select>
        </div>
        <div style="margin-left:auto;display:flex;gap:.5rem">
          <button class="btn-ghost" @click="anioAnterior">← Año anterior</button>
          <button class="btn-primary" @click="anioCorriente">Año actual</button>
          <button class="btn-ghost" @click="descargarPDF" v-if="datos.detalle?.length">📄 PDF</button>
        </div>
      </div>
    </div>

    <!-- KPIs del año -->
    <div class="kpi-grid" style="margin-bottom:1rem">
      <div class="kpi-card" style="--accent:#f59e0b">
        <div class="kpi-icon">💰</div>
        <div class="kpi-body">
          <span class="kpi-label">Total año {{ anio }}</span>
          <span class="kpi-value">{{ fmt(totalAnio) }}</span>
          <span class="kpi-sub">{{ totalTransacciones }} transacciones</span>
        </div>
      </div>
      <div class="kpi-card" style="--accent:#10b981">
        <div class="kpi-icon">📅</div>
        <div class="kpi-body">
          <span class="kpi-label">Mejor mes</span>
          <span class="kpi-value">{{ mejorMes.nombre }}</span>
          <span class="kpi-sub">{{ fmt(mejorMes.total) }}</span>
        </div>
      </div>
      <div class="kpi-card" style="--accent:#3b82f6">
        <div class="kpi-icon">📊</div>
        <div class="kpi-body">
          <span class="kpi-label">Promedio mensual</span>
          <span class="kpi-value">{{ fmt(promedioMensual) }}</span>
          <span class="kpi-sub">meses con ventas</span>
        </div>
      </div>
      <div class="kpi-card" style="--accent:#8b5cf6">
        <div class="kpi-icon">🎯</div>
        <div class="kpi-body">
          <span class="kpi-label">{{ nombresMeses[mesSeleccionado-1] }}</span>
          <span class="kpi-value">{{ fmt(mesActualTotal) }}</span>
          <span class="kpi-sub">{{ anio }}</span>
        </div>
      </div>
    </div>

    <!-- Gráfica línea mes a mes -->
    <div class="card" style="margin-bottom:1rem">
      <div class="card-header">
        <h4>📈 Ventas mes a mes — {{ anio }}</h4>
        <span style="font-size:.8rem;color:var(--text2)">Clic en un punto para ver el detalle</span>
      </div>
      <div style="position:relative;height:300px">
        <canvas ref="chartLinea"></canvas>
      </div>
    </div>

    <!-- Barras + Top productos -->
    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:1rem;margin-bottom:1rem">
      <div class="card">
        <div class="card-header"><h4>📊 Comparativa mensual</h4></div>
        <div style="position:relative;height:250px">
          <canvas ref="chartBarras"></canvas>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <h4>🏆 Top 5 productos</h4>
          <span style="font-size:.75rem;color:var(--text2)">{{ nombresMeses[mesSeleccionado-1] }}</span>
        </div>
        <div class="list-mini" style="margin-top:.5rem">
          <div v-if="!datos.topMes?.length" class="muted" style="padding:.5rem;text-align:center">Sin ventas este mes</div>
          <div v-for="(p, idx) in datos.topMes" :key="idx" class="list-mini-item">
            <span class="lm-name">
              <span style="color:var(--accent);font-weight:800;margin-right:.4rem">{{ idx+1 }}.</span>
              {{ p.nombre }}
            </span>
            <span style="display:flex;gap:.5rem;align-items:center">
              <span style="font-size:.72rem;color:var(--text3)">{{ p.total_cant }} und.</span>
              <span class="lm-val">{{ fmt(p.total_val) }}</span>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Detalle ventas del mes -->
    <div class="card">
      <div class="card-header">
        <h4>🧾 Ventas de {{ nombresMeses[mesSeleccionado-1] }} {{ anio }}</h4>
        <span class="badge badge-blue">{{ datos.detalle?.length || 0 }} ventas</span>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>#</th><th>Fecha</th><th>Cliente</th><th>Método</th><th>Total</th></tr>
          </thead>
          <tbody>
            <tr v-if="!datos.detalle?.length">
              <td colspan="5" style="text-align:center;color:var(--text3);padding:2rem">Sin ventas en este mes</td>
            </tr>
            <tr v-for="v in datos.detalle" :key="v.id_venta">
              <td class="mono">#{{ v.id_venta }}</td>
              <td>{{ fmtD(v.fecha) }}</td>
              <td>{{ v.cliente || 'Consumidor Final' }}</td>
              <td>{{ v.metodo_pago || '—' }}</td>
              <td class="mono">{{ fmt(v.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { api } from '../services/api'
import { generarPDFReporte } from '../services/pdf'
import {
  Chart, BarElement, LineElement, PointElement,
  CategoryScale, LinearScale, BarController, LineController,
  Tooltip, Legend, Filler
} from 'chart.js'

Chart.register(
  BarElement, LineElement, PointElement,
  CategoryScale, LinearScale,
  BarController, LineController,
  Tooltip, Legend, Filler
)

const datos           = ref({ meses:[], detalle:[], topMes:[] })
const anio            = ref(new Date().getFullYear())
const mesSeleccionado = ref(new Date().getMonth() + 1)
const chartLinea      = ref(null)
const chartBarras     = ref(null)
let   chartInstances  = {}

const nombresMeses = [
  'Enero','Febrero','Marzo','Abril','Mayo','Junio',
  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'
]

const anios = computed(() => {
  const actual = new Date().getFullYear()
  return [actual-2, actual-1, actual, actual+1].filter(a => a >= 2020)
})

const fmt  = n => new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0}).format(n||0)
const fmtD = d => d ? new Date(d).toLocaleDateString('es-CO',{day:'2-digit',month:'2-digit',year:'numeric'}) : '—'

const totalAnio = computed(() =>
  (datos.value.meses||[]).reduce((s,m) => s + m.total, 0)
)
const totalTransacciones = computed(() =>
  (datos.value.meses||[]).reduce((s,m) => s + m.cantidad, 0)
)
const mesActualTotal = computed(() => {
  const m = (datos.value.meses||[]).find(m => m.mes === mesSeleccionado.value)
  return m ? m.total : 0
})
const mejorMes = computed(() => {
  const meses = datos.value.meses || []
  if (!meses.length) return { nombre:'—', total:0 }
  return meses.reduce((a,b) => b.total > a.total ? b : a)
})
const promedioMensual = computed(() => {
  const meses = (datos.value.meses||[]).filter(m => m.total > 0)
  if (!meses.length) return 0
  return totalAnio.value / meses.length
})

async function cargar() {
  try {
    const res = await api.getVentasPorMes(anio.value, mesSeleccionado.value)
    datos.value = res.data.data || {}
    await nextTick()
    setTimeout(() => buildCharts(), 300)
  } catch(e) {
    console.error(e)
  }
}

function anioAnterior() { anio.value--; cargar() }
function anioCorriente() { anio.value = new Date().getFullYear(); cargar() }

function descargarPDF() {
  const periodo = `${nombresMeses[mesSeleccionado.value-1]}-${anio.value}`
  generarPDFReporte(
    { total: mesActualTotal.value, cantidad: datos.value.detalle?.length || 0, top: datos.value.topMes || [], ventas: datos.value.detalle || [] },
    periodo,
    {}
  )
}

function buildCharts() {
  Object.values(chartInstances).forEach(c => { try { c?.destroy() } catch {} })
  chartInstances = {}

  const meses   = datos.value.meses || []
  const labels  = meses.map(m => m.nombre.substring(0,3))
  const totales = meses.map(m => m.total)
  const maxVal  = Math.max(...totales)

  // Gráfica de línea
  if (chartLinea.value) {
    chartInstances.linea = new Chart(chartLinea.value, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: `Ventas ${anio.value}`,
          data: totales,
          borderColor: '#f59e0b',
          backgroundColor: 'rgba(245,158,11,.08)',
          pointBackgroundColor: totales.map((v,i) =>
            i+1 === mesSeleccionado.value ? '#ef4444'
            : v === maxVal && maxVal > 0 ? '#10b981'
            : '#f59e0b'
          ),
          pointBorderColor: '#fff',
          pointRadius: totales.map((v,i) => i+1 === mesSeleccionado.value ? 8 : 5),
          pointHoverRadius: 8,
          borderWidth: 3,
          fill: true,
          tension: 0.4,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        onClick: (e, elements) => {
          if (elements.length > 0) {
            mesSeleccionado.value = elements[0].index + 1
            cargar()
          }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => `Ventas: ${fmt(ctx.raw)}`,
              afterLabel: ctx => `Transacciones: ${meses[ctx.dataIndex]?.cantidad || 0}`,
            }
          }
        },
        scales: {
          y: { ticks: { color:'#8892a4', callback: v => fmt(v) }, grid: { color:'#272b38' } },
          x: { ticks: { color:'#8892a4' }, grid: { display:false } }
        }
      }
    })
  }

  // Gráfica de barras
  if (chartBarras.value) {
    chartInstances.barras = new Chart(chartBarras.value, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Total ventas',
          data: totales,
          backgroundColor: totales.map((v,i) =>
            i+1 === mesSeleccionado.value ? '#ef4444'
            : v === maxVal && maxVal > 0 ? '#10b981'
            : 'rgba(59,130,246,.7)'
          ),
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        onClick: (e, elements) => {
          if (elements.length > 0) {
            mesSeleccionado.value = elements[0].index + 1
            cargar()
          }
        },
        plugins: {
          legend: { display:false },
          tooltip: { callbacks: { label: ctx => fmt(ctx.raw) } }
        },
        scales: {
          y: { ticks: { color:'#8892a4', callback: v => fmt(v) }, grid: { color:'#272b38' } },
          x: { ticks: { color:'#8892a4' }, grid: { display:false } }
        }
      }
    })
  }
}

onMounted(cargar)
</script>