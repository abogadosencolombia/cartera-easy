<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { Chart, registerables } from 'chart.js';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// --- ICONOS DE MANDO COMPLETOS Y VERIFICADOS ---
import {
    BanknotesIcon, ShieldExclamationIcon, BriefcaseIcon, ArrowPathIcon,
    UserGroupIcon, ChartBarIcon, ChartPieIcon, ClockIcon,
    DocumentMinusIcon, ExclamationTriangleIcon, FolderMinusIcon, ArrowDownTrayIcon,
    ArrowTrendingUpIcon, ChevronDownIcon, BuildingLibraryIcon,
    CalendarDaysIcon, BellIcon
} from '@heroicons/vue/24/outline';

Chart.register(...registerables);

// --- PROPS UNIFICADOS ---
const props = defineProps({
    // Props de tu reporte estratégico
    kpis: Object,
    graficas: Object,
    rankingAbogados: Array,
    cooperativas: Array,
    abogadosYGestores: Array,
    filtros: Object,
    consolidadoCooperativa: Object,
    // Nuevos props para el reporte de cumplimiento
    statsCumplimiento: Object,
    listadoFallas: Array,
});

// --- LÓGICA DE PESTAÑAS ---
const activeTab = ref('estrategico'); // Inicia en la pestaña estratégica

// --- Lógica para el menú de exportación (tu código) ---
const isExportMenuOpen = ref(false);
const exportMenu = ref(null);
const exportarDatos = (tipo) => {
    const queryParams = { ...filtroForm.value, tipo: tipo };
    const query = new URLSearchParams(queryParams).toString();

    let exportRoute = '';

    // Decidimos qué ruta usar según la pestaña activa
    if (activeTab.value === 'cumplimiento') {
        exportRoute = route('reportes.cumplimiento.exportar');
    } else {
        // Por defecto, usa la exportación estratégica
        exportRoute = route('reportes.exportar');
    }

    // Construimos la URL final y redirigimos para iniciar la descarga
    window.location.href = `${exportRoute}?${query}`;
    isExportMenuOpen.value = false;
};
const closeExportMenu = (event) => {
    if (exportMenu.value && !exportMenu.value.contains(event.target)) {
        isExportMenuOpen.value = false;
    }
};
onMounted(() => { document.addEventListener('click', closeExportMenu); });
onUnmounted(() => { document.removeEventListener('click', closeExportMenu); });

// --- Lógica de filtros (tu código) ---
const filtroForm = ref({
    cooperativa_id: props.filtros.cooperativa_id || '',
    user_id: props.filtros.user_id || '',
    tipo_proceso: props.filtros.tipo_proceso || '',
    estado_proceso: props.filtros.estado_proceso || '',
    fecha_desde: props.filtros.fecha_desde || '',
    fecha_hasta: props.filtros.fecha_hasta || '',
});

// --- Refs para los lienzos de las gráficas (tu código + los nuevos) ---
const chartPagosMensuales = ref(null);
const chartCasosPorEstado = ref(null);
const chartCarteraPorEdad = ref(null);
const chartMoraMensual = ref(null);
const chartAlertasPorTipo = ref(null);
const chartTendenciaSemanal = ref(null);

// --- Lógica para GRÁFICAS DE ALERTAS (tu código) ---
const alertasPorTipoData = computed(() => {
    if (!props.graficas || !props.graficas.alertas_por_tipo) return null;
    const labels = Object.keys(props.graficas.alertas_por_tipo).map(label => label.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
    const data = Object.values(props.graficas.alertas_por_tipo);
    return {
        labels: labels,
        datasets: [{
            label: 'Alertas por Tipo',
            backgroundColor: ['#EF4444', '#F59E0B', '#3B82F6', '#10B981', '#6366F1'],
            data: data,
            borderRadius: 4,
        }]
    };
});

const tendenciaSemanalData = computed(() => {
    if (!props.graficas || !props.graficas.tendencia_semanal_alertas) return null;
    const labels = props.graficas.tendencia_semanal_alertas.map(item => new Date(item.date + 'T00:00:00').toLocaleDateString('es-CO', { month: 'short', day: 'numeric' }));
    const data = props.graficas.tendencia_semanal_alertas.map(item => item.total);
    return {
        labels: labels,
        datasets: [{
            label: 'Alertas Generadas',
            borderColor: '#8B5CF6',
            backgroundColor: 'hsla(262, 83%, 67%, 0.2)',
            data: data,
            fill: true,
            tension: 0.4
        }]
    };
});

// --- Función centralizada para renderizar y actualizar gráficas (tu código) ---
const renderCharts = () => {
    const renderChart = (canvasRef, type, data, options = {}) => {
        if (canvasRef.value?.chartInstance) {
            canvasRef.value.chartInstance.destroy();
        }
        const ctx = canvasRef.value?.getContext('2d');
        if (ctx && data && data.labels && data.labels.length > 0) {
            canvasRef.value.chartInstance = new Chart(ctx, { type, data, options });
        }
    };

    // Tus gráficas existentes
    renderChart(chartPagosMensuales, 'line', {
        labels: props.graficas.pagos_mensuales.map(item => new Date(item.anio, item.mes - 1).toLocaleString('es-CO', { month: 'short', year: '2-digit' })),
        datasets: [{ label: 'Total Recuperado', data: props.graficas.pagos_mensuales.map(item => item.total), borderColor: 'hsl(147, 71%, 41%)', backgroundColor: 'hsla(147, 71%, 41%, 0.2)', fill: true, tension: 0.4 }]
    }, { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } });

    renderChart(chartCasosPorEstado, 'bar', {
        labels: Object.keys(props.graficas.casos_por_estado),
        datasets: [{ label: 'Número de Casos', data: Object.values(props.graficas.casos_por_estado), backgroundColor: ['#36A2EB', '#FFCE56', '#FF6384', '#4BC0C0', '#9966FF'], borderRadius: 4 }]
    }, { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } });

    if (props.graficas.cartera_por_edad && Object.keys(props.graficas.cartera_por_edad).length > 0) {
        renderChart(chartCarteraPorEdad, 'doughnut', {
            labels: Object.keys(props.graficas.cartera_por_edad),
            datasets: [{ label: 'Cartera en Mora', data: Object.values(props.graficas.cartera_por_edad), backgroundColor: ['#FFCE56', '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0'] }]
        }, { responsive: true, maintainAspectRatio: false });
    }

    if (props.graficas.mora_mensual && props.graficas.mora_mensual.length > 0) {
        renderChart(chartMoraMensual, 'line', {
            labels: props.graficas.mora_mensual.map(item => new Date(item.anio, item.mes - 1).toLocaleString('es-CO', { month: 'short', year: '2-digit' })),
            datasets: [{ label: 'Nueva Mora Mensual', data: props.graficas.mora_mensual.map(item => item.total), borderColor: 'hsl(348, 83%, 47%)', backgroundColor: 'hsla(348, 83%, 47%, 0.2)', fill: true, tension: 0.4 }]
        }, { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } });
    }

    if (props.graficas.alertas_por_tipo) {
        renderChart(chartAlertasPorTipo, 'bar', alertasPorTipoData.value, { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } });
    }

    if (props.graficas.tendencia_semanal_alertas) {
        renderChart(chartTendenciaSemanal, 'line', tendenciaSemanalData.value, { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } });
    }
};

onMounted(() => { renderCharts(); });
watch(() => props.graficas, () => { renderCharts(); }, { deep: true });

// --- Tus funciones de formato y filtros ---
const formatCurrency = (value) => {
    if (value === null || value === undefined) return '$ 0';
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value);
};
const aplicarFiltros = () => {
    const query = { ...filtroForm.value };
    Object.keys(query).forEach(key => { if (!query[key]) delete query[key]; });
    router.get(route('reportes.index'), query, { preserveState: true, replace: true });
};
const limpiarFiltros = () => {
    filtroForm.value = { cooperativa_id: '', user_id: '', tipo_proceso: '', estado_proceso: '', fecha_desde: '', fecha_hasta: '' };
    aplicarFiltros();
};

// =================================================================
// ===== INICIO DE LA NUEVA SECCIÓN: LÓGICA PARA CUMPLIMIENTO =====
// =================================================================
const riesgoClasses = {
    alto: 'bg-red-100 text-red-800 border-red-500',
    medio: 'bg-yellow-100 text-yellow-800 border-yellow-500',
    bajo: 'bg-blue-100 text-blue-800 border-blue-500',
};

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

const formatDateForCompliance = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('es-CO', options);
};
// =================================================================
// ===== FIN DE LA NUEVA SECCIÓN: LÓGICA PARA CUMPLIMIENTO =====
// =================================================================
</script>
<template>

    <Head title="Centro de Mando" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Centro de Mando Estratégico
                </h2>
                <div class="relative" ref="exportMenu">
                    <PrimaryButton @click="isExportMenuOpen = !isExportMenuOpen">
                        <ArrowDownTrayIcon class="h-5 w-5 mr-2" />
                        Exportar
                        <ChevronDownIcon class="h-5 w-5 ml-1 -mr-1" />
                    </PrimaryButton>
                    <transition enter-active-class="transition ease-out duration-100"
                        enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="transform opacity-100 scale-100"
                        leave-to-class="transform opacity-0 scale-95">
                        <div v-if="isExportMenuOpen"
                            class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5 focus:outline-none z-10">
                            <div class="py-1">
                                <a @click.prevent="exportarDatos('xlsx')" href="#"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">Excel
                                    (.xlsx)</a>
                                <a @click.prevent="exportarDatos('csv')" href="#"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">CSV
                                    (.csv)</a>
                                <a @click.prevent="exportarDatos('pdf')" href="#"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600">PDF
                                    (.pdf)</a>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <!-- Pestañas de Navegación -->
                <div class="mb-2 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button @click="activeTab = 'estrategico'"
                            :class="[activeTab === 'estrategico' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                            Reporte Estratégico
                        </button>
                        <button @click="activeTab = 'cumplimiento'"
                            :class="[activeTab === 'cumplimiento' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
                            Reporte de Cumplimiento
                        </button>
                    </nav>
                </div>

                <!-- SECCIÓN DE FILTROS (Común para ambas pestañas) -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Filtros Globales del
                            Reporte</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label for="filtro_cooperativa"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cooperativa</label>
                                <select v-model="filtroForm.cooperativa_id" id="filtro_cooperativa"
                                    @change="aplicarFiltros"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Todas</option>
                                    <option v-for="coop in cooperativas" :key="coop.id" :value="coop.id">{{ coop.nombre
                                        }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="filtro_abogado"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gestor
                                    Asignado</label>
                                <select v-model="filtroForm.user_id" id="filtro_abogado"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Todos</option>
                                    <option v-for="user in abogadosYGestores" :key="user.id" :value="user.id">{{
                                        user.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="filtro_tipo_proceso"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de
                                    Proceso</label>
                                <select v-model="filtroForm.tipo_proceso" id="filtro_tipo_proceso"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Todos</option>
                                    <option value="ejecutivo singular">Ejecutivo Singular</option>
                                    <option value="hipotecario">Hipotecario</option>
                                    <option value="prendario">Prendario</option>
                                    <option value="libranza">Libranza</option>
                                </select>
                            </div>
                            <div>
                                <label for="filtro_estado_proceso"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado del
                                    Proceso</label>
                                <select v-model="filtroForm.estado_proceso" id="filtro_estado_proceso"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Todos</option>
                                    <option value="prejurídico">Prejurídico</option>
                                    <option value="demandado">Demandado</option>
                                    <option value="en ejecución">En Ejecución</option>
                                    <option value="sentencia">Sentencia</option>
                                    <option value="cerrado">Cerrado</option>
                                </select>
                            </div>
                            <div>
                                <label for="fecha_desde"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Desde
                                    (Creación)</label>
                                <input type="date" v-model="filtroForm.fecha_desde" id="fecha_desde"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="fecha_hasta"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hasta
                                    (Creación)</label>
                                <input type="date" v-model="filtroForm.fecha_hasta" id="fecha_hasta"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div class="lg:col-start-3 lg:col-span-2 flex items-end space-x-2">
                                <PrimaryButton @click="aplicarFiltros" class="w-full justify-center h-10">Aplicar
                                    Filtros
                                </PrimaryButton>
                                <SecondaryButton @click="limpiarFiltros" class="w-full justify-center h-10"
                                    title="Limpiar Filtros">
                                    <ArrowPathIcon class="h-5 w-5" />
                                </SecondaryButton>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenido Pestaña: Reporte Estratégico -->
                <div v-if="activeTab === 'estrategico'" class="space-y-8">
                    <transition enter-active-class="transition ease-out duration-300"
                        enter-from-class="transform opacity-0 -translate-y-4"
                        enter-to-class="transform opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-200"
                        leave-from-class="transform opacity-100 translate-y-0"
                        leave-to-class="transform opacity-0 -translate-y-4">
                        <div v-if="consolidadoCooperativa"
                            class="bg-indigo-50 dark:bg-indigo-900/50 border-l-4 border-indigo-500 rounded-r-lg shadow-sm p-6">
                            <h3 class="text-lg font-bold text-indigo-800 dark:text-indigo-200 mb-4 flex items-center">
                                <BuildingLibraryIcon class="h-6 w-6 mr-3" />Foco Estratégico: {{
                                consolidadoCooperativa.nombre
                                }}
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="text-center md:text-left">
                                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Casos Activos</h4>
                                    <p class="text-3xl font-bold text-indigo-700 dark:text-indigo-300 mt-1">{{
                                        consolidadoCooperativa.casos_activos }}</p>
                                </div>
                                <div class="text-center md:text-left">
                                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Cartera en Mora
                                    </h4>
                                    <p class="text-3xl font-bold text-indigo-700 dark:text-indigo-300 mt-1">{{
                                        formatCurrency(consolidadoCooperativa.cartera_en_mora) }}</p>
                                </div>
                                <div class="text-center md:text-left">
                                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Recuperado
                                    </h4>
                                    <p class="text-3xl font-bold text-indigo-700 dark:text-indigo-300 mt-1">{{
                                        formatCurrency(consolidadoCooperativa.total_recuperado) }}</p>
                                </div>
                            </div>
                        </div>
                    </transition>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm flex items-center space-x-4">
                            <div class="bg-blue-100 dark:bg-blue-900/50 p-3 rounded-full">
                                <BriefcaseIcon class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Casos Activos
                                </h4>
                                <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ kpis.casos_activos
                                    }}</p>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm flex items-center space-x-4">
                            <div class="bg-red-100 dark:bg-red-900/50 p-3 rounded-full">
                                <ShieldExclamationIcon class="h-8 w-8 text-red-600 dark:text-red-400" />
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Cartera en Mora
                                </h4>
                                <p class="text-3xl font-bold text-red-600 dark:text-red-400 mt-1">{{
                                    formatCurrency(kpis.cartera_en_mora) }}</p>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm flex items-center space-x-4">
                            <div class="bg-green-100 dark:bg-green-900/50 p-3 rounded-full">
                                <BanknotesIcon class="h-8 w-8 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Recuperado</h4>
                                <p class="text-3xl font-bold text-green-600 dark:text-green-400 mt-1">{{
                                    formatCurrency(kpis.total_recuperado) }}</p>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm flex items-center space-x-4">
                            <div class="bg-purple-100 dark:bg-purple-900/50 p-3 rounded-full">
                                <ClockIcon class="h-8 w-8 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Promedio Días
                                    Recuperación</h4>
                                <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{
                                    kpis.promedio_dias_recuperacion }} <span class="text-xl font-semibold">días</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Panel de Control de
                                Alertas
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <div
                                    class="bg-blue-50 dark:bg-gray-700/50 p-6 rounded-lg flex items-center border-l-4 border-blue-500">
                                    <div class="bg-blue-500 p-3 rounded-full mr-4 shadow-lg">
                                        <BellIcon class="h-6 w-6 text-white" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">Total de Alertas Activas</p>
                                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{
                                            kpis.total_alertas_activas }}</p>
                                    </div>
                                </div>
                                <div
                                    class="bg-red-50 dark:bg-gray-700/50 p-6 rounded-lg flex items-center border-l-4 border-red-500">
                                    <div class="bg-red-500 p-3 rounded-full mr-4 shadow-lg">
                                        <ExclamationTriangleIcon class="h-6 w-6 text-white" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">Alertas Vencidas (>7 días)
                                        </p>
                                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{
                                            kpis.alertas_vencidas }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                                    <h4 class="font-semibold text-center text-gray-700 dark:text-gray-300 mb-4">
                                        Distribución de
                                        Alertas</h4>
                                    <div class="h-64"><canvas ref="chartAlertasPorTipo"></canvas></div>
                                </div>
                                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                                    <h4 class="font-semibold text-center text-gray-700 dark:text-gray-300 mb-4">
                                        Tendencia
                                        Semanal de Alertas</h4>
                                    <div class="h-64"><canvas ref="chartTendenciaSemanal"></canvas></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                <ChartBarIcon class="h-6 w-6 mr-2" />Recuperación Mensual
                            </h4>
                            <div class="h-80"><canvas ref="chartPagosMensuales"></canvas></div>
                        </div>
                        <div class="lg:col-span-1 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                <ChartPieIcon class="h-6 w-6 mr-2" />Cartera por Edad
                            </h4>
                            <div class="h-80 flex items-center justify-center"><canvas
                                    v-if="props.graficas.cartera_por_edad && Object.keys(props.graficas.cartera_por_edad).length > 0"
                                    ref="chartCarteraPorEdad"></canvas>
                                <div v-else class="text-center text-gray-500">
                                    <ChartPieIcon class="h-16 w-16 mx-auto text-gray-300 dark:text-gray-600" />
                                    <p class="mt-2 font-semibold">Sin Cartera en Mora</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                <ArrowTrendingUpIcon class="h-6 w-6 mr-2 text-red-500" />Tendencia de Mora Mensual
                            </h4>
                            <div class="h-80 flex items-center justify-center"><canvas
                                    v-if="props.graficas.mora_mensual && props.graficas.mora_mensual.length > 0"
                                    ref="chartMoraMensual"></canvas>
                                <div v-else class="text-center text-gray-500">
                                    <ArrowTrendingUpIcon class="h-16 w-16 mx-auto text-gray-300 dark:text-gray-600" />
                                    <p class="mt-2 font-semibold">Sin Datos de Mora Reciente</p>
                                </div>
                            </div>
                        </div>
                        <div class="lg:col-span-1 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                <BriefcaseIcon class="h-6 w-6 mr-2" />Casos por Estado
                            </h4>
                            <div class="h-80"><canvas ref="chartCasosPorEstado"></canvas></div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Análisis de Riesgo y
                                Eficiencia
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                                <div
                                    class="bg-yellow-50 dark:bg-yellow-900/50 p-4 rounded-lg flex items-center space-x-4">
                                    <div class="bg-yellow-100 dark:bg-yellow-800/50 p-3 rounded-full">
                                        <DocumentMinusIcon class="h-8 w-8 text-yellow-600 dark:text-yellow-400" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Casos Sin
                                            Pagaré</h4>
                                        <div class="flex items-baseline space-x-2 mt-1">
                                            <p class="text-3xl font-bold text-yellow-700 dark:text-yellow-300">{{
                                                kpis.casos_sin_pagare }}</p><span
                                                class="text-sm font-semibold text-yellow-600 dark:text-yellow-400">({{
                                                kpis.porcentaje_sin_pagare }}%)</span>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="bg-orange-50 dark:bg-orange-900/50 p-4 rounded-lg flex items-center space-x-4">
                                    <div class="bg-orange-100 dark:bg-orange-800/50 p-3 rounded-full">
                                        <ExclamationTriangleIcon class="h-8 w-8 text-orange-600 dark:text-orange-400" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Casos Inactivos
                                            (>60d)
                                        </h4>
                                        <p class="text-3xl font-bold text-orange-700 dark:text-orange-300 mt-1">{{
                                            kpis.casos_inactivos }}</p>
                                    </div>
                                </div>
                                <div class="bg-cyan-50 dark:bg-cyan-900/50 p-4 rounded-lg flex items-center space-x-4">
                                    <div class="bg-cyan-100 dark:bg-cyan-800/50 p-3 rounded-full">
                                        <FolderMinusIcon class="h-8 w-8 text-cyan-600 dark:text-cyan-400" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Docs. Faltantes
                                        </h4>
                                        <p class="text-3xl font-bold text-cyan-700 dark:text-cyan-300 mt-1">{{
                                            kpis.casos_con_documentos_faltantes }}</p>
                                    </div>
                                </div>
                                <div
                                    class="bg-fuchsia-50 dark:bg-fuchsia-900/50 p-4 rounded-lg flex items-center space-x-4">
                                    <div class="bg-fuchsia-100 dark:bg-fuchsia-800/50 p-3 rounded-full">
                                        <CalendarDaysIcon class="h-8 w-8 text-fuchsia-600 dark:text-fuchsia-400" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Casos Longevos
                                            (>1 año)
                                        </h4>
                                        <p class="text-3xl font-bold text-fuchsia-700 dark:text-fuchsia-300 mt-1">{{
                                            kpis.casos_longevos }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                <UserGroupIcon class="h-6 w-6 mr-2" />Ranking de Rendimiento por Gestor
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead class="border-b border-gray-200 dark:border-gray-700">
                                        <tr>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Gestor</th>
                                            <th
                                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Casos Asignados</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Monto Recuperado</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-for="abogado in rankingAbogados" :key="abogado.id"
                                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                {{ abogado.name }}</td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                                {{ abogado.casos_count }}</td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600 dark:text-green-400">
                                                {{ formatCurrency(abogado.pagos_sum_monto_pagado) }}</td>
                                        </tr>
                                        <tr v-if="rankingAbogados.length === 0">
                                            <td colspan="3"
                                                class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No
                                                hay datos de rendimiento para mostrar.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenido Pestaña: Reporte de Cumplimiento -->
                <div v-if="activeTab === 'cumplimiento'" class="space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div
                            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6 flex items-center">
                            <div class="p-3 rounded-full bg-red-100 dark:bg-red-900 mr-4">
                                <ExclamationTriangleIcon class="h-8 w-8 text-red-500" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Fallas Activas</p>
                                <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{
                                    statsCumplimiento.totalFallasActivas }}</p>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Fallas por Nivel de
                                Riesgo
                            </h3>
                            <div class="flex justify-around items-center text-center mt-4">
                                <div>
                                    <p class="text-2xl font-bold text-red-600">{{ statsCumplimiento.fallasPorRiesgo.alto
                                        }}</p>
                                    <p class="text-xs font-semibold text-red-500 uppercase">Alto</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-yellow-600">{{
                                        statsCumplimiento.fallasPorRiesgo.medio }}
                                    </p>
                                    <p class="text-xs font-semibold text-yellow-500 uppercase">Medio</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-blue-600">{{
                                        statsCumplimiento.fallasPorRiesgo.bajo }}</p>
                                    <p class="text-xs font-semibold text-blue-500 uppercase">Bajo</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Top Cooperativas con
                                Fallas
                            </h3>
                            <ul v-if="statsCumplimiento.fallasPorCooperativa.length > 0" class="space-y-2 text-sm mt-3">
                                <li v-for="coop in statsCumplimiento.fallasPorCooperativa" :key="coop.nombre"
                                    class="flex justify-between items-center"><span
                                        class="text-gray-700 dark:text-gray-300">{{
                                        coop.nombre }}</span><span
                                        class="font-bold bg-gray-200 dark:bg-gray-700 rounded-full px-2 py-0.5 text-xs">{{
                                        coop.total_fallas }}</span></li>
                            </ul>
                            <p v-else class="text-gray-500 text-center pt-2">Sin fallas registradas.</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Listado de Fallas de
                                Cumplimiento Activas</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Caso ID
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Deudor
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Cooperativa</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Tipo de
                                                Falla</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Riesgo
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Detectado</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-if="!listadoFallas || listadoFallas.length === 0">
                                            <td colspan="6" class="px-6 py-10 text-center text-gray-500">¡Felicidades!
                                                No hay
                                                fallas de cumplimiento activas.</td>
                                        </tr>
                                        <tr v-else v-for="falla in listadoFallas" :key="falla.id"
                                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                <Link :href="route('casos.show', falla.caso.id)"
                                                    class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold">
                                                #{{
                                                falla.caso.id }}</Link>
                                            </td>
                                            <td
                                                class="px-4 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200">
                                                {{
                                                    falla.caso.deudor ?falla.caso.deudor.nombre_completo : 'N/A' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">{{
                                                falla.caso.cooperativa.nombre }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">{{
                                                tiposValidacionNombres[falla.tipo] }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap"><span
                                                    :class="['px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full capitalize border', riesgoClasses[falla.nivel_riesgo]]">{{
                                                    falla.nivel_riesgo }}</span></td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">{{
                                                formatDateForCompliance(falla.ultima_revision) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
