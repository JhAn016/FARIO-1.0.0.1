import { jsPDF } from 'jspdf'

const fmt = n => new Intl.NumberFormat('es-CO', {
    style: 'currency', currency: 'COP', maximumFractionDigits: 0
}).format(n || 0)

const fmtD = d => d ? new Date(d).toLocaleString('es-CO', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
}) : '—'

const fmtFecha = d => d ? new Date(d).toLocaleDateString('es-CO', {
    day: '2-digit', month: '2-digit', year: 'numeric'
}) : '—'

function encabezado(doc, config, titulo, subtitulo = '') {
    doc.setFillColor(13, 15, 20)
    doc.rect(0, 0, 210, 38, 'F')
    doc.setTextColor(245, 158, 11)
    doc.setFontSize(20)
    doc.setFont('helvetica', 'bold')
    doc.text(config.nombre || 'Ferretería', 14, 13)
    doc.setTextColor(180, 180, 180)
    doc.setFontSize(8)
    doc.setFont('helvetica', 'normal')
    if (config.eslogan) doc.text(config.eslogan, 14, 19)
    doc.setTextColor(200, 200, 200)
    doc.setFontSize(7.5)
    const col2x = 130
    if (config.nit)    doc.text(`NIT: ${config.nit}`, col2x, 13)
    if (config.tel)    doc.text(`Tel: ${config.tel}`, col2x, 18)
    if (config.correo) doc.text(config.correo, col2x, 23)
    if (config.ciudad) doc.text(config.ciudad, col2x, 28)
    if (config.dir)    doc.text(config.dir, col2x, 33)
    doc.setFillColor(245, 158, 11)
    doc.rect(0, 38, 210, 11, 'F')
    doc.setTextColor(13, 15, 20)
    doc.setFontSize(12)
    doc.setFont('helvetica', 'bold')
    doc.text(titulo, 105, 45, { align: 'center' })
    if (subtitulo) {
        doc.setFontSize(8)
        doc.text(subtitulo, 105, 50, { align: 'center' })
    }
    return subtitulo ? 56 : 54
}

function tablaEncabezado(doc, y, columnas) {
    doc.setFillColor(30, 32, 48)
    doc.rect(14, y, 182, 8, 'F')
    doc.setTextColor(245, 158, 11)
    doc.setFontSize(7.5)
    doc.setFont('helvetica', 'bold')
    columnas.forEach(col => doc.text(col.texto, col.x, y + 5.5))
    return y + 10
}

function tablaFila(doc, y, columnas, idx) {
    if (idx % 2 === 0) {
        doc.setFillColor(248, 248, 250)
        doc.rect(14, y - 2, 182, 8, 'F')
    }
    doc.setTextColor(40, 40, 40)
    doc.setFontSize(8)
    doc.setFont('helvetica', 'normal')
    columnas.forEach(col => {
        const texto = String(col.texto || '—')
        const truncado = texto.length > (col.max || 50) ? texto.substring(0, col.max || 50) + '...' : texto
        doc.text(truncado, col.x, y + 4)
    })
    return y + 9
}

function totalBox(doc, y, total) {
    doc.setDrawColor(200, 200, 200)
    doc.line(14, y, 196, y)
    y += 6
    doc.setFillColor(13, 15, 20)
    doc.rect(120, y, 76, 13, 'F')
    doc.setTextColor(245, 158, 11)
    doc.setFontSize(12)
    doc.setFont('helvetica', 'bold')
    doc.text('TOTAL:', 125, y + 9)
    doc.text(fmt(total), 192, y + 9, { align: 'right' })
    return y + 20
}

function pie(doc, y) {
    doc.setDrawColor(220, 220, 220)
    doc.line(14, y, 196, y)
    doc.setTextColor(160, 160, 160)
    doc.setFontSize(7)
    doc.setFont('helvetica', 'italic')
    doc.text('Documento generado por FARIO — Sistema de Gestión Ferretería', 105, y + 5, { align: 'center' })
    doc.text(`Fecha de impresión: ${fmtD(new Date())}`, 105, y + 10, { align: 'center' })
}

function infoBox(doc, y, filas) {
    doc.setFillColor(245, 247, 250)
    doc.rect(14, y, 182, filas.length * 7 + 4, 'F')
    doc.setDrawColor(220, 220, 220)
    doc.rect(14, y, 182, filas.length * 7 + 4, 'S')
    let iy = y + 7
    filas.forEach(fila => {
        doc.setFont('helvetica', 'bold')
        doc.setFontSize(8)
        doc.setTextColor(80, 80, 80)
        doc.text(fila.label + ':', fila.x || 18, iy)
        doc.setFont('helvetica', 'normal')
        doc.setTextColor(30, 30, 30)
        doc.text(String(fila.valor || '—'), (fila.x || 18) + fila.offset, iy)
        iy += 7
    })
    return y + filas.length * 7 + 10
}

// ══════════════════════════════════════════════
//  VENTA
// ══════════════════════════════════════════════
export function generarPDFVenta(venta, config = {}) {
    const doc = new jsPDF()
    let y = encabezado(doc, config, `RECIBO DE VENTA #${venta.id_venta}`)
    y = infoBox(doc, y, [
        { label: 'Cliente',        valor: venta.cliNom || 'Consumidor Final', x: 18, offset: 35 },
        { label: 'Método de pago', valor: venta.pago || 'Efectivo',           x: 18, offset: 35 },
        { label: 'Fecha',          valor: fmtD(venta.fecha),                  x: 18, offset: 35 },
        { label: 'Estado',         valor: venta.estado || 'activa',           x: 18, offset: 35 },
    ])
    y = tablaEncabezado(doc, y, [
        { texto: 'PRODUCTO',     x: 18  },
        { texto: 'CANT.',        x: 112 },
        { texto: 'PRECIO UNIT.', x: 132 },
        { texto: 'SUBTOTAL',     x: 170 },
    ])
    ;(venta.items || []).forEach((it, idx) => {
        y = tablaFila(doc, y, [
            { texto: it.nombre,      x: 18,  max: 42 },
            { texto: it.cant,        x: 112          },
            { texto: fmt(it.precio), x: 132          },
            { texto: fmt(it.sub),    x: 170          },
        ], idx)
    })
    y = totalBox(doc, y, venta.total)
    pie(doc, y)
    doc.save(`Venta_${venta.id_venta}.pdf`)
}

// ══════════════════════════════════════════════
//  COTIZACIÓN
// ══════════════════════════════════════════════
export function generarPDFCotizacion(cotizacion, config = {}) {
    const doc = new jsPDF()
    let y = encabezado(doc, config, `COTIZACIÓN #${cotizacion.id}`, 'Válida por 15 días')
    y = infoBox(doc, y, [
        { label: 'Cliente',  valor: cotizacion.cliente  || 'Sin nombre', x: 18, offset: 30 },
        { label: 'Teléfono', valor: cotizacion.telefono || '—',          x: 18, offset: 30 },
        { label: 'Fecha',    valor: fmtD(cotizacion.fecha),              x: 18, offset: 30 },
        { label: 'Notas',    valor: cotizacion.notas    || '—',          x: 18, offset: 30 },
    ])
    y = tablaEncabezado(doc, y, [
        { texto: 'PRODUCTO',     x: 18  },
        { texto: 'CANT.',        x: 112 },
        { texto: 'PRECIO UNIT.', x: 132 },
        { texto: 'SUBTOTAL',     x: 170 },
    ])
    const items = Array.isArray(cotizacion.items)
        ? cotizacion.items
        : (typeof cotizacion.items === 'string' ? JSON.parse(cotizacion.items || '[]') : [])
    items.forEach((it, idx) => {
        y = tablaFila(doc, y, [
            { texto: it.nombre,      x: 18,  max: 42 },
            { texto: it.cant,        x: 112          },
            { texto: fmt(it.precio), x: 132          },
            { texto: fmt(it.sub),    x: 170          },
        ], idx)
    })
    y = totalBox(doc, y, cotizacion.total)
    doc.setFillColor(255, 248, 220)
    doc.rect(14, y, 182, 10, 'F')
    doc.setTextColor(120, 90, 0)
    doc.setFontSize(8)
    doc.setFont('helvetica', 'italic')
    doc.text('Esta cotización tiene validez de 15 días a partir de la fecha de emisión.', 105, y + 6.5, { align: 'center' })
    y += 16
    pie(doc, y)
    doc.save(`Cotizacion_${cotizacion.id}.pdf`)
}

// ══════════════════════════════════════════════
//  COMPRA
// ══════════════════════════════════════════════
export function generarPDFCompra(compra, config = {}) {
    const doc = new jsPDF()
    let y = encabezado(doc, config, `ORDEN DE COMPRA #${compra.id_compra}`)
    y = infoBox(doc, y, [
        { label: 'Proveedor',     valor: compra.proveedor     || '—', x: 18, offset: 35 },
        { label: 'Fecha',         valor: fmtD(compra.fecha),          x: 18, offset: 35 },
        { label: 'Observaciones', valor: compra.observaciones || '—', x: 18, offset: 35 },
    ])
    y = tablaEncabezado(doc, y, [
        { texto: 'PRODUCTO',      x: 18  },
        { texto: 'CANT.',         x: 112 },
        { texto: 'PRECIO COMPRA', x: 132 },
        { texto: 'SUBTOTAL',      x: 170 },
    ])
    ;(compra.items || []).forEach((it, idx) => {
        y = tablaFila(doc, y, [
            { texto: it.nombre,      x: 18,  max: 42 },
            { texto: it.cantidad,    x: 112          },
            { texto: fmt(it.precio), x: 132          },
            { texto: fmt(it.sub),    x: 170          },
        ], idx)
    })
    y = totalBox(doc, y, compra.total)
    pie(doc, y)
    doc.save(`Compra_${compra.id_compra}.pdf`)
}

// ══════════════════════════════════════════════
//  ALQUILER
// ══════════════════════════════════════════════
export function generarPDFAlquiler(alquiler, config = {}) {
    const doc = new jsPDF()
    let y = encabezado(doc, config, `CONTRATO DE ALQUILER #${alquiler.id_alquiler}`)
    y = infoBox(doc, y, [
        { label: 'Cliente',      valor: alquiler.cliente || '—',       x: 18, offset: 35 },
        { label: 'Cédula',       valor: alquiler.cedula  || '—',       x: 18, offset: 35 },
        { label: 'Teléfono',     valor: alquiler.tel     || '—',       x: 18, offset: 35 },
        { label: 'Fecha inicio', valor: fmtFecha(alquiler.ini),        x: 18, offset: 35 },
        { label: 'Fecha fin',    valor: fmtFecha(alquiler.fin),        x: 18, offset: 35 },
        { label: 'Días',         valor: String(alquiler.dias || 1),    x: 18, offset: 35 },
        { label: 'Estado',       valor: alquiler.estado  || '—',       x: 18, offset: 35 },
    ])
    y = tablaEncabezado(doc, y, [
        { texto: 'HERRAMIENTA', x: 18  },
        { texto: 'CANT.',       x: 112 },
        { texto: '$/DÍA',       x: 135 },
        { texto: 'SUBTOTAL',    x: 168 },
    ])
    ;(alquiler.items || []).forEach((it, idx) => {
        y = tablaFila(doc, y, [
            { texto: it.prodNom,      x: 18,  max: 42 },
            { texto: String(it.cant), x: 112          },
            { texto: fmt(it.pd),      x: 135          },
            { texto: fmt(it.sub),     x: 168          },
        ], idx)
    })
    y = totalBox(doc, y, alquiler.total)
    if (alquiler.notas) {
        doc.setFillColor(245, 245, 245)
        doc.rect(14, y, 182, 10, 'F')
        doc.setFontSize(8)
        doc.setTextColor(80, 80, 80)
        doc.setFont('helvetica', 'bold')
        doc.text('Observaciones:', 18, y + 6.5)
        doc.setFont('helvetica', 'normal')
        doc.text(alquiler.notas.substring(0, 60), 55, y + 6.5)
        y += 16
    }
    y += 5
    doc.setDrawColor(150, 150, 150)
    doc.line(20, y + 15, 90, y + 15)
    doc.line(120, y + 15, 190, y + 15)
    doc.setFontSize(8)
    doc.setTextColor(100, 100, 100)
    doc.setFont('helvetica', 'normal')
    doc.text('Firma del cliente', 55, y + 20, { align: 'center' })
    doc.text('Firma de la empresa', 155, y + 20, { align: 'center' })
    doc.text(alquiler.cliente || '—', 55, y + 26, { align: 'center' })
    doc.text(config.nombre || 'Ferretería', 155, y + 26, { align: 'center' })
    y += 35
    pie(doc, y)
    doc.save(`Alquiler_${alquiler.id_alquiler}.pdf`)
}

// ══════════════════════════════════════════════
//  CAJA — ARQUEO
// ══════════════════════════════════════════════
export function generarPDFCaja(descuadre, movimientos, config = {}) {
    const doc = new jsPDF()
    const fecha = fmtFecha(new Date())
    let y = encabezado(doc, config, 'ARQUEO DE CAJA', `Fecha: ${fecha}`)
    y = infoBox(doc, y, [
        { label: 'Estado caja',       valor: descuadre.estado             || '—', x: 18, offset: 45 },
        { label: 'Monto apertura',    valor: fmt(descuadre.montoApertura),         x: 18, offset: 45 },
        { label: 'Ingresos efectivo', valor: fmt(descuadre.ingresosEfectivo),      x: 18, offset: 45 },
        { label: 'Transferencias',    valor: fmt(descuadre.transferencias),        x: 18, offset: 45 },
        { label: 'Egresos',           valor: fmt(descuadre.egresos),               x: 18, offset: 45 },
        { label: 'Saldo teórico',     valor: fmt(descuadre.saldoTeorico),          x: 18, offset: 45 },
    ])
    doc.setFontSize(10)
    doc.setFont('helvetica', 'bold')
    doc.setTextColor(30, 30, 30)
    doc.text('Detalle de movimientos', 14, y)
    y += 6
    y = tablaEncabezado(doc, y, [
        { texto: 'TIPO',     x: 18  },
        { texto: 'CONCEPTO', x: 45  },
        { texto: 'MONTO',    x: 130 },
        { texto: 'MÉTODO',   x: 158 },
        { texto: 'FECHA',    x: 178 },
    ])
    ;(movimientos || []).slice(0, 25).forEach((m, idx) => {
        y = tablaFila(doc, y, [
            { texto: m.tipo,                x: 18,  max: 10 },
            { texto: m.concepto   || '—',   x: 45,  max: 30 },
            { texto: fmt(m.monto),          x: 130          },
            { texto: m.metodo_pago || '—',  x: 158, max: 12 },
            { texto: fmtFecha(m.fecha),     x: 178          },
        ], idx)
        if (y > 270) { doc.addPage(); y = 20 }
    })
    y += 4
    pie(doc, y)
    doc.save(`Arqueo_Caja_${fecha.replace(/\//g,'-')}.pdf`)
}

// ══════════════════════════════════════════════
//  REPORTE DE VENTAS
// ══════════════════════════════════════════════
export function generarPDFReporte(reporte, periodo, config = {}) {
    const doc = new jsPDF()
    let y = encabezado(doc, config, 'REPORTE DE VENTAS', `Período: ${periodo}`)
    y = infoBox(doc, y, [
        { label: 'Total vendido',   valor: fmt(reporte.total),                                               x: 18, offset: 40 },
        { label: 'Nº de ventas',    valor: String(reporte.cantidad),                                         x: 18, offset: 40 },
        { label: 'Ticket promedio', valor: reporte.cantidad ? fmt(reporte.total / reporte.cantidad) : fmt(0), x: 18, offset: 40 },
    ])
    if (reporte.top && reporte.top.length) {
        doc.setFontSize(10)
        doc.setFont('helvetica', 'bold')
        doc.setTextColor(30, 30, 30)
        doc.text('Top productos más vendidos', 14, y)
        y += 6
        y = tablaEncabezado(doc, y, [
            { texto: 'PRODUCTO',  x: 18  },
            { texto: 'UNIDADES',  x: 130 },
            { texto: 'TOTAL',     x: 160 },
        ])
        reporte.top.forEach((p, idx) => {
            y = tablaFila(doc, y, [
                { texto: p.nombre,              x: 18,  max: 50 },
                { texto: String(p.total_cant),  x: 130          },
                { texto: fmt(p.total_val),      x: 160          },
            ], idx)
        })
        y += 6
    }
    doc.setFontSize(10)
    doc.setFont('helvetica', 'bold')
    doc.setTextColor(30, 30, 30)
    doc.text('Detalle de ventas', 14, y)
    y += 6
    y = tablaEncabezado(doc, y, [
        { texto: '#',       x: 18  },
        { texto: 'FECHA',   x: 30  },
        { texto: 'CLIENTE', x: 70  },
        { texto: 'MÉTODO',  x: 130 },
        { texto: 'TOTAL',   x: 163 },
    ])
    ;(reporte.ventas || []).forEach((v, idx) => {
        y = tablaFila(doc, y, [
            { texto: `#${v.id_venta}`,    x: 18         },
            { texto: fmtFecha(v.fecha),   x: 30         },
            { texto: v.cliente || '—',    x: 70, max:28 },
            { texto: v.metodo_pago || '—', x: 130        },
            { texto: fmt(v.total),        x: 163        },
        ], idx)
        if (y > 270) { doc.addPage(); y = 20 }
    })
    y += 4
    pie(doc, y)
    doc.save(`Reporte_Ventas_${periodo}.pdf`)
}

// ══════════════════════════════════════════════
//  DASHBOARD — RESUMEN EJECUTIVO
// ══════════════════════════════════════════════
export function generarPDFDashboard(datos, ultimasVentas, stockCritico, config = {}) {
    const doc = new jsPDF()
    const fecha = fmtFecha(new Date())
    let y = encabezado(doc, config, 'RESUMEN EJECUTIVO', `Generado el ${fecha}`)
    y = infoBox(doc, y, [
        { label: 'Ventas hoy',         valor: fmt(datos.ventasHoy),         x: 18, offset: 45 },
        { label: 'Transacciones hoy',  valor: String(datos.ventasHoyN),     x: 18, offset: 45 },
        { label: 'Ventas del mes',     valor: fmt(datos.ventasMes),          x: 18, offset: 45 },
        { label: 'Alquileres activos', valor: String(datos.alqActivos),      x: 18, offset: 45 },
        { label: 'Stock bajo',         valor: String(datos.stockBajo),       x: 18, offset: 45 },
        { label: 'Productos agotados', valor: String(datos.agotado),         x: 18, offset: 45 },
        { label: 'Clientes activos',   valor: String(datos.clientes || 0),   x: 18, offset: 45 },
    ])
    if (ultimasVentas && ultimasVentas.length) {
        doc.setFontSize(10)
        doc.setFont('helvetica', 'bold')
        doc.setTextColor(30, 30, 30)
        doc.text('Últimas ventas', 14, y)
        y += 6
        y = tablaEncabezado(doc, y, [
            { texto: '#',       x: 18  },
            { texto: 'CLIENTE', x: 35  },
            { texto: 'PAGO',    x: 110 },
            { texto: 'TOTAL',   x: 150 },
            { texto: 'FECHA',   x: 170 },
        ])
        ultimasVentas.forEach((v, idx) => {
            y = tablaFila(doc, y, [
                { texto: `#${v.id_venta}`,   x: 18         },
                { texto: v.cliNom || '—',    x: 35, max:35 },
                { texto: v.pago   || '—',    x: 110        },
                { texto: fmt(v.total),       x: 150        },
                { texto: fmtFecha(v.fecha),  x: 170        },
            ], idx)
        })
        y += 6
    }
    if (stockCritico && stockCritico.length) {
        doc.setFontSize(10)
        doc.setFont('helvetica', 'bold')
        doc.setTextColor(30, 30, 30)
        doc.text('Productos con stock crítico', 14, y)
        y += 6
        y = tablaEncabezado(doc, y, [
            { texto: 'PRODUCTO',    x: 18  },
            { texto: 'STOCK ACT.', x: 130 },
            { texto: 'STOCK MÍN.', x: 158 },
            { texto: 'ESTADO',     x: 182 },
        ])
        stockCritico.forEach((p, idx) => {
            y = tablaFila(doc, y, [
                { texto: p.nombre,                            x: 18,  max: 50 },
                { texto: String(p.stock),                     x: 130          },
                { texto: String(p.stock_minimo),              x: 158          },
                { texto: p.stock <= 0 ? 'AGOTADO' : 'BAJO',  x: 182          },
            ], idx)
        })
        y += 6
    }
    pie(doc, y)
    doc.save(`Dashboard_${fecha.replace(/\//g,'-')}.pdf`)
}