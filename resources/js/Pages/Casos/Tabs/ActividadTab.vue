<script setup>
import HistorialAuditoria from '@/Components/HistorialAuditoria.vue';
import { ClockIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    bitacoras: Array,
    auditoria: Array,
});

// --- Lógica de formato (copiada) ---
const parseDate = (s) => {
    if (!s) return null;
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        const [y, m, d] = s.split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    return new Date(String(s).replace(' ', 'T'));
};
const formatDateTime = (s) =>
    parseDate(s)?.toLocaleString('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }) || 'N/A';
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
            <h3 class="text-lg font-bold mb-4 flex items-center">
                <ClockIcon class="h-6 w-6 mr-2 text-gray-500" />Bitácora de Actividad
            </h3>
            <div
                class="relative border-l-2 border-gray-200 dark:border-gray-700 ml-3 max-h-96 overflow-y-auto pr-2"
            >
                <div v-if="!bitacoras || !bitacoras.length" class="pl-8 pb-4">
                    <p class="text-sm text-gray-500">No hay actividades.</p>
                </div>
                <ol v-else class="space-y-6">
                    <li v-for="item in bitacoras" :key="item.id" class="relative pl-8">
                        <div
                            class="absolute -left-[7px] top-1 h-3 w-3 rounded-full bg-indigo-500 ring-4 ring-gray-50 dark:ring-gray-900/50"
                        ></div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                            {{ item.accion }}
                        </p>
                        <p v-if="item.comentario" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ item.comentario }}
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5">
                            Por <span class="font-medium">{{ item.user ? item.user.name : 'N/A' }}</span>
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            {{ formatDateTime(item.created_at) }}
                        </p>
                    </li>
                </ol>
            </div>
        </div>

        <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
            <HistorialAuditoria :eventos="auditoria" />
        </div>
    </div>
</template>