<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue'; // <-- AÑADIDO: computed para el JSON formateado
import throttle from 'lodash/throttle';

const props = defineProps({
  logs: Object,
  filters: Object,
});

const formFilters = ref({
  servicio: props.filters.servicio || '',
  fecha_desde: props.filters.fecha_desde || '',
  fecha_hasta: props.filters.fecha_hasta || '',
});

// ===== NUEVA LÓGICA PARA EL MODAL =====
const isModalVisible = ref(false); // Controla si el modal se muestra o no
const selectedLog = ref(null);     // Almacena el log en el que hicimos clic

// Función para abrir el modal
const openModal = (log) => {
  selectedLog.value = log;
  isModalVisible.value = true;
};

// Función para cerrar el modal
const closeModal = () => {
  isModalVisible.value = false;
  selectedLog.value = null;
};

// Función para formatear el JSON y que se vea bonito
const formattedJson = (data) => {
    try {
        // Si el dato ya es un objeto/array, lo convierte a string JSON formateado
        const parsed = typeof data === 'string' ? JSON.parse(data) : data;
        return JSON.stringify(parsed, null, 2); // 2 espacios de indentación
    } catch (e) {
        // Si no es un JSON válido (como el texto de un error 404), devuelve el texto tal cual.
        return data;
    }
};
// =====================================

watch(formFilters, throttle(() => {
    router.get(route('integraciones.index'), formFilters.value, {
        preserveState: true,
        replace: true,
    });
}, 300), {
    deep: true
});
</script>

<template>
  <Head title="Logs de Integraciones" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Panel de Integraciones Externas
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-6 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <!-- Contenedor de Filtros -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 flex-grow">
            <div>
                <label for="servicio" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Servicio</label>
                <input v-model="formFilters.servicio" type="text" id="servicio" class="mt-1 block w-full rounded-md ...">
            </div>
            <div>
                <label for="fecha_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Desde</label>
                <input v-model="formFilters.fecha_desde" type="date" id="fecha_desde" class="mt-1 block w-full rounded-md ...">
            </div>
            <div>
                <label for="fecha_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hasta</label>
                <input v-model="formFilters.fecha_hasta" type="date" id="fecha_hasta" class="mt-1 block w-full rounded-md ...">
            </div>
        </div>
        <!-- Contenedor del Botón de Exportar -->
        <div class="mt-4 md:mt-0">
            <a :href="route('integraciones.exportar', formFilters)" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">
                Exportar a Excel
            </a>
        </div>
    </div>
</div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Servicio</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Endpoint</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                <tr v-for="log in logs.data" :key="log.id">
                  <td class="px-6 py-4 whitespace-nowrap text-sm">{{ log.id }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">{{ log.servicio }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 truncate max-w-xs">{{ log.endpoint }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(log.fecha_solicitud).toLocaleString() }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <span v-if="!JSON.stringify(log.respuesta).includes('error')" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                      Éxito
                    </span>
                    <span v-else class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                      Error
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                      <button @click="openModal(log)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200">
                          Ver Detalle
                      </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div v-if="isModalVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click="closeModal">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4" @click.stop>
            <div class="p-6">
                <div class="flex justify-between items-center pb-3 border-b dark:border-gray-700">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Detalle del Log #{{ selectedLog.id }}</h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">&times;</button>
                </div>
                <div class="mt-4 space-y-4 max-h-[60vh] overflow-y-auto">
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-gray-200">Datos Enviados:</h4>
                        <pre class="bg-gray-100 dark:bg-gray-900 p-3 rounded-md text-sm text-gray-600 dark:text-gray-300 whitespace-pre-wrap">{{ formattedJson(selectedLog.datos_enviados) }}</pre>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800 dark:text-gray-200">Respuesta Recibida:</h4>
                        <pre class="bg-gray-100 dark:bg-gray-900 p-3 rounded-md text-sm text-gray-600 dark:text-gray-300 whitespace-pre-wrap">{{ formattedJson(selectedLog.respuesta) }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

  </AuthenticatedLayout>
</template>