<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineOptions({ name: 'AlertasPreventivas' });

// Props que envías desde el controlador Inertia
const props = defineProps({
  defaultDays: { type: Number, default: 7 },
});

// Estado de la vista
const days = ref(props.defaultDays);
const items = ref([]);
const loading = ref(false);
const error = ref('');

// Lógica de carga
const load = async () => {
  loading.value = true;
  error.value = '';
  try {
    // Ojo: en app.js tienes axios.defaults.baseURL = '/api'
    // Por eso aquí basta con '/alertas/preventivas' (→ /api/alertas/preventivas)
    const { data } = await axios.get('alertas/preventivas', { params: { days: days.value } });

    // Soporta payloads { items: [...] } o directamente un array
    items.value = Array.isArray(data?.items)
      ? data.items
      : Array.isArray(data)
      ? data
      : [];
  } catch (e) {
    const status = e?.response?.status;
    // Mensajes útiles por código
    if (status === 401) {
      error.value = 'Tu sesión no está autenticada (401). Inicia sesión nuevamente.';
    } else if (status === 419) {
      error.value = 'La sesión/CSRF ha expirado (419). Recarga la página e inicia sesión.';
    } else {
      error.value =
        e?.response?.data?.message ||
        'No fue posible cargar los datos. Verifica que la API responda y la sesión esté activa.';
    }
    items.value = [];
  } finally {
    loading.value = false;
  }
};

// Descargar (redirección directa al endpoint de exportación)
const descargar = () => {
  const url = route('api.alertas.preventivas.export', { days: days.value });
  window.location.assign(url);
};

onMounted(load);
</script>

<template>
  <Head title="Alertas Preventivas" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Alertas <span class="text-orange-600">Preventivas</span>
      </h2>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Acciones / Filtro -->
        <div class="flex items-center gap-3 mb-4">
          <label class="text-sm text-gray-600">
            Días
            <input
              type="number"
              min="1"
              class="ml-2 border rounded px-2 py-1 w-20"
              v-model.number="days"
            />
          </label>

          <button
            class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"
            :disabled="loading"
            @click="load"
          >
            {{ loading ? 'Cargando…' : 'Actualizar' }}
          </button>

          <button
            class="px-4 py-2 rounded bg-gray-800 text-white hover:bg-gray-900"
            @click="descargar"
          >
            Descargar
          </button>
        </div>

        <!-- Alertas de error -->
        <div
          v-if="error"
          class="mb-4 px-4 py-3 rounded border border-red-200 bg-red-50 text-red-700 text-sm"
        >
          {{ error }}
        </div>

        <!-- Tabla -->
        <div class="bg-white dark:bg-gray-800 rounded shadow overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr class="text-left text-xs font-semibold text-gray-600 dark:text-gray-200 uppercase tracking-wider">
                <th class="px-4 py-3">ID</th>
                <th class="px-4 py-3">Rad. Corto</th>
                <th class="px-4 py-3">Etapa</th>
                <th class="px-4 py-3">Últ. Actuación</th>
                <th class="px-4 py-3">Vencimiento</th>
                <th class="px-4 py-3">Días sin gestión</th>
                <th class="px-4 py-3">Encargado</th>
              </tr>
            </thead>

            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-if="loading">
                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300" colspan="7">
                  Cargando resultados…
                </td>
              </tr>

              <tr v-else-if="items.length === 0">
                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300" colspan="7">
                  Sin resultados para los últimos {{ days }} días.
                </td>
              </tr>

              <tr v-for="row in items" :key="row.id">
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                  {{ row.id }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                  {{ row.rad_corto ?? row.radCorto ?? '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                  {{ row.etapa ?? '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                  {{ row.ultima_actuacion ?? row.ultActuacion ?? '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                  {{ row.vencimiento ?? '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                  {{ row.dias_sin_gestion ?? row.diasSinGestion ?? '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                  {{ row.encargado ?? '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
