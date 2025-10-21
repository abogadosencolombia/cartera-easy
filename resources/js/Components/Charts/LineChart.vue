<script setup>
import { computed } from 'vue'
import BaseChart from './BaseChart.vue'

const props = defineProps({
  labels: { type: Array, required: true },
  /**
   * datasets: Array<{
   *   label: string,
   *   data: number[] | {x:any,y:number}[],
   *   borderColor?: string,
   *   backgroundColor?: string,
   *   fill?: boolean,
   *   tension?: number
   * }>
   */
  datasets: { type: Array, required: true },
  title: { type: String, default: '' },
  xTitle: { type: String, default: '' },
  yTitle: { type: String, default: '' },
  beginAtZero: { type: Boolean, default: true },
  stacked: { type: Boolean, default: false },
  tension: { type: Number, default: 0.35 } // suavizado por defecto
})

const data = computed(() => ({
  labels: props.labels,
  datasets: props.datasets.map(ds => ({
    borderWidth: 2,
    pointRadius: 3,
    tension: ds.tension ?? props.tension,
    ...ds
  }))
}))

const options = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { display: true },
    title: { display: !!props.title, text: props.title }
  },
  scales: {
    x: {
      title: { display: !!props.xTitle, text: props.xTitle }
    },
    y: {
      beginAtZero: props.beginAtZero,
      stacked: props.stacked,
      title: { display: !!props.yTitle, text: props.yTitle }
    }
  },
  elements: {
    line: { borderCapStyle: 'round', borderJoinStyle: 'round' }
  }
}))
</script>

<template>
  <BaseChart type="line" :data="data" :options="options" />
</template>