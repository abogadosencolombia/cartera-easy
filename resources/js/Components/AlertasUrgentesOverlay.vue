<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { 
    ExclamationTriangleIcon, 
    ChevronDoubleDownIcon, 
    BellAlertIcon,
    ArrowRightCircleIcon,
    ScaleIcon,
    ArchiveBoxIcon,
    ClockIcon,
    ArrowPathIcon
} from '@heroicons/vue/24/outline';

const page = usePage();
const urgenciasCount = computed(() => page.props.auth.urgencias);

const isExpanded = ref(false);
const paginatedData = ref(null);
const isLoading = ref(false);

const fetchUrgencias = (url = '/api/urgencias/list') => {
    isLoading.value = true;
    axios.get(url)
        .then(res => {
            paginatedData.value = res.data;
        })
        .catch(err => {
            console.error("Error cargando urgencias:", err);
        })
        .finally(() => {
            isLoading.value = false;
        });
};

onMounted(() => {
    const dismissed = sessionStorage.getItem('urgencia_alert_dismissed');
    if (urgenciasCount.value?.total > 0 && !dismissed) {
        isExpanded.value = true;
        fetchUrgencias();
    }
});

const minimize = () => {
    isExpanded.value = false;
    sessionStorage.setItem('urgencia_alert_dismissed', 'true');
};

const open = () => {
    isExpanded.value = true;
    if (!paginatedData.value) fetchUrgencias();
};
</script>

<template>
    <div v-if="urgenciasCount?.total > 0">
        <!-- MODAL PANTALLA COMPLETA 100% RESPONSIVO -->
        <div v-if="isExpanded" class="fixed inset-0 z-[100] flex items-center justify-center p-2 md:p-8 bg-slate-950/95 backdrop-blur-xl animate-in fade-in duration-500">
            
            <div class="relative bg-white dark:bg-gray-900 w-full max-w-4xl rounded-[1.5rem] md:rounded-[3rem] shadow-2xl overflow-hidden border border-red-500/20 flex flex-col md:flex-row h-full max-h-[98vh] md:max-h-[85vh]">
                
                <!-- Sidebar Lateral (En móvil es cabecera compacta) -->
                <div class="w-full md:w-1/3 bg-red-600 p-4 md:p-10 flex flex-row md:flex-col justify-between items-center md:items-start shrink-0 relative overflow-hidden">
                    <div class="absolute -right-6 -bottom-6 opacity-10 hidden md:block">
                        <ExclamationTriangleIcon class="w-48 h-48 text-white rotate-12" />
                    </div>
                    <div class="relative z-10 flex items-center md:block gap-3">
                        <div class="p-2 md:p-4 bg-white/20 rounded-xl md:rounded-3xl w-fit md:mb-6">
                            <BellAlertIcon class="w-5 h-5 md:w-10 md:h-10 text-white" />
                        </div>
                        <div>
                            <h2 class="text-lg md:text-3xl font-black text-white uppercase tracking-tighter leading-tight">
                                Alerta <span class="hidden md:inline"><br/></span> Crítica
                            </h2>
                            <div class="hidden md:block h-1 w-10 bg-white rounded-full mt-4"></div>
                        </div>
                    </div>
                    <p class="hidden md:block relative z-10 text-red-100 text-[10px] font-bold uppercase tracking-widest opacity-80">
                        Atención Despacho Global
                    </p>
                </div>

                <!-- Contenido Principal -->
                <div class="flex-1 flex flex-col min-h-0 bg-white dark:bg-gray-900 overflow-hidden">
                    <!-- Área de Scroll -->
                    <div class="flex-1 p-5 md:p-10 overflow-y-auto custom-scrollbar space-y-6">
                        
                        <p class="text-base md:text-lg text-gray-600 dark:text-gray-300 font-medium leading-tight">
                            Hay <span class="font-black text-red-600 underline decoration-2 underline-offset-4">{{ urgenciasCount.total }}</span> expedientes pendientes.
                        </p>

                        <!-- Módulos Compactos -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 md:p-5 bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl border border-indigo-100 dark:border-indigo-800">
                                <span class="text-[8px] font-black text-indigo-400 uppercase tracking-widest block mb-1">Abogados Col.</span>
                                <span class="text-xl md:text-2xl font-black text-indigo-700 dark:text-indigo-300">{{ urgenciasCount.radicados_vencidos + urgenciasCount.radicados_riesgo }}</span>
                            </div>
                            <div class="p-3 md:p-5 bg-blue-50 dark:bg-blue-900/20 rounded-2xl border border-blue-100 dark:border-blue-800">
                                <span class="text-[8px] font-black text-blue-400 uppercase tracking-widest block mb-1">Cooperativas</span>
                                <span class="text-xl md:text-2xl font-black text-blue-700 dark:text-blue-300">{{ urgenciasCount.casos_vencidos }}</span>
                            </div>
                        </div>

                        <!-- Lista Paginada -->
                        <div class="space-y-2 relative min-h-[250px]">
                            <div v-if="isLoading" class="absolute inset-0 bg-white/60 dark:bg-gray-900/60 z-10 flex items-center justify-center rounded-2xl backdrop-blur-sm">
                                <ArrowPathIcon class="w-8 h-8 text-red-600 animate-spin" />
                            </div>

                            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Listado de Urgencias:</p>
                            
                            <div v-for="item in paginatedData?.data" :key="item.id + item.tipo_modulo" class="group bg-gray-50 dark:bg-gray-800/50 p-3 md:p-4 rounded-xl flex items-center justify-between border border-transparent hover:border-red-200 transition-all">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div :class="item.estado === 'VENCIDO' ? 'bg-red-500' : 'bg-amber-400'" class="p-1.5 rounded-lg shrink-0">
                                        <ClockIcon class="w-4 h-4 text-white" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <p class="text-[11px] font-black text-gray-800 dark:text-gray-100 uppercase truncate">{{ item.identificador }}</p>
                                            <span class="text-[7px] font-black px-1 rounded bg-red-100 text-red-600 uppercase" v-if="item.estado === 'VENCIDO'">Vencido</span>
                                        </div>
                                        <p class="text-[9px] text-gray-500 font-bold truncate italic leading-tight">{{ item.partes }}</p>
                                    </div>
                                </div>
                                <Link :href="item.url" @click="minimize" class="p-1.5 bg-white dark:bg-gray-700 rounded-lg shadow-sm text-gray-400 hover:text-red-600 shrink-0 ml-2">
                                    <ArrowRightCircleIcon class="w-5 h-5" />
                                </Link>
                            </div>

                            <!-- Sin datos -->
                            <div v-if="!isLoading && paginatedData?.data.length === 0" class="py-10 text-center">
                                <p class="text-[10px] font-black text-gray-400 uppercase italic">No se pudieron cargar los detalles</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer: Solo Minimizar y Paginación -->
                    <div class="p-5 md:p-8 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-700 shrink-0">
                        <div class="flex flex-col gap-4">
                            <!-- Controles de Paginación Mejorados -->
                            <div v-if="paginatedData?.last_page > 1" class="flex items-center justify-between px-2">
                                <button 
                                    type="button"
                                    @click.prevent="fetchUrgencias(paginatedData.prev_page_url)" 
                                    :disabled="!paginatedData.prev_page_url || isLoading"
                                    class="text-[10px] font-black uppercase flex items-center gap-1 text-gray-400 hover:text-red-600 disabled:opacity-10 transition-colors"
                                >
                                    &larr; Ant.
                                </button>
                                <span class="text-[10px] font-black text-gray-400 bg-gray-200 dark:bg-gray-700 px-3 py-1 rounded-full uppercase tracking-tighter">
                                    Pag. {{ paginatedData.current_page }} / {{ paginatedData.last_page }}
                                </span>
                                <button 
                                    type="button"
                                    @click.prevent="fetchUrgencias(paginatedData.next_page_url)" 
                                    :disabled="!paginatedData.next_page_url || isLoading"
                                    class="text-[10px] font-black uppercase flex items-center gap-1 text-gray-400 hover:text-red-600 disabled:opacity-10 transition-colors"
                                >
                                    Sig. &rarr;
                                </button>
                            </div>

                            <button @click="minimize" class="w-full py-4 bg-gray-900 dark:bg-gray-700 text-white rounded-2xl font-black text-xs md:text-sm uppercase tracking-widest hover:bg-red-600 transition-all flex items-center justify-center gap-2 shadow-lg">
                                <ChevronDoubleDownIcon class="w-5 h-5" /> Minimizar Alerta
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BURBUJA MINIMIZADA -->
        <button 
            v-else 
            @click="open" 
            class="fixed bottom-4 left-4 md:bottom-8 md:left-8 z-[90] flex items-center gap-3 p-2 md:p-3 pr-4 md:pr-8 bg-red-600 text-white rounded-2xl md:rounded-3xl shadow-[0_20px_50px_rgba(220,38,38,0.4)] hover:scale-105 transition-all group animate-bounce hover:animate-none"
        >
            <div class="h-10 w-10 md:h-12 md:w-12 bg-white rounded-xl md:rounded-2xl flex items-center justify-center text-red-600 shadow-inner font-black text-lg md:text-xl">
                {{ urgenciasCount.total }}
            </div>
            <div class="flex flex-col items-start text-left">
                <span class="text-[9px] md:text-[10px] font-black uppercase tracking-widest leading-none">Alertas</span>
                <span class="text-[7px] md:text-[8px] font-bold text-red-100 uppercase tracking-tighter">Globales</span>
            </div>
        </button>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(239, 68, 68, 0.2); border-radius: 10px; }
</style>
