<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({
  titulo: { type: String, required: true },
  endpoint: { type: String, required: true },      // ej: '/alertas/preventivas'
  defaultDays: { type: Number, default: 7 },
});

const dias = ref(props.defaultDays);
const rows = ref([]);
const loading = ref(false);
const errorMsg = ref('');

async function cargar() {
  errorMsg.value = '';
  loading.value = true;
  try {
    const { data } = await axios.get(`${props.endpoint}`, { params: { days: dias.value } });
    // Se espera: { ok: true, items: [...] }
    rows.value = Array.isArray(data?.items) ? data.items : [];
  } catch (e) {
    console.error(e);
    errorMsg.value = 'No fue posible cargar los datos. Verifica que la API responda y la sesión esté activa.';
    rows.value = [];
  } finally {
    loading.value = false;
  }
}

function descargar() {
  // Asumimos endpoint de exportación: /alertas/<tipo>/export
  const url = `${props.endpoint}/export?days=${encodeURIComponent(dias.value)}`;
  window.location.href = `/api${url}`; // baseURL '/api' en Axios; para descarga mejor ir directo al endpoint real
}

onMounted(cargar);
</script>

<template>
  <Head :title="titulo" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        {{ titulo }}
      </h2>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <!-- Filtros -->
            <div class="flex items-center gap-3 mb-4">
              <label class="text-sm">Días</label>
              <input
                type="number"
                min="1"
                v-model.number="dias"
                class="w-24 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
              />
              <button
                @click="cargar"
                :disabled="loading"
                class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-60"
              >
                {{ loading ? 'Cargando...' : 'Actualizar' }}
              </button>

              <button
                @click="descargar"
                class="px-4 py-2 rounded bg-gray-700 text-white hover:bg-gray-800"
              >
                Descargar
              </button>
            </div>

            <!-- Error -->
            <div v-if="errorMsg" class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-red-700">
              {{ errorMsg }}
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ID</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Rad. Corto</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Etapa</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Últ. Actuación</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Vencimiento</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Días sin gestión</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Encargado</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                  <tr v-for="(r, idx) in rows" :key="idx">
                    <td class="px-4 py-2 text-sm">{{ r.id ?? '-' }}</td>
                    <td class="px-4 py-2 text-sm">{{ r.rad_corto ?? r.radCorto ?? '-' }}</td>
                    <td class="px-4 py-2 text-sm">{{ r.etapa ?? '-' }}</td>
                    <td class="px-4 py-2 text-sm">{{ r.ultima_actuacion ?? r.ultActuacion ?? '-' }}</td>
                    <td class="px-4 py-2 text-sm">{{ r.vencimiento ?? '-' }}</td>
                    <td class="px-4 py-2 text-sm">{{ r.dias_sin_gestion ?? r.diasSinGestion ?? '-' }}</td>
                    <td class="px-4 py-2 text-sm">{{ r.encargado ?? '-' }}</td>
                  </tr>

                  <tr v-if="!loading && rows.length === 0">
                    <td colspan="7" class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
                      Sin resultados para los últimos {{ dias }} días.
                    </td>
                  </tr>

                  <tr v-if="loading">
                    <td colspan="7" class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
                      Cargando…
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
