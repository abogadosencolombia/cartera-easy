<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref, watch, onMounted } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { onClickOutside } from '@vueuse/core'

const props = defineProps({
  clientes: { type: Array, default: () => [] },
  modalidades: { type: Array, default: () => [] },
  // MODIFICACIÓN: Se añade la prop 'plantilla' para manejar la reestructuración
  plantilla: { type: Object, default: null }
})

const todayYmd = () => new Date().toISOString().slice(0, 10)

const form = useForm({
  cliente_id: null,
  monto_total: null,
  anticipo: 0,
  modalidad: 'CUOTAS',
  cuotas: 12,
  inicio: todayYmd(),
  nota: '',
  porcentaje_litis: null,
  // MODIFICACIÓN: Se añade el campo para rastrear el origen
  contrato_origen_id: null,
})

// --- BÚSQUEDA DE CLIENTES MEJORADA ---
const clienteSearch = ref('')
const selectedClientName = ref('')
const isClientListOpen = ref(false)
const clientDropdown = ref(null)

const filteredClients = computed(() => {
  if (!clienteSearch.value) return props.clientes.slice(0, 10)
  return props.clientes.filter(c => 
    c.nombre.toLowerCase().includes(clienteSearch.value.toLowerCase())
  ).slice(0, 10)
})

const selectClient = (client) => {
  form.cliente_id = client.id
  selectedClientName.value = client.nombre
  clienteSearch.value = client.nombre
  isClientListOpen.value = false
}

watch(clienteSearch, (newVal) => {
  if (newVal !== selectedClientName.value) {
    form.cliente_id = null
  }
})

onClickOutside(clientDropdown, () => isClientListOpen.value = false)


// --- LÓGICA DEL FORMULARIO Y CRONOGRAMA ---
const fmtMoney = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(n || 0))

watch(() => form.modalidad, (newModalidad) => {
    if (newModalidad === 'LITIS') {
        form.monto_total = null;
        form.cuotas = 1;
    } else if (newModalidad === 'PAGO_UNICO') {
        form.cuotas = 1;
        form.porcentaje_litis = null;
    } else if (newModalidad === 'CUOTAS') {
        form.porcentaje_litis = null;
    }
});

// MODIFICACIÓN: Hook onMounted para pre-llenar el formulario si viene de una reestructuración
onMounted(() => {
  if (props.plantilla) {
    const clienteOriginal = props.clientes.find(c => c.id === props.plantilla.cliente_id)
    
    form.defaults({
      cliente_id: props.plantilla.cliente_id,
      monto_total: props.plantilla.monto_total,
      anticipo: props.plantilla.anticipo,
      modalidad: props.plantilla.modalidad,
      cuotas: 12, // Se resetea el número de cuotas
      inicio: todayYmd(),
      nota: `Reestructuración del contrato #${props.plantilla.id}.`,
      porcentaje_litis: props.plantilla.porcentaje_litis,
      contrato_origen_id: props.plantilla.id,
    })
    form.reset()

    // Actualizar la UI del buscador de clientes
    if (clienteOriginal) {
      selectClient(clienteOriginal)
    }
  }
})


const addMonthsNoOverflow = (ymd, add) => {
  if (!ymd) return ''
  const src = new Date(ymd.replace(/-/g, '/'))
  const day = src.getDate()
  const tmp = new Date(src)
  tmp.setMonth(src.getMonth() + add)
  const last = new Date(tmp.getFullYear(), tmp.getMonth() + 1, 0).getDate()
  tmp.setDate(Math.min(day, last))
  const mm = String(tmp.getMonth() + 1).padStart(2, '0')
  const dd = String(tmp.getDate()).padStart(2, '0')
  return `${tmp.getFullYear()}-${mm}-${dd}`
}

const cronograma = computed(() => {
  const total = Number(form.monto_total || 0)
  const anticipo = Number(form.anticipo || 0)
  const neto = Math.max(0, total - anticipo)
  const cuotas = Math.max(1, parseInt(form.cuotas || 1, 10))

  if (!form.inicio || neto <= 0 || !form.monto_total) {
    return { neto, cuotas, filas: [], totalCuotas: 0 }
  }
  
  if (form.modalidad === 'PAGO_UNICO') {
    return { neto, cuotas: 1, filas: [{ numero: 1, fecha: form.inicio, valor: neto }], totalCuotas: neto }
  }

  const netoCents = Math.round(neto * 100)
  const base = Math.floor(netoCents / cuotas)
  const resto = netoCents - base * cuotas
  
  const filas = Array.from({ length: cuotas }, (_, i) => {
    const numero = i + 1
    const cents = base + (numero <= resto ? 1 : 0)
    const valor = cents / 100
    const fecha = addMonthsNoOverflow(form.inicio, i)
    return { numero, fecha, valor }
  })
  
  const totalCuotas = filas.reduce((sum, fila) => sum + fila.valor, 0)

  return { neto, cuotas, filas, totalCuotas }
})

const submit = () => {
  form.post(route('honorarios.contratos.store'), {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head :title="`${props.plantilla ? 'Reestructurar' : 'Nuevo'} Contrato · Honorarios`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
            {{ props.plantilla ? `Reestructurar Contrato #${props.plantilla.id}` : 'Crear Nuevo Contrato' }}
        </h2>
        <div class="flex items-center gap-4">
          <Link :href="route('honorarios.contratos.index')" class="text-sm px-3 py-2 rounded-md bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Volver
          </Link>
          <button @click="submit" :disabled="form.processing"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
            Guardar Contrato
          </button>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <!-- MODIFICACIÓN: Alerta de reestructuración -->
        <div v-if="props.plantilla" class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-400 p-4 rounded-md shadow-sm">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-amber-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Modo Reestructuración</p>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                        Estás creando un nuevo contrato basado en el <strong>#{{ props.plantilla.id }}</strong>. Ajusta los valores y la modalidad según sea necesario.
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 1: Información Principal -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
          <div class="p-6 border-b dark:border-gray-700 flex items-center gap-3">
            <div class="bg-indigo-100 dark:bg-indigo-900/50 p-2 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600 dark:text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg></div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Información Principal</h3>
          </div>
          <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2 relative" ref="clientDropdown">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente *</label>
              <input type="text" v-model="clienteSearch" @focus="isClientListOpen = true"
                     :disabled="!!props.plantilla"
                     placeholder="Escribe para buscar un cliente..."
                     class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100 dark:disabled:bg-gray-800/50" />
              <p v-if="form.errors.cliente_id" class="mt-1 text-sm text-red-600">{{ form.errors.cliente_id }}</p>
              <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                <div v-if="isClientListOpen && !props.plantilla" class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-900 shadow-lg rounded-md border dark:border-gray-700 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                      <li v-if="filteredClients.length === 0" class="px-4 py-2 text-sm text-gray-500">No hay coincidencias</li>
                      <li v-for="client in filteredClients" :key="client.id" @click="selectClient(client)"
                          class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-500 hover:text-white dark:hover:bg-indigo-600 cursor-pointer">
                        {{ client.nombre }}
                      </li>
                    </ul>
                </div>
              </transition>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de Inicio *</label>
              <input type="date" v-model="form.inicio" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
              <p v-if="form.errors.inicio" class="mt-1 text-sm text-red-600">{{ form.errors.inicio }}</p>
            </div>
            
              <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Anticipo (Opcional)</label>
                <div class="relative mt-1">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500">COP</div>
                  <input type="number" v-model.number="form.anticipo" placeholder="0" class="pl-12 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                </div>
                <p v-if="form.errors.anticipo" class="mt-1 text-sm text-red-600">{{ form.errors.anticipo }}</p>
            </div>
          </div>
        </div>

        <!-- Card 2: Detalles del Pago (100% DINÁMICO) -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
          <div class="p-6 border-b dark:border-gray-700 flex items-center gap-3">
             <div class="bg-emerald-100 dark:bg-emerald-900/50 p-2 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-600 dark:text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg></div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Detalles del Pago</h3>
          </div>
          <div class="p-6 space-y-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Modalidad de Pago *</label>
              <fieldset class="mt-2">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                  <label v-for="mod in modalidades" :key="mod" :class="['relative flex items-center justify-center p-3 rounded-lg border cursor-pointer transition-all', form.modalidad === mod ? 'bg-indigo-50 dark:bg-indigo-900/30 border-indigo-500 dark:border-indigo-600 ring-2 ring-indigo-500' : 'bg-white dark:bg-gray-900/50 border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500']">
                    <input type="radio" v-model="form.modalidad" :value="mod" class="hidden" />
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-200">{{ mod.replace('_', ' ') }}</span>
                  </label>
                </div>
              </fieldset>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t dark:border-gray-700">
                <div v-if="form.modalidad === 'CUOTAS' || form.modalidad === 'PAGO_UNICO' || form.modalidad === 'CUOTA_MIXTA'">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ form.modalidad === 'CUOTA_MIXTA' ? 'Monto Fijo a Cuotas *' : 'Monto Total *' }}
                    </label>
                    <div class="relative mt-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500">COP</div>
                        <input type="number" v-model.number="form.monto_total" placeholder="5000000" class="pl-12 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <p v-if="form.errors.monto_total" class="mt-1 text-sm text-red-600">{{ form.errors.monto_total }}</p>
                </div>

                <div v-if="form.modalidad === 'CUOTAS' || form.modalidad === 'CUOTA_MIXTA'">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Número de Cuotas *</label>
                    <input type="number" v-model.number="form.cuotas" min="1" max="120" step="1" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                    <p v-if="form.errors.cuotas" class="mt-1 text-sm text-red-600">{{ form.errors.cuotas }}</p>
                </div>
                
                <div v-if="form.modalidad === 'LITIS' || form.modalidad === 'CUOTA_MIXTA'">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Porcentaje de Éxito *</label>
                    <div class="relative mt-1">
                        <input type="number" v-model.number="form.porcentaje_litis" placeholder="30" class="pr-8 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-500">%</div>
                    </div>
                    <p v-if="form.errors.porcentaje_litis" class="mt-1 text-sm text-red-600">{{ form.errors.porcentaje_litis }}</p>
                </div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nota (Opcional)</label>
              <textarea v-model="form.nota" rows="3" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
              <p v-if="form.errors.nota" class="mt-1 text-sm text-red-600">{{ form.errors.nota }}</p>
            </div>
          </div>
        </div>

        <!-- Card 3: Cronograma de Pagos -->
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
          <div class="p-6 border-b dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Vista Previa del Cronograma</h3>
            <p class="text-sm text-gray-500 mt-1">
                <span v-if="form.modalidad !== 'LITIS'">Así se generarán las cuotas para la parte fija del contrato.</span>
                <span v-else>No se genera cronograma inicial para la modalidad LITIS.</span>
            </p>
          </div>
          
          <template v-if="form.modalidad !== 'LITIS'">
            <div class="p-6 space-y-4 text-sm bg-gray-50/50 dark:bg-gray-900/20">
                <div class="flex justify-between font-bold text-lg">
                  <span class="text-gray-600 dark:text-gray-300">Neto a Cobrar (Parte Fija)</span>
                  <span class="text-emerald-600 dark:text-emerald-400">{{ fmtMoney(cronograma.neto) }}</span>
                </div>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha Vencimiento</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Valor Cuota</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-if="cronograma.filas.length === 0">
                    <td colspan="3" class="px-4 py-10 text-center text-gray-500">
                      <p class="font-medium">Esperando datos...</p>
                      <p class="text-xs">Ingresa monto, anticipo e inicio para ver el cronograma.</p>
                    </td>
                  </tr>
                  <tr v-for="fila in cronograma.filas" :key="fila.numero">
                    <td class="px-4 py-2 text-sm font-mono text-gray-500">{{ fila.numero }}</td>
                    <td class="px-4 py-2 text-sm">{{ fila.fecha }}</td>
                    <td class="px-4 py-2 text-sm text-right font-mono">{{ fmtMoney(fila.valor) }}</td>
                  </tr>
                </tbody>
                <tfoot v-if="cronograma.filas.length > 0" class="bg-gray-100 dark:bg-gray-700">
                    <tr>
                      <th colspan="2" class="px-4 py-2 text-right text-sm font-semibold">Total a Financiar</th>
                      <th class="px-4 py-2 text-right text-sm font-semibold font-mono">{{ fmtMoney(cronograma.totalCuotas) }}</th>
                    </tr>
                </tfoot>
              </table>
            </div>
          </template>
          <div v-else class="p-10 text-center text-gray-500">
             <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
             <h4 class="mt-2 font-semibold">Modalidad Litis</h4>
             <p class="text-sm">El cobro se generará como un cargo único al finalizar el proceso, basado en el porcentaje de éxito.</p>
          </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4">
            <button @click="submit" :disabled="form.processing"
                    class="w-full inline-flex justify-center items-center gap-2 px-4 py-3 rounded-md text-base font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 transition-colors">
              Guardar Contrato
            </button>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

