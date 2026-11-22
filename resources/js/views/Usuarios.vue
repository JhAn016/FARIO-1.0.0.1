<template>
  <div>
    <div class="view-toolbar">
      <button class="btn-primary" @click="abrirModal()">+ Nuevo usuario</button>
      <input class="search-input" v-model="busqueda" placeholder="Buscar usuario…" />
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Nombre</th><th>Correo</th><th>Cédula</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
          <tr v-if="!filtrados.length">
            <td colspan="7" style="text-align:center;color:var(--text3);padding:2rem">Sin usuarios</td>
          </tr>
          <tr v-for="u in filtrados" :key="u.id_usuario">
            <td class="mono">{{ u.id_usuario }}</td>
            <td><strong>{{ u.nombre }}</strong></td>
            <td>{{ u.correo || '—' }}</td>
            <td class="mono">{{ u.cedula || '—' }}</td>
            <td>
              <span class="badge" :class="u.rol === 'Administrador' ? 'badge-orange' : 'badge-blue'">
                {{ u.rol }}
              </span>
            </td>
            <td>
              <span class="badge" :class="u.estado === 'activo' ? 'badge-green' : 'badge-gray'">
                {{ u.estado }}
              </span>
            </td>
            <td class="actions">
              <button class="btn-icon edit" @click="abrirModal(u)">✏️</button>
              <button class="btn-icon del" v-if="u.id_usuario !== auth.user.id_usuario" @click="eliminar(u.id_usuario)">🗑</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="modal-overlay" :class="{ open: modalAbierto }" @click.self="modalAbierto = false">
      <div class="modal-box">
        <button class="modal-close" @click="modalAbierto = false">✕</button>
        <h3 class="modal-title">{{ editId ? 'Editar' : 'Nuevo' }} Usuario</h3>
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group"><label>Nombre *</label><input v-model="form.nombre" /></div>
            <div class="form-group"><label>Cédula</label><input v-model="form.cedula" /></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label>Correo</label><input v-model="form.correo" type="email" /></div>
            <div class="form-group"><label>Teléfono</label><input v-model="form.telefono" /></div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Contraseña {{ editId ? '(vacío = no cambiar)' : '*' }}</label>
              <input v-model="form.contrasena" type="password" />
            </div>
            <div class="form-group">
              <label>Rol</label>
              <select v-model="form.rol">
                <option>Vendedor</option>
                <option>Empleado</option>
                <option>Administrador</option>
              </select>
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
import { useAuthStore } from '../stores/auth'
import { api } from '../services/api'

const auth         = useAuthStore()
const usuarios     = ref([])
const modalAbierto = ref(false)
const guardando    = ref(false)
const busqueda     = ref('')
const editId       = ref(null)
const formVacio    = { nombre:'', cedula:'', correo:'', telefono:'', contrasena:'', rol:'Vendedor' }
const form         = ref({ ...formVacio })

const filtrados = computed(() => {
  const s = busqueda.value.toLowerCase()
  return (usuarios.value || []).filter(u =>
    (u.nombre || '').toLowerCase().includes(s) ||
    (u.correo || '').toLowerCase().includes(s) ||
    (u.cedula || '').includes(s)
  )
})

async function cargar() {
  try {
    const res = await api.getUsuarios()
    usuarios.value = res.data.data
  } catch (e) {
    alert(e.response?.data?.msg || 'No se pudieron cargar los usuarios')
  }
}

function abrirModal(u = null) {
  editId.value = u ? u.id_usuario : null
  form.value   = u ? {
    nombre: u.nombre, cedula: u.cedula || '',
    correo: u.correo || '', telefono: u.telefono || '',
    contrasena: '', rol: u.rol || 'Vendedor'
  } : { ...formVacio }
  modalAbierto.value = true
}

async function guardar() {
  if (!form.value.nombre) return alert('El nombre es obligatorio')
  if (!editId.value && !form.value.contrasena) return alert('La contraseña es obligatoria')
  guardando.value = true
  try {
    if (editId.value) await api.actualizarUsuario(editId.value, form.value)
    else await api.crearUsuario(form.value)
    modalAbierto.value = false
    await cargar()
  } catch(e) {
    alert(e.response?.data?.msg || 'Error al guardar')
  } finally {
    guardando.value = false
  }
}

async function eliminar(id) {
  if (!confirm('¿Desactivar usuario?')) return
  await api.eliminarUsuario(id)
  await cargar()
}

onMounted(cargar)
</script>