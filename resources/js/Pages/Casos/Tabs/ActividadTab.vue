<script setup>
import HistorialAuditoria from '@/Components/HistorialAuditoria.vue';
import { ClockIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
    bitacoras: { type: Array, default: () => [] },
    auditoria: { type: Array, default: () => [] },
});

// --- Paginación Local para Bitácora ---
const itemsPerPage = 10;
const currentPage = ref(1);

const totalPages = computed(() => Math.ceil(props.bitacoras.length / itemsPerPage));

const paginatedBitacoras = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return props.bitacoras.slice(start, end);
});

const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };

// --- Lógica de formato ---
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
        <!-- BITÁCORA DE ACTIVIDAD -->
        <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg flex flex-col h-[600px]">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold flex items-center">
                    <ClockIcon class="h-6 w-6 mr-2 text-indigo-500" />Bitácora de Actividad
                </h3>
                <span class="text-xs font-medium text-gray-500 bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded-full">
                    {{ bitacoras.length }} registros
                </span>
            </div>

            <div class="flex-grow overflow-y-auto pr-4 custom-scrollbar">
                <div v-if="!bitacoras || !bitacoras.length" class="text-center py-12">
                    <p class="text-sm text-gray-500 italic">No hay actividades registradas aún.</p>
                </div>
                
                <ol v-else class="relative border-l-2 border-gray-200 dark:border-gray-700 ml-3 space-y-8">
                    <li v-for="item in paginatedBitacoras" :key="item.id" class="relative pl-8 group">
                        <div class="absolute -left-[7px] top-1 h-3 w-3 rounded-full bg-indigo-500 ring-4 ring-gray-50 dark:ring-gray-900/50 transition-transform group-hover:scale-125"></div>
                        
                        <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                {{ item.accion }}
                            </p>
                            <p v-if="item.comentario" class="text-sm text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                                {{ item.comentario }}
                            </p>
                            <div class="mt-3 flex items-center justify-between text-[11px] text-gray-400 dark:text-gray-500 border-t dark:border-gray-700 pt-2">
                                <span>Por <span class="font-bold text-gray-600 dark:text-gray-300">{{ item.user ? item.user.name : 'Sistema' }}</span></span>
                                <span>{{ formatDateTime(item.created_at) }}</span>
                            </div>
                        </div>
                    </li>
                </ol>
            </div>

            <!-- Controles de Paginación Local -->
            <div v-if="totalPages > 1" class="mt-6 pt-4 border-t dark:border-gray-700 flex items-center justify-between">
                <p class="text-xs text-gray-500">
                    Página <span class="font-bold">{{ currentPage }}</span> de {{ totalPages }}
                </p>
                <div class="flex gap-2">
                    <button 
                        @click="prevPage" 
                        :disabled="currentPage === 1"
                        class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-30 transition-colors"
                    >
                        <ChevronLeftIcon class="h-5 w-5" />
                    </button>
                    <button 
                        @click="nextPage" 
                        :disabled="currentPage === totalPages"
                        class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-30 transition-colors"
                    >
                        <ChevronRightIcon class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- HISTORIAL DE AUDITORÍA -->
        <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg h-[600px] overflow-y-auto custom-scrollbar">
            <HistorialAuditoria :eventos="auditoria" />
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.3);
    border-radius: 20px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(75, 85, 99, 0.5);
}
</style>