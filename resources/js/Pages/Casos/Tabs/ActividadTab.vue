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
    DocumentMagnifyingGlassIcon
} from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
    bitacoras: { type: Array, default: () => [] },
    auditoria: { type: Array, default: () => [] },
});

// --- Paginación Local para Bitácora ---
const itemsPerPage = 8;
const currentPage = ref(1);
const totalPages = computed(() => props.bitacoras ? Math.ceil(props.bitacoras.length / itemsPerPage) : 0);
const paginatedBitacoras = computed(() => {
    if (!props.bitacoras) return [];
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return props.bitacoras.slice(start, end);
});

const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };

const formatDateTime = (s) => {
    if (!s) return 'N/A';
    const date = new Date(s);
    return date.toLocaleString('es-CO', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 animate-in fade-in duration-500">
        
        <!-- BITÁCORA DE ACTIVIDAD (DIARIO DE TRABAJO) -->
        <div class="lg:col-span-7 bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm flex flex-col h-[750px] overflow-hidden">
            <div class="px-8 py-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center shrink-0">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl">
                        <ClockIcon class="w-5 h-5 text-indigo-600" />
                    </div>
                    <div>
                        <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Bitácora Procesal</h3>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Diario de acciones del equipo</p>
                    </div>
                </div>
                <span class="px-3 py-1 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-full text-[10px] font-black text-gray-500">
                    {{ bitacoras ? bitacoras.length : '...' }} EVENTOS
                </span>
            </div>

            <div class="flex-grow overflow-y-auto p-8 custom-scrollbar relative bg-gray-50/10 dark:bg-transparent">
                <div v-if="!bitacoras" class="flex flex-col items-center justify-center h-full text-center opacity-40">
                    <ArrowPathIcon class="w-12 h-12 mb-4 text-indigo-500 animate-spin" />
                    <p class="text-sm font-black uppercase tracking-widest text-gray-400">Cargando bitácora...</p>
                </div>
                
                <div v-else-if="!bitacoras.length" class="flex flex-col items-center justify-center h-full text-center opacity-40">
                    <DocumentMagnifyingGlassIcon class="w-16 h-16 mb-4 text-gray-300" />
                    <p class="text-sm font-black uppercase tracking-widest text-gray-400">Sin historial registrado</p>
                </div>
                
                <div v-else class="space-y-8 relative before:absolute before:inset-0 before:ml-4 before:-translate-x-px before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-gray-100 dark:before:via-gray-700 before:to-transparent">
                    <div v-for="(item, idx) in paginatedBitacoras" :key="item.id || idx" class="relative pl-10 group">
                        <div class="absolute left-0 top-1.5 h-8 w-8 rounded-xl bg-white dark:bg-gray-800 border-2 border-indigo-500 shadow-sm flex items-center justify-center z-10 group-hover:scale-110 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                            <FingerPrintIcon class="w-4 h-4" />
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm group-hover:shadow-md transition-all">
                            <div class="flex justify-between items-start mb-3">
                                <span class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">{{ item.accion }}</span>
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tighter">{{ formatDateTime(item.created_at) }}</span>
                            </div>
                            <p v-if="item.comentario" class="text-sm text-gray-700 dark:text-gray-300 font-medium leading-relaxed italic">
                                "{{ item.comentario }}"
                            </p>
                            <div class="mt-4 pt-3 border-t border-gray-50 dark:border-gray-700 flex items-center gap-2">
                                <div class="w-5 h-5 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center overflow-hidden">
                                    <UserIcon class="w-3 h-3 text-gray-400" />
                                </div>
                                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Ejecutado por <span class="text-gray-900 dark:text-white font-black">{{ item.user ? item.user.name : 'Sistema Inteligente' }}</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Controles de Paginación Local -->
            <div v-if="totalPages > 1" class="px-8 py-4 border-t border-gray-50 dark:border-gray-700 bg-gray-50/30 flex items-center justify-between shrink-0">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                    Página <span class="text-indigo-600 font-black">{{ currentPage }}</span> / {{ totalPages }}
                </p>
                <div class="flex gap-2">
                    <button @click="prevPage" :disabled="currentPage === 1" class="p-2 bg-white dark:bg-gray-700 rounded-xl border border-gray-200 dark:border-gray-600 disabled:opacity-30 hover:bg-indigo-50 transition-colors shadow-sm">
                        <ChevronLeftIcon class="h-4 w-4" />
                    </button>
                    <button @click="nextPage" :disabled="currentPage === totalPages" class="p-2 bg-white dark:bg-gray-700 rounded-xl border border-gray-200 dark:border-gray-600 disabled:opacity-30 hover:bg-indigo-50 transition-colors shadow-sm">
                        <ChevronRightIcon class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- HISTORIAL DE AUDITORÍA (CONTROL TÉCNICO) -->
        <div class="lg:col-span-5 flex flex-col h-[750px]">
            <div class="bg-gray-900 rounded-3xl p-8 shadow-2xl relative overflow-hidden h-full flex flex-col">
                <div class="absolute -right-10 -bottom-10 opacity-5">
                    <ShieldCheckIcon class="w-64 h-64 text-white" />
                </div>
                <div class="flex items-center gap-3 mb-8 relative z-10 shrink-0">
                    <div class="p-2 bg-green-500/20 rounded-xl border border-green-500/30">
                        <ShieldCheckIcon class="w-5 h-5 text-green-400" />
                    </div>
                    <div>
                        <h3 class="font-black text-white uppercase tracking-wider text-xs">Registro de Auditoría</h3>
                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">Trazabilidad Técnica de cambios</p>
                    </div>
                </div>
                
                <div class="relative z-10 flex-grow overflow-y-auto custom-scrollbar-dark pr-2">
                    <div v-if="!auditoria" class="flex flex-col items-center justify-center h-full text-center opacity-40 py-10">
                        <ArrowPathIcon class="w-10 h-10 mb-4 text-green-400 animate-spin" />
                        <p class="text-xs font-black uppercase tracking-widest text-gray-500">Cargando auditoría...</p>
                    </div>
                    <HistorialAuditoria v-else :eventos="auditoria" />
                </div>

                <div class="mt-8 p-4 bg-white/5 border border-white/10 rounded-2xl relative z-10 shrink-0">
                    <div class="flex items-center gap-3">
                        <ArrowPathIcon class="w-4 h-4 text-green-400 animate-spin" />
                        <span class="text-[10px] font-bold text-gray-300 uppercase tracking-widest">Monitoreo Activo de Integridad</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(79, 70, 229, 0.1); border-radius: 20px; }
.custom-scrollbar-dark::-webkit-scrollbar { width: 4px; }
.custom-scrollbar-dark::-webkit-scrollbar-thumb { background-color: rgba(255, 255, 255, 0.1); border-radius: 20px; }
</style>

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