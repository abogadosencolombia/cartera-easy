<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    listadoFallas: {
        type: Array,
        required: true,
    }
});

// Clases para los colores de riesgo
const riesgoClasses = {
    alto: 'bg-red-100 text-red-800 border-red-500',
    medio: 'bg-yellow-100 text-yellow-800 border-yellow-500',
    bajo: 'bg-blue-100 text-blue-800 border-blue-500',
};

// Nombres amigables para los tipos de validación
const tiposValidacionNombres = {
  poder_vencido: 'Poder Vencido',
  tasa_usura: 'Tasa de Usura Excedida',
  sin_pagare: 'Falta Pagaré',
  sin_carta_instrucciones: 'Falta Carta de Instrucciones',
  sin_certificacion_saldo: 'Falta Certificación de Saldo',
  tipo_proceso_vs_garantia: 'Proceso vs. Garantía',
  plazo_excedido_sin_demanda: 'Plazo para Demandar Excedido',
  documento_faltante_para_radicar: 'Docs. Faltantes para Radicar',
};

// Función para formatear fechas
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('es-CO', options);
};
</script>

<template>
    <Head title="Reporte de Cumplimiento Legal" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Dashboard de Cumplimiento Legal
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Sección de Estadísticas (KPIs) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <!-- Card Total Fallas -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6 flex items-center">
                        <div class="p-3 rounded-full bg-red-100 dark:bg-red-900 mr-4">
                            <ExclamationTriangleIcon class="h-8 w-8 text-red-500"/>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Fallas Activas</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ stats.totalFallasActivas }}</p>
                        </div>
                    </div>
                    <!-- Card Fallas por Riesgo -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Fallas por Nivel de Riesgo</h3>
                        <div class="flex justify-around items-center text-center mt-4">
                            <div>
                                <p class="text-2xl font-bold text-red-600">{{ stats.fallasPorRiesgo.alto }}</p>
                                <p class="text-xs font-semibold text-red-500 uppercase">Alto</p>
                            </div>
                             <div>
                                <p class="text-2xl font-bold text-yellow-600">{{ stats.fallasPorRiesgo.medio }}</p>
                                <p class="text-xs font-semibold text-yellow-500 uppercase">Medio</p>
                            </div>
                             <div>
                                <p class="text-2xl font-bold text-blue-600">{{ stats.fallasPorRiesgo.bajo }}</p>
                                <p class="text-xs font-semibold text-blue-500 uppercase">Bajo</p>
                            </div>
                        </div>
                    </div>
                     <!-- Card Top Cooperativas -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                         <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Top Cooperativas con Fallas</h3>
                         <ul class="space-y-2 text-sm mt-3">
                            <li v-for="coop in stats.fallasPorCooperativa" :key="coop.nombre" class="flex justify-between items-center">
                                <span class="text-gray-700 dark:text-gray-300">{{ coop.nombre }}</span>
                                <span class="font-bold bg-gray-200 dark:bg-gray-700 rounded-full px-2 py-0.5 text-xs">{{ coop.total_fallas }}</span>
                            </li>
                            <li v-if="stats.fallasPorCooperativa.length === 0" class="text-gray-500 text-center pt-2">Sin fallas registradas.</li>
                         </ul>
                    </div>
                </div>

                <!-- Tabla de Detalles de Fallas -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Listado de Fallas de Cumplimiento Activas</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Caso ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deudor</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cooperativa</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo de Falla</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Riesgo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Detectado</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="listadoFallas.length === 0">
                                        <td colspan="6" class="px-6 py-10 text-center text-gray-500">¡Felicidades! No hay fallas de cumplimiento activas.</td>
                                    </tr>
                                    <tr v-for="falla in listadoFallas" :key="falla.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                                            <Link :href="route('casos.show', falla.caso.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold">
                                                #{{ falla.caso.id }}
                                            </Link>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200">{{ falla.caso.deudor ? falla.caso.deudor.nombre_completo : 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">{{ falla.caso.cooperativa.nombre }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">{{ tiposValidacionNombres[falla.tipo] }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span :class="['px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full capitalize border', riesgoClasses[falla.nivel_riesgo]]">{{ falla.nivel_riesgo }}</span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(falla.ultima_revision) }}</td>
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
