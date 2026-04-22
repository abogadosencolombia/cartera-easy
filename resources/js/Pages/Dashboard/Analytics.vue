<script setup>
import { computed, ref, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
    Chart as ChartJS, 
    Title, 
    Tooltip, 
    Legend, 
    BarElement, 
    CategoryScale, 
    LinearScale, 
    PointElement, 
    LineElement, 
    ArcElement,
    Filler
} from 'chart.js';
import { Doughnut, Line, Bar } from 'vue-chartjs';
import Pagination from '@/Components/Pagination.vue';
import { 
    ScaleIcon, 
    UserGroupIcon, 
    CurrencyDollarIcon, 
    ArrowTrendingUpIcon,
    BuildingLibraryIcon,
    BriefcaseIcon,
    CheckBadgeIcon,
    ClockIcon,
    ChartPieIcon,
    CursorArrowRaysIcon,
    ArrowPathIcon
} from '@heroicons/vue/24/outline';

// Registrar componentes de Chart.js
ChartJS.register(
    Title, Tooltip, Legend, BarElement, CategoryScale, 
    LinearScale, PointElement, LineElement, ArcElement, Filler
);

const props = defineProps({
    stats: Object,
    charts: Object
});

// --- LÓGICA DE PESTAÑAS CON PERSISTENCIA EN URL ---
const getInitialTab = () => {
    if (typeof window === 'undefined') return 'graficas';
    const params = new URLSearchParams(window.location.search);
    if (params.has('page_actividad')) return 'acciones';
    return params.get('tab') || 'graficas';
};

const activeTab = ref(getInitialTab());

const setActiveTab = (tab) => {
    activeTab.value = tab;
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    if (tab === 'graficas') url.searchParams.delete('page_actividad');
    window.history.replaceState({}, '', url);
};

// --- CONTROL DE RENDERIZADO DE GRÁFICOS ---
const isReady = ref(false);
onMounted(() => {
    // Retraso intencional para asegurar que el DOM esté listo y Tailwind haya calculado anchos
    setTimeout(() => { isReady.value = true; }, 200);
});

// Forzamos re-render si cambia la pestaña o los datos están listos
const chartKey = computed(() => isReady.value ? `ready-${activeTab.value}` : 'loading');

// --- CONFIGURACIÓN DE GRÁFICOS (COMPUTADOS PARA ASEGURAR CARGA) ---

// 1. Salud del Despacho (Doughnut)
const saludData = computed(() => {
    if (!props.charts?.salud) return null;
    return {
        labels: ['Al Día', 'En Riesgo', 'Vencidos'],
        datasets: [{
            data: [props.charts.salud.al_dia, props.charts.salud.en_riesgo, props.charts.salud.vencidos],
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
            hoverOffset: 4,
            borderWidth: 0
        }]
    };
});

// 2. Carga de Trabajo (Bar Horizontal)
const cargaData = computed(() => {
    if (!props.charts?.carga) return null;
    return {
        labels: props.charts.carga.map(a => a.name),
        datasets: [{
            label: 'Procesos Activos',
            data: props.charts.carga.map(a => a.procesos_count),
            backgroundColor: '#6366f1',
            borderRadius: 8,
        }]
    };
});

// 3. Finanzas: Recaudo vs Programado (Bar/Line Mixto)
const finanzasData = computed(() => {
    if (!props.charts?.finanzas) return null;
    return {
        labels: props.charts.finanzas.labels,
        datasets: [
            {
                type: 'line',
                label: 'Meta Programada',
                data: props.charts.finanzas.programado,
                borderColor: '#fbbf24',
                borderWidth: 2,
                fill: false,
                pointBackgroundColor: '#fbbf24'
            },
            {
                type: 'bar',
                label: 'Recaudado Real',
                data: props.charts.finanzas.recaudado,
                backgroundColor: '#6366f1',
                borderRadius: 6
            }
        ]
    };
});

// 4. Crecimiento de Casos (Line Area)
const crecimientoData = computed(() => {
    if (!props.charts?.crecimiento) return null;
    return {
        labels: props.charts.crecimiento.labels,
        datasets: [{
            label: 'Nuevos Expedientes',
            data: props.charts.crecimiento.data,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
        }]
    };
});

const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 20,
                font: { size: 10, weight: 'bold' }
            }
        }
    }
};

const horizontalBarOptions = {
    ...commonOptions,
    indexAxis: 'y',
    scales: {
        x: { grid: { display: false } },
        y: { grid: { display: false } }
    }
};
</script>

<template>
    <Head title="Analítica y Salud del Despacho" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-indigo-600 rounded-2xl shadow-lg shadow-indigo-200 dark:shadow-none">
                        <ArrowTrendingUpIcon class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight tracking-tight">Centro de Inteligencia Judicial</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Panel estratégico y monitor de productividad.</p>
                    </div>
                </div>

                <!-- SELECTOR DE VISTA (TABS) -->
                <div class="flex p-1 bg-gray-100 dark:bg-gray-800 rounded-2xl">
                    <button 
                        @click="setActiveTab('radar')"
                        :class="[activeTab === 'radar' ? 'bg-white dark:bg-gray-700 shadow-md text-red-600' : 'text-gray-500 hover:text-gray-700']"
                        class="flex items-center gap-2 px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all"
                    >
                        <ExclamationTriangleIcon class="w-4 h-4" /> Radar
                    </button>
                    <button 
                        @click="setActiveTab('graficas')"
                        :class="[activeTab === 'graficas' ? 'bg-white dark:bg-gray-700 shadow-md text-indigo-600' : 'text-gray-500 hover:text-gray-700']"
                        class="flex items-center gap-2 px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all"
                    >
                        <ChartPieIcon class="w-4 h-4" /> Gráficas
                    </button>
                    <button 
                        @click="setActiveTab('acciones')"
                        :class="[activeTab === 'acciones' ? 'bg-white dark:bg-gray-700 shadow-md text-indigo-600' : 'text-gray-500 hover:text-gray-700']"
                        class="flex items-center gap-2 px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all"
                    >
                        <CursorArrowRaysIcon class="w-4 h-4" /> Acciones
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 bg-gray-50/50 dark:bg-gray-900/50 min-h-screen">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                
                <!-- SECCIÓN 0: RADAR DE IMPULSO (INTELIGENCIA) -->
                <div v-if="activeTab === 'radar'" class="space-y-8 animate-in fade-in slide-in-from-top-4 duration-700">
                    <div class="bg-white dark:bg-gray-800 p-8 md:p-12 rounded-[3rem] shadow-xl border border-red-100 dark:border-red-900/20 relative overflow-hidden">
                        <div class="absolute right-0 top-0 p-10 opacity-5 pointer-events-none">
                            <ExclamationTriangleIcon class="w-64 h-64 text-red-600 rotate-12" />
                        </div>

                        <div class="relative z-10">
                            <div class="flex items-center gap-4 mb-10">
                                <div class="p-4 bg-red-600 rounded-3xl shadow-lg shadow-red-200 dark:shadow-none">
                                    <ExclamationTriangleIcon class="w-8 h-8 text-white" />
                                </div>
                                <div>
                                    <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter leading-none">Radar de Impulso Procesal</h3>
                                    <p class="text-sm text-gray-500 font-bold uppercase tracking-widest mt-1">Inteligencia de Acción Inmediata</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <div v-for="item in charts.radar" :key="item.id + item.tipo" class="group bg-gray-50 dark:bg-gray-900/50 p-8 rounded-[2.5rem] border border-transparent hover:border-red-200 dark:hover:border-red-900 transition-all hover:shadow-2xl hover:shadow-red-100 dark:hover:shadow-none relative">
                                    <div class="flex justify-between items-start mb-6">
                                        <div>
                                            <span class="px-3 py-1 bg-white dark:bg-gray-800 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-sm" :class="item.tipo === 'CASO' ? 'text-blue-600' : 'text-indigo-600'">
                                                {{ item.tipo === 'CASO' ? 'Cooperativa' : 'Radicado Col.' }}
                                            </span>
                                            <h4 class="mt-3 text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">{{ item.identificador }}</h4>
                                        </div>
                                        <div :class="item.prioridad === 'CRÍTICA' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600'" class="px-4 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest animate-pulse">
                                            Prioridad {{ item.prioridad }}
                                        </div>
                                    </div>

                                    <div class="space-y-4 mb-8">
                                        <div class="flex items-center gap-3">
                                            <div class="w-2 h-2 rounded-full bg-gray-300"></div>
                                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Etapa: <span class="text-gray-900 dark:text-gray-300 font-black">{{ item.etapa_actual }}</span></p>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div class="w-2 h-2 rounded-full bg-gray-300"></div>
                                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Inactividad: <span class="text-red-600 font-black">{{ item.dias_inactivo }} Días</span></p>
                                        </div>
                                    </div>

                                    <div class="p-6 bg-white dark:bg-gray-800 rounded-[1.5rem] border-l-4 border-red-500 shadow-inner mb-6">
                                        <p class="text-[10px] font-black text-red-500 uppercase tracking-widest mb-1">Acción Recomendada:</p>
                                        <p class="text-sm font-bold text-gray-800 dark:text-gray-200 leading-relaxed">{{ item.accion_sugerida }}</p>
                                    </div>

                                    <Link :href="item.url" class="flex items-center justify-center gap-2 w-full py-4 bg-gray-900 dark:bg-gray-700 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-red-600 transition-all group-hover:scale-[1.02]">
                                        Ejecutar Impulso <ArrowRightCircleIcon class="w-5 h-5" />
                                    </Link>
                                </div>
                            </div>

                            <div v-if="charts.radar.length === 0" class="text-center py-20">
                                <CheckBadgeIcon class="w-20 h-20 text-emerald-500 mx-auto mb-4 opacity-20" />
                                <h4 class="text-xl font-black text-gray-300 uppercase tracking-widest">¡Despacho al Día!</h4>
                                <p class="text-sm text-gray-400 font-bold uppercase">No se detectan procesos estancados hoy.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 1: GRÁFICAS Y MÉTRICAS -->
                <div v-if="activeTab === 'graficas'" class="space-y-8 animate-in fade-in zoom-in-95 duration-500">
                    <!-- KPI CARDS -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden group">
                            <div class="absolute -right-4 -top-4 opacity-5 group-hover:scale-110 transition-transform text-indigo-600">
                                <ScaleIcon class="w-24 h-24" />
                            </div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Procesos Activos</p>
                            <h3 class="text-3xl font-black text-gray-900 dark:text-white">{{ stats.total_activos }}</h3>
                            <div class="mt-4 flex items-center gap-2">
                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-[10px] font-black rounded-lg uppercase">En Gestión</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden group">
                            <div class="absolute -right-4 -top-4 opacity-5 group-hover:scale-110 transition-transform text-emerald-600">
                                <CheckBadgeIcon class="w-24 h-24" />
                            </div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Tasa de Cierre (30d)</p>
                            <h3 class="text-3xl font-black text-emerald-600">{{ stats.tasa_cierre }}%</h3>
                            <div class="mt-4 flex items-center gap-2">
                                <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-lg uppercase">Rendimiento</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden group">
                            <div class="absolute -right-4 -top-4 opacity-5 group-hover:scale-110 transition-transform text-amber-600">
                                <CurrencyDollarIcon class="w-24 h-24" />
                            </div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Recaudo este Mes</p>
                            <h3 class="text-3xl font-black text-gray-900 dark:text-white">${{ stats.recaudo_mes }}</h3>
                            <div class="mt-4 flex items-center gap-2">
                                <span class="px-2 py-0.5 bg-amber-50 text-amber-600 text-[10px] font-black rounded-lg uppercase">Cobros Efectivos</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden group">
                            <div class="absolute -right-4 -top-4 opacity-5 group-hover:scale-110 transition-transform text-rose-600">
                                <ClockIcon class="w-24 h-24" />
                            </div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Archivados</p>
                            <h3 class="text-3xl font-black text-gray-900 dark:text-white">{{ stats.total_cerrados }}</h3>
                            <div class="mt-4 flex items-center gap-2">
                                <span class="px-2 py-0.5 bg-rose-50 text-rose-600 text-[10px] font-black rounded-lg uppercase">Histórico</span>
                            </div>
                        </div>
                    </div>

                    <!-- GRÁFICOS PRINCIPALES -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                        <div class="lg:col-span-4 bg-white dark:bg-gray-800 p-8 rounded-[2.5rem] shadow-sm border border-gray-100 dark:border-gray-700">
                            <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-8 flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div> Salud del Despacho
                            </h3>
                            <div class="h-64 relative">
                                <Doughnut v-if="isReady && saludData" :data="saludData" :options="commonOptions" :key="chartKey" />
                                <div v-else class="absolute inset-0 flex items-center justify-center bg-gray-50/50 dark:bg-gray-900/50 rounded-2xl">
                                    <ArrowPathIcon class="w-8 h-8 text-indigo-500 animate-spin" />
                                </div>
                            </div>
                            <div class="mt-8 grid grid-cols-3 gap-2">
                                <div class="text-center p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-2xl">
                                    <p class="text-[10px] font-black text-emerald-600 uppercase">{{ charts.salud.al_dia }}</p>
                                    <p class="text-[8px] font-bold text-emerald-400 uppercase">Al día</p>
                                </div>
                                <div class="text-center p-3 bg-amber-50 dark:bg-amber-900/20 rounded-2xl">
                                    <p class="text-[10px] font-black text-amber-600 uppercase">{{ charts.salud.en_riesgo }}</p>
                                    <p class="text-[8px] font-bold text-amber-400 uppercase">Riesgo</p>
                                </div>
                                <div class="text-center p-3 bg-rose-50 dark:bg-rose-900/20 rounded-2xl">
                                    <p class="text-[10px] font-black text-rose-600 uppercase">{{ charts.salud.vencidos }}</p>
                                    <p class="text-[8px] font-bold text-rose-400 uppercase">Vencidos</p>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-8 bg-white dark:bg-gray-800 p-8 rounded-[2.5rem] shadow-sm border border-gray-100 dark:border-gray-700">
                            <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-8 flex items-center gap-2">
                                <UserGroupIcon class="w-5 h-5 text-indigo-500" /> Carga Total (Casos Cooperativas + Casos Abogados en Colombia)
                            </h3>
                            <div class="h-80 relative">
                                <Bar v-if="isReady && cargaData" :data="cargaData" :options="horizontalBarOptions" :key="chartKey" />
                                <div v-else class="absolute inset-0 flex items-center justify-center">
                                    <ArrowPathIcon class="w-8 h-8 text-indigo-500 animate-spin" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- GRÁFICOS SECUNDARIOS -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                        <div class="lg:col-span-7 bg-white dark:bg-gray-800 p-8 rounded-[2.5rem] shadow-sm border border-gray-100 dark:border-gray-700">
                            <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-8 flex items-center gap-2">
                                <CurrencyDollarIcon class="w-5 h-5 text-indigo-500" /> Cobros Efectivos vs Metas Programadas
                            </h3>
                            <div class="h-80 relative">
                                <Bar v-if="isReady && finanzasData" :data="finanzasData" :options="commonOptions" :key="chartKey" />
                                <div v-else class="absolute inset-0 flex items-center justify-center">
                                    <ArrowPathIcon class="w-8 h-8 text-indigo-500 animate-spin" />
                                </div>
                            </div>
                        </div>
                        <div class="lg:col-span-5 bg-white dark:bg-gray-800 p-8 rounded-[2.5rem] shadow-sm border border-gray-100 dark:border-gray-700">
                            <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-8 flex items-center gap-2">
                                <ArrowTrendingUpIcon class="w-5 h-5 text-emerald-500" /> Crecimiento Consolidado de Expedientes
                            </h3>
                            <div class="h-80 relative">
                                <Line v-if="isReady && crecimientoData" :data="crecimientoData" :options="commonOptions" :key="chartKey" />
                                <div v-else class="absolute inset-0 flex items-center justify-center">
                                    <ArrowPathIcon class="w-8 h-8 text-indigo-500 animate-spin" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RANKINGS -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="bg-indigo-900 rounded-[2.5rem] p-8 text-white shadow-xl overflow-hidden relative">
                            <BriefcaseIcon class="absolute -right-10 -bottom-10 opacity-10 w-64 h-64" />
                            <h3 class="text-sm font-black uppercase tracking-[0.2em] mb-8 relative z-10">Especialidades</h3>
                            <div class="space-y-6 relative z-10">
                                <div v-for="(item, idx) in charts.especialidades" :key="idx" class="flex items-center justify-between">
                                    <span class="text-sm font-bold uppercase truncate max-w-[200px]">{{ item.nombre }}</span>
                                    <span class="text-xs font-black bg-white/10 px-3 py-1 rounded-lg">{{ item.total }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] p-8 border border-gray-100 dark:border-gray-700 shadow-sm">
                            <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-8">Top Despachos</h3>
                            <div class="space-y-6">
                                <div v-for="(item, idx) in charts.juzgados" :key="idx" class="flex items-center justify-between">
                                    <span class="text-xs font-black text-gray-700 dark:text-gray-300 truncate max-w-[250px] uppercase">{{ item.nombre }}</span>
                                    <span class="text-xs font-black text-indigo-600 bg-indigo-50 px-2 py-1 rounded-lg">{{ item.total }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: ACCIONES Y PRODUCTIVIDAD -->
                <div v-if="activeTab === 'acciones'" class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                        <!-- Monitor de Actividad -->
                        <div class="lg:col-span-8 bg-white dark:bg-gray-800 p-8 rounded-[2.5rem] shadow-sm border border-gray-100 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-8">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest flex items-center gap-2">
                                    <ClockIcon class="w-5 h-5 text-indigo-500" /> Bitácora de Acciones Reales
                                </h3>
                                <div class="flex items-center gap-2 px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full text-[9px] font-black uppercase">
                                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-600 animate-ping"></div> En Vivo
                                </div>
                            </div>

                            <div class="flow-root">
                                <ul role="list" class="-mb-8">
                                    <li v-for="(event, eventIdx) in charts.actividad.data" :key="event.id">
                                        <div class="relative pb-8">
                                            <span v-if="eventIdx !== charts.actividad.data.length - 1" class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-100 dark:bg-gray-700" aria-hidden="true"></span>
                                            <div class="relative flex items-start space-x-4">
                                                <div class="h-10 w-10 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 flex items-center justify-center font-black text-xs text-indigo-600 uppercase">{{ event.usuario[0] }}</div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                                        <span 
                                                            class="text-[8px] font-black px-1.5 py-0.5 rounded uppercase tracking-tighter"
                                                            :class="{
                                                                'bg-blue-100 text-blue-700': event.modulo === 'Casos Cooperativas',
                                                                'bg-indigo-100 text-indigo-700': event.modulo === 'Casos Abogados en Colombia',
                                                                'bg-emerald-100 text-emerald-700': event.modulo === 'Finanzas',
                                                                'bg-gray-100 text-gray-500': ['Directorio', 'Sistema'].includes(event.modulo)
                                                            }"
                                                        >
                                                            {{ event.modulo }}
                                                        </span>
                                                        <div class="text-xs">
                                                            <span class="font-black text-gray-900 dark:text-white uppercase">{{ event.usuario }}</span>
                                                            <span class="mx-2 text-gray-400">→</span>
                                                            <span class="font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-tighter bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded">{{ event.evento }}</span>
                                                        </div>
                                                    </div>
                                                    <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed font-medium break-words">
                                                        {{ event.descripcion }}
                                                    </p>
                                                    <div class="mt-2 flex items-center gap-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                                                        <span>{{ event.fecha }}</span>
                                                        <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                                        <span>{{ event.hora }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <!-- PAGINACIÓN DE ACCIONES -->
                            <div v-if="charts.actividad.links.length > 3" class="mt-12 flex justify-center pt-8 border-t border-gray-50 dark:border-gray-700">
                                <Pagination :links="charts.actividad.links" />
                            </div>
                        </div>

                        <!-- Productividad Hoy -->
                        <div class="lg:col-span-4 space-y-8">
                            <div class="bg-white dark:bg-gray-800 p-8 rounded-[2.5rem] shadow-sm border border-gray-100 dark:border-gray-700">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-8 flex items-center gap-2">
                                    <CheckBadgeIcon class="w-5 h-5 text-emerald-500" /> Rendimiento Hoy
                                </h3>
                                <div class="space-y-6">
                                    <div v-if="charts.productividad.length === 0" class="text-center py-10">
                                        <p class="text-xs text-gray-400 font-bold uppercase italic">Sin acciones registradas todavía</p>
                                    </div>
                                    <div v-for="(user, idx) in charts.productividad" :key="idx" class="flex items-center justify-between">
                                        <span class="text-xs font-black text-gray-700 dark:text-gray-300 uppercase truncate pr-4">{{ user.name }}</span>
                                        <div class="flex items-center gap-2 bg-emerald-50 dark:bg-emerald-900/20 px-3 py-1 rounded-xl border border-emerald-100 dark:border-emerald-800">
                                            <span class="text-xs font-black text-emerald-600">{{ user.total }}</span>
                                            <CursorArrowRaysIcon class="w-3 h-3 text-emerald-400" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-indigo-600 p-8 rounded-[2.5rem] text-white shadow-xl shadow-indigo-100 dark:shadow-none">
                                <h4 class="text-xs font-black uppercase tracking-[0.2em] mb-4">¿Qué monitoreamos?</h4>
                                <ul class="space-y-3 text-xs font-bold text-indigo-100">
                                    <li>✅ Casos Cooperativas</li>
                                    <li>✅ Casos Abogados en Colombia</li>
                                    <li>✅ Gestión de Pagos y Finanzas</li>
                                    <li>✅ Actuaciones y Memoriales</li>
                                    <li>✅ Carga de Documentos</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
