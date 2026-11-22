import axios from 'axios'

const http = axios.create({
    baseURL: '/api',
    headers: { 'Content-Type': 'application/json' }
})

// Adjunta el token de sesión a cada petición
http.interceptors.request.use(config => {
    try {
        const raw  = sessionStorage.getItem('fario_user')
        const user = raw ? JSON.parse(raw) : null
        if (user?.api_token) config.headers.Authorization = `Bearer ${user.api_token}`
    } catch { /* sessionStorage corrupto: se envía sin token, el backend responderá 401 */ }
    return config
})

// Si el token es inválido/expiró, cierra sesión y regresa al login
http.interceptors.response.use(
    res => res,
    err => {
        if (err.response?.status === 401 && !window.location.pathname.startsWith('/login')) {
            sessionStorage.removeItem('fario_user')
            window.location.href = '/login'
        }
        return Promise.reject(err)
    }
)


export const api = {
    // Auth
    login:        (u, p)    => http.post('/login', { usuario: u, password: p }),
    cambiarPass:  (d)       => http.post('/cambiar-password', d),

    // Ping
    ping: () => http.get('/ping'),

    // Productos
    getProductos:        ()      => http.get('/productos'),
    crearProducto:       (d)     => http.post('/productos', d),
    actualizarProducto:  (id, d) => http.put(`/productos/${id}`, d),
    eliminarProducto:    (id)    => http.delete(`/productos/${id}`),
    stockCritico:        ()      => http.get('/productos/stock-critico'),
    getMovimientosProducto: (id) => http.get(`/productos/${id}/movimientos`),

    // Clientes
    getClientes:        ()      => http.get('/clientes'),
    crearCliente:       (d)     => http.post('/clientes', d),
    actualizarCliente:  (id, d) => http.put(`/clientes/${id}`, d),
    eliminarCliente:    (id)    => http.delete(`/clientes/${id}`),
    getClientesMapa:    ()      => http.get('/clientes/mapa'),
    getHistorialCliente:(id)    => http.get(`/clientes/${id}/historial`),

    // Proveedores
    getProveedores:       ()      => http.get('/proveedores'),
    crearProveedor:       (d)     => http.post('/proveedores', d),
    actualizarProveedor:  (id, d) => http.put(`/proveedores/${id}`, d),
    eliminarProveedor:    (id)    => http.delete(`/proveedores/${id}`),

    // Ventas
    getVentas:       ()      => http.get('/ventas'),
    crearVenta:      (d)     => http.post('/ventas', d),
    anularVenta:     (id)    => http.patch(`/ventas/${id}/anular`),
    getVentasPorMes: (anio, mes) => http.get(`/reportes/ventas-por-mes?anio=${anio}&mes=${mes}`),

    // Alquileres
    getAlquileres:         ()      => http.get('/alquileres'),
    crearAlquiler:         (d)     => http.post('/alquileres', d),
    devolverAlquiler:      (id)    => http.patch(`/alquileres/${id}/devolver`),
    prorrogarAlquiler:     (id, d) => http.patch(`/alquileres/${id}/prorroga`, d),
    cambiarEstadoAlquiler: (id, d) => http.patch(`/alquileres/${id}/estado`, d),
    getAlquileresMapa:     ()      => http.get('/alquileres/mapa'),

    // Cotizaciones
    getCotizaciones:      ()      => http.get('/cotizaciones'),
    crearCotizacion:      (d)     => http.post('/cotizaciones', d),
    actualizarCotizacion: (id, d) => http.put(`/cotizaciones/${id}`, d),
    eliminarCotizacion:   (id)    => http.delete(`/cotizaciones/${id}`),
    convertirCotizacion:  (id, d) => http.post(`/cotizaciones/${id}/convertir`, d),

    // Caja
    getCaja:         ()  => http.get('/caja'),
    operarCaja:      (d) => http.post('/caja', d),
    getMovimientos:  ()  => http.get('/movimientos-caja'),
    crearMovimiento: (d) => http.post('/movimientos-caja', d),
    getDescuadre:    ()  => http.get('/caja/descuadre'),

    // Reportes
    getDashboard:     (params = {}) => http.get('/reportes/dashboard', { params }),
    getReporteVentas: (tipo, fecha) => http.get(`/reportes/ventas?tipo=${tipo}&fecha=${fecha}`),
    getGraficos:      (tipo, fecha) => http.get(`/reportes/graficos?tipo=${tipo}&fecha=${fecha}`),
    getAnalisis:      (periodo, anio, mes) => http.get(`/reportes/analisis?periodo=${periodo}&anio=${anio}&mes=${mes}`),

    // Config
    getConfig:    ()  => http.get('/config'),
    guardarConfig:(d) => http.post('/config', d),

    // Usuarios
    getUsuarios:       ()      => http.get('/usuarios'),
    crearUsuario:      (d)     => http.post('/usuarios', d),
    actualizarUsuario: (id, d) => http.put(`/usuarios/${id}`, d),
    eliminarUsuario:   (id)    => http.delete(`/usuarios/${id}`),

    // Compras
    getCompras:  ()  => http.get('/compras'),
    crearCompra: (d) => http.post('/compras', d),

    // Devoluciones
    getDevoluciones: ()  => http.get('/devoluciones'),
    crearDevolucion: (d) => http.post('/devoluciones', d),
}
