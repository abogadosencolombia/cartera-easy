<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, useRemember, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { addDaysToDate, addMonthsToDate } from '@/Utils/formatters';
import Swal from 'sweetalert2';

// --- IMPORTAMOS LOS COMPONENTES DE PESTAÑA ---
import ResumenTab from './Tabs/ResumenTab.vue';
import DocumentosTab from './Tabs/DocumentosTab.vue';
import FinancieroTab from './Tabs/FinancieroTab.vue';
import ActuacionesTab from './Tabs/ActuacionesTab.vue';
import ActividadTab from './Tabs/ActividadTab.vue';

// Iconos
import {
    InformationCircleIcon,
    FolderOpenIcon,
    CreditCardIcon,
    ClipboardDocumentListIcon,
    ClockIcon,
    LockOpenIcon,
    DocumentDuplicateIcon,
    ArrowLeftIcon,
    PencilSquareIcon,
    UserCircleIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    BriefcaseIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: { type: Object, required: true },
    can: { type: Object, required: true },
    plantillas: { type: Array, default: () => [] },
    actuaciones: { type: Array, default: () => [] },
    resumen_financiero: { type: Object, required: true },
    bitacoras: { type: Array, default: () => [] },
    auditoria: { type: Array, default: () => [] },
});

const page = usePage();
const validTabs = ['resumen', 'documentos', 'financiero', 'actuaciones', 'actividad'];
const getInitialTab = () => {
    if (typeof window === 'undefined') return 'resumen';
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    return (tabParam && validTabs.includes(tabParam)) ? tabParam : 'resumen';
};

const activeTab = ref(getInitialTab());
const user = usePage().props.auth.user;

const setActiveTab = (tab) => {
    activeTab.value = tab;
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
};

const parseMoney = (input) => {
    let s = String(input ?? '').trim();
    if (!s) return 0;
    const n = Number(s.replace(/[^0-9.-]+/g,""));
    return Number.isFinite(n) ? n : 0;
};

const formatCurrency = (value) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 })
    .format(parseMoney(value));

const formatDate = (s) => {
    if (!s) return 'N/A';
    const date = new Date(s);
    return date.toLocaleDateString('es-CO', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' });
};

const formatLabel = (text) => {
    if (!text) return 'N/A';
    return text.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, char => char.toUpperCase());
};

const statusProcessoClasses = {
    'prejurídico': 'bg-amber-50 text-amber-700 ring-amber-600/20',
    'demandado': 'bg-blue-50 text-blue-700 ring-blue-600/20',
    'en ejecución': 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    'sentencia': 'bg-purple-50 text-purple-700 ring-purple-600/20',
    'cerrado': 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'default': 'bg-gray-50 text-gray-600 ring-gray-500/10',
};

const puedeEditar = computed(() => {
    if (!props.can.update) return false;
    if (user?.tipo_usuario === 'admin') return true;
    return !props.caso.bloqueado;
});

const copyLegalInfo = () => {
    const text = `EXPEDIENTE: ${props.caso.radicado || 'N/A'}\nDEUDOR: ${props.caso.deudor?.nombre_completo || 'N/A'}\nJUZGADO: ${props.caso.juzgado?.nombre || 'N/A'}`;
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({ title: 'Copiado', icon: 'success', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
    });
};

const confirmUnlockCase = () => {
    Swal.fire({
        title: '¿Desbloquear?',
        text: "Permitirá editar este proceso nuevamente.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desbloquear'
    }).then((res) => { if (res.isConfirmed) router.patch(route('casos.unlock', props.caso.id)); });
};
</script>

<template>
    <Head :title="'Caso #' + caso.id" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('casos.index')" class="p-1.5 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 text-gray-400 hover:text-indigo-600 transition-all">
                        <ArrowLeftIcon class="h-4 w-4" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-bold text-xl text-gray-900 dark:text-white leading-tight">Expediente #{{ caso.id }}</h2>
                            <span v-if="caso.bloqueado" class="px-1.5 py-0.5 text-[9px] font-bold uppercase rounded bg-rose-50 text-rose-600 ring-1 ring-rose-500/20">Bloqueado</span>
                        </div>
                        <p class="text-xs text-gray-500 font-medium">Gestión del deudor: {{ caso.deudor?.nombre_completo }}</p>
                    </div>
                </div>
                
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="copyLegalInfo" class="inline-flex items-center px-3 py-1.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg font-bold text-[10px] text-gray-600 dark:text-gray-300 uppercase tracking-wider hover:bg-white transition-all shadow-sm">
                        <DocumentDuplicateIcon class="h-3.5 w-3.5 mr-1.5 text-gray-400" /> Copiar Datos
                    </button>
                    <Link v-if="puedeEditar" :href="route('casos.edit', caso.id)" class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg font-bold text-[10px] text-gray-700 dark:text-gray-300 uppercase tracking-wider hover:bg-gray-50 transition-all shadow-sm">
                        <PencilSquareIcon class="h-3.5 w-3.5 mr-1.5 text-indigo-500" /> Editar
                    </Link>
                    <Link v-if="!caso.contrato && puedeEditar" :href="route('honorarios.contratos.create', { caso_id: caso.id, monto: resumen_financiero.saldo_pendiente })" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white rounded-lg font-bold text-[10px] uppercase tracking-wider hover:bg-indigo-700 transition-all shadow-sm">
                        <BriefcaseIcon class="h-3.5 w-3.5 mr-1.5" /> Generar Contrato
                    </Link>
                    <Link v-else-if="caso.contrato" :href="route('honorarios.contratos.show', caso.contrato.id)" class="inline-flex items-center px-3 py-1.5 bg-emerald-600 text-white rounded-lg font-bold text-[10px] uppercase tracking-wider hover:bg-emerald-700 transition-all shadow-sm">
                        <CheckCircleIcon class="h-3.5 w-3.5 mr-1.5" /> Contrato #{{ caso.contrato.id }}
                    </Link>
                    <button v-if="caso.bloqueado && user?.tipo_usuario === 'admin'" @click="confirmUnlockCase" class="p-1.5 bg-amber-50 text-amber-600 border border-amber-200 rounded-lg hover:bg-amber-100 transition-all"><LockOpenIcon class="h-4 w-4" /></button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- DASHBOARD COMPACTO -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-4">
                        <!-- Info Resumida -->
                        <div class="p-5 bg-gray-50/50 dark:bg-gray-700/30 border-b lg:border-b-0 lg:border-r border-gray-200 dark:border-gray-700">
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1">Estado del Proceso</p>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-md ring-1" :class="statusProcessoClasses[caso.etapa_procesal || caso.estado_proceso] || statusProcessoClasses['default']">
                                    {{ formatLabel(caso.etapa_procesal || caso.estado_proceso) }}
                                </span>
                            </div>
                            <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">Empresa / Cooperativa</p>
                            <p class="text-xs font-bold text-gray-700 dark:text-gray-200 truncate">{{ caso.cooperativa ? caso.cooperativa.nombre : 'N/A' }}</p>
                        </div>

                        <!-- KPIs Financieros en una sola fila -->
                        <div class="lg:col-span-3 p-5 grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="border-r border-gray-100 dark:border-gray-700 last:border-0 pr-4">
                                <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1 flex items-center gap-1"><BanknotesIcon class="h-3 w-3" /> Capital</p>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ formatCurrency(resumen_financiero.monto_total) }}</p>
                            </div>
                            <div class="border-r border-gray-100 dark:border-gray-700 last:border-0 pr-4">
                                <p class="text-[9px] font-bold text-rose-500 uppercase tracking-widest mb-1 flex items-center gap-1"><ExclamationTriangleIcon class="h-3 w-3" /> Saldo</p>
                                <p class="text-sm font-bold text-rose-600 dark:text-rose-400">{{ formatCurrency(resumen_financiero.saldo_pendiente) }}</p>
                            </div>
                            <div class="border-r border-gray-100 dark:border-gray-700 last:border-0 pr-4">
                                <p class="text-[9px] font-bold text-emerald-500 uppercase tracking-widest mb-1 flex items-center gap-1"><CheckCircleIcon class="h-3 w-3" /> Pagado</p>
                                <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(resumen_financiero.total_pagado) }}</p>
                            </div>
                            <div>
                                <template v-if="resumen_financiero.dias_mora > 0">
                                    <p class="text-[9px] font-bold text-amber-500 uppercase tracking-widest mb-1 flex items-center gap-1"><ClockIcon class="h-3 w-3" /> Mora</p>
                                    <p class="text-sm font-bold text-amber-600">{{ Math.floor(resumen_financiero.dias_mora) }} días</p>
                                </template>
                                <template v-else>
                                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1 flex items-center gap-1"><CalendarDaysIcon class="h-3 w-3" /> Apertura</p>
                                    <p class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ formatDate(caso.fecha_apertura) }}</p>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NAVEGACIÓN Y CONTENIDO -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-200 dark:border-gray-700 overflow-visible">
                    <div class="border-b border-gray-100 dark:border-gray-700 px-4">
                        <nav class="-mb-px flex space-x-6 overflow-x-auto scrollbar-hide">
                            <button
                                v-for="tab in [
                                    { id: 'resumen', label: 'Resumen', icon: InformationCircleIcon },
                                    { id: 'documentos', label: 'Documentos', icon: FolderOpenIcon },
                                    { id: 'financiero', label: 'Financiero', icon: CreditCardIcon },
                                    { id: 'actuaciones', label: 'Actuaciones', icon: ClipboardDocumentListIcon },
                                    { id: 'actividad', label: 'Historial', icon: ClockIcon },
                                ]"
                                :key="tab.id" @click="setActiveTab(tab.id)"
                                :class="[ activeTab === tab.id ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-400 hover:text-gray-600 hover:border-gray-300' ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-bold text-[11px] uppercase tracking-wider transition-all group"
                            >
                                <component :is="tab.icon" :class="[activeTab === tab.id ? 'text-indigo-600' : 'text-gray-300 group-hover:text-gray-400']" class="h-4 w-4 mr-2 transition-colors" />
                                {{ tab.label }}
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <ResumenTab v-show="activeTab === 'resumen'" :caso="caso" :resumen_financiero="resumen_financiero" :formatCurrency="formatCurrency" :formatDate="formatDate" :formatLabel="formatLabel" />
                        <DocumentosTab v-show="activeTab === 'documentos'" :caso="caso" :plantillas="plantillas" :puedeEditar="puedeEditar" />
                        <FinancieroTab v-show="activeTab === 'financiero'" :caso="caso" :resumen_financiero="resumen_financiero" :contrato_id="caso.contrato?.id" :formatCurrency="formatCurrency" />
                        <ActuacionesTab v-show="activeTab === 'actuaciones'" :caso="caso" :actuaciones="actuaciones" :is-form-disabled="!puedeEditar" />
                        <ActividadTab v-show="activeTab === 'actividad'" :bitacoras="bitacoras" :auditoria="auditoria" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
