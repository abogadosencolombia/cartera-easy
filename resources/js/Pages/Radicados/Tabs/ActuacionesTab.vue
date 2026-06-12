<script setup>
import {
    PlusIcon,
    ClockIcon,
    CheckCircleIcon,
    UserIcon,
    PencilIcon,
    TrashIcon,
    ChatBubbleBottomCenterTextIcon,
    SparklesIcon,
    CalendarDaysIcon,
    ScaleIcon,
    ClipboardDocumentListIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppAlert from '@/Utils/appAlert';

const props = defineProps({
    proceso: { type: Object, required: true },
    actuaciones: { type: Array, default: () => [] },
    isClosed: { type: Boolean, default: false },
    fmtDateTime: { type: Function, required: true },
    fmtDateSimple: { type: Function, required: true },
});

const emit = defineEmits(['save', 'edit', 'delete']);

const quickTexts = [
    'Se libra mandamiento de pago.',
    'Se radica demanda ante el despacho.',
    'Se solicita medidas cautelares.',
    'Se contesta demanda.',
    'Audiencia inicial celebrada.',
    'Liquidación de crédito aprobada.',
    'Sentencia favorable ejecutoriada.',
];

const actuacionForm = useForm({
    nota: '',
    fecha_actuacion: new Date().toISOString().slice(0, 10),
});

const normalizeText = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const cleanValue = (value, fallback = 'N/A') => value ? String(value).trim() : fallback;
const userInitial = (name) => (name || 'Sistema').trim().charAt(0).toUpperCase();
const firstParty = (items) => items?.[0]?.nombre_completo || null;

const actuacionesList = computed(() => props.actuaciones || []);
const ultimaActuacion = computed(() => {
    if (!actuacionesList.value.length) return null;
    return [...actuacionesList.value].sort((a, b) => new Date(b.fecha_actuacion || b.created_at) - new Date(a.fecha_actuacion || a.created_at))[0];
});

const summaryCards = computed(() => [
    {
        label: 'Actuaciones',
        value: actuacionesList.value.length,
        subtext: actuacionesList.value.length === 1 ? 'hito procesal' : 'hitos procesales',
        icon: ClipboardDocumentListIcon,
        iconClass: 'text-indigo-500',
    },
    {
        label: 'Último hito',
        value: ultimaActuacion.value ? props.fmtDateSimple(ultimaActuacion.value.fecha_actuacion) : 'Sin registros',
        subtext: ultimaActuacion.value ? `Por ${ultimaActuacion.value.user?.name || 'Sistema'}` : 'Cronología pendiente',
        icon: CalendarDaysIcon,
        iconClass: 'text-amber-500',
    },
    {
        label: 'Etapa actual',
        value: props.proceso.etapa_actual?.nombre || props.proceso.estado || 'N/A',
        subtext: props.proceso.radicado || 'Sin radicado visible',
        icon: ScaleIcon,
        iconClass: 'text-sky-500',
    },
]);

const appendQuickText = (text) => {
    actuacionForm.nota = actuacionForm.nota ? `${actuacionForm.nota} ${text}` : text;
};

const generarNotaInteligente = () => {
    const p = props.proceso || {};
    const etapa = cleanValue(p.etapa_actual?.nombre || p.estado, 'trámite sin etapa definida');
    const etapaNormalizada = normalizeText(etapa);
    const fechaActuacion = props.fmtDateSimple(actuacionForm.fecha_actuacion);
    const radicado = cleanValue(p.radicado, null);
    const despacho = cleanValue(p.juzgado?.nombre || p.entidad, null);
    const demandante = cleanValue(firstParty(p.demandantes), null);
    const demandado = cleanValue(firstParty(p.demandados), null);
    const asunto = cleanValue(p.asunto, null);

    const contexto = [
        radicado ? `radicado ${radicado}` : null,
        despacho ? `despacho ${despacho}` : null,
        demandante ? `demandante ${demandante}` : null,
        demandado ? `demandado ${demandado}` : null,
        asunto ? `asunto: ${asunto}` : null,
    ].filter(Boolean).join('; ');

    const lineas = [
        `El ${fechaActuacion}, en seguimiento al expediente${contexto ? ` (${contexto})` : ''}, se deja constancia de revisión en la etapa: ${etapa}.`,
    ];

    if (etapaNormalizada.includes('demanda') || etapaNormalizada.includes('radic')) {
        lineas.push('Se verifica el estado de radicación y la suficiencia de soportes para continuar el impulso procesal ante el despacho.');
    } else if (etapaNormalizada.includes('mandamiento')) {
        lineas.push('Se recomienda revisar notificación, términos procesales, constancias y actuaciones posteriores al mandamiento.');
    } else if (etapaNormalizada.includes('notifica')) {
        lineas.push('Se deja seguimiento a la gestión de notificación, soportes enviados, constancias disponibles y términos pendientes.');
    } else if (etapaNormalizada.includes('medida') || etapaNormalizada.includes('cautelar')) {
        lineas.push('Se revisa el estado de medidas cautelares, oficios, respuestas de entidades y gestiones pendientes para su materialización.');
    } else if (etapaNormalizada.includes('audiencia')) {
        lineas.push('Se registra seguimiento a la audiencia, decisiones adoptadas, compromisos y tareas procesales derivadas.');
    } else if (etapaNormalizada.includes('sentencia') || etapaNormalizada.includes('liquid') || etapaNormalizada.includes('termin')) {
        lineas.push('Se verifica el estado posterior a la decisión, ejecutoria, liquidación, cumplimiento y actuaciones necesarias para cierre o continuidad.');
    } else {
        lineas.push('Se actualiza la trazabilidad del expediente y se recomienda validar actuaciones recientes, documentos, términos y próximo impulso requerido.');
    }

    lineas.push('Observación: ajustar esta nota con los hechos concretos verificados antes de guardar la actuación.');

    const texto = lineas.join('\n');
    const teniaContenido = Boolean(actuacionForm.nota?.trim());
    actuacionForm.nota = teniaContenido ? `${actuacionForm.nota.trim()}\n\n${texto}` : texto;

    AppAlert.fire({
        title: teniaContenido ? 'Nota añadida' : 'Nota generada',
        text: teniaContenido ? 'Añadí una sugerencia sin borrar el texto existente.' : 'Generé una nota contextual para revisar antes de guardar.',
        icon: 'success',
        toast: true,
        position: 'top-end',
        timer: 3000,
    });
};

const handleSave = () => {
    if (!actuacionForm.nota?.trim()) return;
    emit('save', { ...actuacionForm.data() });
};
</script>

<template>
    <div class="space-y-5 animate-in fade-in duration-500">
        <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div
                v-for="item in summaryCards"
                :key="item.label"
                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex items-start gap-3">
                    <div class="shrink-0 rounded-lg bg-gray-50 p-2 dark:bg-gray-900">
                        <component :is="item.icon" class="h-5 w-5" :class="item.iconClass" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ item.label }}</p>
                        <p class="mt-1 break-words text-sm font-black leading-5 text-gray-900 dark:text-white">{{ item.value }}</p>
                        <p class="mt-0.5 break-words text-[10px] font-semibold text-gray-500 dark:text-gray-400">{{ item.subtext }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="!isClosed" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <PlusIcon class="h-5 w-5 text-indigo-500" />
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">Nueva actuación procesal</h3>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400">Registro jurídico del avance del radicado</p>
                    </div>
                </div>
                <button type="button" @click="generarNotaInteligente" class="inline-flex w-fit items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white transition-colors hover:bg-indigo-700">
                    <SparklesIcon class="h-3.5 w-3.5" />
                    Autocompletar nota
                </button>
            </div>

            <form @submit.prevent="handleSave" class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-12">
                <div class="space-y-3 xl:col-span-8">
                    <Textarea v-model="actuacionForm.nota" rows="5" class="w-full rounded-lg border-gray-200 bg-gray-50 p-3 text-sm leading-6 dark:bg-gray-900/40" placeholder="Detalle claro de la actuación, gestión, decisión o revisión procesal..." required />
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <button v-for="txt in quickTexts" :key="txt" type="button" @click="appendQuickText(txt)" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-left text-[11px] font-bold text-gray-600 transition-colors hover:border-indigo-300 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300">
                            {{ txt }}
                        </button>
                    </div>
                </div>
                <div class="space-y-4 rounded-lg border border-gray-100 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-900/30 xl:col-span-4">
                    <div>
                        <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-gray-400">Fecha de actuación</p>
                        <DatePicker v-model="actuacionForm.fecha_actuacion" class="w-full !text-xs" required />
                    </div>
                    <PrimaryButton :disabled="actuacionForm.processing" class="w-full !justify-center !rounded-lg !bg-indigo-600 !py-2.5 !text-[10px] !font-bold uppercase tracking-widest transition-all">
                        <PlusIcon class="mr-1.5 h-3.5 w-3.5" />
                        Registrar actuación
                    </PrimaryButton>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <ClockIcon class="h-5 w-5 text-indigo-500" />
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">Historial de actuaciones</h3>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-500 dark:text-gray-400">Cronología jurídica del expediente</p>
                    </div>
                </div>
                <span class="w-fit rounded-full bg-gray-100 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                    {{ actuacionesList.length }} evento{{ actuacionesList.length !== 1 ? 's' : '' }}
                </span>
            </div>

            <div v-if="!actuacionesList.length" class="mt-5 flex min-h-64 flex-col items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50/60 text-center dark:border-gray-700 dark:bg-gray-900/30">
                <ChatBubbleBottomCenterTextIcon class="mb-3 h-12 w-12 text-gray-300" />
                <p class="text-[11px] font-black uppercase tracking-widest text-gray-400">Sin actividad procesal registrada</p>
            </div>

            <ol v-else class="relative mt-5 space-y-4 before:absolute before:bottom-0 before:left-8 before:top-0 before:w-px before:bg-gray-100 dark:before:bg-gray-700">
                <li v-for="act in actuacionesList" :key="act.id" class="relative grid grid-cols-[4rem_1fr] gap-4">
                    <div class="relative z-10 flex h-16 w-16 flex-col items-center justify-center rounded-lg border border-indigo-100 bg-indigo-50 text-indigo-700 shadow-sm dark:border-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-300">
                        <CheckCircleIcon class="h-5 w-5" />
                        <span class="mt-1 text-[9px] font-black uppercase tracking-widest">Hito</span>
                    </div>

                    <article class="min-w-0 rounded-lg border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-900/30">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-300">{{ fmtDateSimple(act.fecha_actuacion) }}</p>
                                <p class="mt-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400">Registrada: {{ fmtDateTime(act.created_at) }}</p>
                            </div>
                            <div v-if="!isClosed" class="flex shrink-0 gap-2">
                                <button @click="emit('edit', act)" class="rounded-lg border border-gray-200 bg-white p-2 text-gray-500 transition-colors hover:border-indigo-300 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-800" title="Editar actuación">
                                    <PencilIcon class="h-4 w-4" />
                                </button>
                                <button @click="emit('delete', act.id)" class="rounded-lg border border-gray-200 bg-white p-2 text-gray-500 transition-colors hover:border-rose-300 hover:text-rose-600 dark:border-gray-700 dark:bg-gray-800" title="Eliminar actuación">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <p class="mt-3 whitespace-pre-wrap break-words text-sm font-medium leading-6 text-gray-800 dark:text-gray-200">{{ act.nota }}</p>

                        <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-[10px] font-black uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {{ userInitial(act.user?.name) }}
                                </div>
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Responsable</p>
                                    <p class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300">{{ act.user?.name || 'Sistema' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                                <DocumentTextIcon class="h-3.5 w-3.5" />
                                Actuación procesal
                            </div>
                        </div>
                    </article>
                </li>
            </ol>
        </section>
    </div>
</template>
