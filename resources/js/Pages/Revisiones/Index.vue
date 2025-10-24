<script setup>
import { ref, computed } from 'vue';
// --- INICIO: CORRECCIÓN DE ERROR DE COMPILACIÓN ---
// Se elimina 'useForm' ya que no se está utilizando y causa el error.
import { Head, Link, router } from '@inertiajs/vue3';
// --- FIN: CORRECCIÓN DE ERROR DE COMPILACIÓN ---
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { CheckIcon } from '@heroicons/vue/24/solid';

// --- INICIO: Usar sintaxis de Array para defineProps ---
const props = defineProps([
    'casos', 
    'radicados', 
    'contratos'
]);
// --- FIN: Usar sintaxis de Array ---

const activeTab = ref('casos');

// --- INICIO: CORRECCIÓN DE ERROR DE COMPILACIÓN ---
// Las variables 'form' y 'formData' no se estaban utilizando
// y causaban el error de compilación. Se han eliminado.
// --- FIN: CORRECCIÓN DE ERROR DE COMPILACIÓN ---


// Función para manejar el clic en el checkbox
const toggleRevision = (item, tipo) => {
    // Obtenemos la página actual de la URL
    const urlParams = new URLSearchParams(window.location.search);
    // --- INICIO: Corrección nombre de Pestaña Radicados ---
    const pageKey = (tipo === 'ProcesoRadicado' ? 'radicados' : tipo.toLowerCase() + 's') + '_page';
    const onlyKey = (tipo === 'ProcesoRadicado' ? 'radicados' : tipo.toLowerCase() + 's');
    // --- FIN: Corrección nombre de Pestaña Radicados ---
    const currentPage = urlParams.get(pageKey) || 1;

    router.post(route('revision.toggle'), {
        id: item.id,
        tipo: tipo,
        [pageKey]: currentPage // Enviar la página actual para preservar la paginación
    }, {
        preserveState: true,
        preserveScroll: true,
        // Recargar solo los props necesarios para esa pestaña
        only: [onlyKey],
        onSuccess: () => {
            // Actualizar el estado localmente para reactividad instantánea
            // Esto se actualiza automáticamente por el 'only', pero lo forzamos por si acaso
            const collection = props[onlyKey];
            const updatedItem = collection.data.find(i => i.id === item.id);
            if(updatedItem) {
                // El backend ya habrá recargado el prop, pero forzamos la fecha por si acaso
                updatedItem.revisadoHoy = !updatedItem.revisadoHoy; 
                if(updatedItem.revisadoHoy) {
                    updatedItem.ultimaRevisionFecha = new Date().toISOString().split('T')[0];
                }
            }
        }
    });
};

// --- INICIO: CORRECCIÓN Formatear nombres ---
const formatNombre = (persona) => {
    if (!persona) return 'N/A';
    const nombreCompleto = `${persona.nombres || ''} ${persona.apellidos || ''}`.trim();
    return nombreCompleto || 'N/A'; // Devolver N/A si el nombre está vacío
};
// --- FIN: CORRECCIÓN Formatear nombres ---

// --- INICIO: Helper para formatear fecha ---
const formatFecha = (fechaISO) => {
    if (!fechaISO) return '';
    // Sumar 1 día para ajustar la zona horaria (Y-m-d)
    const date = new Date(fechaISO);
    date.setMinutes(date.getMinutes() + date.getTimezoneOffset()); // Ajustar a UTC
    return date.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
// --- FIN: Helper para formatear fecha ---

</script>

<template>
    <Head title="Revisión Diaria (Checklist)" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Revisión Diaria (Checklist)
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">

                        <!-- Pestañas -->
                        <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
                            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                <button @click="activeTab = 'casos'"
                                    :class="[
                                        activeTab === 'casos'
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                                    ]">
                                    Casos ({{ casos.total }})
                                </button>
                                <button @click="activeTab = 'radicados'"
                                    :class="[
                                        activeTab === 'radicados'
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                                    ]">
                                    Radicados ({{ radicados.total }})
                                </button>
                                <button @click="activeTab = 'contratos'"
                                    :class="[
                                        activeTab === 'contratos'
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                                    ]">
                                    Contratos ({{ contratos.total }})
                                </button>
                            </nav>
                        </div>

                        <!-- Contenido de Pestañas -->
                        <div>
                            <!-- Pestaña Casos -->
                            <div v-show="activeTab === 'casos'">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th scope="col" class="w-2/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Revisado</th>
                                                <th scope="col" class="w-3/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Deudor</th>
                                                <th scope="col" class="w-4/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cooperativa</th>
                                                <th scope="col" class="w-3/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ref. Crédito</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-for="caso in casos.data" :key="caso.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <!-- INICIO: CORRECCIÓN CELDA REVISADO -->
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex flex-col items-start">
                                                        <input type="checkbox"
                                                            :checked="caso.revisadoHoy"
                                                            @change="toggleRevision(caso, 'Caso')"
                                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                        <span v-if="!caso.revisadoHoy && caso.ultimaRevisionFecha" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            Últ. vez: {{ formatFecha(caso.ultimaRevisionFecha) }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <!-- FIN: CORRECCIÓN CELDA REVISADO -->
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <Link :href="route('casos.show', caso.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900">
                                                        {{ formatNombre(caso.deudor) }}
                                                    </Link>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                    {{ caso.cooperativa ? caso.cooperativa.nombre : 'N/A' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                    {{ caso.referencia_credito }}
                                                </td>
                                            </tr>
                                            <tr v-if="casos.data.length === 0">
                                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">No hay casos para mostrar.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <Pagination :links="casos.links" class="mt-4" />
                            </div>

                            <!-- Pestaña Radicados -->
                            <div v-show="activeTab === 'radicados'">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th scope="col" class="w-2/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Revisado</th>
                                                <th scope="col" class="w-3/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Radicado</th>
                                                <th scope="col" class="w-3/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Demandante</th>
                                                <th scope="col" class="w-4/1ANHO-12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Demandado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-for="radicado in radicados.data" :key="radicado.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <!-- INICIO: CORRECCIÓN CELDA REVISADO -->
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex flex-col items-start">
                                                        <input type="checkbox"
                                                            :checked="radicado.revisadoHoy"
                                                            @change="toggleRevision(radicado, 'ProcesoRadicado')"
                                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                        <span v-if="!radicado.revisadoHoy && radicado.ultimaRevisionFecha" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            Últ. vez: {{ formatFecha(radicado.ultimaRevisionFecha) }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <!-- FIN: CORRECCIÓN CELDA REVISADO -->
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <Link :href="route('procesos.show', radicado.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900">
                                                        {{ radicado.radicado }}
                                                    </Link>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                    {{ formatNombre(radicado.demandante) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                    {{ formatNombre(radicado.demandado) }}
                                                </td>
                                            </tr>
                                             <tr v-if="radicados.data.length === 0">
                                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">No hay radicados para mostrar.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <Pagination :links="radicados.links" class="mt-4" />
                            </div>

                            <!-- Pestaña Contratos -->
                            <div v-show="activeTab === 'contratos'">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th scope="col" class="w-2/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Revisado</th>
                                                <th scope="col" class="w-2/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID Contrato</th>
                                                <th scope="col" class="w-5/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cliente</th>
                                                <th scope="col" class="w-3/12 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-for="contrato in contratos.data" :key="contrato.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <!-- INICIO: CORRECCIÓN CELDA REVISADO -->
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex flex-col items-start">
                                                        <input type="checkbox"
                                                            :checked="contrato.revisadoHoy"
                                                            @change="toggleRevision(contrato, 'Contrato')"
                                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                        <span v-if="!contrato.revisadoHoy && contrato.ultimaRevisionFecha" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            Últ. vez: {{ formatFecha(contrato.ultimaRevisionFecha) }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <!-- FIN: CORRECCIÓN CELDA REVISADO -->
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <Link :href="route('honorarios.contratos.show', contrato.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900">
                                                        {{ contrato.id }}
                                                    </Link>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                    {{ formatNombre(contrato.cliente) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                        :class="{
                                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': contrato.estado === 'ACTIVO',
                                                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': contrato.estado !== 'ACTIVO'
                                                        }">
                                                        {{ contrato.estado }}
                                                    </span>
                                                </td>
                                             </tr>
                                            <tr v-if="contratos.data.length === 0">
                                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">No hay contratos para mostrar.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <Pagination :links="contratos.links" class="mt-4" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

