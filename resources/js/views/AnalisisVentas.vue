<template>
  <div>

    <!-- Selector de período -->
    <div class="card" style="margin-bottom:1rem">
      <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
        <div style="display:flex;gap:.5rem">
          <button
            v-for="p in periodos" :key="p.val"
            @click="cambiarPeriodo(p.val)"
            :style="{
              padding:'.4rem 1rem',borderRadius:'6px',fontSize:'.83rem',fontWeight:'600',cursor:'pointer',border:'1px solid var(--border)',
              background: periodo===p.val ? 'var(--accent)' : 'var(--surface2)',
              color: periodo===p.val ? '#000' : 'var(--text)',
            }"
          >{{ p.label }}</button>
        </div>
        <template v-if="periodo !== 'todo'">
          <select v-model="anio" @change="cargar" style="background:var(--bg3);border:1px solid var(--border2);border-radius:6px;color:var(--text);padding:.5rem .75rem">
            <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
          </select>
          <select v-if="periodo === 'mes'" v-model="mes" @change="cargar" style="background:var(--bg3);border:1px solid var(--border2);border-radius:6px;color:var(--text);padding:.5rem .75rem">
            <option v-for="(m,i) in nombresMeses" :key="i" :value="i+1">{{ m }}</option>
          </select>
        </template>
        <span style="margin-left:auto;font-size:.78rem;color:var(--text3)">{{ labelPeriodo }}</span>
      </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid" style="margin-bottom:1rem">
      <div class="kpi-card" style="--accent:#f59e0b">
        <div class="kpi-icon">💰</div>
        <div class="kpi-body">
          <span class="kpi-label">Ingresos ventas</span>
          <span class="kpi-value">{{ fmt(datos.totalVentas) }}</span>
          <span class="kpi-sub">{{ labelPeriodo }}</span>
        </div>
      </div>
      <div class="kpi-card" style="--accent:#10b981">
        <div class="kpi-icon">🔧</div>
        <div class="kpi-body">
          <span class="kpi-label">Ingresos alquileres</span>
          <span class="kpi-value">{{ fmt(datos.totalAlquileres) }}</span>
          <span class="kpi-sub">{{ labelPeriodo }}</span>
        </div>
      </div>
      <div class="kpi-card" style="--accent:#3b82f6">
        <div class="kpi-icon">🏆</div>
        <div class="kpi-body">
          <span class="kpi-label">Producto más vendido</span>
          <span class="kpi-value" style="font-size:1rem">{{ datos.topProductos?.[0]?.nombre || '—' }}</span>
          <span class="kpi-sub">{{ datos.topProductos?.[0] ? datos.topProductos[0].total_cant + ' unidades' : 'Sin datos' }}</span>
        </div>
      </div>
      <div class="kpi-card" style="--accent:#8b5cf6">
        <div class="kpi-icon">📦</div>
        <div class="kpi-body">
          <span class="kpi-label">Herramienta más alquilada</span>
          <span class="kpi-value" style="font-size:1rem">{{ datos.topAlquiladas?.[0]?.nombre || '—' }}</span>
          <span class="kpi-sub">{{ datos.topAlquiladas?.[0] ? datos.topAlquiladas[0].total_alquileres + ' alquileres' : 'Sin datos' }}</span>
        </div>
      </div>
    </div>

    <!-- Gráficas de barras horizontales -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">

      <!-- Top productos vendidos -->
      <div class="card">
        <div class="card-header">
          <h4>🛒 Top productos más vendidos</h4>
          <span style="font-size:.75rem;color:var(--text3)">por cantidad de unidades</span>
        </div>
        <div v-if="!datos.topProductos?.length" style="padding:2rem;text-align:center;color:var(--text3)">Sin ventas en el período</div>
        <div v-else style="position:relative;height:300px">
          <canvas ref="chartProductos"></canvas>
        </div>
      </div>

      <!-- Top herramientas alquiladas -->
      <div class="card">
        <div class="card-header">
          <h4>🔧 Top herramientas más alquiladas</h4>
          <span style="font-size:.75rem;color:var(--text3)">por número de alquileres</span>
        </div>
        <div v-if="!datos.topAlquiladas?.length" style="padding:2rem;text-align:center;color:var(--text3)">Sin alquileres en el período</div>
        <div v-else style="position:relative;height:300px">
          <canvas ref="chartAlquiladas"></canvas>
        </div>
      </div>
    </div>

    <!-- Método de pago + Top clientes -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">

      <!-- Distribución método de pago -->
      <div class="card">
        <div class="card-header"><h4>💳 Ventas por método de pago</h4></div>
        <div v-if="!datos.porMetodo?.length" style="padding:2rem;text-align:center;color:var(--text3)">Sin datos</div>
        <div v-else style="display:flex;align-items:center;gap:1.5rem;padding:.5rem">
          <div style="position:relative;height:200px;width:200px;flex-shrink:0">
            <canvas ref="chartMetodo"></canvas>
          </div>
          <div style="flex:1;display:flex;flex-direction:column;gap:.5rem">
            <div v-for="(m,i) in datos.porMetodo" :key="m.metodo_pago" style="display:flex;align-items:center;gap:.5rem;font-size:.82rem">
              <span :style="{width:'10px',height:'10px',borderRadius:'50%',background:coloresMetodo[i%coloresMetodo.length],display:'inline-block',flexShrink:0}"></span>
              <span style="flex:1;color:var(--text2)">{{ m.metodo_pago || 'No especificado' }}</span>
              <span style="font-weight:600">{{ fmt(m.total) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Top 5 clientes -->
      <div class="card">
        <div class="card-header"><h4>👥 Top 5 clientes por gasto</h4></div>
        <div v-if="!datos.topClientes?.length" style="padding:2rem;text-align:center;color:var(--text3)">Sin datos</div>
        <div v-else class="list-mini" style="margin-top:.5rem">
          <div v-for="(c, idx) in datos.topClientes" :key="idx" class="list-mini-item">
            <span class="lm-name">
              <span style="color:var(--accent);font-weight:800;margin-right:.4rem;min-width:1.2rem;display:inline-block">{{ idx+1 }}.</span>
              {{ c.nombre }}
            </span>
            <span style="display:flex;flex-direction:column;align-items:flex-end;gap:.1rem">
              <span class="lm-val">{{ fmt(c.total) }}</span>
              <span style="font-size:.7rem;color:var(--text3)">{{ c.compras }} compras</span>
            </span>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { api } from '../services/api'
import {
  Chart, BarElement, BarController, ArcElement, DoughnutController,
  CategoryScale, LinearScale, Tooltip, Legend
} from 'chart.js'

Chart.register(BarElement, BarController, ArcElement, DoughnutController, CategoryScale, LinearScale, Tooltip, Legend)

const datos   = ref({ topProductos:[], topAlquiladas:[], porMetodo:[], topClientes:[], totalVentas:0, totalAlquileres:0 })
const periodo = ref('anio')
const anio    = ref(new Date().getFullYear())
const mes     = ref(new Date().getMonth() + 1)

const chartProductos  = ref(null)
const chartAlquiladas = ref(null)
const chartMetodo     = ref(null)
let   charts          = {}

const periodos     = [{ val:'mes', label:'Este mes' }, { val:'anio', label:'Este año' }, { val:'todo', label:'Todo el historial' }]
const nombresMeses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']
const coloresMetodo = ['#f59e0b','#10b981','#3b82f6','#8b5cf6','#ef4444','#06b6d4']

const anios = computed(() => {
  const a = new Date().getFullYear()
  return [a-2, a-1, a, a+1].filter(x => x >= 2020)
})

const labelPeriodo = computed(() => {
  if (periodo.value === 'todo')  return 'Historial completo'
  if (periodo.value === 'anio')  return `Año ${anio.value}`
  return `${nombresMeses[mes.value-1]} ${anio.value}`
})

const fmt = n => new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0}).format(n||0)

function cambiarPeriodo(p) {
  periodo.value = p
  cargar()
}

async function cargar() {
  try {
    const res = await api.getAnalisis(periodo.value, anio.value, mes.value)
    datos.value = res.data.data || {}
    await nextTick()
    buildCharts()
  } catch(e) {
    console.error(e)
  }
}

function destroyCharts() {
  Object.values(charts).forEach(c => { try { c?.destroy() } catch {} })
  charts = {}
}

function buildCharts() {
  destroyCharts()

  const prodLabels = (datos.value.topProductos || []).map(p => (p.nombre||'').length > 20 ? p.nombre.substring(0,18)+'…' : (p.nombre||'Sin nombre'))
  const prodData   = (datos.value.topProductos || []).map(p => Number(p.total_cant))

  const alqLabels  = (datos.value.topAlquiladas || []).map(p => (p.nombre||'').length > 20 ? p.nombre.substring(0,18)+'…' : (p.nombre||'Sin nombre'))
  const alqData    = (datos.value.topAlquiladas || []).map(p => Number(p.total_alquileres))

  const optsBase = {
    responsive: true, maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.raw} und.` } } },
    scales: {
      x: { ticks: { color:'#8892a4' }, grid: { color:'#272b38' } },
      y: { ticks: { color:'#8892a4' }, grid: { display: false } }
    }
  }

  if (chartProductos.value && prodData.length) {
    charts.productos = new Chart(chartProductos.value, {
      type: 'bar',
      data: {
        labels: prodLabels,
        datasets: [{ data: prodData, backgroundColor: '#f59e0b', borderRadius: 4 }]
      },
      options: { ...optsBase, plugins: { ...optsBase.plugins, tooltip: { callbacks: { label: ctx => ` ${ctx.raw} unidades` } } } }
    })
  }

  if (chartAlquiladas.value && alqData.length) {
    charts.alquiladas = new Chart(chartAlquiladas.value, {
      type: 'bar',
      data: {
        labels: alqLabels,
        datasets: [{ data: alqData, backgroundColor: '#10b981', borderRadius: 4 }]
      },
      options: { ...optsBase, plugins: { ...optsBase.plugins, tooltip: { callbacks: { label: ctx => ` ${ctx.raw} alquileres` } } } }
    })
  }

  if (chartMetodo.value && datos.value.porMetodo?.length) {
    charts.metodo = new Chart(chartMetodo.value, {
      type: 'doughnut',
      data: {
        labels: datos.value.porMetodo.map(m => m.metodo_pago || 'No especificado'),
        datasets: [{
          data: datos.value.porMetodo.map(m => Number(m.total)),
          backgroundColor: coloresMetodo,
          borderWidth: 2,
          borderColor: '#1a1d27',
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => ` ${fmt(ctx.raw)}` } }
        },
        cutout: '65%',
      }
    })
  }
}

onMounted(cargar)
</script>
