<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, useRemember, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';

// --- IMPORTAMOS LOS NUEVOS COMPONENTES DE PESTAÑA ---
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
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: { type: Object, required: true },
    can: { type: Object, required: true },
    plantillas: { type: Array, default: () => [] },
    actuaciones: { type: Array, default: () => [] },
    resumen_financiero: { type: Object, required: true },
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

// --- SINCRONIZACIÓN CON URL ---
watch(activeTab, (newTab) => {
    router.replace(route('casos.show', props.caso.id), {
        data: { tab: newTab },
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
});

// --- Lógica de formato ---
const parseMoney = (input) => {
    let s = String(input ?? '').trim();
    if (!s) return 0;
    s = s.replace(/\s/g, '');
    const hasComma = s.includes(',');
    const hasDot = s.includes('.');
    if (hasComma && hasDot) {
        const decimalSep = s.lastIndexOf(',') > s.lastIndexOf('.') ? ',' : '.';
        const thousandSep = decimalSep === ',' ? '.' : ',';
        s = s.replace(new RegExp('\\' + thousandSep, 'g'), '');
        if (decimalSep === ',') s = s.replace(',', '.');
    } else if (hasComma) {
        s = s.replace(/\./g, '');
        s = s.replace(',', '.');
    } else {
        s = s.replace(/,/g, '');
    }
    const n = Number(s);
    return Number.isFinite(n) ? n : 0;
};

const formatCurrency = (value) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 })
    .format(parseMoney(value));

const parseDate = (s) => {
    if (!s) return null;
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        const [y, m, d] = s.split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    return new Date(String(s).replace(' ', 'T'));
};

const formatDate = (s) =>
    parseDate(s)?.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        timeZone: 'UTC'
    }) || 'No especificada';

const formatLabel = (text) => {
    if (!text) return 'N/A';
    if (text.indexOf('_') === -1) {
        return text;
    }
    return text.replace(/_/g, ' ')
            .toLowerCase()
            .replace(/\b\w/g, char => char.toUpperCase());
};

const statusProcessoClasses = {
    'prejurídico': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    'demandado': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    'en ejecución': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
    'sentencia': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    'cerrado': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    'default': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    'DEMANDA PRESENTADA': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
};

const puedeEditar = computed(() => {
    if (!props.can.update) return false;
    if (user?.tipo_usuario === 'admin') return true;
    return !props.caso.bloqueado;
});

const montoParaContrato = computed(() => {
    return props.resumen_financiero.saldo_pendiente; 
});

</script>

<template>
    <Head :title="'Ficha del Caso #' + caso.id" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                <div>
                    <Link
                        :href="route('casos.index')"
                        class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center mb-1"
                    >
                        &larr; Volver a Gestión de Casos
                    </Link>
                    <h2 class="font-semibold text-2xl text-gray-800 dark:text-gray-200 leading-tight">
                        Ficha del Caso <span class="text-indigo-500">#{{ caso.id }}</span>
                        <span
                            v-if="caso.bloqueado"
                            class="ml-2 px-2 py-0.5 text-xs rounded bg-red-100 text-red-700 align-middle"
                        >Bloqueado</span>
                        
                        <button
                            v-if="caso.bloqueado && user?.tipo_usuario === 'admin'" 
                            @click="confirmUnlockCase"
                            class="ml-2 inline-flex items-center px-2.5 py-1 border border-transparent rounded-md shadow-sm text-xs font-medium text-yellow-800 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150 align-middle"
                            title="Desbloquear Caso"
                        >
                            <LockOpenIcon class="h-4 w-4 mr-1.5" />
                            Desbloquear
                        </button>
                    </h2>
                </div>
                <div class="flex items-center space-x-2 flex-shrink-0">
                    <Link
                        v-if="puedeEditar"
                        :href="route('casos.clonar', caso.id)"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none disabled:opacity-25 transition ease-in-out duration-150"
                    >
                        Clonar
                    </Link>
                    <Link
                        v-if="puedeEditar"
                        :href="route('casos.edit', caso.id)"
                        class="inline-flex items-center px-4 py-2 bg-blue-500 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-blue-600 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    >
                        Editar Caso
                    </Link>

                    <Link
                        v-if="!caso.contrato && puedeEditar"
                        :href="route('honorarios.contratos.create', { caso_id: caso.id, monto: montoParaContrato })"
                        class="inline-flex items-center px-4 py-2 bg-blue-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 focus:bg-blue-600 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 shadow-sm"
                    >
                        Generar Contrato
                    </Link>
                    

                    <Link
                        v-else-if="caso.contrato"
                        :href="route('honorarios.contratos.show', caso.contrato.id)"
                        class="inline-flex items-center px-4 py-2 bg-green-100 border border-green-300 rounded-md font-semibold text-xs text-green-800 uppercase tracking-widest hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:bg-green-900 dark:border-green-700 dark:text-green-200 dark:hover:bg-green-800 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    >
                        Ver Contrato
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash.success"
                    class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md"
                    role="status"
                    aria-live="polite"
                >
                    <p class="font-bold">Éxito</p>
                    <p>{{ page.props.flash.success }}</p>
                </div>
                <div
                    v-if="page.props.flash.error"
                    class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md"
                    role="alert"
                >
                    <p class="font-bold">Error</p>
                    <p>{{ page.props.flash.error }}</p>
                </div>

                <!-- TARJETA DE RESUMEN PRINCIPAL -->
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl p-6 mb-8">
                    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                        <div class="flex-grow">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                Deudor Principal
                            </p>
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">
                                {{ caso.deudor ? caso.deudor.nombre_completo : 'No Asignado' }}
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                {{ caso.cooperativa ? caso.cooperativa.nombre : 'N/A' }}
                            </p>
                        </div>
                        <div class="flex items-center justify-end flex-wrap gap-4 mt-4 md:mt-0">
                            
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Monto de Crédito (Inicial)</p>
                                <p class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                                    <!-- CORRECCIÓN: Usamos el monto del resumen (que ya puede ser el del contrato) -->
                                    {{ formatCurrency(resumen_financiero.monto_total) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Deuda Actual (Calculada)</p>
                                <p class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                                    {{ formatCurrency(resumen_financiero.saldo_pendiente) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Pagado (Contrato)</p>
                                <p class="text-xl font-semibold text-green-600 dark:text-green-400">
                                    {{ formatCurrency(resumen_financiero.total_pagado) }}
                                </p>
                            </div>

                            <!-- CORRECCIÓN: Usar la mora calculada por el backend -->
                            <div
                                v-if="resumen_financiero.dias_mora > 0"
                                class="text-right bg-red-50 dark:bg-red-900/50 p-3 rounded-lg"
                            >
                                <p class="text-sm font-medium text-red-600 dark:text-red-300">Días en Mora</p>
                                <p class="text-xl font-bold text-red-700 dark:text-red-200">
                                    {{ Math.floor(resumen_financiero.dias_mora) }}
                                </p>
                            </div>
                            
                            <div class="text-right" v-if="caso.etapa_procesal || caso.estado_proceso">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado</p>
                                <span
                                    class="px-3 py-1.5 inline-flex text-sm leading-5 font-semibold rounded-full"
                                    :class="statusProcessoClasses[caso.etapa_procesal || caso.estado_proceso] || statusProcessoClasses['default']"
                                >{{ formatLabel(caso.etapa_procesal || caso.estado_proceso) }}</span>
                            </div>
                            
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-6 overflow-x-auto px-6" aria-label="Tabs">
                            <button
                                @click="activeTab = 'resumen'"
                                :class="[
                                    activeTab === 'resumen'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <InformationCircleIcon class="h-5 w-5 mr-2" /> Resumen
                            </button>
                            <button
                                @click="activeTab = 'documentos'"
                                :class="[
                                    activeTab === 'documentos'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <FolderOpenIcon class="h-5 w-5 mr-2" /> Documentos
                            </button>
                            <button
                                @click="activeTab = 'financiero'"
                                :class="[
                                    activeTab === 'financiero'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <CreditCardIcon class="h-5 w-5 mr-2" /> Financiero
                            </button>
                            <button
                                @click="activeTab = 'actuaciones'"
                                :class="[
                                    activeTab === 'actuaciones'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <ClipboardDocumentListIcon class="h-5 w-5 mr-2" /> Actuaciones
                            </button>
                            <button
                                @click="activeTab = 'actividad'"
                                :class="[
                                    activeTab === 'actividad'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                                ]"
                                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                            >
                                <ClockIcon class="h-5 w-5 mr-2" /> Actividad y Logs
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <ResumenTab 
                            v-show="activeTab === 'resumen'"
                            :caso="caso"
                            :resumen_financiero="resumen_financiero"
                            :formatCurrency="formatCurrency"
                            :formatDate="formatDate"
                            :formatLabel="formatLabel"
                        />
                        
                        <DocumentosTab
                            v-show="activeTab === 'documentos'"
                            :caso="caso"
                            :plantillas="plantillas"
                            :puedeEditar="puedeEditar"
                        />
                        
                        <FinancieroTab
                            v-show="activeTab === 'financiero'"
                            :resumen_financiero="resumen_financiero"
                            :contrato_id="caso.contrato?.id"
                            :formatCurrency="formatCurrency"
                        />
                        
                        <ActuacionesTab
                            v-show="activeTab === 'actuaciones'"
                            :caso="caso"
                            :actuaciones="actuaciones"
                            :is-form-disabled="!puedeEditar"
                        />
                        
                        <ActividadTab
                            v-show="activeTab === 'actividad'"
                            :bitacoras="caso.bitacoras"
                            :auditoria="caso.auditoria"
                        />
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style>
.vue-select-style .vs__dropdown-toggle {
    border-color: #d1d5db; 
    background-color: white;
    border-radius: 0.375rem; 
    min-height: 42px;
}
.dark .vue-select-style .vs__dropdown-toggle {
    border-color: #4b5563; 
    background-color: #1f2937; 
}
.vue-select-style .vs__search, .vue-select-style .vs__selected {
    color: #111827; 
    margin-top: 0;
    padding-left: 0.25rem;
}
.dark .vue-select-style .vs__search, .dark .vue-select-style .vs__selected {
    color: #f3f4f6; 
}
.vue-select-style .vs__dropdown-menu {
    background-color: white;
    border-color: #d1d5db;
    z-index: 50;
}
.dark .vue-select-style .vs__dropdown-menu {
    background-color: #1f2937;
    border-color: #4b5563;
}
.vue-select-style .vs__option {
    color: #374151; 
}
.dark .vue-select-style .vs__option {
    color: #d1d5db; 
}
.vue-select-style .vs__option--highlight {
    background-color: #4f46e5; 
    color: white;
}
.vue-select-style .vs__clear, .vue-select-style .vs__open-indicator {
    fill: #6b7280; 
}
.dark .vue-select-style .vs__clear, .dark .vue-select-style .vs__open-indicator {
    fill: #9ca3af; 
}
.vue-select-style .vs__selected-options {
    padding-left: 0.25rem;
}
</style>