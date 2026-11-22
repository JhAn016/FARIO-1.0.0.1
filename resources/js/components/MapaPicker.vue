<template>
  <div class="mp-wrap">
    <div class="mp-hint">👆 Haz clic en el mapa para marcar la ubicación del cliente</div>
    <div ref="mapaEl" class="mapa-picker" :style="{ height }"></div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl:       'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl:     'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
})

const props = defineProps({
  lat:    { type: Number, default: null },
  lng:    { type: Number, default: null },
  height: { type: String, default: '260px' },
})
// ubicado → { ciudad, direccion } obtenidos por geocodificación inversa (pueden venir vacíos)
const emit = defineEmits(['update:lat', 'update:lng', 'ubicado'])

const mapaEl = ref(null)
let mapa     = null
let marcador = null

function ponerMarcador(lat, lng) {
  if (marcador) mapa.removeLayer(marcador)
  marcador = L.marker([lat, lng]).addTo(mapa)
}

onMounted(() => {
  const tiene = props.lat != null && props.lng != null
  mapa = L.map(mapaEl.value, {
    center: tiene ? [props.lat, props.lng] : [3.5394, -76.3026], // Palmira
    zoom:   tiene ? 16 : 13,
    attributionControl: false,
  })
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(mapa)
  if (tiene) ponerMarcador(props.lat, props.lng)

  // El mapa se crea dentro de un modal que acaba de abrirse: recalcular tamaño
  setTimeout(() => mapa?.invalidateSize(), 150)

  mapa.on('click', async (e) => {
    const { lat, lng } = e.latlng
    ponerMarcador(lat, lng)
    emit('update:lat', lat)
    emit('update:lng', lng)

    try {
      const res  = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=es`)
      const a    = (await res.json())?.address || {}
      const ciudad = a.city || a.town || a.village || a.municipality || a.county || ''
      const road   = a.road ? (a.road + (a.house_number ? ' #' + a.house_number : '')) : ''
      const barrio = a.neighbourhood || a.suburb || ''
      emit('ubicado', { ciudad, direccion: [road, barrio, ciudad].filter(Boolean).join(', ') })
    } catch {
      // Sin geocodificación inversa las coordenadas quedan guardadas igual
    }
  })
})

// Si la ubicación cambia desde fuera (p. ej. botón "Ubicar"), mover el mapa
watch(() => [props.lat, props.lng], ([lat, lng]) => {
  if (!mapa || lat == null || lng == null) return
  const m = marcador?.getLatLng()
  if (m && m.lat === lat && m.lng === lng) return
  ponerMarcador(lat, lng)
  mapa.setView([lat, lng], 16)
})

onBeforeUnmount(() => { mapa?.remove(); mapa = null })
</script>

<style scoped>
.mp-wrap { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
.mp-hint { font-size: .75rem; color: var(--text2, #64748b); padding: .35rem .6rem; }
.mapa-picker { width: 100%; }
.mapa-picker :deep(.leaflet-tile-pane) {
  filter: invert(92%) hue-rotate(180deg) brightness(0.95) contrast(0.9);
}
</style>
