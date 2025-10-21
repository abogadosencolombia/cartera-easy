<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { 
    BuildingOffice2Icon, 
    UsersIcon, 
    BriefcaseIcon, 
    ScaleIcon, 
    BanknotesIcon,
    ShieldCheckIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    stats: Object,
});

const user = usePage().props.auth.user;

// Formateador para números y moneda
const formatCurrency = (value) => {
    if (value === null || value === undefined) return '$ 0';
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(value);
};

// Objeto para dar estilo y nombres legibles a los estados de los casos
const estadoStyles = {
    'prejurídico': { text: 'Prejurídico', color: 'text-blue-500', icon: ScaleIcon },
    'demandado': { text: 'Demandado', color: 'text-yellow-500', icon: ShieldCheckIcon },
    'en ejecución': { text: 'En Ejecución', color: 'text-orange-500', icon: BriefcaseIcon },
    'sentencia': { text: 'Sentencia', color: 'text-green-500', icon: BanknotesIcon },
    'cerrado': { text: 'Cerrado', color: 'text-gray-500', icon: BuildingOffice2Icon },
};

</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Centro de Mando
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Mensaje de Bienvenida Personalizado -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="text-2xl font-bold">¡Bienvenido de nuevo, {{ user.name }}!</h3>
                        <p class="text-gray-500 dark:text-gray-400">Este es el resumen de la situación actual de tus operaciones.</p>
                    </div>
                </div>

                <!-- Contenedor Principal de Estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    
                    <!-- Tarjetas de Estadísticas para Admin -->
                    <template v-if="user.tipo_usuario === 'admin'">
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                            <BuildingOffice2Icon class="h-10 w-10 text-indigo-500" />
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Cooperativas Totales</p>
                                <p class="text-3xl font-bold">{{ stats.totalCooperativas }}</p>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                            <UsersIcon class="h-10 w-10 text-indigo-500" />
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Usuarios Registrados</p>
                                <p class="text-3xl font-bold">{{ stats.totalUsuarios }}</p>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                            <BriefcaseIcon class="h-10 w-10 text-indigo-500" />
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Casos Totales</p>
                                <p class="text-3xl font-bold">{{ stats.totalCasos }}</p>
                            </div>
                        </div>
                    </template>

                    <!-- Tarjetas de Estadísticas para Gestor/Abogado -->
                    <template v-if="user.tipo_usuario === 'gestor' || user.tipo_usuario === 'abogado'">
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                            <BuildingOffice2Icon class="h-10 w-10 text-teal-500" />
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Cooperativas Asignadas</p>
                                <p class="text-3xl font-bold">{{ stats.totalCooperativas }}</p>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                            <BriefcaseIcon class="h-10 w-10 text-teal-500" />
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Mis Casos Totales</p>
                                <p class="text-3xl font-bold">{{ stats.totalCasos }}</p>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Tarjetas de Estadísticas para Cliente -->
                    <template v-if="user.tipo_usuario === 'cli'">
                         <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                            <BriefcaseIcon class="h-10 w-10 text-sky-500" />
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Mis Casos Registrados</p>
                                <p class="text-3xl font-bold">{{ stats.totalCasos }}</p>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                            <BanknotesIcon class="h-10 w-10 text-sky-500" />
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Monto Total Involucrado</p>
                                <p class="text-3xl font-bold">{{ formatCurrency(stats.montoTotal) }}</p>
                            </div>
                        </div>
                    </template>

                </div>

                <!-- Desglose de Casos por Estado -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-8">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                         <h3 class="text-xl font-bold mb-4">Desglose de Casos por Estado</h3>
                         <div v-if="stats.casosPorEstado && stats.casosPorEstado.length > 0" class="space-y-4">
                            <div v-for="estado in stats.casosPorEstado" :key="estado.estado_proceso" class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <div class="flex items-center">
                                    <component :is="estadoStyles[estado.estado_proceso]?.icon || ShieldCheckIcon" class="h-6 w-6 mr-3" :class="estadoStyles[estado.estado_proceso]?.color || 'text-gray-500'" />
                                    <span class="font-medium capitalize">{{ estadoStyles[estado.estado_proceso]?.text || estado.estado_proceso }}</span>
                                </div>
                                <span class="font-bold text-lg">{{ estado.total }}</span>
                            </div>
                         </div>
                         <div v-else class="text-center py-8 text-gray-500">
                            <p>No hay datos de casos para mostrar.</p>
                         </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
