<script setup>
import { ShieldCheckIcon, EyeIcon, ArrowDownTrayIcon, PencilSquareIcon, LockClosedIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    eventos: Array,
});

// Función para formatear fechas y horas
const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
    return new Date(dateString).toLocaleString('es-CO', options);
};

// Objeto para mapear criticidad a estilos de Tailwind
const criticidadClasses = {
    alta: 'bg-red-500',
    media: 'bg-yellow-500',
    baja: 'bg-blue-500',
};

// Objeto para mapear eventos a iconos
const eventoIconos = {
    'LOGIN_EXITOSO': LockClosedIcon,
    'DOCUMENTO_DESCARGADO': ArrowDownTrayIcon,
    'CUMPLIMIENTO_CORREGIDO': ShieldCheckIcon,
    'DEFAULT': EyeIcon
};

const getIcon = (evento) => {
    return eventoIconos[evento] || eventoIconos['DEFAULT'];
};
</script>

<template>
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                <ShieldCheckIcon class="h-6 w-6 mr-3 text-gray-400" />
                Rastro de Auditoría del Caso
            </h3>
            <div class="relative border-l-2 border-gray-200 dark:border-gray-700 ml-3">
                <div v-if="!eventos || eventos.length === 0" class="pl-8 pb-4">
                    <p class="text-sm text-gray-500">No hay eventos de auditoría registrados para este caso.</p>
                </div>
                <ol v-else class="space-y-6">
                    <li v-for="evento in eventos" :key="evento.id" class="relative pl-8">
                        <span :class="['absolute -left-[7px] top-1 h-3 w-3 rounded-full ring-4 ring-white dark:ring-gray-800', criticidadClasses[evento.criticidad]]"></span>
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 rounded-full p-2">
                                <component :is="getIcon(evento.evento)" class="h-5 w-5 text-gray-600 dark:text-gray-300" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ evento.descripcion_breve }}</p>
                                
                                <!-- Detalles de cambios (NUEVO) -->
                                <div v-if="evento.detalle_nuevo && Object.keys(evento.detalle_nuevo).length > 0" class="mt-3 overflow-x-auto">
                                    <table class="min-w-full text-[10px] border dark:border-gray-700 rounded-lg overflow-hidden">
                                        <thead class="bg-gray-100 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-2 py-1 text-left font-bold">Campo</th>
                                                <th class="px-2 py-1 text-left font-bold">Anterior</th>
                                                <th class="px-2 py-1 text-left font-bold">Nuevo</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y dark:divide-gray-700">
                                            <tr v-for="(val, key) in evento.detalle_nuevo" :key="key" class="bg-white dark:bg-gray-800">
                                                <td class="px-2 py-1 font-medium text-gray-500 uppercase">{{ key.replace(/_/g, ' ') }}</td>
                                                <td class="px-2 py-1 text-red-600 dark:text-red-400 italic line-through">{{ evento.detalle_anterior ? (evento.detalle_anterior[key] ?? '—') : '—' }}</td>
                                                <td class="px-2 py-1 text-green-600 dark:text-green-400 font-bold">{{ val ?? '—' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                    Por <span class="font-medium">{{ evento.usuario ? evento.usuario.name : 'Sistema' }}</span> desde la IP {{ evento.direccion_ip }}
                                </p>
                                <time class="text-xs text-gray-400 dark:text-gray-500">{{ formatDateTime(evento.created_at) }}</time>
                            </div>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</template>
