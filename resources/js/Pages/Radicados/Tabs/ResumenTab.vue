<script setup>
import { computed } from 'vue';
import {
    ScaleIcon,
    BuildingLibraryIcon,
    CalendarDaysIcon,
    EnvelopeIcon,
    GlobeAltIcon,
    ArrowTopRightOnSquareIcon,
    ChatBubbleBottomCenterTextIcon,
    ClipboardDocumentCheckIcon,
    HandThumbUpIcon,
    IdentificationIcon,
    MapPinIcon,
} from '@heroicons/vue/24/outline';
import GuiaEtapa from '@/Components/GuiaEtapa.vue';

const props = defineProps({
    proceso: { type: Object, required: true },
    formatDate: { type: Function, required: true },
});

const asText = (value) => value ?? '—';

const tipoProceso = computed(() => props.proceso.tipo_proceso?.nombre || 'General');
const despacho = computed(() => props.proceso.juzgado?.nombre || 'Pendiente de asignacion');
const estadoDatos = computed(() => props.proceso.info_incompleta ? 'Revision requerida' : 'Datos completos');
const fechaRadicado = computed(() => props.formatDate(props.proceso.fecha_radicado) || 'No registrada');
const fechaCambioEtapa = computed(() => props.proceso.fecha_cambio_etapa ? props.formatDate(props.proceso.fecha_cambio_etapa) : 'Sin cambios registrados');
const naturalezaClase = computed(() => {
    const partes = [props.proceso.naturaleza, props.proceso.clase_proceso].filter(Boolean);
    return partes.length ? partes.join(' / ') : 'Sin clasificar';
});

const resumenRapido = computed(() => [
    {
        label: 'Radicado',
        value: props.proceso.radicado || 'Sin numero asignado',
        detail: props.proceso.es_spoa_nunc ? 'SPOA / NUNC' : tipoProceso.value,
        icon: IdentificationIcon,
    },
    {
        label: 'Etapa actual',
        value: props.proceso.etapa_actual?.nombre || 'No definida',
        detail: fechaCambioEtapa.value,
        icon: ClipboardDocumentCheckIcon,
    },
    {
        label: 'Despacho',
        value: despacho.value,
        detail: props.proceso.estado || 'Sin estado',
        icon: BuildingLibraryIcon,
    },
    {
        label: 'Datos',
        value: estadoDatos.value,
        detail: props.proceso.a_favor_de ? `A favor del ${props.proceso.a_favor_de}` : 'Sin posicion registrada',
        icon: ScaleIcon,
    },
]);
</script>

<template>
    <div class="space-y-5 animate-in fade-in duration-500">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="item in resumenRapido"
                :key="item.label"
                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ item.label }}</p>
                        <p class="mt-1 truncate text-sm font-black text-gray-950 dark:text-white" :title="item.value">{{ item.value }}</p>
                        <p class="mt-1 truncate text-xs font-semibold text-gray-600 dark:text-gray-300" :title="item.detail">{{ item.detail }}</p>
                    </div>
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50 p-2 text-indigo-600 dark:border-indigo-900/60 dark:bg-indigo-950/40 dark:text-indigo-300">
                        <component :is="item.icon" class="h-5 w-5" />
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
            <section class="xl:col-span-8 space-y-5">
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-300">Resumen juridico</p>
                            <h3 class="text-base font-black text-gray-950 dark:text-white">Datos principales del radicado</h3>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-if="proceso.a_favor_de"
                                class="inline-flex items-center rounded-md border px-2.5 py-1 text-[10px] font-black uppercase"
                                :class="proceso.a_favor_de === 'DEMANDANTE'
                                    ? 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900/60 dark:bg-blue-950/40 dark:text-blue-300'
                                    : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300'"
                            >
                                <HandThumbUpIcon class="mr-1 h-3.5 w-3.5" />
                                A favor del {{ proceso.a_favor_de }}
                            </span>
                            <span class="rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[10px] font-black uppercase text-indigo-700 dark:border-indigo-900/60 dark:bg-indigo-950/40 dark:text-indigo-300">
                                {{ tipoProceso }}
                            </span>
                        </div>
                    </div>

                    <div class="p-5">
                        <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                <dt class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                    <BuildingLibraryIcon class="h-4 w-4 text-indigo-500" />
                                    Despacho judicial
                                </dt>
                                <dd class="mt-2 text-sm font-bold leading-relaxed text-gray-900 dark:text-gray-100">{{ despacho }}</dd>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                <dt class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                    <IdentificationIcon class="h-4 w-4 text-indigo-500" />
                                    Radicado oficial
                                </dt>
                                <dd class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-sm font-black text-indigo-700 dark:text-indigo-300">{{ proceso.radicado || 'SIN NUMERO ASIGNADO' }}</span>
                                    <span v-if="proceso.es_spoa_nunc" class="rounded-full border border-indigo-200 bg-indigo-100 px-2 py-0.5 text-[9px] font-black uppercase text-indigo-700 dark:border-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">SPOA/NUNC</span>
                                </dd>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                <dt class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                    <ScaleIcon class="h-4 w-4 text-indigo-500" />
                                    Naturaleza / clase
                                </dt>
                                <dd class="mt-2 text-sm font-bold text-gray-900 dark:text-gray-100">{{ naturalezaClase }}</dd>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                <dt class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                    <CalendarDaysIcon class="h-4 w-4 text-indigo-500" />
                                    Fecha de radicacion
                                </dt>
                                <dd class="mt-2 text-sm font-bold text-gray-900 dark:text-gray-100">{{ fechaRadicado }}</dd>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                <dt class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Estado del expediente</dt>
                                <dd
                                    class="mt-2 text-sm font-black uppercase"
                                    :class="proceso.estado === 'ACTIVO' || proceso.estado === 'ABIERTO' ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'"
                                >
                                    {{ proceso.estado || 'Sin estado' }}
                                </dd>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                                <dt class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Ultimo cambio de etapa</dt>
                                <dd class="mt-2 text-sm font-bold text-gray-900 dark:text-gray-100">{{ fechaCambioEtapa }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Asunto / descripcion</p>
                            <p class="mt-2 whitespace-pre-line text-sm font-medium leading-relaxed text-gray-800 dark:text-gray-200">{{ asText(proceso.asunto) }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="proceso.observaciones || proceso.nota_cierre" class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <ChatBubbleBottomCenterTextIcon class="h-5 w-5 text-indigo-500" />
                        <h3 class="text-sm font-black text-gray-950 dark:text-white">Observaciones e historial de estado</h3>
                    </div>
                    <div class="space-y-4 p-5">
                        <div v-if="proceso.observaciones" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Notas internas</p>
                            <p class="mt-2 whitespace-pre-line text-sm font-medium leading-relaxed text-gray-800 dark:text-gray-200">{{ proceso.observaciones }}</p>
                        </div>

                        <div v-if="proceso.nota_cierre" class="rounded-lg border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/60 dark:bg-rose-950/30">
                            <p class="text-[10px] font-black uppercase tracking-widest text-rose-700 dark:text-rose-300">Nota de finalizacion / cierre</p>
                            <p class="mt-2 whitespace-pre-line text-sm font-semibold leading-relaxed text-rose-900 dark:text-rose-200">{{ proceso.nota_cierre }}</p>
                        </div>
                    </div>
                </div>

                <GuiaEtapa
                    v-if="proceso.etapa_actual"
                    :etapa="proceso.etapa_actual.nombre"
                    :checklist-completados="proceso.checklist_seguimiento || []"
                    :model-id="proceso.id"
                    model-type="proceso"
                    :entity="proceso"
                />
            </section>

            <aside class="xl:col-span-4 space-y-5">
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <EnvelopeIcon class="h-5 w-5 text-indigo-500" />
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-300">Contacto</p>
                            <h3 class="text-sm font-black text-gray-950 dark:text-white">Canales y enlaces</h3>
                        </div>
                    </div>

                    <div class="space-y-4 p-5">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Correo de radicacion</p>
                            <p class="mt-2 break-words text-sm font-bold text-gray-900 dark:text-gray-100">{{ proceso.correo_radicacion || 'No registra' }}</p>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Correos de la entidad</p>
                            <p class="mt-2 whitespace-pre-line break-words text-sm font-medium leading-relaxed text-gray-800 dark:text-gray-200">{{ proceso.correos_juzgado || 'Sin correos adicionales' }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-3">
                            <a
                                v-if="proceso.ubicacion_drive"
                                :href="proceso.ubicacion_drive"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-black text-blue-700 transition hover:bg-blue-100 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-300"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <MapPinIcon class="h-4 w-4" />
                                    Carpeta Drive
                                </span>
                                <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                            </a>

                            <a
                                v-if="proceso.link_expediente"
                                :href="proceso.link_expediente"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-black text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <GlobeAltIcon class="h-4 w-4" />
                                    Expediente digital
                                </span>
                                <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                            </a>

                            <div v-if="!proceso.ubicacion_drive && !proceso.link_expediente" class="rounded-lg border border-dashed border-gray-300 p-4 text-sm font-semibold text-gray-600 dark:border-gray-700 dark:text-gray-300">
                                No hay enlaces externos registrados.
                            </div>
                        </div>

                        <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900/60 dark:bg-indigo-950/30">
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg bg-white p-2 text-indigo-600 shadow-sm dark:bg-gray-900 dark:text-indigo-300">
                                    <ClipboardDocumentCheckIcon class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-indigo-800 dark:text-indigo-200">Estado de datos</p>
                                    <p class="text-sm font-black text-indigo-700 dark:text-indigo-300">{{ estadoDatos }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <slot name="notificaciones" />
            </aside>
        </div>
    </div>
</template>
