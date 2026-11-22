import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

import Login         from '../views/Login.vue'
import Dashboard     from '../views/Dashboard.vue'
import Ventas        from '../views/Ventas.vue'
import Cotizaciones  from '../views/Cotizaciones.vue'
import Inventario    from '../views/Inventario.vue'
import Clientes      from '../views/Clientes.vue'
import Alquileres    from '../views/Alquileres.vue'
import Proveedores   from '../views/Proveedores.vue'
import Usuarios      from '../views/Usuarios.vue'
import Caja          from '../views/Caja.vue'
import Reportes      from '../views/Reportes.vue'
import Configuracion from '../views/Configuracion.vue'
import Compras       from '../views/Compras.vue'
import Devoluciones  from '../views/Devoluciones.vue'
import AnalisisVentas from '../views/AnalisisVentas.vue'
import Mapa from '../views/Mapa.vue'

const routes = [
  { path: '/login',          component: Login,         meta: { public: true } },
  { path: '/',               component: Dashboard                              },
  { path: '/ventas',         component: Ventas,         meta: { modulo: 'ventas' } },
  { path: '/cotizaciones',   component: Cotizaciones,   meta: { modulo: 'cotizaciones' } },
  { path: '/inventario',     component: Inventario,     meta: { modulo: 'inventario' } },
  { path: '/clientes',       component: Clientes,       meta: { modulo: 'clientes' } },
  { path: '/alquileres',     component: Alquileres,     meta: { modulo: 'alquileres' } },
  { path: '/proveedores',    component: Proveedores,    meta: { modulo: 'proveedores' } },
  { path: '/usuarios',       component: Usuarios,       meta: { modulo: 'usuarios' } },
  { path: '/caja',           component: Caja,           meta: { modulo: 'caja' } },
  { path: '/reportes',       component: Reportes,       meta: { modulo: 'reportes' } },
  { path: '/configuracion',  component: Configuracion,  meta: { modulo: 'configuracion' } },
  { path: '/compras',        component: Compras,        meta: { modulo: 'compras' } },
  { path: '/devoluciones',   component: Devoluciones,   meta: { modulo: 'devoluciones' } },
  { path: '/analisis',      component: AnalisisVentas, meta: { modulo: 'reportes' } },
  { path: '/mapa',          component: Mapa,           meta: { modulo: 'mapa' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (!to.meta.public && !auth.user) return '/login'
  if (to.meta.modulo && !auth.tienePermiso(to.meta.modulo)) return '/'
})

export default router