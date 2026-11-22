<template>
  <div>
    <div class="view-toolbar">
      <button class="btn-primary" @click="abrirModal()">+ Nuevo proveedor</button>
      <input class="search-input" v-model="busqueda" placeholder="Buscar proveedor…" />
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Nombre</th><th>Empresa</th><th>NIT</th><th>Teléfono</th><th>Correo</th><th>Acciones</th></tr>
        </thead>
        <tbody>
          <tr v-if="!filtrados.length">
            <td colspan="7" style="text-align:center;color:var(--text3);padding:2rem">Sin proveedores</td>
          </tr>
          <tr v-for="p in filtrados" :key="p.id_proveedor">
            <td class="mono">{{ p.id_proveedor }}</td>
            <td><strong>{{ p.nombre }}</strong></td>
            <td>{{ p.empresa || '—' }}</td>
            <td class="mono">{{ p.nit || '—' }}</td>
            <td>{{ p.telefono || '—' }}</td>
            <td>{{ p.correo || '—' }}</td>
            <td class="actions">
              <button class="btn-icon edit" @click="abrirModal(p)">✏️</button>
              <button class="btn-icon del" @click="eliminar(p.id_proveedor)">🗑</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="modal-overlay" :class="{ open: modalAbierto }" @click.self="modalAbierto = false">
      <div class="modal-box">
        <button class="modal-close" @click="modalAbierto = false">✕</button>
        <h3 class="modal-title">{{ editId ? 'Editar' : 'Nuevo' }} Proveedor</h3>
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group"><label>Nombre *</label><input v-model="form.nombre" /></div>
            <div class="form-group"><label>Empresa</label><input v-model="form.empresa" /></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label>NIT</label><input v-model="form.nit" /></div>
            <div class="form-group"><label>Teléfono</label><input v-model="form.telefono" /></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label>Correo</label><input v-model="form.correo" type="email" /></div>
            <div class="form-group"><label>Dirección</label><input v-model="form.direccion" /></div>
          </div>
          <div class="form-group"><label>Contacto</label><input v-model="form.contacto" /></div>
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

const proveedores  = ref([])
const busqueda     = ref('')
const modalAbierto = ref(false)
const guardando    = ref(false)
const editId       = ref(null)
const formVacio    = { nombre:'', empresa:'', nit:'', telefono:'', correo:'', direccion:'', contacto:'' }
const form         = ref({ ...formVacio })

const filtrados = computed(() => {
  const s = busqueda.value.toLowerCase()
  return (proveedores.value || []).filter(p =>
    (p.nombre || '').toLowerCase().includes(s) ||
    (p.empresa || '').toLowerCase().includes(s) ||
    (p.nit || '').includes(s)
  )
})

async function cargar() {
  try {
    const res = await api.getProveedores()
    proveedores.value = res.data.data
  } catch (e) {
    alert(e.response?.data?.msg || 'No se pudieron cargar los proveedores')
  }
}

function abrirModal(p = null) {
  editId.value = p ? p.id_proveedor : null
  form.value   = p ? {
    nombre: p.nombre, empresa: p.empresa || '',
    nit: p.nit || '', telefono: p.telefono || '',
    correo: p.correo || '', direccion: p.direccion || '',
    contacto: p.contacto || ''
  } : { ...formVacio }
  modalAbierto.value = true
}

async function guardar() {
  if (!form.value.nombre) return alert('El nombre es obligatorio')
  guardando.value = true
  try {
    if (editId.value) await api.actualizarProveedor(editId.value, form.value)
    else await api.crearProveedor(form.value)
    modalAbierto.value = false
    await cargar()
  } catch(e) {
    alert(e.response?.data?.msg || 'Error al guardar')
  } finally {
    guardando.value = false
  }
}

async function eliminar(id) {
  if (!confirm('¿Eliminar proveedor?')) return
  await api.eliminarProveedor(id)
  await cargar()
}

onMounted(cargar)
</script>