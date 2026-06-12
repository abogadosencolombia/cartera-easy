<script setup>
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Textarea from '@/Components/Textarea.vue';
import DatePicker from '@/Components/DatePicker.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    PencilIcon,
    TrashIcon,
    PlusIcon,
    BookmarkIcon,
    PencilSquareIcon,
    SparklesIcon,
    ClipboardDocumentListIcon,
    CalendarDaysIcon,
    UserIcon,
    ScaleIcon,
    DocumentTextIcon
} from '@heroicons/vue/24/outline';
import AppAlert from '@/Utils/appAlert';

const props = defineProps({
    caso: Object,
    actuaciones: { type: Array, default: () => [] },
    isFormDisabled: Boolean,
});

const formatDateTime = (s) => {
    if (!s) return 'N/A';
    const date = new Date(s);
    if (Number.isNaN(date.getTime())) return 'N/A';
    return date.toLocaleString('es-CO', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatDate = (s) => {
    if (!s) return 'N/A';
    const date = new Date(s);
    if (Number.isNaN(date.getTime())) return 'N/A';
    return date.toLocaleDateString('es-CO', { year: 'numeric', month: 'short', day: 'numeric' });
};

const formatDay = (s) => {
    if (!s) return '—';
    const date = new Date(s);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('es-CO', { day: '2-digit' });
};

const formatMonth = (s) => {
    if (!s) return 'N/A';
    const date = new Date(s);
    if (Number.isNaN(date.getTime())) return 'N/A';
    return date.toLocaleDateString('es-CO', { month: 'short' });
};

const formatYear = (s) => {
    if (!s) return '';
    const date = new Date(s);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString('es-CO', { year: 'numeric' });
};

const userInitial = (name) => (name || 'Sistema').trim().charAt(0).toUpperCase();
const normalizeText = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const cleanValue = (value, fallback = 'N/A') => value ? String(value).trim() : fallback;
const formatCurrency = (value) => {
    const number = Number(String(value ?? '').replace(/[^0-9.-]+/g, ''));
    if (!Number.isFinite(number) || number === 0) return null;

    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(number);
};

const quickTexts = [
    'Se libra mandamiento de pago.',
    'Se radica demanda ante el despacho.',
    'Se solicita medidas cautelares.',
    'Se contesta demanda.',
    'Audiencia inicial celebrada.',
    'Sentencia favorable ejecutoriada.',
];

const appendQuickText = (text) => {
    actuacionForm.nota = actuacionForm.nota ? `${actuacionForm.nota} ${text}` : text;
};

const actuacionForm = useForm({ nota: '', fecha_actuacion: new Date().toISOString().slice(0, 10) });

const actuacionesList = computed(() => props.actuaciones || []);
const ultimaActuacion = computed(() => {
    if (!actuacionesList.value.length) return null;
    return [...actuacionesList.value].sort((a, b) => new Date(b.fecha_actuacion || b.created_at) - new Date(a.fecha_actuacion || a.created_at))[0];
});

const summaryCards = computed(() => [
    {
        label: 'Actuaciones',
        value: actuacionesList.value.length,
        subtext: actuacionesList.value.length === 1 ? 'registro procesal' : 'registros procesales',
        icon: ClipboardDocumentListIcon,
        iconClass: 'text-indigo-500'
    },
    {
        label: 'Última actuación',
        value: ultimaActuacion.value ? formatDate(ultimaActuacion.value.fecha_actuacion) : 'Sin registros',
        subtext: ultimaActuacion.value ? `Registrada por ${ultimaActuacion.value.user?.name || 'Sistema'}` : 'Historial pendiente',
        icon: CalendarDaysIcon,
        iconClass: 'text-amber-500'
    },
    {
        label: 'Responsable reciente',
        value: ultimaActuacion.value?.user?.name || 'Sistema',
        subtext: ultimaActuacion.value ? formatDateTime(ultimaActuacion.value.created_at) : 'Sin responsable',
        icon: UserIcon,
        iconClass: 'text-emerald-500'
    },
    {
        label: 'Etapa del caso',
        value: props.caso?.etapa_procesal || 'N/A',
        subtext: props.caso?.radicado || props.caso?.referencia_credito || 'Sin radicado visible',
        icon: ScaleIcon,
        iconClass: 'text-sky-500'
    },
]);

const generarNotaInteligente = () => {
    const c = props.caso || {};
    const etapa = cleanValue(c.etapa_procesal, 'etapa sin especificar');
    const etapaNormalizada = normalizeText(etapa);
    const radicado = cleanValue(c.radicado, null);
    const referencia = cleanValue(c.referencia_credito, null);
    const deudor = cleanValue(c.deudor?.nombre_completo, null);
    const juzgado = cleanValue(c.juzgado?.nombre, null);
    const deuda = formatCurrency(c.monto_deuda_actual);
    const fechaActuacion = formatDate(actuacionForm.fecha_actuacion);

    const contexto = [
        radicado ? `radicado ${radicado}` : null,
        referencia ? `referencia/pagaré ${referencia}` : null,
        deudor ? `demandado/deudor ${deudor}` : null,
        juzgado ? `despacho ${juzgado}` : null,
        deuda ? `saldo reportado ${deuda}` : null,
    ].filter(Boolean).join('; ');

    const lineas = [
        `El ${fechaActuacion}, en seguimiento al expediente${contexto ? ` (${contexto})` : ''}, se deja constancia de la revisión de la etapa actual: ${etapa}.`,
    ];

    if (etapaNormalizada.includes('prejur')) {
        lineas.push('Se recomienda documentar las gestiones de cobro persuasivo realizadas, verificar canales de contacto y dejar soporte de cualquier propuesta o ausencia de respuesta del deudor.');
    } else if (etapaNormalizada.includes('demanda') || etapaNormalizada.includes('radic')) {
        lineas.push('Se revisa el estado de preparación/radicación de la demanda y la suficiencia de los soportes documentales requeridos para continuar el impulso procesal.');
    } else if (etapaNormalizada.includes('mandamiento')) {
        lineas.push('Se sugiere verificar notificación, términos procesales y actuaciones pendientes posteriores al mandamiento de pago.');
    } else if (etapaNormalizada.includes('medida') || etapaNormalizada.includes('cautelar')) {
        lineas.push('Se deja constancia del seguimiento a medidas cautelares, oficios, respuestas de entidades y gestiones pendientes para su materialización.');
    } else if (etapaNormalizada.includes('audiencia')) {
        lineas.push('Se registra seguimiento a la audiencia, compromisos, decisiones adoptadas y tareas procesales derivadas.');
    } else if (etapaNormalizada.includes('sentencia') || etapaNormalizada.includes('termin')) {
        lineas.push('Se revisa el estado posterior a la decisión, ejecutoria, liquidación, cumplimiento y actuaciones necesarias para cierre o continuidad del trámite.');
    } else {
        lineas.push('Se actualiza la trazabilidad del caso y se recomienda verificar información financiera, datos de contacto, soportes documentales y próximo impulso requerido.');
    }

    lineas.push('Observación: ajustar esta nota con los hechos concretos verificados antes de guardar la actuación.');

    const texto = lineas.join('\n');
    const teniaContenido = Boolean(actuacionForm.nota?.trim());
    actuacionForm.nota = teniaContenido ? `${actuacionForm.nota.trim()}\n\n${texto}` : texto;

    AppAlert.fire({
        title: teniaContenido ? 'Nota añadida' : 'Nota generada',
        text: teniaContenido ? 'Añadí una sugerencia sin borrar el texto existente.' : 'Generé una nota contextual para este caso. Revísala antes de guardar.',
        icon: 'success',
        toast: true,
        position: 'top-end',
        timer: 3000
    });
};

const guardarActuacion = () => {
    actuacionForm.post(route('casos.actuaciones.store', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => { actuacionForm.reset(); AppAlert.fire({ title: 'Registrado', icon: 'success', timer: 1000, showConfirmButton: false }); }
    });
};

const editActuacionModalAbierto = ref(false);
const actuacionEnEdicion = ref(null);
const editActuacionForm = useForm({ nota: '', fecha_actuacion: '' });

const abrirModalEditar = (actuacion) => {
    actuacionEnEdicion.value = actuacion;
    editActuacionForm.nota = actuacion.nota;
    editActuacionForm.fecha_actuacion = actuacion.fecha_actuacion ? String(actuacion.fecha_actuacion).split('T')[0] : '';
    editActuacionModalAbierto.value = true;
};

const actualizarActuacion = () => {
    editActuacionForm.put(route('casos.actuaciones.update', actuacionEnEdicion.value.id), {
        preserveScroll: true,
        onSuccess: () => { editActuacionModalAbierto.value = false; router.reload({ only: ['actuaciones'] }); }
    });
};

const eliminarActuacion = (id) => {
    AppAlert.fire({ title: '¿Eliminar?', icon: 'warning', showCancelButton: true }).then((result) => {
        if (result.isConfirmed) router.delete(route('casos.actuaciones.destroy', id));
    });
};
</script>

<template>
    <div class="space-y-5 animate-in fade-in duration-500">
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
            <div
                v-for="item in summaryCards"
                :key="item.label"
                class="min-w-0 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm"
            >
                <div class="flex items-start gap-3">
                    <div class="shrink-0 rounded-lg bg-gray-50 dark:bg-gray-900 p-2">
                        <component :is="item.icon" class="h-5 w-5" :class="item.iconClass" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ item.label }}</p>
                        <p class="mt-1 break-words text-sm font-black uppercase leading-5 text-gray-900 dark:text-white">{{ item.value }}</p>
                        <p class="mt-0.5 break-words text-[10px] font-semibold text-gray-500 dark:text-gray-400">{{ item.subtext }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- REGISTRO RÁPIDO -->
        <section v-if="!isFormDisabled" class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-gray-100 dark:border-gray-700 pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <PencilSquareIcon class="w-5 h-5 text-indigo-500" />
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-tight">Nueva Actuación Procesal</h3>
                        <p class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Registro jurídico del avance del caso</p>
                    </div>
                </div>
                <button type="button" @click="generarNotaInteligente" class="inline-flex w-fit items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white transition-all hover:bg-indigo-700">
                    <SparklesIcon class="w-3.5 h-3.5" />
                    Autocompletar nota
                </button>
            </div>

            <form @submit.prevent="guardarActuacion" class="mt-4 grid grid-cols-1 xl:grid-cols-12 gap-4">
                <div class="xl:col-span-8 space-y-3">
                    <Textarea v-model="actuacionForm.nota" rows="5" class="w-full rounded-lg border-gray-200 bg-gray-50 dark:bg-gray-900/40 text-sm leading-6 p-3" placeholder="Detalle claro de la actuación, gestión o decisión procesal..." required />
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <button v-for="txt in quickTexts" :key="txt" type="button" @click="appendQuickText(txt)" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-3 py-2 text-left text-[11px] font-bold text-gray-600 dark:text-gray-300 transition-colors hover:border-indigo-300 hover:text-indigo-600">
                            {{ txt }}
                        </button>
                    </div>
                </div>
                <div class="xl:col-span-4 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 p-4 space-y-4">
                    <div>
                        <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-gray-400">Fecha de actuación</p>
                        <DatePicker v-model="actuacionForm.fecha_actuacion" class="w-full !text-xs" required />
                    </div>
                    <PrimaryButton :disabled="actuacionForm.processing" class="w-full !justify-center !py-2.5 !bg-indigo-600 !rounded-lg !text-[10px] !font-bold uppercase tracking-widest transition-all">
                        <PlusIcon class="h-3.5 w-3.5 mr-1.5" />
                        Registrar actuación
                    </PrimaryButton>
                </div>
            </form>
        </section>

        <!-- LÍNEA DE TIEMPO -->
        <section class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-gray-100 dark:border-gray-700 pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <BookmarkIcon class="w-5 h-5 text-indigo-500" />
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-tight">Historial de Actuaciones</h3>
                        <p class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Cronología jurídica del expediente</p>
                    </div>
                </div>
                <span class="w-fit rounded-full bg-gray-100 dark:bg-gray-900 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                    {{ actuacionesList.length }} registro{{ actuacionesList.length !== 1 ? 's' : '' }}
                </span>
            </div>

            <div v-if="!actuacionesList.length" class="mt-5 flex min-h-64 flex-col items-center justify-center rounded-lg border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 text-center">
                <DocumentTextIcon class="w-12 h-12 mb-3 text-gray-300" />
                <p class="text-[11px] font-black uppercase tracking-widest text-gray-400">Sin historial registrado</p>
            </div>

            <ol v-else class="relative mt-5 space-y-4 before:absolute before:bottom-0 before:left-8 before:top-0 before:w-px before:bg-gray-100 dark:before:bg-gray-700">
                <li v-for="act in actuacionesList" :key="act.id" class="relative grid grid-cols-[4rem_1fr] gap-4">
                    <div class="relative z-10 flex h-16 w-16 flex-col items-center justify-center rounded-lg border border-indigo-100 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 shadow-sm">
                        <span class="text-lg font-black leading-none">{{ formatDay(act.fecha_actuacion) }}</span>
                        <span class="mt-1 text-[9px] font-black uppercase tracking-widest">{{ formatMonth(act.fecha_actuacion) }}</span>
                        <span class="text-[9px] font-bold">{{ formatYear(act.fecha_actuacion) }}</span>
                    </div>

                    <article class="min-w-0 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-300">Actuación procesal</p>
                                <p class="mt-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400">Registrada: {{ formatDateTime(act.created_at) }}</p>
                            </div>
                            <div v-if="!isFormDisabled" class="flex shrink-0 gap-2">
                                <button @click="abrirModalEditar(act)" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 text-gray-500 transition-colors hover:border-indigo-300 hover:text-indigo-600" title="Editar actuación">
                                    <PencilIcon class="w-4 h-4" />
                                </button>
                                <button @click="eliminarActuacion(act.id)" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 text-gray-500 transition-colors hover:border-rose-300 hover:text-rose-600" title="Eliminar actuación">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 font-medium text-gray-800 dark:text-gray-200">{{ act.nota }}</p>

                        <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 dark:border-gray-700 pt-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-[10px] font-black uppercase text-gray-500 dark:text-gray-300">
                                    {{ userInitial(act.user?.name) }}
                                </div>
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Responsable</p>
                                    <p class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300">{{ act.user?.name || 'Sistema' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                                <CalendarDaysIcon class="h-3.5 w-3.5" />
                                {{ formatDate(act.fecha_actuacion) }}
                            </div>
                        </div>
                    </article>
                </li>
            </ol>
        </section>

        <!-- MODAL EDICIÓN -->
        <Modal :show="editActuacionModalAbierto" @close="editActuacionModalAbierto = false" max-width="lg">
            <div class="p-6">
                <div class="flex items-center gap-3 border-b border-gray-100 pb-4">
                    <PencilSquareIcon class="h-5 w-5 text-indigo-500" />
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-tight">Editar Actuación Procesal</h2>
                </div>
                <form @submit.prevent="actualizarActuacion" class="mt-5 space-y-4">
                    <div>
                        <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-gray-400">Detalle de la actuación</p>
                        <Textarea v-model="editActuacionForm.nota" rows="6" class="w-full rounded-lg border-gray-200 text-sm leading-6" required />
                    </div>
                    <div>
                        <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-gray-400">Fecha de actuación</p>
                        <DatePicker v-model="editActuacionForm.fecha_actuacion" class="w-full text-xs" required />
                    </div>
                    <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
                        <SecondaryButton @click="editActuacionModalAbierto = false" class="!text-[10px]">Cerrar</SecondaryButton>
                        <PrimaryButton type="submit" class="!bg-indigo-600 !text-[10px]" :disabled="editActuacionForm.processing">Actualizar</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>
