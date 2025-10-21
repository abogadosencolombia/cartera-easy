<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
export default { layout: AuthenticatedLayout };
</script>

<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  defaultDays: { type: Number, default: 30 },
});

const days    = ref(props.defaultDays);
const items   = ref([]);
const loading = ref(false);
const error   = ref('');

const load = async () => {
  loading.value = true;
  error.value   = '';
  try {
    const { data } = await axios.get('/alertas/hallazgos', { params: { days: days.value } });
    items.value = Array.isArray(data.items) ? data.items : [];
  } catch (e) {
    const status  = e?.response?.status;
    const message = e?.response?.data?.message || 'No fue posible cargar los datos. Verifica que la API responda y la sesión esté activa.';
    error.value   = (status ? `[${status}] ` : '') + message;
    items.value   = [];
  } finally {
    loading.value = false;
  }
};

const descargar = () => {
  window.location.href = `/api/alertas/hallazgos/export?days=${encodeURIComponent(days.value)}`;
};

onMounted(load);
</script>

<template>
  <Head title="Alertas - Hallazgos" />

  <div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <h1 class="text-2xl font-semibold mb-4">
        Alertas <span class="text-orange-600">Hallazgos</span>
      </h1>

      <div class="flex items-center gap-3 mb-4">
        <label class="text-sm text-gray-600">
          Días
          <input type="number" class="ml-2 border rounded px-2 py-1 w-20" min="1" v-model.number="days" />
        </label>

        <button class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="loading" @click="load">
          {{ loading ? 'Cargando…' : 'Actualizar' }}
        </button>

        <button class="px-4 py-2 rounded bg-gray-800 text-white hover:bg-gray-900" @click="descargar">
          Descargar
        </button>
      </div>

      <div v-if="error" class="mb-4 px-4 py-3 rounded border border-red-200 bg-red-50 text-red-700 text-sm">
        {{ error }}
      </div>

      <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <th class="px-4 py-3">ID</th>
              <th class="px-4 py-3">Rad. Corto</th>
              <th class="px-4 py-3">Etapa</th>
              <th class="px-4 py-3">Últ. Actuación</th>
              <th class="px-4 py-3">Vencimiento</th>
              <th class="px-4 py-3">Días sin gestión</th>
              <th class="px-4 py-3">Encargado</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-100">
            <tr v-if="!loading && items.length === 0">
              <td class="px-4 py-4 text-sm text-gray-500" colspan="7">
                Sin resultados para los últimos {{ days }} días.
              </td>
            </tr>

            <tr v-for="row in items" :key="row.id">
              <td class="px-4 py-3 text-sm text-gray-700">{{ row.id }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ row.rad_corto }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ row.etapa }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ row.ultima_actuacion }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ row.vencimiento }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ row.dias_sin_gestion }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ row.encargado }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
