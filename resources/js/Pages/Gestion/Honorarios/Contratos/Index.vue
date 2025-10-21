<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  contratos: { type: Object, default: () => ({ data: [] }) },
  filters:   { type: Object, default: () => ({ q: '', estado: '' }) },
  stats:     { type: Object, default: () => ({ activeValue: 0, activeCount: 0, closedCount: 0 }) },
})

const q = ref(props.filters?.q ?? '')
const estado = ref(props.filters?.estado ?? '')

const buscarContratos = () => {
  router.get(route('gestion.honorarios.index'),
    { q: q.value, estado: estado.value },
    { preserveState: true, preserveScroll: true }
  )
}

watch(q, () => buscarContratos())
watch(estado, () => buscarContratos())

const limpiarFiltros = () => {
  q.value = ''
  estado.value = ''
}

const fmtMoney = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(n || 0))
const fmtDateShort = (d) => d ? new Date(d.replace(/-/g, '/')).toLocaleDateString('es-CO', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'

const estadoClass = (e) => {
  const s = String(e || '').toUpperCase()
  if (s === 'EN_MORA') return 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300'
  if (s === 'CERRADO') return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
  if (s === 'PAGOS_PENDIENTES') return 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300'
  return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
}

const calcularProgreso = (contrato) => {
  const total = Number(contrato.monto_total || 0)
  if (total === 0) return 0
  const pagado = Number(contrato.total_pagado || 0)
  return Math.min(100, (pagado / total) * 100)
}
</script>

<template>
  <Head title="Honorarios · Contratos" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <Link :href="route('dashboard')" class="text-sm p-2 rounded-md bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </Link>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Centro de Mando · Honorarios</h2>
        </div>
        <Link :href="route('honorarios.contratos.create')"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
          Nuevo Contrato
        </Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- KPIs Renovados -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-5 flex items-start gap-4">
            <div class="bg-emerald-100 dark:bg-emerald-900/50 p-3 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-600 dark:text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg></div>
            <div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Valor Total Activo</div>
              <div class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ fmtMoney(stats.activeValue) }}</div>
            </div>
          </div>
          <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-5 flex items-start gap-4">
            <div class="bg-blue-100 dark:bg-blue-900/50 p-3 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 dark:text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
            <div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Contratos Activos</div>
              <div class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ stats.activeCount }}</div>
            </div>
          </div>
          <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-5 flex items-start gap-4">
            <div class="bg-gray-100 dark:bg-gray-700 p-3 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg></div>
            <div>
              <div class="text-sm text-gray-500 dark:text-gray-400">Contratos Cerrados</div>
              <div class="mt-1 text-2xl font-bold text-gray-600 dark:text-gray-400">{{ stats.closedCount }}</div>
            </div>
          </div>
        </div>

        <!-- Filtros y Lista de Contratos -->
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
          <div class="p-4 border-b dark:border-gray-700 flex flex-col md:flex-row items-center gap-4">
            <div class="relative w-full md:w-1/2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
              <input v-model="q" type="text" placeholder="Buscar por ID o nombre de cliente..." class="w-full pl-10 pr-4 py-2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
            </div>
            <select v-model="estado" class="w-full md:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
              <option value="">Todos los Estados</option>
              <option value="ACTIVO">Activo</option>
              <option value="PAGOS_PENDIENTES">Pagos Pendientes</option>
              <option value="EN_MORA">En Mora</option>
              <option value="CERRADO">Cerrado</option>
            </select>
            <button v-if="q || estado" @click="limpiarFiltros" class="text-sm text-gray-500 hover:text-gray-700">Limpiar</button>
          </div>

          <!-- Lista de Tarjetas de Contrato -->
          <div v-if="contratos.data.length > 0" class="divide-y divide-gray-200 dark:divide-gray-700">
            <div v-for="c in contratos.data" :key="c.id" class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
              <div class="flex flex-col md:flex-row items-start md:items-center gap-4">
                <div class="flex-1">
                  <div class="flex items-center gap-3">
                    <Link :href="route('honorarios.contratos.show', c.id)" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">
                      {{ c.persona_nombre }}
                    </Link>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="estadoClass(c.estado)">
                      {{ c.estado.replace('_', ' ') }}
                    </span>
                  </div>
                  <p class="text-sm text-gray-500 mt-1">
                    Contrato #{{ c.id }} · Creado el {{ fmtDateShort(c.created_at) }}
                  </p>
                </div>
                <div class="w-full md:w-1/3">
                  <div class="flex justify-between items-center text-sm mb-1">
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ fmtMoney(c.total_pagado || 0) }}</span>
                    <span class="text-gray-500">{{ fmtMoney(c.monto_total) }}</span>
                  </div>
                  <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div class="bg-emerald-500 h-2 rounded-full" :style="{ width: calcularProgreso(c) + '%' }"></div>
                  </div>
                </div>
                <div class="flex-shrink-0">
                  <Link :href="route('honorarios.contratos.show', c.id)" class="px-3 py-1.5 rounded-md text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700">
                    Ver Detalles
                  </Link>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Estado Vacío -->
          <div v-else class="text-center py-16 text-gray-500">
              <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" /></svg>
              <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">No se encontraron contratos</h3>
              <p class="mt-1 text-sm">Intenta ajustar los filtros o crea un nuevo contrato.</p>
          </div>
          
          <!-- Paginación -->
          <div v-if="contratos.data.length > 0" class="p-4 border-t dark:border-gray-700">
             <Pagination :links="contratos.links" />
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
