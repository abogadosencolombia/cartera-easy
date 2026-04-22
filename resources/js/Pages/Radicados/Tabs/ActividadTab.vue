<script setup>
import HistorialAuditoria from '@/Components/HistorialAuditoria.vue';
import { 
    ClockIcon, 
    ChevronLeftIcon, 
    ChevronRightIcon,
    ShieldCheckIcon,
    ArrowPathIcon,
    FingerPrintIcon,
    UserIcon,
    DocumentMagnifyingGlassIcon,
    BookmarkIcon
} from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
    proceso: { type: Object, required: true },
    auditoria: { type: Array, default: () => [] },
});

// --- Paginación para Actuaciones (Bitácora) ---
const itemsPerPage = 6;
const currentPage = ref(1);
const totalPages = computed(() => props.proceso.actuaciones ? Math.ceil(props.proceso.actuaciones.length / itemsPerPage) : 0);
const paginatedActuaciones = computed(() => {
    if (!props.proceso.actuaciones) return [];
    const start = (currentPage.value - 1) * itemsPerPage;
    return props.proceso.actuaciones.slice(start, start + itemsPerPage);
});

const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };

const formatDateTime = (s) => {
    if (!s) return 'N/A';
    const date = new Date(s);
    return date.toLocaleString('es-CO', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 animate-in fade-in duration-500">
        
        <!-- BITÁCORA DE ACTUACIONES (Equivalente a Bitácora Procesal en Casos) -->
        <div class="lg:col-span-7 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex flex-col h-[650px] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center shrink-0">
                <div class="flex items-center gap-2">
                    <ClockIcon class="w-5 h-5 text-indigo-500" />
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-tight">Hitos y Actuaciones</h3>
                </div>
                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">{{ proceso.actuaciones?.length || 0 }} Eventos</span>
            </div>

            <div class="flex-grow overflow-y-auto p-6 custom-scrollbar relative">
                <div v-if="!proceso.actuaciones?.length" class="flex flex-col items-center justify-center h-full opacity-40">
                    <DocumentMagnifyingGlassIcon class="w-12 h-12 mb-2 text-gray-300" />
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Sin hitos registrados</p>
                </div>
                
                <div v-else class="space-y-6 relative before:absolute before:inset-0 before:ml-3 before:-translate-x-px before:h-full before:w-0.5 before:bg-gray-100 dark:before:bg-gray-700">
                    <div v-for="item in paginatedActuaciones" :key="item.id" class="relative pl-8 group">
                        <div class="absolute left-0 top-1 h-6 w-6 rounded-lg bg-white dark:bg-gray-800 border border-indigo-50 shadow-sm flex items-center justify-center z-10 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                            <BookmarkIcon class="w-3.5 h-3.5" />
                        </div>
                        
                        <div class="bg-gray-50/50 dark:bg-gray-900/30 p-4 rounded-xl border border-gray-100 dark:border-gray-700 hover:border-indigo-100 transition-all">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-[9px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Actuación Registrada</span>
                                <span class="text-[8px] font-bold text-gray-400 uppercase tracking-tighter">{{ formatDateTime(item.fecha_actuacion) }}</span>
                            </div>
                            <p class="text-xs text-gray-700 dark:text-gray-300 leading-normal font-medium line-clamp-3">{{ item.nota }}</p>
                            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center gap-2">
                                <div class="w-5 h-5 bg-white dark:bg-gray-700 rounded-full flex items-center justify-center text-[8px] font-bold text-gray-400 uppercase">
                                    {{ (item.user?.name || 'S')[0] }}
                                </div>
                                <span class="text-[9px] font-bold text-gray-500 uppercase tracking-tighter">{{ item.user?.name || 'Sistema' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            <div v-if="totalPages > 1" class="px-6 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50/30 flex items-center justify-between shrink-0">
                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">{{ currentPage }} / {{ totalPages }}</span>
                <div class="flex gap-2">
                    <button @click="prevPage" :disabled="currentPage === 1" class="p-1.5 bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 disabled:opacity-30"><ChevronLeftIcon class="h-4 w-4" /></button>
                    <button @click="nextPage" :disabled="currentPage === totalPages" class="p-1.5 bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 disabled:opacity-30"><ChevronRightIcon class="h-4 w-4" /></button>
                </div>
            </div>
        </div>

        <!-- AUDITORÍA TÉCNICA -->
        <div class="lg:col-span-5 flex flex-col h-[650px]">
            <div class="bg-slate-900 rounded-xl p-6 shadow-sm relative overflow-hidden h-full flex flex-col">
                <div class="flex items-center gap-2 mb-6 shrink-0 relative z-10">
                    <ShieldCheckIcon class="w-5 h-5 text-emerald-400" />
                    <h3 class="text-sm font-bold text-white uppercase tracking-tight">Registro de Integridad</h3>
                </div>
                
                <div class="relative z-10 flex-grow overflow-y-auto custom-scrollbar-dark pr-2">
                    <div v-if="!auditoria || auditoria.length === 0" class="flex flex-col items-center justify-center h-full opacity-40 py-20">
                        <DocumentMagnifyingGlassIcon class="w-12 h-12 mb-2 text-gray-400" />
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Sin historial técnico</p>
                    </div>
                    <HistorialAuditoria v-else :eventos="auditoria" class="!text-[10px]" />
                </div>

                <div class="mt-6 p-3 bg-white/5 border border-white/10 rounded-lg relative z-10 shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Monitoreo técnico activo</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(79, 70, 229, 0.1); border-radius: 10px; }
.custom-scrollbar-dark::-webkit-scrollbar { width: 4px; }
.custom-scrollbar-dark::-webkit-scrollbar-thumb { background-color: rgba(255, 255, 255, 0.1); border-radius: 10px; }
</style>
