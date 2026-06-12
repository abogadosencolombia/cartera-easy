<script setup>
import { useForm, router, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import DateTimePicker from '@/Components/DateTimePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import GuiaEtapa from '@/Components/GuiaEtapa.vue';
import PersonaCompletenessIndicator from '@/Components/PersonaCompletenessIndicator.vue';
import { getCaseFinancialStatus } from '@/Utils/caseFinancialStatus';
import {
    CreditCardIcon,
    ScaleIcon,
    UsersIcon,
    PhoneIcon,
    EnvelopeIcon,
    MapPinIcon,
    BellIcon,
    ArrowTopRightOnSquareIcon,
    GlobeAltIcon,
    BuildingOfficeIcon,
    IdentificationIcon,
    CalendarIcon,
    ClipboardDocumentListIcon,
    PlusIcon,
    BanknotesIcon,
    FolderOpenIcon,
    PencilSquareIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: { type: Object, required: true },
    resumen_financiero: { type: Object, required: true },
    financialStatus: { type: Object, default: null },
    puedeEditar: { type: Boolean, default: false },
    formatCurrency: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    formatLabel: { type: Function, required: true },
});

const resolvedFinancialStatus = computed(() => props.financialStatus || getCaseFinancialStatus(props.caso));
const financialField = (key) => resolvedFinancialStatus.value.fields.find((field) => field.key === key);

const safeJsonParse = (jsonString) => {
    if (!jsonString) return [];
    if (Array.isArray(jsonString)) return jsonString;
    if (typeof jsonString === 'object') return [];
    try {
        const parsed = JSON.parse(jsonString);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
};

const normalizePhone = (phone) => String(phone || '').replace(/\D/g, '');
const whatsappHref = (phone) => {
    const digits = normalizePhone(phone);
    if (!digits) return null;
    return `https://wa.me/${digits.startsWith('57') ? digits : `57${digits}`}`;
};
const personPhone = (person) => person?.celular_1 || person?.celular || person?.telefono || null;
const personEmail = (person) => person?.correo_1 || person?.correo || person?.email || null;
const personDocument = (person) => [person?.tipo_documento, person?.numero_documento].filter(Boolean).join(' ') || 'Sin documento';
const personAddresses = (person) => safeJsonParse(person?.addresses).map((addr) => typeof addr === 'string' ? addr : addr?.address).filter(Boolean);

const legalFields = computed(() => [
    { label: 'Radicado', value: props.caso.radicado || 'SIN ASIGNAR', mono: true, badge: props.caso.es_spoa_nunc ? 'SPOA/NUNC' : null },
    { label: 'Pagaré / Referencia', value: props.caso.referencia_credito || 'SIN ASIGNAR' },
    { label: 'Especialidad', value: props.caso.especialidad?.nombre || props.formatLabel(props.caso.especialidad_nombre) },
    { label: 'Tipo de Proceso', value: props.formatLabel(props.caso.tipo_proceso) },
    { label: 'Proceso', value: props.formatLabel(props.caso.subtipo_proceso) },
    { label: 'Subproceso', value: props.formatLabel(props.caso.subproceso) || 'N/A' },
    { label: 'Etapa Procesal', value: props.formatLabel(props.caso.etapa_procesal), highlight: true },
    { label: 'Canal de Notificación', value: props.caso.medio_contacto || 'No especificado' },
    { label: 'Juzgado Asignado', value: props.caso.juzgado?.nombre || 'Pendiente de asignación', wide: true, icon: BuildingOfficeIcon },
]);

const timelineFields = computed(() => [
    { label: 'Fecha Demanda', value: props.formatDate(props.caso.fecha_apertura) },
    { label: 'Vencimiento', value: props.formatDate(props.caso.fecha_vencimiento) },
    { label: 'Garantía', value: props.caso.tipo_garantia_asociada || 'N/A' },
    { label: 'Origen', value: props.caso.origen_documental || 'N/A' },
]);

const financialCards = computed(() => {
    const paidField = financialField('monto_total_pagado');

    return [
        {
            label: 'Monto de Crédito (Capital)',
            value: financialField('monto_total')?.missing ? 'Pendiente por registrar' : props.formatCurrency(props.caso.monto_total),
            missing: financialField('monto_total')?.missing,
            valueClass: 'text-gray-950 dark:text-white'
        },
        {
            label: 'Deuda Actual',
            value: financialField('monto_deuda_actual')?.missing ? 'Pendiente por registrar' : props.formatCurrency(props.caso.monto_deuda_actual),
            missing: financialField('monto_deuda_actual')?.missing,
            valueClass: 'text-rose-600 dark:text-rose-400'
        },
        {
            label: 'Total Pagado',
            value: paidField?.missing ? 'Pendiente por registrar' : (paidField?.displayLabel || props.formatCurrency(props.caso.monto_total_pagado)),
            missing: paidField?.missing,
            valueClass: paidField?.displayLabel ? 'text-gray-500 dark:text-gray-300' : 'text-emerald-600 dark:text-emerald-400'
        },
        {
            label: 'Fecha Inicio Crédito',
            value: props.formatDate(props.caso.fecha_inicio_credito),
            valueClass: 'text-gray-700 dark:text-gray-200'
        },
    ];
});

const topHighlights = computed(() => [
    {
        label: 'Etapa',
        value: props.formatLabel(props.caso.etapa_procesal),
        subtext: props.formatLabel(props.caso.tipo_proceso),
        icon: ScaleIcon,
        iconClass: 'text-indigo-500',
        valueClass: 'text-indigo-700 dark:text-indigo-300'
    },
    {
        label: 'Deuda Actual',
        value: financialField('monto_deuda_actual')?.missing ? 'Pendiente' : props.formatCurrency(props.caso.monto_deuda_actual),
        subtext: 'Saldo vigente',
        icon: BanknotesIcon,
        iconClass: financialField('monto_deuda_actual')?.missing ? 'text-amber-500' : 'text-rose-500',
        valueClass: financialField('monto_deuda_actual')?.missing ? 'text-amber-700 dark:text-amber-300' : 'text-rose-600 dark:text-rose-400'
    },
    {
        label: 'Vencimiento',
        value: props.formatDate(props.caso.fecha_vencimiento),
        subtext: props.caso.fecha_apertura ? `Demanda: ${props.formatDate(props.caso.fecha_apertura)}` : 'Sin fecha de demanda',
        icon: CalendarIcon,
        iconClass: 'text-amber-500',
        valueClass: 'text-gray-900 dark:text-white'
    },
    {
        label: 'Demandado',
        value: props.caso.deudor?.nombre_completo || 'N/A',
        subtext: personDocument(props.caso.deudor),
        icon: UsersIcon,
        iconClass: 'text-sky-500',
        valueClass: 'text-gray-900 dark:text-white',
        person: props.caso.deudor
    },
]);

const hasObservations = computed(() => props.caso.notas_legales || props.caso.nota_cierre || props.caso.motivo_bloqueo);
const hasExternalLinks = computed(() => props.caso.link_drive || props.caso.link_expediente);

const notifForm = useForm({ fecha_programada: '', mensaje: '', prioridad: 'media' });
const submitNotification = () => {
    notifForm.post(route('casos.notificaciones.store', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => { notifForm.reset(); router.reload({ only: ['auth'] }); },
    });
};
</script>

<template>
    <div class="space-y-5 animate-in fade-in duration-500">
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
            <div
                v-for="item in topHighlights"
                :key="item.label"
                class="min-w-0 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm"
            >
                <div class="flex items-start gap-3">
                    <div class="shrink-0 rounded-lg bg-gray-50 dark:bg-gray-900 p-2">
                        <component :is="item.icon" class="h-5 w-5" :class="item.iconClass" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ item.label }}</p>
                        <p class="mt-1 break-words text-sm font-black uppercase leading-5" :class="item.valueClass" :title="item.value">{{ item.value }}</p>
                        <p class="mt-0.5 break-words text-[10px] font-semibold text-gray-500 dark:text-gray-400" :title="item.subtext">{{ item.subtext }}</p>
                        <PersonaCompletenessIndicator v-if="item.person" :persona="item.person" compact class="mt-2" />
                    </div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
            <div class="xl:col-span-8 space-y-5">
                <!-- DETALLES DEL PROCESO -->
                <section class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-gray-100 dark:border-gray-700 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <ScaleIcon class="h-5 w-5 text-indigo-500" />
                            <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-tight text-sm">Información Jurídica</h3>
                        </div>
                        <Link
                            v-if="puedeEditar"
                            :href="route('casos.edit', caso.id)"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-[10px] font-black uppercase text-gray-600 dark:text-gray-300 transition-colors hover:border-indigo-300 hover:text-indigo-600"
                        >
                            <PencilSquareIcon class="h-3.5 w-3.5" />
                            Editar
                        </Link>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        <div
                            v-for="field in legalFields"
                            :key="field.label"
                            class="min-w-0 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 p-3"
                            :class="field.wide ? 'sm:col-span-2 xl:col-span-3' : ''"
                        >
                            <p class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-gray-400">
                                <component v-if="field.icon" :is="field.icon" class="h-3.5 w-3.5" />
                                {{ field.label }}
                            </p>
                            <div class="mt-1 flex min-w-0 items-center gap-2">
                                <p
                                    class="break-words text-xs font-bold uppercase"
                                    :class="[field.highlight ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-800 dark:text-gray-200', field.mono ? 'font-mono' : '']"
                                    :title="field.value"
                                >
                                    {{ field.value }}
                                </p>
                                <span v-if="field.badge" class="shrink-0 rounded border border-indigo-200 bg-indigo-100 px-1.5 py-0.5 text-[8px] font-black uppercase tracking-tighter text-indigo-700">{{ field.badge }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3 border-t border-gray-100 dark:border-gray-700 pt-4">
                        <div v-for="field in timelineFields" :key="field.label" class="min-w-0">
                            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest">{{ field.label }}</p>
                            <p class="mt-1 break-words text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300" :title="field.value">{{ field.value }}</p>
                        </div>
                    </div>
                </section>

                <!-- INFORMACIÓN FINANCIERA DETALLADA -->
                <section class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-700 pb-4">
                        <CreditCardIcon class="h-5 w-5 text-emerald-500" />
                        <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-tight text-sm">Detalles del Crédito</h3>
                    </div>

                    <div
                        v-if="resolvedFinancialStatus.hasMissing"
                        class="mt-4 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/15 p-4"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-black text-amber-900 dark:text-amber-100 uppercase">Montos por confirmar</p>
                                <p class="text-[11px] font-semibold text-amber-700 dark:text-amber-300 mt-1">{{ resolvedFinancialStatus.detail }}</p>
                            </div>
                            <Link
                                v-if="puedeEditar"
                                :href="route('casos.edit', caso.id) + '?tab=info-principal'"
                                class="inline-flex shrink-0 items-center justify-center rounded-lg border border-amber-200 dark:border-amber-700 bg-white dark:bg-gray-800 px-3 py-2 text-[10px] font-black uppercase text-amber-700 dark:text-amber-200 transition-colors hover:border-amber-400"
                            >
                                Editar montos
                            </Link>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                        <div v-for="field in financialCards" :key="field.label" class="rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 p-3">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ field.label }}</p>
                            <p class="mt-1 text-sm font-black leading-5" :class="field.missing ? 'text-amber-600 dark:text-amber-300 uppercase text-xs' : field.valueClass">{{ field.value }}</p>
                        </div>
                    </div>
                </section>

                <!-- OBSERVACIONES Y NOTAS -->
                <section v-if="hasObservations" class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-700 pb-4">
                        <ClipboardDocumentListIcon class="h-5 w-5 text-amber-500" />
                        <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-tight text-sm">Observaciones y Estado</h3>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3">
                        <div v-if="caso.notas_legales" class="rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Notas Legales / Internas</p>
                            <p class="text-xs leading-5 text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ caso.notas_legales }}</p>
                        </div>

                        <div v-if="caso.nota_cierre" class="rounded-lg border border-rose-100 dark:border-rose-900/30 bg-rose-50 dark:bg-rose-900/10 p-4">
                            <p class="text-[10px] font-black text-rose-600 dark:text-rose-400 uppercase tracking-widest mb-2">Nota de Cierre (Caso Finalizado)</p>
                            <p class="text-xs leading-5 text-rose-800 dark:text-rose-300 font-medium">{{ caso.nota_cierre }}</p>
                        </div>

                        <div v-if="caso.bloqueado && caso.motivo_bloqueo" class="rounded-lg border border-amber-100 dark:border-amber-900/30 bg-amber-50 dark:bg-amber-900/10 p-4">
                            <p class="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest mb-2">Motivo de Bloqueo</p>
                            <p class="text-xs leading-5 text-amber-800 dark:text-amber-300 font-medium">{{ caso.motivo_bloqueo }}</p>
                        </div>
                    </div>
                </section>

                <section class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-visible">
                    <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-tight text-sm mb-5 flex items-center gap-2">
                        <CalendarIcon class="h-5 w-5 text-amber-500" /> Guía de Gestión Procesal
                    </h3>
                    <GuiaEtapa :etapa="caso.etapa_procesal" :tipo-proceso="caso.tipo_proceso" :checklist-completados="caso.checklist_seguimiento || []" :model-id="caso.id" model-type="caso" :entity="caso" />
                </section>
            </div>

            <aside class="xl:col-span-4 space-y-5">
                <!-- ENLACES -->
                <section v-if="hasExternalLinks" class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-700 pb-4">
                        <FolderOpenIcon class="h-5 w-5 text-indigo-500" />
                        <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-tight text-sm">Accesos del Expediente</h3>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3">
                        <a v-if="caso.link_drive" :href="caso.link_drive" target="_blank" class="flex items-center justify-between rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 p-3 transition-all hover:border-indigo-300 group">
                            <div class="flex min-w-0 items-center gap-3">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/1/12/Google_Drive_icon_%282020%29.svg" class="w-5 h-5 shrink-0" alt="">
                                <span class="truncate text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-tight">Expediente en Drive</span>
                            </div>
                            <ArrowTopRightOnSquareIcon class="h-4 w-4 shrink-0 text-gray-300 group-hover:text-indigo-500" />
                        </a>
                        <a v-if="caso.link_expediente" :href="caso.link_expediente" target="_blank" class="flex items-center justify-between rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 p-3 transition-all hover:border-emerald-300 group">
                            <div class="flex min-w-0 items-center gap-3">
                                <GlobeAltIcon class="w-5 h-5 shrink-0 text-emerald-500" />
                                <span class="truncate text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-tight">Expediente Digital</span>
                            </div>
                            <ArrowTopRightOnSquareIcon class="h-4 w-4 shrink-0 text-gray-300 group-hover:text-emerald-500" />
                        </a>
                    </div>
                </section>

                <!-- SUJETOS PROCESALES -->
                <section class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-700 pb-4">
                        <UsersIcon class="h-5 w-5 text-indigo-500" />
                        <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-tight text-sm">Sujetos Procesales</h3>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div class="rounded-lg border border-indigo-100 dark:border-indigo-800/50 bg-indigo-50/50 dark:bg-indigo-900/10 p-4">
                            <p class="text-[10px] font-black text-indigo-500 uppercase tracking-widest">Demandado (Deudor)</p>
                            <h4 class="mt-2 text-sm font-black text-gray-900 dark:text-white uppercase leading-5">{{ caso.deudor ? caso.deudor.nombre_completo : 'N/A' }}</h4>
                            <PersonaCompletenessIndicator :persona="caso.deudor" class="mt-2" />
                            <div class="mt-3 space-y-2 text-[11px] font-semibold text-gray-600 dark:text-gray-300">
                                <p class="flex items-center gap-2 font-mono"><IdentificationIcon class="h-3.5 w-3.5 text-gray-400" />{{ personDocument(caso.deudor) }}</p>
                                <a v-if="personPhone(caso.deudor)" :href="whatsappHref(personPhone(caso.deudor))" target="_blank" class="flex items-center gap-2 text-emerald-700 dark:text-emerald-300 hover:underline"><PhoneIcon class="h-3.5 w-3.5" />{{ personPhone(caso.deudor) }}</a>
                                <a v-if="personEmail(caso.deudor)" :href="'mailto:' + personEmail(caso.deudor)" class="flex items-center gap-2 text-indigo-700 dark:text-indigo-300 hover:underline"><EnvelopeIcon class="h-3.5 w-3.5" />{{ personEmail(caso.deudor) }}</a>
                                <p v-for="(addr, idx) in personAddresses(caso.deudor)" :key="idx" class="flex items-start gap-2 text-gray-500 dark:text-gray-400"><MapPinIcon class="mt-0.5 h-3.5 w-3.5 shrink-0" />{{ addr }}</p>
                            </div>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Codeudores</p>
                                <span class="rounded-full bg-gray-100 dark:bg-gray-900 px-2 py-0.5 text-[9px] font-black text-gray-500 dark:text-gray-400">{{ caso.codeudores?.length || 0 }}</span>
                            </div>

                            <div v-if="caso.codeudores?.length" class="space-y-2">
                                <div v-for="codeudor in caso.codeudores" :key="codeudor.id" class="rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 p-3">
                                    <h4 class="text-[11px] font-black text-gray-800 dark:text-gray-200 uppercase leading-5">{{ codeudor.nombre_completo }}</h4>
                                    <PersonaCompletenessIndicator :persona="codeudor" :href="route('casos.edit', caso.id) + '?tab=codeudores'" class="mt-2" />
                                    <div class="mt-2 space-y-1.5 text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                                        <p class="flex items-center gap-2 font-mono"><IdentificationIcon class="h-3 w-3" />{{ personDocument(codeudor) }}</p>
                                        <a v-if="personPhone(codeudor)" :href="whatsappHref(personPhone(codeudor))" target="_blank" class="flex items-center gap-2 text-emerald-700 dark:text-emerald-300 hover:underline"><PhoneIcon class="h-3 w-3" />{{ personPhone(codeudor) }}</a>
                                        <a v-if="personEmail(codeudor)" :href="'mailto:' + personEmail(codeudor)" class="flex items-center gap-2 text-indigo-700 dark:text-indigo-300 hover:underline"><EnvelopeIcon class="h-3 w-3" />{{ personEmail(codeudor) }}</a>
                                        <p v-for="(addr, idx) in personAddresses(codeudor)" :key="idx" class="flex items-start gap-2"><MapPinIcon class="mt-0.5 h-3 w-3 shrink-0" />{{ addr }}</p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="rounded-lg border border-dashed border-gray-200 dark:border-gray-700 p-3 text-[10px] font-semibold text-gray-400">Sin codeudores registrados</p>
                        </div>

                        <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Abogados Asignados</p>
                            <div class="flex flex-wrap gap-1.5">
                                <template v-if="caso.users?.length">
                                    <span v-for="u in caso.users" :key="u.id" class="rounded-lg border border-gray-100 dark:border-gray-700 bg-slate-50 dark:bg-gray-900 px-2 py-1 text-[9px] font-bold text-gray-600 dark:text-gray-400 uppercase">{{ u.name }}</span>
                                </template>
                                <span v-else class="text-[10px] text-gray-400 italic">No asignado</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- RECORDATORIO -->
                <section class="bg-indigo-950 p-5 rounded-lg shadow-lg relative overflow-visible z-20">
                    <h3 class="text-xs font-bold text-white uppercase tracking-widest flex items-center gap-2">
                        <BellIcon class="h-4 w-4 text-amber-400" /> Próximo Recordatorio
                    </h3>
                    <form @submit.prevent="submitNotification" class="mt-4 space-y-3">
                        <DateTimePicker v-model="notifForm.fecha_programada" class="!bg-white/10 !border-white/10 !text-white !rounded-lg text-xs" />
                        <Textarea v-model="notifForm.mensaje" class="!bg-white/10 !border-white/10 !text-white !rounded-lg text-[11px]" rows="2" placeholder="Nota rápida..." required />
                        <PrimaryButton :disabled="notifForm.processing" class="w-full !bg-white !text-indigo-950 !rounded-lg !py-2.5 !text-[10px] !font-bold uppercase tracking-widest shadow-md">
                            <PlusIcon class="h-3 w-3 mr-1.5" /> Agendar
                        </PrimaryButton>
                    </form>
                </section>
            </aside>
        </div>
    </div>
</template>
