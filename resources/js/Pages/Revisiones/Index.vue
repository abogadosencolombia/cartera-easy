<script setup>
import { ref } from 'vue';
// Se elimina 'useForm' ya que no se está utilizando y causa el error.
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue'; // Asegúrate que este componente esté bien estilizado
import { CheckIcon } from '@heroicons/vue/24/solid';

// Usar sintaxis de Array para defineProps
const props = defineProps([
    'casos',
    'radicados',
    'contratos'
]);

const activeTab = ref('casos');

// Función para manejar el clic en el checkbox
const toggleRevision = (item, tipo) => {
    // Obtenemos la página actual de la URL
    const urlParams = new URLSearchParams(window.location.search);
    // Corrección nombre de Pestaña Radicados
    const pageKey = (tipo === 'ProcesoRadicado' ? 'radicados' : tipo.toLowerCase() + 's') + '_page';
    const onlyKey = (tipo === 'ProcesoRadicado' ? 'radicados' : tipo.toLowerCase() + 's');
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
        // No necesitamos onSuccess aquí, Inertia actualizará los props automáticamente
        // gracias a 'only'. Forzar el estado local puede causar inconsistencias.
    });
};

// Formatear nombres (Robusto)
const formatNombre = (persona) => {
    if (!persona) return 'N/A';
    if (persona.nombre_completo) return persona.nombre_completo;
    if (persona.name) return persona.name;
    if (persona.nombre) return persona.nombre;
    const nombreCompleto = `${persona.nombres || ''} ${persona.apellidos || ''}`.trim();
    return nombreCompleto || 'N/A';
};

// Helper para formatear fecha
const formatFecha = (fechaISO) => {
    if (!fechaISO) return '';
    const date = new Date(fechaISO);
    // Ajustar a UTC para evitar problemas con zonas horarias al mostrar solo Y-m-d
    date.setMinutes(date.getMinutes() + date.getTimezoneOffset());
    return date.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'short', // 'short' -> 'oct.'
        day: 'numeric',
    });
};

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
                        <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                <button @click="activeTab = 'casos'"
                                    :class="[
                                        activeTab === 'casos'
                                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600',
                                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-150 ease-in-out'
                                    ]">
                                    Casos ({{ casos.total }})
                                </button>
                                <button @click="activeTab = 'radicados'"
                                    :class="[
                                        activeTab === 'radicados'
                                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600',
                                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-150 ease-in-out'
                                    ]">
                                    Radicados ({{ radicados.total }})
                                </button>
                                <button @click="activeTab = 'contratos'"
                                    :class="[
                                        activeTab === 'contratos'
                                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600',
                                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-150 ease-in-out'
                                    ]">
                                    Contratos ({{ contratos.total }})
                                </button>
                            </nav>
                        </div>

                        <!-- Contenido de Pestañas -->
                        <div class="mt-4">
                            <!-- Pestaña Casos -->
                            <div v-show="activeTab === 'casos'">
                                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                                            <tr>
                                                <!-- Ajuste de anchos relativos -->
                                                <th scope="col" class="w-1/12 px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Revisado</th>
                                                <th scope="col" class="w-3/12 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Deudor</th>
                                                <th scope="col" class="w-5/12 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cooperativa</th>
                                                <th scope="col" class="w-3/12 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ref. Crédito</th>
                                            </tr>
                                        </thead>
                                        <tbody class="dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-for="caso in casos.data" :key="`caso-${caso.id}`" class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-800 dark:even:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150 ease-in-out">
                                                <td class="px-4 py-3 whitespace-nowrap align-middle">
                                                    <div class="flex flex-col items-center justify-center h-full">
                                                        <input type="checkbox"
                                                            :checked="caso.revisadoHoy"
                                                            @change="toggleRevision(caso, 'Caso')"
                                                            class="h-5 w-5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-900 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800 cursor-pointer">
                                                        <!-- Fecha "Últ. vez" más sutil y tooltip -->
                                                        <span v-if="!caso.revisadoHoy && caso.ultimaRevisionFecha"
                                                              class="text-xs text-gray-400 dark:text-gray-500 mt-1"
                                                              :title="`Última revisión: ${formatFecha(caso.ultimaRevisionFecha)}`">
                                                            {{ formatFecha(caso.ultimaRevisionFecha) }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium align-middle">
                                                    <Link :href="route('casos.show', caso.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                                        {{ formatNombre(caso.deudor) }}
                                                    </Link>
                                                    <div v-if="caso.deudor && caso.deudor.numero_documento" class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ caso.deudor.tipo_documento }} {{ caso.deudor.numero_documento }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300 align-middle">
                                                    {{ caso.cooperativa ? caso.cooperativa.nombre : 'N/A' }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300 align-middle">
                                                    {{ caso.referencia_credito }}
                                                </td>
                                            </tr>
                                            <tr v-if="casos.data.length === 0">
                                                <td colspan="4" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No hay casos activos para revisar hoy.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <Pagination :links="casos.links" class="mt-6 flex justify-center" />
                            </div>

                            <!-- Pestaña Radicados -->
                            <div v-show="activeTab === 'radicados'">
                                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                                            <tr>
                                                <th scope="col" class="w-1/12 px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Revisado</th>
                                                <th scope="col" class="w-3/12 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Radicado</th>
                                                <th scope="col" class="w-4/12 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Demandante</th>
                                                <th scope="col" class="w-4/12 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Demandado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-for="radicado in radicados.data" :key="`radicado-${radicado.id}`" class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-800 dark:even:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150 ease-in-out">
                                                <td class="px-4 py-3 whitespace-nowrap align-middle">
                                                     <div class="flex flex-col items-center justify-center h-full">
                                                        <input type="checkbox"
                                                            :checked="radicado.revisadoHoy"
                                                            @change="toggleRevision(radicado, 'ProcesoRadicado')"
                                                            class="h-5 w-5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-900 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800 cursor-pointer">
                                                        <span v-if="!radicado.revisadoHoy && radicado.ultimaRevisionFecha"
                                                              class="text-xs text-gray-400 dark:text-gray-500 mt-1"
                                                              :title="`Última revisión: ${formatFecha(radicado.ultimaRevisionFecha)}`">
                                                            {{ formatFecha(radicado.ultimaRevisionFecha) }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium align-middle">
                                                    <Link :href="route('procesos.show', radicado.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                                        {{ radicado.radicado }}
                                                    </Link>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300 align-middle">
                                                    {{ formatNombre(radicado.demandante) }}
                                                    <div v-if="radicado.demandante && radicado.demandante.numero_documento" class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ radicado.demandante.tipo_documento }} {{ radicado.demandante.numero_documento }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300 align-middle">
                                                    {{ formatNombre(radicado.demandado) }}
                                                    <div v-if="radicado.demandado && radicado.demandado.numero_documento" class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ radicado.demandado.tipo_documento }} {{ radicado.demandado.numero_documento }}
                                                    </div>
                                                </td>
                                            </tr>
                                             <tr v-if="radicados.data.length === 0">
                                                <td colspan="4" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No hay radicados activos para revisar hoy.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <Pagination :links="radicados.links" class="mt-6 flex justify-center" />
                            </div>

                            <!-- Pestaña Contratos -->
                            <div v-show="activeTab === 'contratos'">
                                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-md">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                                            <tr>
                                                <th scope="col" class="w-1/12 px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Revisado</th>
                                                <th scope="col" class="w-2/12 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID Contrato</th>
                                                <th scope="col" class="w-6/12 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cliente</th>
                                                <th scope="col" class="w-3/12 px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody class="dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-for="contrato in contratos.data" :key="`contrato-${contrato.id}`" class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-800 dark:even:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150 ease-in-out">
                                                <td class="px-4 py-3 whitespace-nowrap align-middle">
                                                    <div class="flex flex-col items-center justify-center h-full">
                                                        <input type="checkbox"
                                                            :checked="contrato.revisadoHoy"
                                                            @change="toggleRevision(contrato, 'Contrato')"
                                                            class="h-5 w-5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-900 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800 cursor-pointer">
                                                        <span v-if="!contrato.revisadoHoy && contrato.ultimaRevisionFecha"
                                                              class="text-xs text-gray-400 dark:text-gray-500 mt-1"
                                                              :title="`Última revisión: ${formatFecha(contrato.ultimaRevisionFecha)}`">
                                                            {{ formatFecha(contrato.ultimaRevisionFecha) }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium align-middle">
                                                    <Link :href="route('honorarios.contratos.show', contrato.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                                        #{{ contrato.id }}
                                                    </Link>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300 align-middle">
                                                    {{ formatNombre(contrato.cliente) }}
                                                    <div v-if="contrato.cliente && contrato.cliente.numero_documento" class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ contrato.cliente.tipo_documento }} {{ contrato.cliente.numero_documento }}
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center align-middle">
                                                    <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                        :class="{
                                                            'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-200': contrato.estado === 'ACTIVO',
                                                            'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200': contrato.estado === 'CERRADO',
                                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/60 dark:text-yellow-200': contrato.estado === 'ARCHIVADO',
                                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200': contrato.estado === 'SALDADO',
                                                            'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200': !['ACTIVO', 'CERRADO', 'ARCHIVADO', 'SALDADO'].includes(contrato.estado) // Default style
                                                        }">
                                                        {{ contrato.estado }}
                                                    </span>
                                                </td>
                                            </tr>
                                             <tr v-if="contratos.data.length === 0">
                                                <td colspan="4" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No hay contratos activos para revisar hoy.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <Pagination :links="contratos.links" class="mt-6 flex justify-center" />
                            </div>
                        </div> <!-- Fin Contenido Pestañas -->
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
