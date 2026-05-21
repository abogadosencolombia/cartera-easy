<script setup>
import { computed, ref, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
    ArcElement,
} from 'chart.js';
import { Doughnut, Bar } from 'vue-chartjs';
import {
    ArrowPathIcon,
    ArrowRightCircleIcon,
    BanknotesIcon,
    BriefcaseIcon,
    ChartBarSquareIcon,
    CheckBadgeIcon,
    ClipboardDocumentCheckIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    ScaleIcon,
    ShieldExclamationIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

const props = defineProps({
    stats: Object,
    charts: Object,
});

const isReady = ref(false);

onMounted(() => {
    setTimeout(() => {
        isReady.value = true;
    }, 150);
});

const statsSafe = computed(() => props.stats ?? {});
const radarItems = computed(() => props.charts?.radar ?? []);
const salud = computed(() => props.charts?.salud ?? { al_dia: 0, en_riesgo: 0, vencidos: 0 });
const carga = computed(() => props.charts?.carga ?? []);
const actividad = computed(() => props.charts?.actividad?.data ?? []);
const actividadLinks = computed(() => props.charts?.actividad?.links ?? []);
const finanzas = computed(() => props.charts?.finanzas ?? { labels: [], recaudado: [], programado: [] });

const totalSalud = computed(() => salud.value.al_dia + salud.value.en_riesgo + salud.value.vencidos);
const asuntosCriticos = computed(() => radarItems.value.filter((item) => item.prioridad === 'CRÍTICA').length);
const asuntosPorAtender = computed(() => salud.value.en_riesgo + salud.value.vencidos);
const cargaVisible = computed(() => carga.value.filter((item) => item.procesos_count > 0).slice(0, 8));
const totalCarga = computed(() => carga.value.reduce((sum, item) => sum + Number(item.procesos_count || 0), 0));

const parseAmount = (value) => {
    if (typeof value === 'number') return value;
    return Number(String(value ?? 0).replace(/[^\d-]/g, '')) || 0;
};

const recaudoActual = computed(() => {
    const values = finanzas.value.recaudado ?? [];
    return parseAmount(values[values.length - 1] ?? statsSafe.value.recaudo_mes ?? 0);
});

const programadoActual = computed(() => {
    const values = finanzas.value.programado ?? [];
    return parseAmount(values[values.length - 1] ?? 0);
});

const diferenciaRecaudo = computed(() => programadoActual.value - recaudoActual.value);
const cumplimientoRecaudo = computed(() => {
    if (!programadoActual.value) return 0;
    return Math.min(999, Math.round((recaudoActual.value / programadoActual.value) * 100));
});

const formatNumber = (value) => new Intl.NumberFormat('es-CO').format(Number(value || 0));

const formatMoney = (value) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
}).format(parseAmount(value));

const priorityClass = (priority) => priority === 'CRÍTICA'
    ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300'
    : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300';

const kpiCards = computed(() => [
    {
        label: 'Trabajo abierto',
        value: formatNumber(statsSafe.value.total_activos ?? 0),
        help: 'Asuntos que siguen en gestión.',
        icon: ScaleIcon,
        accent: 'text-sky-600 dark:text-sky-400',
        tone: 'border-sky-100 bg-sky-50/70 dark:border-sky-900/50 dark:bg-sky-950/20',
    },
    {
        label: 'Atención inmediata',
        value: formatNumber(asuntosPorAtender.value),
        help: 'Vencidos o cerca de vencerse.',
        icon: ShieldExclamationIcon,
        accent: 'text-red-600 dark:text-red-300',
        tone: 'border-red-100 bg-red-50/80 dark:border-red-900/60 dark:bg-red-950/25',
    },
    {
        label: 'Prioridad crítica',
        value: formatNumber(asuntosCriticos.value),
        help: 'Recomendados para actuar hoy.',
        icon: ExclamationTriangleIcon,
        accent: 'text-amber-600 dark:text-amber-300',
        tone: 'border-amber-100 bg-amber-50/80 dark:border-amber-900/60 dark:bg-amber-950/25',
    },
    {
        label: 'Recaudo actual',
        value: formatMoney(recaudoActual.value),
        help: `${cumplimientoRecaudo.value}% del programado.`,
        icon: BanknotesIcon,
        accent: 'text-emerald-600 dark:text-emerald-300',
        tone: 'border-emerald-100 bg-emerald-50/80 dark:border-emerald-900/60 dark:bg-emerald-950/25',
    },
]);

const healthLegend = computed(() => [
    { label: 'Al día', value: salud.value.al_dia, color: 'bg-emerald-500', text: 'text-emerald-700 dark:text-emerald-300' },
    { label: 'En riesgo', value: salud.value.en_riesgo, color: 'bg-amber-500', text: 'text-amber-700 dark:text-amber-300' },
    { label: 'Vencidos', value: salud.value.vencidos, color: 'bg-red-500', text: 'text-red-700 dark:text-red-300' },
]);

const financeProgress = computed(() => Math.min(100, cumplimientoRecaudo.value));

const healthData = computed(() => ({
    labels: ['Al día', 'En riesgo', 'Vencidos'],
    datasets: [{
        data: [salud.value.al_dia, salud.value.en_riesgo, salud.value.vencidos],
        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
        hoverBackgroundColor: ['#059669', '#d97706', '#dc2626'],
        borderColor: '#ffffff',
        borderWidth: 4,
    }],
}));

const workloadData = computed(() => ({
    labels: cargaVisible.value.map((item) => item.name),
    datasets: [{
        label: 'Asuntos activos',
        data: cargaVisible.value.map((item) => item.procesos_count),
        backgroundColor: '#2563eb',
        hoverBackgroundColor: '#1d4ed8',
        borderRadius: 8,
        barThickness: 18,
    }],
}));

const financeData = computed(() => ({
    labels: finanzas.value.labels ?? [],
    datasets: [
        {
            label: 'Programado',
            data: finanzas.value.programado ?? [],
            backgroundColor: '#cbd5e1',
            hoverBackgroundColor: '#94a3b8',
            borderRadius: 8,
        },
        {
            label: 'Recaudado',
            data: finanzas.value.recaudado ?? [],
            backgroundColor: '#10b981',
            hoverBackgroundColor: '#059669',
            borderRadius: 8,
        },
    ],
}));

const baseChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        tooltip: {
            backgroundColor: '#0f172a',
            borderColor: '#334155',
            borderWidth: 1,
            padding: 12,
            cornerRadius: 10,
            titleFont: { size: 12, weight: 'bold' },
            bodyFont: { size: 12, weight: 'bold' },
        },
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                boxWidth: 8,
                padding: 18,
                color: '#64748b',
                font: { size: 11, weight: 'bold' },
            },
        },
    },
};

const horizontalOptions = {
    ...baseChartOptions,
    indexAxis: 'y',
    plugins: {
        ...baseChartOptions.plugins,
        legend: { display: false },
    },
    scales: {
        x: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: '#e2e8f0' },
            ticks: { color: '#64748b', precision: 0 },
        },
        y: {
            border: { display: false },
            grid: { display: false },
            ticks: { color: '#475569', font: { size: 11, weight: 'bold' } },
        },
    },
};

const moneyOptions = {
    ...baseChartOptions,
    scales: {
        x: {
            border: { display: false },
            grid: { display: false },
            ticks: { color: '#64748b', font: { size: 11, weight: 'bold' } },
        },
        y: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: '#e2e8f0' },
            ticks: {
                color: '#64748b',
                callback: (value) => new Intl.NumberFormat('es-CO', {
                    notation: 'compact',
                    maximumFractionDigits: 1,
                }).format(value),
            },
        },
    },
};
</script>

<template>
    <Head title="Analítica jurídica" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-sky-600 dark:text-sky-400">Analítica jurídica</p>
                    <h2 class="mt-1 text-3xl font-black leading-tight text-gray-950 dark:text-white">Centro de control operativo</h2>
                    <p class="mt-2 max-w-3xl text-sm font-semibold leading-6 text-gray-500 dark:text-gray-400">
                        Priorice vencimientos, carga del equipo, recaudo y actividad reciente desde una sola vista.
                    </p>
                </div>
                <div class="flex w-full items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800 md:w-auto">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                            <ClockIcon class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-gray-400">Estado</p>
                            <p class="text-sm font-black text-gray-900 dark:text-white">Datos actualizados</p>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div class="min-h-screen bg-slate-50 py-8 dark:bg-gray-950">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="card in kpiCards"
                        :key="card.label"
                        class="rounded-lg border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900"
                        :class="card.tone"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                                <p class="mt-3 break-words text-3xl font-black leading-none text-gray-950 dark:text-white">{{ card.value }}</p>
                            </div>
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-white/80 shadow-sm dark:bg-gray-900/80">
                                <component :is="card.icon" class="h-5 w-5" :class="card.accent" />
                            </span>
                        </div>
                        <p class="mt-4 text-sm font-semibold leading-5 text-gray-600 dark:text-gray-300">{{ card.help }}</p>
                    </article>
                </section>

                <section class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 class="text-lg font-black text-gray-950 dark:text-white">Ruta de atención</h3>
                                <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">Use el color como orden de trabajo y no como decoración.</p>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 dark:border-red-900/60 dark:bg-red-950/30">
                                    <p class="text-xs font-black uppercase tracking-widest text-red-700 dark:text-red-300">Hoy</p>
                                    <p class="text-xs font-semibold text-red-700/80 dark:text-red-300/80">Vencido o crítico</p>
                                </div>
                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-900/60 dark:bg-amber-950/30">
                                    <p class="text-xs font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">Pronto</p>
                                    <p class="text-xs font-semibold text-amber-700/80 dark:text-amber-300/80">Riesgo creciente</p>
                                </div>
                                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 dark:border-emerald-900/60 dark:bg-emerald-950/30">
                                    <p class="text-xs font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300">Seguimiento</p>
                                    <p class="text-xs font-semibold text-emerald-700/80 dark:text-emerald-300/80">Sin urgencia</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-widest text-gray-400">Carga total visible</p>
                                <p class="mt-1 text-2xl font-black text-gray-950 dark:text-white">{{ formatNumber(totalCarga) }}</p>
                            </div>
                            <UserGroupIcon class="h-8 w-8 text-sky-600 dark:text-sky-400" />
                        </div>
                        <p class="mt-3 text-sm font-semibold text-gray-500 dark:text-gray-400">Casos y procesos abiertos asignados al equipo.</p>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 p-5 dark:border-gray-800 md:flex md:items-center md:justify-between">
                        <div>
                            <h3 class="flex items-center gap-2 text-lg font-black text-gray-950 dark:text-white">
                                <ClipboardDocumentCheckIcon class="h-5 w-5 text-red-600" />
                                Pendientes priorizados
                            </h3>
                            <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">Empiece por los críticos y cierre el ciclo con una gestión registrada.</p>
                        </div>
                        <span class="mt-3 inline-flex rounded-lg bg-gray-100 px-3 py-2 text-xs font-black uppercase tracking-widest text-gray-600 dark:bg-gray-800 dark:text-gray-300 md:mt-0">
                            {{ radarItems.length }} pendientes
                        </span>
                    </div>

                    <div v-if="radarItems.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                        <article v-for="item in radarItems" :key="item.id + item.tipo" class="grid gap-5 p-5 transition hover:bg-slate-50 dark:hover:bg-gray-800/50 lg:grid-cols-[1fr_auto] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-lg border px-2.5 py-1 text-[11px] font-black uppercase tracking-widest" :class="priorityClass(item.prioridad)">
                                        {{ item.prioridad }}
                                    </span>
                                    <span class="rounded-lg bg-sky-50 px-2.5 py-1 text-[11px] font-black uppercase tracking-widest text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                                        {{ item.tipo === 'CASO' ? 'Caso de cooperativa' : 'Proceso judicial' }}
                                    </span>
                                    <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-[11px] font-black uppercase tracking-widest text-gray-500 dark:bg-gray-800 dark:text-gray-300">
                                        {{ Math.round(item.dias_inactivo) }} días sin movimiento
                                    </span>
                                </div>
                                <h4 class="mt-3 break-words text-lg font-black text-gray-950 dark:text-white">{{ item.identificador }}</h4>
                                <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                    Etapa actual: <span class="font-black text-gray-900 dark:text-white">{{ item.etapa_actual }}</span>
                                </p>
                                <p class="mt-3 rounded-lg border-l-4 border-red-500 bg-red-50 p-3 text-sm font-bold leading-relaxed text-red-900 dark:bg-red-950/30 dark:text-red-200">
                                    {{ item.accion_sugerida }}
                                </p>
                            </div>
                            <Link :href="item.url" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-gray-950 px-4 text-xs font-black uppercase tracking-widest text-white transition hover:bg-sky-700 dark:bg-white dark:text-gray-950 dark:hover:bg-sky-200">
                                Abrir
                                <ArrowRightCircleIcon class="h-5 w-5" />
                            </Link>
                        </article>
                    </div>

                    <div v-else class="p-10 text-center">
                        <CheckBadgeIcon class="mx-auto h-12 w-12 text-emerald-500" />
                        <h4 class="mt-3 text-lg font-black text-gray-950 dark:text-white">Sin tareas urgentes</h4>
                        <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">No hay asuntos vencidos o estancados para atender de inmediato.</p>
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-4">
                        <div class="mb-5 flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-black text-gray-950 dark:text-white">Estado general</h3>
                                <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ totalSalud }} asuntos revisados por el sistema.</p>
                            </div>
                            <ChartBarSquareIcon class="h-6 w-6 text-sky-600 dark:text-sky-400" />
                        </div>
                        <div class="h-64">
                            <Doughnut v-if="isReady" :data="healthData" :options="baseChartOptions" />
                            <div v-else class="flex h-full items-center justify-center rounded-lg bg-slate-50 dark:bg-gray-800">
                                <ArrowPathIcon class="h-7 w-7 animate-spin text-sky-600" />
                            </div>
                        </div>
                        <div class="mt-5 space-y-3">
                            <div v-for="item in healthLegend" :key="item.label" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 dark:bg-gray-800">
                                <div class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full" :class="item.color"></span>
                                    <span class="text-sm font-black text-gray-700 dark:text-gray-200">{{ item.label }}</span>
                                </div>
                                <span class="text-sm font-black" :class="item.text">{{ formatNumber(item.value) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-8">
                        <div class="mb-5 flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-black text-gray-950 dark:text-white">Trabajo por persona</h3>
                                <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">Top de responsables con más asuntos abiertos.</p>
                            </div>
                            <UserGroupIcon class="h-6 w-6 text-sky-600 dark:text-sky-400" />
                        </div>
                        <div class="h-80">
                            <Bar v-if="isReady" :data="workloadData" :options="horizontalOptions" />
                            <div v-else class="flex h-full items-center justify-center rounded-lg bg-slate-50 dark:bg-gray-800">
                                <ArrowPathIcon class="h-7 w-7 animate-spin text-sky-600" />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-8">
                        <div class="mb-5 flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-black text-gray-950 dark:text-white">Cobros del mes</h3>
                                <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">Programado frente a recaudo registrado.</p>
                            </div>
                            <BanknotesIcon class="h-6 w-6 text-emerald-600 dark:text-emerald-300" />
                        </div>
                        <div class="h-80">
                            <Bar v-if="isReady" :data="financeData" :options="moneyOptions" />
                            <div v-else class="flex h-full items-center justify-center rounded-lg bg-slate-50 dark:bg-gray-800">
                                <ArrowPathIcon class="h-7 w-7 animate-spin text-sky-600" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 xl:col-span-4">
                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <h3 class="text-lg font-black text-gray-950 dark:text-white">Resumen financiero</h3>
                            <div class="mt-5">
                                <div class="flex items-end justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-black uppercase tracking-widest text-gray-400">Cumplimiento</p>
                                        <p class="mt-1 text-3xl font-black text-gray-950 dark:text-white">{{ cumplimientoRecaudo }}%</p>
                                    </div>
                                    <p class="text-sm font-black text-emerald-700 dark:text-emerald-300">{{ formatMoney(recaudoActual) }}</p>
                                </div>
                                <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-gray-800">
                                    <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${financeProgress}%` }"></div>
                                </div>
                            </div>
                            <dl class="mt-5 space-y-4">
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-sm font-bold text-gray-500 dark:text-gray-400">Programado</dt>
                                    <dd class="text-sm font-black text-gray-950 dark:text-white">{{ formatMoney(programadoActual) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                                    <dt class="text-sm font-bold text-gray-500 dark:text-gray-400">{{ diferenciaRecaudo > 0 ? 'Pendiente' : 'Excedente' }}</dt>
                                    <dd class="text-sm font-black" :class="diferenciaRecaudo > 0 ? 'text-red-700 dark:text-red-300' : 'text-emerald-700 dark:text-emerald-300'">
                                        {{ formatMoney(Math.abs(diferenciaRecaudo)) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="rounded-lg border border-sky-200 bg-sky-50 p-5 dark:border-sky-900/60 dark:bg-sky-950/30">
                            <BriefcaseIcon class="h-6 w-6 text-sky-700 dark:text-sky-300" />
                            <h4 class="mt-3 text-sm font-black uppercase tracking-widest text-sky-900 dark:text-sky-100">Orden recomendado</h4>
                            <p class="mt-2 text-sm font-semibold leading-relaxed text-sky-900/80 dark:text-sky-100/80">
                                Atienda primero lo vencido o crítico, revise los casos en riesgo y después ajuste la distribución de carga del equipo.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-100 p-5 dark:border-gray-800 md:flex md:items-center md:justify-between">
                        <div>
                            <h3 class="text-lg font-black text-gray-950 dark:text-white">Actividad reciente</h3>
                            <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">Últimos movimientos del equipo en casos, procesos, documentos, actuaciones y pagos.</p>
                        </div>
                    </div>

                    <div v-if="actividad.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                        <article v-for="event in actividad" :key="event.id" class="grid gap-4 p-5 transition hover:bg-slate-50 dark:hover:bg-gray-800/50 md:grid-cols-[13rem_1fr_auto] md:items-start">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sm font-black uppercase text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                                    {{ event.usuario?.[0] ?? 'S' }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-gray-950 dark:text-white">{{ event.usuario }}</p>
                                    <p class="text-xs font-bold text-gray-400">{{ event.hora }}</p>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-[11px] font-black uppercase tracking-widest text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ event.modulo }}</span>
                                    <span class="rounded-lg bg-sky-50 px-2.5 py-1 text-[11px] font-black uppercase tracking-widest text-sky-700 dark:bg-sky-950 dark:text-sky-300">{{ event.evento }}</span>
                                </div>
                                <p class="mt-2 break-words text-sm font-semibold leading-relaxed text-gray-600 dark:text-gray-300">{{ event.descripcion }}</p>
                            </div>
                            <p class="text-xs font-bold uppercase tracking-widest text-gray-400 md:text-right">{{ event.fecha }}</p>
                        </article>
                    </div>

                    <div v-else class="p-10 text-center">
                        <p class="text-sm font-bold text-gray-500 dark:text-gray-400">Todavía no hay cambios recientes para mostrar.</p>
                    </div>

                    <div v-if="actividadLinks.length > 3" class="border-t border-gray-100 p-5 dark:border-gray-800">
                        <Pagination :links="actividadLinks" />
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
