<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { debounce } from 'lodash';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import TextInput from '@/Components/TextInput.vue';
import {
    ArrowTopRightOnSquareIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    ClockIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    FolderOpenIcon,
    MagnifyingGlassIcon,
    PlusIcon,
    ScaleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    contratos: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({ q: '', estado: '' }) },
    stats: {
        type: Object,
        default: () => ({
            activeValue: 0,
            activePaid: 0,
            activeBalance: 0,
            activeCount: 0,
            closedCount: 0,
            overdueCount: 0,
            pendingCharges: 0,
        }),
    },
});

const q = ref(props.filters?.q ?? '');
const estado = ref(props.filters?.estado ?? '');
let suppressWatch = false;

const estadoOptions = [
    { value: '', label: 'Todos' },
    { value: 'ACTIVO', label: 'Activos' },
    { value: 'PAGOS_PENDIENTES', label: 'Pagos pendientes' },
    { value: 'PAGO_PARCIAL', label: 'Pago parcial' },
    { value: 'EN_MORA', label: 'En mora' },
    { value: 'CERRADO', label: 'Cerrados' },
];

const contratosRows = computed(() => props.contratos?.data || []);
const hasFilters = computed(() => Boolean(q.value || estado.value));

const buscarContratos = () => {
    router.get(
        route('honorarios.contratos.index'),
        { q: q.value, estado: estado.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const debouncedSearch = debounce(buscarContratos, 350);

watch(q, () => {
    if (!suppressWatch) debouncedSearch();
});

watch(estado, () => {
    if (!suppressWatch) buscarContratos();
});

onBeforeUnmount(() => debouncedSearch.cancel());

const limpiarFiltros = () => {
    suppressWatch = true;
    q.value = '';
    estado.value = '';
    buscarContratos();
    queueMicrotask(() => { suppressWatch = false; });
};

const fmtMoney = (n) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
}).format(Number(n || 0));

const fmtDateShort = (d) => {
    if (!d) return 'Sin fecha';
    const value = String(d);
    const normalized = value.length === 10 ? `${value}T00:00:00` : value.replace(' ', 'T');
    const dateObj = new Date(normalized);

    if (Number.isNaN(dateObj.getTime())) return 'Fecha invalida';

    return dateObj.toLocaleDateString('es-CO', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    });
};

const estadoLabel = (value) => ({
    ACTIVO: 'Activo',
    PAGOS_PENDIENTES: 'Pagos pendientes',
    PAGO_PARCIAL: 'Pago parcial',
    EN_MORA: 'En mora',
    CERRADO: 'Cerrado',
    REESTRUCTURADO: 'Reestructurado',
}[String(value || '').toUpperCase()] || 'Sin estado');

const estadoClass = (value) => {
    const s = String(value || '').toUpperCase();
    if (s === 'EN_MORA') return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300';
    if (s === 'CERRADO') return 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300';
    if (s === 'PAGOS_PENDIENTES') return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200';
    if (s === 'PAGO_PARCIAL') return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300';
    return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300';
};

const modalidadLabel = (value) => ({
    CUOTAS: 'Cuotas',
    PAGO_UNICO: 'Pago unico',
    LITIS: 'Litis',
    CUOTA_MIXTA: 'Cuota mixta',
}[String(value || '').toUpperCase()] || 'Sin modalidad');

const frecuenciaLabel = (value) => ({
    DIARIO: 'Diario',
    SEMANAL: 'Semanal',
    QUINCENAL: 'Quincenal',
    MENSUAL: 'Mensual',
    AL_FINALIZAR: 'Al finalizar',
}[String(value || '').toUpperCase()] || value);

const calcularProgreso = (contrato) => {
    const total = Number(contrato.monto_total || 0);
    const pagado = Number(contrato.total_pagado || 0);

    if (total <= 0) return pagado > 0 ? 100 : 0;

    return Math.max(0, Math.min(100, (pagado / total) * 100));
};

const saldoContrato = (contrato) => Number(contrato.saldo_estimado ?? Math.max(
    (Number(contrato.monto_total || 0) + Number(contrato.cargos_pendientes || 0)) - Number(contrato.total_pagado || 0),
    0,
));

const progressClass = (contrato) => {
    if (String(contrato.estado || '').toUpperCase() === 'EN_MORA' || Number(contrato.cuotas_vencidas || 0) > 0) return 'bg-rose-500';
    if (saldoContrato(contrato) <= 0 && Number(contrato.monto_total || 0) > 0) return 'bg-emerald-500';
    return 'bg-indigo-500';
};

const diasHasta = (dateValue) => {
    if (!dateValue) return null;
    const today = new Date();
    const date = new Date(`${String(dateValue).slice(0, 10)}T00:00:00`);
    if (Number.isNaN(date.getTime())) return null;

    today.setHours(0, 0, 0, 0);
    return Math.ceil((date.getTime() - today.getTime()) / 86400000);
};

const vencimientoLabel = (contrato) => {
    if (Number(contrato.cuotas_vencidas || 0) > 0) return `${contrato.cuotas_vencidas} vencida(s)`;

    const days = diasHasta(contrato.proxima_cuota);
    if (days === null) return 'Sin cuotas abiertas';
    if (days === 0) return 'Vence hoy';
    if (days > 0) return `Vence en ${days} dia(s)`;
    return `Vencio hace ${Math.abs(days)} dia(s)`;
};

const vencimientoClass = (contrato) => {
    const days = diasHasta(contrato.proxima_cuota);
    if (Number(contrato.cuotas_vencidas || 0) > 0 || (days !== null && days < 0)) return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300';
    if (days !== null && days <= 7) return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200';
    return 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300';
};

const resumenVinculo = (contrato) => {
    if (contrato.proceso) return `Radicado ${contrato.proceso.numero}`;
    if (contrato.caso_id) return contrato.caso_radicado ? `Caso ${contrato.caso_radicado}` : `Caso #${contrato.caso_id}`;
    return 'Contrato independiente';
};
</script>

<template>
    <Head title="Honorarios · Contratos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Gestion de honorarios</p>
                    <h2 class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white">Contratos</h2>
                    <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Cartera, vencimientos, recaudo y contratos asociados a expedientes.</p>
                </div>
                <Link
                    :href="route('honorarios.contratos.create')"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-600 bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700"
                >
                    <PlusIcon class="h-4 w-4" />
                    Nuevo contrato
                </Link>
            </div>
        </template>

        <div class="min-h-screen bg-gray-50/70 py-6 dark:bg-gray-950/40">
            <div class="mx-auto max-w-[1600px] space-y-5 px-4 sm:px-6 lg:px-8">
                <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-lg border border-emerald-200 bg-white p-4 shadow-sm dark:border-emerald-900/60 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300">Valor activo</p>
                                <p class="mt-2 text-2xl font-black text-gray-950 dark:text-white">{{ fmtMoney(stats.activeValue) }}</p>
                                <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ stats.activeCount }} contrato(s) abiertos</p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                <BanknotesIcon class="h-5 w-5" />
                            </span>
                        </div>
                    </article>

                    <article class="rounded-lg border border-indigo-200 bg-white p-4 shadow-sm dark:border-indigo-900/60 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-700 dark:text-indigo-300">Saldo por recaudar</p>
                                <p class="mt-2 text-2xl font-black text-gray-950 dark:text-white">{{ fmtMoney(stats.activeBalance) }}</p>
                                <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Pagado: {{ fmtMoney(stats.activePaid) }}</p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                                <ScaleIcon class="h-5 w-5" />
                            </span>
                        </div>
                    </article>

                    <article class="rounded-lg border border-rose-200 bg-white p-4 shadow-sm dark:border-rose-900/60 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-rose-700 dark:text-rose-300">Alertas de mora</p>
                                <p class="mt-2 text-2xl font-black text-gray-950 dark:text-white">{{ stats.overdueCount }}</p>
                                <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Contratos con cuotas vencidas</p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">
                                <ExclamationTriangleIcon class="h-5 w-5" />
                            </span>
                        </div>
                    </article>

                    <article class="rounded-lg border border-amber-200 bg-white p-4 shadow-sm dark:border-amber-900/60 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">Cargos pendientes</p>
                                <p class="mt-2 text-2xl font-black text-gray-950 dark:text-white">{{ fmtMoney(stats.pendingCharges) }}</p>
                                <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Cerrados: {{ stats.closedCount }}</p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                <DocumentTextIcon class="h-5 w-5" />
                            </span>
                        </div>
                    </article>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-5">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="relative min-w-0 flex-1">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                            <TextInput
                                v-model="q"
                                type="text"
                                placeholder="Buscar por contrato, cliente, radicado, caso o pagare..."
                                class="w-full rounded-lg border-gray-300 py-2.5 pl-10 pr-10 text-sm font-semibold dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                            />
                            <button
                                v-if="q"
                                type="button"
                                @click="q = ''"
                                class="absolute right-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                            >
                                <XMarkIcon class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-for="option in estadoOptions"
                                :key="option.value"
                                type="button"
                                @click="estado = option.value"
                                class="rounded-lg border px-3 py-2 text-[10px] font-black uppercase tracking-widest transition"
                                :class="estado === option.value
                                    ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-indigo-200 hover:text-indigo-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:hover:border-indigo-800 dark:hover:text-indigo-300'"
                            >
                                {{ option.label }}
                            </button>
                            <button
                                v-if="hasFilters"
                                type="button"
                                @click="limpiarFiltros"
                                class="rounded-lg border border-gray-200 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-gray-500 transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            >
                                Limpiar
                            </button>
                        </div>
                    </div>
                </section>

                <section class="space-y-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-widest text-gray-950 dark:text-white">Contratos registrados</h3>
                            <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Cada fila muestra saldo estimado, pagos, cargos y proximo vencimiento.</p>
                        </div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ contratosRows.length }} visible(s) en esta pagina</p>
                    </div>

                    <div v-if="contratosRows.length > 0" class="space-y-3">
                        <article
                            v-for="c in contratosRows"
                            :key="c.id"
                            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-indigo-900/70 sm:p-5"
                        >
                            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(22rem,0.9fr)_minmax(13rem,0.45fr)] xl:items-center">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Link
                                            :href="route('honorarios.contratos.show', c.id)"
                                            class="min-w-0 text-base font-black text-gray-950 transition hover:text-indigo-700 dark:text-white dark:hover:text-indigo-300"
                                            :title="c.persona_nombre"
                                        >
                                            {{ c.persona_nombre || 'Cliente sin nombre' }}
                                        </Link>
                                        <span class="inline-flex items-center rounded-lg border px-2.5 py-1 text-[10px] font-black uppercase tracking-widest" :class="estadoClass(c.estado)">
                                            {{ estadoLabel(c.estado) }}
                                        </span>
                                        <span class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                            {{ modalidadLabel(c.modalidad) }}
                                        </span>
                                        <span v-if="c.frecuencia_pago" class="inline-flex items-center rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300">
                                            {{ frecuenciaLabel(c.frecuencia_pago) }}
                                        </span>
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex items-center gap-1.5">
                                            <DocumentTextIcon class="h-4 w-4" /> Contrato #{{ c.id }}
                                        </span>
                                        <span class="inline-flex items-center gap-1.5">
                                            <CalendarDaysIcon class="h-4 w-4" /> Inicio {{ fmtDateShort(c.inicio || c.created_at) }}
                                        </span>
                                        <span v-if="c.porcentaje_litis" class="inline-flex items-center gap-1.5">
                                            <ScaleIcon class="h-4 w-4" /> Litis {{ Number(c.porcentaje_litis).toFixed(0) }}%
                                        </span>
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <Link
                                            v-if="c.caso_id"
                                            :href="route('casos.show', c.caso_id)"
                                            class="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300"
                                        >
                                            <FolderOpenIcon class="h-4 w-4 shrink-0" />
                                            <span class="truncate">{{ c.caso_radicado ? `Caso ${c.caso_radicado}` : `Caso #${c.caso_id}` }}</span>
                                        </Link>
                                        <Link
                                            v-if="c.proceso"
                                            :href="route('procesos.show', c.proceso.id)"
                                            class="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700 transition hover:bg-sky-100 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300"
                                        >
                                            <ScaleIcon class="h-4 w-4 shrink-0" />
                                            <span class="truncate">Radicado {{ c.proceso.numero }}</span>
                                        </Link>
                                        <span v-if="!c.caso_id && !c.proceso" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-bold text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                            {{ resumenVinculo(c) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="min-w-0 space-y-3">
                                    <div class="flex items-end justify-between gap-4">
                                        <div>
                                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Pagado</p>
                                            <p class="mt-1 text-sm font-black text-emerald-700 dark:text-emerald-300">{{ fmtMoney(c.total_pagado) }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Saldo estimado</p>
                                            <p class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ fmtMoney(saldoContrato(c)) }}</p>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="h-2.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                                            <div class="h-full rounded-full transition-all" :class="progressClass(c)" :style="{ width: `${calcularProgreso(c)}%` }"></div>
                                        </div>
                                        <div class="mt-2 grid grid-cols-3 gap-2 text-[11px] font-bold text-gray-500 dark:text-gray-400">
                                            <span>Total {{ fmtMoney(c.monto_total) }}</span>
                                            <span class="text-center">{{ Math.round(calcularProgreso(c)) }}%</span>
                                            <span class="text-right">Cargos {{ fmtMoney(c.cargos_pendientes) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-3 xl:items-end">
                                    <div class="w-full rounded-lg border px-3 py-2 xl:max-w-[14rem]" :class="vencimientoClass(c)">
                                        <div class="flex items-start gap-2">
                                            <ClockIcon class="mt-0.5 h-4 w-4 shrink-0" />
                                            <div class="min-w-0">
                                                <p class="text-[10px] font-black uppercase tracking-widest">Vencimiento</p>
                                                <p class="mt-1 text-xs font-black">{{ vencimientoLabel(c) }}</p>
                                                <p class="mt-0.5 text-[11px] font-semibold opacity-80">{{ c.proxima_cuota ? fmtDateShort(c.proxima_cuota) : `${c.cuotas_pendientes || 0} cuota(s) pendiente(s)` }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex w-full flex-wrap items-center justify-between gap-2 xl:max-w-[14rem] xl:justify-end">
                                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 dark:text-gray-400">
                                            <CheckCircleIcon class="h-4 w-4" /> {{ c.cuotas_pendientes || 0 }} abierta(s)
                                        </span>
                                        <Link
                                            :href="route('honorarios.contratos.show', c.id)"
                                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-widest text-gray-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:hover:border-indigo-800 dark:hover:text-indigo-300"
                                        >
                                            Ver
                                            <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-else class="rounded-lg border border-dashed border-gray-300 bg-white py-16 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <DocumentTextIcon class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                        <h3 class="mt-3 text-base font-black text-gray-950 dark:text-white">No se encontraron contratos</h3>
                        <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Ajusta los filtros o registra un nuevo contrato de honorarios.</p>
                    </div>

                    <div v-if="contratos.links && contratos.links.length > 3" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <Pagination :links="contratos.links" />
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
