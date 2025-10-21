<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'
import {
  Chart,
  // controladores
  LineController, BarController, DoughnutController, PieController, ScatterController, BubbleController,
  // elementos
  LineElement, BarElement, PointElement, ArcElement,
  // escalas
  LinearScale, CategoryScale, TimeScale, LogarithmicScale,
  // plugins core
  Tooltip, Legend, Filler
} from 'chart.js'
import 'chartjs-adapter-date-fns'

// Registro global (incluye el Filler para permitir `fill: true`)
Chart.register(
  LineController, BarController, DoughnutController, PieController, ScatterController, BubbleController,
  LineElement, BarElement, PointElement, ArcElement,
  LinearScale, CategoryScale, TimeScale, LogarithmicScale,
  Tooltip, Legend, Filler
)

const props = defineProps({
  type: { type: String, required: true },
  data: { type: Object, required: true },
  options: { type: Object, default: () => ({ responsive: true, maintainAspectRatio: false }) },
  plugins: { type: Array, default: () => [] },
  id: { type: String, default: () => `chart-${Math.random().toString(36).slice(2)}` }
})

const canvasRef = ref(null)
let chart = null

function render () {
  if (!canvasRef.value) return
  if (chart) chart.destroy()
  chart = new Chart(canvasRef.value, {
    type: props.type,
    data: props.data,
    options: props.options,
    plugins: props.plugins
  })
}

onMounted(render)
onBeforeUnmount(() => chart?.destroy())

// Re-render si cambian props (deep para data/options)
watch(() => [props.type, props.data, props.options], render, { deep: true })

// por si quieres acceder a la instancia desde el padre
defineExpose({ instance: () => chart })
</script>

<template>
  <div class="relative w-full h-full">
    <canvas :id="id" ref="canvasRef"></canvas>
  </div>
</template>

<style scoped>
.relative { position: relative; }
</style>
