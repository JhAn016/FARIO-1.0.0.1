<template>
  <div class="config-grid">
    <div class="card">
      <div class="card-header"><h4>Datos de la empresa</h4></div>
      <div style="display:flex;flex-direction:column;gap:.75rem">
        <div class="form-group"><label>Nombre del negocio</label><input v-model="cfg.nombre" /></div>
        <div class="form-group"><label>Eslogan</label><input v-model="cfg.eslogan" /></div>
        <div class="form-group"><label>NIT</label><input v-model="cfg.nit" /></div>
        <div class="form-group"><label>Teléfono</label><input v-model="cfg.tel" /></div>
        <div class="form-group"><label>Correo</label><input v-model="cfg.correo" type="email" /></div>
        <div class="form-group"><label>Ciudad</label><input v-model="cfg.ciudad" /></div>
        <div class="form-group"><label>Dirección</label><input v-model="cfg.dir" /></div>
        <div class="form-group">
          <label>Meta mensual de ventas ($)</label>
          <input v-model.number="cfg.meta_mensual" type="number" min="0" placeholder="Ej: 5000000" />
        </div>
        <button class="btn-primary" @click="guardar" :disabled="guardando">
          {{ guardando ? 'Guardando...' : 'Guardar configuración' }}
        </button>
        <div v-if="mensaje" class="alert-error" style="background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.3);color:#6ee7b7">
          {{ mensaje }}
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h4>Cambiar contraseña</h4></div>
      <div style="display:flex;flex-direction:column;gap:.75rem">
        <div class="form-group"><label>Contraseña actual</label><input v-model="pw.actual" type="password" /></div>
        <div class="form-group"><label>Nueva contraseña</label><input v-model="pw.nueva" type="password" /></div>
        <div class="form-group"><label>Confirmar nueva</label><input v-model="pw.confirma" type="password" /></div>
        <button class="btn-primary" @click="cambiarPass" :disabled="cambiando">
          {{ cambiando ? 'Cambiando...' : 'Cambiar contraseña' }}
        </button>
        <div v-if="msgPw" :class="['alert-error', msgPw.ok ? 'ok' : '']">{{ msgPw.txt }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { api } from '../services/api'

const auth     = useAuthStore()
const cfg      = ref({ nombre:'', eslogan:'', nit:'', tel:'', correo:'', ciudad:'', dir:'' })
const pw       = ref({ actual:'', nueva:'', confirma:'' })
const guardando = ref(false)
const cambiando = ref(false)
const mensaje   = ref('')
const msgPw     = ref(null)

async function cargar() {
  const res = await api.getConfig()
  cfg.value = res.data.data || {}
}

async function guardar() {
  guardando.value = true
  try {
    await api.guardarConfig(cfg.value)
    mensaje.value = '✓ Configuración guardada'
    setTimeout(() => mensaje.value = '', 3000)
  } catch(e) {
    alert(e.response?.data?.msg || 'Error al guardar')
  } finally {
    guardando.value = false
  }
}

async function cambiarPass() {
  if (!pw.value.actual || !pw.value.nueva) return alert('Completa todos los campos')
  if (pw.value.nueva !== pw.value.confirma) return alert('Las contraseñas no coinciden')
  if (pw.value.nueva.length < 4) return alert('Mínimo 4 caracteres')
  cambiando.value = true
  try {
    await api.cambiarPass({ uid: auth.user.id_usuario, actual: pw.value.actual, nueva: pw.value.nueva })
    msgPw.value = { ok: true, txt: '✓ Contraseña actualizada' }
    pw.value = { actual:'', nueva:'', confirma:'' }
    setTimeout(() => msgPw.value = null, 3000)
  } catch(e) {
    msgPw.value = { ok: false, txt: e.response?.data?.msg || 'Error' }
  } finally {
    cambiando.value = false
  }
}

onMounted(cargar)
</script>