<template>
  <div>
    <div class="dashboard-greeting">
      <h3>{{ saludo }}, {{ auth.user?.nombre?.split(' ')[0] || 'usuario' }} 👋</h3>
      <p>Resumen ejecutivo — <strong>{{ config.nombre || 'FARIO' }}</strong> · {{ fechaHoy }}</p>
    </div>

    <!-- Selector de período -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">

        <select v-model="periodoVer" @change="onPeriodoChange"
          style="background:var(--bg2);color:var(--text1);border:1px solid var(--border);border-radius:6px;padding:.3rem .6rem;font-size:.85rem;cursor:pointer">
          <option value="mes">Por Mes</option>
          <option value="semestre">Por Semestre</option>
          <option value="anio">Por Año</option>
        </select>

        <select v-if="periodoVer === 'mes'" v-model.number="mesVer" @change="cargarDatos"
          style="background:var(--bg2);color:var(--text1);border:1px solid var(--border);border-radius:6px;padding:.3rem .6rem;font-size:.85rem;cursor:pointer">
          <option v-for="(nombre, idx) in MESES" :key="idx+1" :value="idx+1">{{ nombre }}</option>
        </select>

        <select v-if="periodoVer === 'semestre'" v-model.number="semestreVer" @change="cargarDatos"
          style="background:var(--bg2);color:var(--text1);border:1px solid var(--border);border-radius:6px;padding:.3rem .6rem;font-size:.85rem;cursor:pointer">
          <option :value="1">S1 · Ene – Jun</option>
          <option :value="2">S2 · Jul – Dic</option>
        </select>

        <select v-model.number="anioVer" @change="cargarDatos"
          style="background:var(--bg2);color:var(--text1);border:1px solid var(--border);border-radius:6px;padding:.3rem .6rem;font-size:.85rem;cursor:pointer">
          <option v-for="y in aniosDisponibles" :key="y" :value="y">{{ y }}</option>
        </select>

        <span style="font-size:.8rem;font-weight:600;color:var(--accent);background:rgba(245,158,11,.12);padding:.2rem .6rem;border-radius:12px">
          {{ etiquetaPeriodo }}
        </span>
        <span v-if="!esMesActual && periodoVer === 'mes'" style="font-size:.75rem;color:var(--accent)">📅 Histórico</span>
        <span v-if="cargando" style="font-size:.75rem;color:var(--text3)">Cargando…</span>
      </div>
      <button class="btn-ghost" @click="descargarPDF">📄 Descargar resumen PDF</button>
    </div>

    <!-- KPIs principales -->
    <div class="kpi-grid">
      <div class="kpi-card" style="--accent:#f59e0b">
        <div class="kpi-icon">💰</div>
        <div class="kpi-body">
          <span class="kpi-label">Ventas hoy</span>
          <span class="kpi-value">{{ fmt(datos.ventasHoy) }}</span>
          <span class="kpi-sub">{{ datos.ventasHoyN }} transacciones</span>
        </div>
      </div>
      <div class="kpi-card" style="--accent:#10b981">
        <div class="kpi-icon">📈</div>
        <div class="kpi-body">
          <span class="kpi-label">Ferretería · {{ etiquetaPeriodo }}</span>
          <span class="kpi-value">{{ fmt(datos.ventasMes) }}</span>
          <span class="kpi-sub" :style="{ color: crecimiento >= 0 ? 'var(--green)' : 'var(--red)' }">
            {{ crecimiento >= 0 ? '▲' : '▼' }} {{ Math.abs(crecimiento) }}% vs período anterior
          </span>
        </div>
      </div>
      <div class="kpi-card" style="--accent:#3b82f6">
        <div class="kpi-icon">🔧</div>
        <div class="kpi-body">
          <span class="kpi-label">Alquileres activos</span>
          <span class="kpi-value">{{ datos.alqActivos }}</span>
          <span class="kpi-sub" style="color:var(--red)" v-if="datos.alqEnMora > 0">
            ⚠ {{ datos.alqEnMora }} en mora
          </span>
          <span class="kpi-sub" style="color:var(--orange)" v-else-if="datos.alqPorVencer > 0">
            ⏰ {{ datos.alqPorVencer }} vencen hoy/mañana
          </span>
          <span class="kpi-sub" v-else>en curso</span>
        </div>
      </div>
      <div class="kpi-card" style="--accent:#ef4444">
        <div class="kpi-icon">⚠️</div>
        <div class="kpi-body">
          <span class="kpi-label">Stock bajo / agotado</span>
          <span class="kpi-value">{{ datos.stockBajo + datos.agotado }}</span>
          <span class="kpi-sub">{{ datos.agotado }} agotados · {{ datos.sinMovimiento }} sin movimiento</span>
        </div>
      </div>
    </div>

    <!-- Líneas de negocio -->
    <div class="card" style="margin-bottom:1rem">
      <div class="card-header">
        <h4>📊 Ventas por Línea de Negocio · {{ etiquetaPeriodo }}</h4>
        <span style="font-size:.82rem;color:var(--text2)">Total combinado: <strong>{{ fmt(datos.totalCombinado) }}</strong></span>
      </div>
      <div style="display:flex;flex-direction:column;gap:.85rem;padding:.25rem 0">
        <div>
          <div style="display:flex;justify-content:space-between;margin-bottom:.3rem;font-size:.85rem">
            <span>🔨 Ferretería (Ventas)</span>
            <span>{{ fmt(datos.ventasMes) }} &nbsp;·&nbsp; <strong style="color:var(--accent)">{{ datos.pctFerreteria }}%</strong></span>
          </div>
          <div style="background:var(--bg3);border-radius:8px;height:12px;overflow:hidden">
            <div :style="{ width: datos.pctFerreteria + '%', height:'100%', background:'#f59e0b', borderRadius:'8px', transition:'width 1s ease' }"></div>
          </div>
        </div>
        <div>
          <div style="display:flex;justify-content:space-between;margin-bottom:.3rem;font-size:.85rem">
            <span>🔧 Alquiler de Herramientas</span>
            <span>{{ fmt(datos.totalAlquileres) }} &nbsp;·&nbsp; <strong style="color:#3b82f6">{{ datos.pctAlquiler }}%</strong></span>
          </div>
          <div style="background:var(--bg3);border-radius:8px;height:12px;overflow:hidden">
            <div :style="{ width: datos.pctAlquiler + '%', height:'100%', background:'#3b82f6', borderRadius:'8px', transition:'width 1s ease' }"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Fila de gráficas -->
    <div class="dash-charts-row">
      <div class="card dash-chart-card">
        <div class="card-header">
          <h4>📊 {{ esMesActual ? 'Ventas últimos 7 días' : 'Ventas por día · ' + etiquetaPeriodo }}</h4>
        </div>
        <div style="position:relative;height:220px">
          <canvas ref="chartVentasDias"></canvas>
        </div>
      </div>
      <div class="card dash-chart-card">
        <div class="card-header">
          <h4>💳 Métodos de pago · {{ etiquetaPeriodo }}</h4>
        </div>
        <div style="position:relative;height:220px;display:flex;align-items:center;justify-content:center">
          <canvas ref="chartMetodos" style="max-width:220px;max-height:220px"></canvas>
        </div>
      </div>
      <div class="card dash-chart-card">
        <div class="card-header">
          <h4>📅 Ventas por día de semana</h4>
        </div>
        <div style="position:relative;height:220px">
          <canvas ref="chartDiaSemana"></canvas>
        </div>
      </div>
    </div>

    <!-- Meta mensual — solo en vista por mes -->
    <div class="card" v-if="periodoVer === 'mes'" style="margin-bottom:1rem">
      <div class="card-header">
        <h4>🎯 Meta mensual</h4>
        <span style="font-size:.82rem;color:var(--text2)">{{ fmt(datos.ventasMes) }} / {{ fmt(metaMensual) }}</span>
      </div>
      <div style="background:var(--bg3);border-radius:8px;height:12px;overflow:hidden">
        <div :style="{
          width: Math.min(progresoMeta, 100) + '%',
          height: '100%',
          background: progresoMeta >= 100 ? 'var(--green)' : progresoMeta >= 60 ? 'var(--accent)' : 'var(--red)',
          borderRadius: '8px',
          transition: 'width 1s ease'
        }"></div>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:.35rem;font-size:.75rem;color:var(--text3)">
        <span>0%</span>
        <span :style="{ color: progresoMeta >= 100 ? 'var(--green)' : 'var(--accent)' }">
          {{ progresoMeta }}% completado
        </span>
        <span>100%</span>
      </div>
    </div>

    <!-- Bottom grid -->
    <div class="dashboard-grid-bottom">

      <!-- Top productos -->
      <div class="card">
        <div class="card-header">
          <h4>🏆 Top productos · {{ etiquetaPeriodo }}</h4>
          <router-link to="/inventario" class="btn-sm">Ver inventario →</router-link>
        </div>
        <div class="list-mini">
          <div v-if="!datos.topProductos?.length" class="muted" style="padding:.5rem">Sin ventas en este período</div>
          <div v-for="(p, idx) in datos.topProductos" :key="idx" class="list-mini-item">
            <span class="lm-name">
              <span style="color:var(--accent);font-weight:800;margin-right:.4rem">{{ idx + 1 }}.</span>
              {{ p.nombre }}
            </span>
            <span style="display:flex;gap:.5rem;align-items:center">
              <span style="font-size:.75rem;color:var(--text3)">{{ p.total_cant }} und.</span>
              <span class="lm-val">{{ fmt(p.total_val) }}</span>
            </span>
          </div>
        </div>
      </div>

      <!-- Top clientes · Ferretería -->
      <div class="card">
        <div class="card-header">
          <h4>👥 Top clientes · Ferretería</h4>
          <router-link to="/clientes" class="btn-sm">Ver clientes →</router-link>
        </div>
        <div class="list-mini">
          <div v-if="!datos.topClientes?.length" class="muted" style="padding:.5rem">Sin datos en este período</div>
          <div v-for="(c, idx) in datos.topClientes" :key="idx" class="list-mini-item">
            <span class="lm-name">
              <span style="color:var(--accent);font-weight:800;margin-right:.4rem">{{ idx + 1 }}.</span>
              {{ c.nombre }}
            </span>
            <span style="display:flex;gap:.5rem;align-items:center">
              <span style="font-size:.75rem;color:var(--text3)">{{ c.compras }} compras</span>
              <span class="lm-val">{{ fmt(c.total) }}</span>
            </span>
          </div>
        </div>
      </div>

      <!-- Top clientes · Alquiler -->
      <div class="card">
        <div class="card-header">
          <h4>🔧 Top clientes · Alquiler</h4>
          <router-link to="/alquileres" class="btn-sm">Ver alquileres →</router-link>
        </div>
        <div class="list-mini">
          <div v-if="!datos.topClientesAlquiler?.length" class="muted" style="padding:.5rem">Sin datos en este período</div>
          <div v-for="(c, idx) in datos.topClientesAlquiler" :key="idx" class="list-mini-item">
            <span class="lm-name">
              <span style="color:#3b82f6;font-weight:800;margin-right:.4rem">{{ idx + 1 }}.</span>
              {{ c.nombre }}
            </span>
            <span style="display:flex;gap:.5rem;align-items:center">
              <span style="font-size:.75rem;color:var(--text3)">{{ c.alquileres }} alq.</span>
              <span class="lm-val">{{ fmt(c.total) }}</span>
            </span>
          </div>
        </div>
      </div>

      <!-- Top vendedores -->
      <div class="card">
        <div class="card-header">
          <h4>🏅 Vendedores · {{ etiquetaPeriodo }}</h4>
        </div>
        <div class="list-mini">
          <div v-if="!datos.topVendedores?.length" class="muted" style="padding:.5rem">Sin datos en este período</div>
          <div v-for="(v, idx) in datos.topVendedores" :key="idx" class="list-mini-item">
            <span class="lm-name">
              <span style="color:#10b981;font-weight:800;margin-right:.4rem">{{ idx + 1 }}.</span>
              {{ v.nombre }}
            </span>
            <span style="display:flex;gap:.5rem;align-items:center">
              <span style="font-size:.75rem;color:var(--text3)">{{ v.cantidad }} ventas</span>
              <span class="lm-val">{{ fmt(v.total) }}</span>
            </span>
          </div>
        </div>
      </div>

      <!-- Top proveedores -->
      <div class="card">
        <div class="card-header">
          <h4>📦 Proveedores · {{ etiquetaPeriodo }}</h4>
          <router-link to="/compras" class="btn-sm">Ver compras →</router-link>
        </div>
        <div class="list-mini">
          <div v-if="!datos.topProveedores?.length" class="muted" style="padding:.5rem">Sin compras en este período</div>
          <div v-for="(p, idx) in datos.topProveedores" :key="idx" class="list-mini-item">
            <span class="lm-name">
              <span style="color:#8b5cf6;font-weight:800;margin-right:.4rem">{{ idx + 1 }}.</span>
              {{ p.empresa || p.nombre }}
            </span>
            <span style="display:flex;gap:.5rem;align-items:center">
              <span style="font-size:.75rem;color:var(--text3)">{{ p.compras }} compras</span>
              <span class="lm-val">{{ fmt(p.total) }}</span>
            </span>
          </div>
        </div>
      </div>

      <!-- Alertas -->
      <div class="card">
        <div class="card-header"><h4>🚨 Alertas del sistema</h4></div>
        <div class="list-mini">
          <div v-if="datos.alqEnMora > 0" class="list-mini-item" style="background:rgba(239,68,68,.1);border-left:3px solid var(--red)">
            <span class="lm-name" style="color:#fca5a5">⛔ {{ datos.alqEnMora }} alquiler(es) en mora</span>
            <router-link to="/alquileres" class="btn-sm">Ver →</router-link>
          </div>
          <div v-if="datos.alqPorVencer > 0" class="list-mini-item" style="background:rgba(249,115,22,.1);border-left:3px solid var(--orange)">
            <span class="lm-name" style="color:#fdba74">⏰ {{ datos.alqPorVencer }} alquiler(es) vencen hoy/mañana</span>
            <router-link to="/alquileres" class="btn-sm">Ver →</router-link>
          </div>
          <div v-if="datos.agotado > 0" class="list-mini-item" style="background:rgba(239,68,68,.1);border-left:3px solid var(--red)">
            <span class="lm-name" style="color:#fca5a5">📦 {{ datos.agotado }} producto(s) agotados</span>
            <router-link to="/inventario" class="btn-sm">Ver →</router-link>
          </div>
          <div v-if="datos.stockBajo > 0" class="list-mini-item" style="background:rgba(249,115,22,.1);border-left:3px solid var(--orange)">
            <span class="lm-name" style="color:#fdba74">⚠ {{ datos.stockBajo }} producto(s) con stock bajo</span>
            <router-link to="/inventario" class="btn-sm">Ver →</router-link>
          </div>
          <div v-if="datos.sinMovimiento > 0" class="list-mini-item" style="background:rgba(59,130,246,.1);border-left:3px solid var(--blue)">
            <span class="lm-name" style="color:#93c5fd">🔍 {{ datos.sinMovimiento }} producto(s) sin ventas en 30 días</span>
          </div>
          <div v-if="!datos.alqEnMora && !datos.alqPorVencer && !datos.agotado && !datos.stockBajo && !datos.sinMovimiento"
               class="list-mini-item" style="background:rgba(16,185,129,.08);border-left:3px solid var(--green)">
            <span class="lm-name" style="color:#6ee7b7">✅ Todo en orden — sin alertas activas</span>
          </div>
        </div>
      </div>

      <!-- Comparativo de período -->
      <div class="card">
        <div class="card-header"><h4>📊 Comparativo de período</h4></div>
        <div class="list-mini">
          <div class="list-mini-item">
            <span class="lm-name">{{ etiquetaPeriodo }}</span>
            <span class="lm-val">{{ fmt(datos.ventasMes) }}</span>
          </div>
          <div class="list-mini-item">
            <span class="lm-name">Período anterior</span>
            <span class="lm-val" style="color:var(--text2)">{{ fmt(datos.ventasMesAnterior) }}</span>
          </div>
          <div class="list-mini-item" :style="{ background: crecimiento >= 0 ? 'rgba(16,185,129,.08)' : 'rgba(239,68,68,.08)' }">
            <span class="lm-name" :style="{ color: crecimiento >= 0 ? 'var(--green)' : 'var(--red)' }">
              {{ crecimiento >= 0 ? '▲ Crecimiento' : '▼ Decrecimiento' }}
            </span>
            <span :style="{ color: crecimiento >= 0 ? 'var(--green)' : 'var(--red)', fontFamily: 'JetBrains Mono', fontSize: '.85rem' }">
              {{ Math.abs(crecimiento) }}%
            </span>
          </div>
          <div class="list-mini-item">
            <span class="lm-name">Clientes activos</span>
            <span class="lm-val">{{ datos.clientes }}</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useAuthStore } from '../stores/auth'
import { api } from '../services/api'
import { generarPDFDashboard } from '../services/pdf'
import {
  Chart,
  BarElement, LineElement, PointElement, ArcElement,
  CategoryScale, LinearScale,
  BarController, LineController, DoughnutController,
  Tooltip, Legend, Filler
} from 'chart.js'

Chart.register(
  BarElement, LineElement, PointElement, ArcElement,
  CategoryScale, LinearScale,
  BarController, LineController, DoughnutController,
  Tooltip, Legend, Filler
)

const auth = useAuthStore()

const periodoVer  = ref('mes')
const mesVer      = ref(new Date().getMonth() + 1)
const semestreVer = ref(new Date().getMonth() < 6 ? 1 : 2)
const anioVer     = ref(new Date().getFullYear())
const cargando    = ref(false)

const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']

const esMesActual = computed(() => {
  const ahora = new Date()
  return periodoVer.value === 'mes'
    && mesVer.value === (ahora.getMonth() + 1)
    && anioVer.value === ahora.getFullYear()
})

const etiquetaPeriodo = computed(() => {
  if (periodoVer.value === 'mes')      return MESES[mesVer.value - 1] + ' ' + anioVer.value
  if (periodoVer.value === 'semestre') return `S${semestreVer.value} ${anioVer.value}`
  return `Año ${anioVer.value}`
})

const aniosDisponibles = computed(() => {
  const actual = new Date().getFullYear()
  return [actual - 2, actual - 1, actual]
})

const datos = ref({
  ventasHoy: 0, ventasHoyN: 0, ventasMes: 0, ventasMesAnterior: 0,
  totalAlquileres: 0, totalCombinado: 0, pctFerreteria: 0, pctAlquiler: 0,
  alqActivos: 0, alqPorVencer: 0, alqEnMora: 0,
  stockBajo: 0, agotado: 0, clientes: 0, sinMovimiento: 0,
  ventasPorDia: [], ventasPorMetodo: [], topProductos: [],
  topClientes: [], topClientesAlquiler: [], topVendedores: [], topProveedores: [],
  ventasPorDiaSemana: []
})
const ultimasVentas = ref([])
const stockCritico  = ref([])
const config        = ref({})
const metaMensual   = ref(5000000)

const chartVentasDias = ref(null)
const chartMetodos    = ref(null)
const chartDiaSemana  = ref(null)
let chartInstances = {}

const fmt = n => new Intl.NumberFormat('es-CO', {
  style: 'currency', currency: 'COP', maximumFractionDigits: 0
}).format(n || 0)

const saludo = computed(() => {
  const h = new Date().getHours()
  return h < 12 ? 'Buenos días' : h < 18 ? 'Buenas tardes' : 'Buenas noches'
})

const fechaHoy = computed(() =>
  new Date().toLocaleDateString('es-CO', { weekday:'long', year:'numeric', month:'long', day:'numeric' })
)

const crecimiento = computed(() => {
  const ant = datos.value.ventasMesAnterior
  const act = datos.value.ventasMes
  if (!ant) return act > 0 ? 100 : 0
  return Math.round(((act - ant) / ant) * 100)
})

const progresoMeta = computed(() =>
  Math.round((datos.value.ventasMes / metaMensual.value) * 100)
)

const DIAS    = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb']
const COLORES = ['#f59e0b','#10b981','#3b82f6','#ef4444','#8b5cf6','#f97316','#06b6d4']

function destroyCharts() {
  Object.values(chartInstances).forEach(c => { try { c?.destroy() } catch {} })
  chartInstances = {}
}

function buildCharts() {
  destroyCharts()

  const el1 = chartVentasDias.value
  if (el1) {
    const dias   = datos.value.ventasPorDia || []
    const labels = dias.map(d => {
      const f = new Date(d.dia + 'T12:00:00')
      return f.toLocaleDateString('es-CO', { weekday:'short', day:'2-digit', month:'2-digit' })
    })
    chartInstances.dias = new Chart(el1, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Ventas ($)',
          data: dias.map(d => parseFloat(d.total) || 0),
          backgroundColor: 'rgba(245,158,11,.75)',
          borderColor: '#f59e0b',
          borderWidth: 2,
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => fmt(ctx.raw) } }
        },
        scales: {
          y: { ticks: { color:'#8892a4', callback: v => fmt(v) }, grid: { color:'#272b38' } },
          x: { ticks: { color:'#8892a4' }, grid: { display: false } }
        }
      }
    })
  }

  const el2 = chartMetodos.value
  if (el2) {
    const metodos = datos.value.ventasPorMetodo || []
    const labels  = metodos.map(m => m.metodo_pago || 'Otro')
    const totales = metodos.map(m => parseFloat(m.total) || 0)
    chartInstances.metodos = new Chart(el2, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data: totales.length ? totales : [1],
          backgroundColor: totales.length ? COLORES.slice(0, labels.length) : ['#272b38'],
          borderWidth: 0,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { position:'bottom', labels: { color:'#8892a4', font: { size:10 } } },
          tooltip: { callbacks: { label: ctx => `${ctx.label}: ${fmt(ctx.raw)}` } }
        }
      }
    })
  }

  const el3 = chartDiaSemana.value
  if (el3) {
    const porDia = Array(7).fill(0)
    ;(datos.value.ventasPorDiaSemana || []).forEach(d => {
      const idx = (parseInt(d.dia_semana) - 1 + 7) % 7
      porDia[idx] = parseFloat(d.total) || 0
    })
    const maxVal = Math.max(...porDia)
    chartInstances.semana = new Chart(el3, {
      type: 'bar',
      data: {
        labels: DIAS,
        datasets: [{
          label: 'Ventas ($)',
          data: porDia,
          backgroundColor: porDia.map(v => v === maxVal && maxVal > 0 ? '#f59e0b' : 'rgba(59,130,246,.6)'),
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => fmt(ctx.raw) } }
        },
        scales: {
          y: { ticks: { color:'#8892a4', callback: v => fmt(v) }, grid: { color:'#272b38' } },
          x: { ticks: { color:'#8892a4' }, grid: { display: false } }
        }
      }
    })
  }
}

function descargarPDF() {
  generarPDFDashboard(datos.value, ultimasVentas.value, stockCritico.value, config.value)
}

function onPeriodoChange() {
  cargarDatos()
}

async function cargarDatos(inicial = false) {
  cargando.value = true
  try {
    const params = {
      anio: anioVer.value,
      mes: mesVer.value,
      periodo: periodoVer.value,
      semestre: semestreVer.value,
    }
    const promesas = [
      api.getDashboard(params),
      api.stockCritico(),
      api.getConfig(),
    ]
    if (inicial) promesas.push(api.getVentas())
    // allSettled: si el rol no tiene acceso a una fuente (p. ej. Bodega a ventas),
    // el resto del Dashboard se muestra igual
    const resultados = await Promise.allSettled(promesas)
    const dato = (i, def) => resultados[i]?.status === 'fulfilled' ? (resultados[i].value.data.data || def) : def
    datos.value        = dato(0, {})
    stockCritico.value = dato(1, []).slice(0, 6)
    config.value       = dato(2, {})
    if (config.value.meta_mensual) metaMensual.value = Number(config.value.meta_mensual)
    if (inicial) ultimasVentas.value = dato(3, []).slice(0, 6)
  } catch(e) {
    console.error('Dashboard error:', e)
  } finally {
    cargando.value = false
  }
  await nextTick()
  setTimeout(() => buildCharts(), inicial ? 800 : 50)
}

onMounted(() => cargarDatos(true))
</script>
