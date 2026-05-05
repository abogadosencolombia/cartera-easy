<script setup>
import { useForm, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import DateTimePicker from '@/Components/DateTimePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import CumplimientoLegal from '@/Components/CumplimientoLegal.vue';
import GuiaEtapa from '@/Components/GuiaEtapa.vue';
import { addDaysToDate, addMonthsToDate } from '@/Utils/formatters';
import { 
    ScaleIcon, 
    UserCircleIcon, 
    UsersIcon, 
    PhoneIcon, 
    EnvelopeIcon, 
    MapPinIcon, 
    ChevronDownIcon,
    BellIcon,
    ArrowTopRightOnSquareIcon,
    GlobeAltIcon,
    BuildingOfficeIcon,
    IdentificationIcon,
    CalendarIcon,
    PlusIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: { type: Object, required: true },
    resumen_financiero: { type: Object, required: true },
    formatCurrency: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    formatLabel: { type: Function, required: true },
});

const codeudorAbiertoId = ref(null);
const toggleCodeudor = (id) => { codeudorAbiertoId.value = (codeudorAbiertoId.value === id) ? null : id; };

const safeJsonParse = (jsonString) => {
    if (!jsonString) return [];
    if (typeof jsonString === 'object') return jsonString;
    try { return JSON.parse(jsonString) || []; } catch (e) { return []; }
};

const notifForm = useForm({ fecha_programada: '', mensaje: '', prioridad: 'media' });
const submitNotification = () => {
    notifForm.post(route('casos.notificaciones.store', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => { notifForm.reset(); router.reload({ only: ['auth'] }); },
    });
};
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 animate-in fade-in duration-500">
        
        <!-- COLUMNA IZQUIERDA -->
        <div class="lg:col-span-8 space-y-6">
            
            <!-- DETALLES DEL PROCESO -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center gap-3 mb-6 border-b border-gray-50 dark:border-gray-700 pb-4">
                    <ScaleIcon class="h-5 w-5 text-indigo-500" />
                    <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-tight text-sm">Información Jurídica</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Radicado</p>
                        <div class="flex items-center gap-1.5">
                            <p class="text-xs font-bold text-gray-700 dark:text-gray-200 font-mono">{{ caso.radicado || 'SIN ASIGNAR' }}</p>
                            <span v-if="caso.es_spoa_nunc" class="text-[8px] font-black bg-indigo-100 text-indigo-700 px-1 rounded border border-indigo-200 uppercase tracking-tighter">SPOA/NUNC</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Pagaré / Referencia</p>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ caso.referencia_credito || 'SIN ASIGNAR' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Especialidad</p>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ caso.especialidad?.nombre || formatLabel(caso.especialidad_nombre) }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Tipo de Proceso</p>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ formatLabel(caso.tipo_proceso) }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Proceso</p>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ formatLabel(caso.subtipo_proceso) }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Subproceso</p>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ formatLabel(caso.subproceso) || 'N/A' }}</p>
                    </div>
                    <div class="space-y-1 text-indigo-600">
                        <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest">Etapa Procesal</p>
                        <p class="text-xs font-black uppercase">{{ formatLabel(caso.etapa_procesal) }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Canal de Notificación</p>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-200 uppercase">{{ caso.medio_contacto || 'No especificado' }}</p>
                    </div>
                    <div class="md:col-span-3 p-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-100 dark:border-gray-700">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1.5 mb-1">
                            <BuildingOfficeIcon class="h-3 w-3" /> Juzgado Asignado
                        </p>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ caso.juzgado?.nombre || 'Pendiente de asignación' }}</p>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-gray-50 dark:border-gray-700 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-tight">Fecha Demanda</p>
                        <p class="text-[11px] font-bold text-gray-600 dark:text-gray-300">{{ formatDate(caso.fecha_apertura) }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-tight">Vencimiento</p>
                        <p class="text-[11px] font-bold text-gray-600 dark:text-gray-300">{{ formatDate(caso.fecha_vencimiento) }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-tight">Garantía</p>
                        <p class="text-[11px] font-bold text-gray-600 dark:text-gray-300 uppercase">{{ caso.tipo_garantia_asociada }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-tight">Origen</p>
                        <p class="text-[11px] font-bold text-gray-600 dark:text-gray-300 uppercase">{{ caso.origen_documental }}</p>
                    </div>
                </div>
            </div>

            <!-- INFORMACIÓN FINANCIERA DETALLADA -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center gap-3 mb-6 border-b border-gray-50 dark:border-gray-700 pb-4">
                    <CreditCardIcon class="h-5 w-5 text-emerald-500" />
                    <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-tight text-sm">Detalles del Crédito</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Monto de Crédito (Capital)</p>
                        <p class="text-sm font-black text-gray-900 dark:text-white">{{ formatCurrency(caso.monto_total) }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Deuda Actual</p>
                        <p class="text-sm font-black text-rose-600 dark:text-rose-400">{{ formatCurrency(caso.monto_deuda_actual) }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Pagado</p>
                        <p class="text-sm font-black text-emerald-600 dark:text-emerald-400">{{ formatCurrency(caso.monto_total_pagado) }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Tasa Interés Corriente</p>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ caso.tasa_interes_corriente }}%</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Fecha Inicio Crédito</p>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ formatDate(caso.fecha_inicio_credito) }}</p>
                    </div>
                </div>
            </div>

            <!-- OBSERVACIONES Y NOTAS -->
            <div v-if="caso.notas_legales || caso.nota_cierre || caso.motivo_bloqueo" class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center gap-3 mb-4 border-b border-gray-50 dark:border-gray-700 pb-4">
                    <ClipboardDocumentListIcon class="h-5 w-5 text-amber-500" />
                    <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-tight text-sm">Observaciones y Estado</h3>
                </div>

                <div class="space-y-4">
                    <div v-if="caso.notas_legales">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Notas Legales / Internas</p>
                        <div class="p-4 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-100 dark:border-gray-700">
                            <p class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ caso.notas_legales }}</p>
                        </div>
                    </div>

                    <div v-if="caso.nota_cierre" class="p-4 bg-rose-50 dark:bg-rose-900/10 border border-rose-100 dark:border-rose-900/30 rounded-lg">
                        <p class="text-[10px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-widest mb-1">Nota de Cierre (Caso Finalizado)</p>
                        <p class="text-xs text-rose-800 dark:text-rose-300 font-medium italic">"{{ caso.nota_cierre }}"</p>
                    </div>

                    <div v-if="caso.bloqueado && caso.motivo_bloqueo" class="p-4 bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30 rounded-lg">
                        <p class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest mb-1">Motivo de Bloqueo</p>
                        <p class="text-xs text-amber-800 dark:text-amber-300 font-medium italic">"{{ caso.motivo_bloqueo }}"</p>
                    </div>
                </div>
            </div>

            <!-- ENLACES -->
            <div v-if="caso.link_drive || caso.link_expediente" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a v-if="caso.link_drive" :href="caso.link_drive" target="_blank" class="flex items-center justify-between p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:border-indigo-300 transition-all group">
                    <div class="flex items-center gap-3">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/1/12/Google_Drive_icon_%282020%29.svg" class="w-5 h-5">
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-tight">Expediente en Drive</span>
                    </div>
                    <ArrowTopRightOnSquareIcon class="h-4 w-4 text-gray-300 group-hover:text-indigo-500" />
                </a>
                <a v-if="caso.link_expediente" :href="caso.link_expediente" target="_blank" class="flex items-center justify-between p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:border-emerald-300 transition-all group">
                    <div class="flex items-center gap-3">
                        <GlobeAltIcon class="w-5 h-5 text-emerald-500" />
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-tight">Rama Judicial (Tyba)</span>
                    </div>
                    <ArrowTopRightOnSquareIcon class="h-4 w-4 text-gray-300 group-hover:text-emerald-500" />
                </a>
            </div>

            <!-- CUMPLIMIENTO -->
            <CumplimientoLegal :validaciones="caso.validaciones_legales" />
            
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-visible">
                <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-tight text-sm mb-6 flex items-center gap-2">
                    <CalendarIcon class="h-5 w-5 text-amber-500" /> Guía de Gestión Procesal
                </h3>
                <GuiaEtapa :etapa="caso.etapa_procesal" :tipo-proceso="caso.tipo_proceso" :checklist-completados="caso.checklist_seguimiento || []" :model-id="caso.id" model-type="caso" :entity="caso" />
            </div>
        </div>

        <!-- COLUMNA DERECHA -->
        <div class="lg:col-span-4 space-y-6">
            
            <!-- SUJETOS PROCESALES -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex items-center gap-3 mb-6 border-b border-gray-50 dark:border-gray-700 pb-4">
                    <UsersIcon class="h-5 w-5 text-indigo-500" />
                    <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-tight text-sm">Sujetos Procesales</h3>
                </div>

                <div class="space-y-4">
                    <div class="p-4 bg-indigo-50/50 dark:bg-indigo-900/10 rounded-xl border border-indigo-100/50 dark:border-indigo-800/50">
                        <p class="text-[10px] font-bold text-indigo-400 uppercase mb-2">Demandado (Deudor)</p>
                        <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase truncate">{{ caso.deudor ? caso.deudor.nombre_completo : 'N/A' }}</h4>
                        <p class="text-[10px] text-gray-500 mt-0.5">{{ caso.deudor?.tipo_documento }} {{ caso.deudor?.numero_documento }}</p>
                        <div class="mt-3 flex gap-2">
                            <a v-if="caso.deudor?.celular_1" :href="'https://wa.me/57' + caso.deudor.celular_1.replace(/\s/g, '')" target="_blank" class="p-1.5 bg-white dark:bg-gray-800 text-emerald-500 rounded-lg shadow-sm border border-emerald-50"><PhoneIcon class="h-3.5 w-3.5" /></a>
                            <a v-if="caso.deudor?.correo_1" :href="'mailto:' + caso.deudor.correo_1" class="p-1.5 bg-white dark:bg-gray-800 text-indigo-500 rounded-lg shadow-sm border border-indigo-50"><EnvelopeIcon class="h-3.5 w-3.5" /></a>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest pl-1">Codeudores ({{ caso.codeudores?.length || 0 }})</p>
                        <div v-for="codeudor in caso.codeudores" :key="codeudor.id" class="border border-gray-100 dark:border-gray-700 rounded-lg overflow-hidden">
                            <button @click="toggleCodeudor(codeudor.id)" class="w-full flex items-center justify-between p-3 bg-white dark:bg-gray-800 text-left">
                                <span class="text-[11px] font-bold text-gray-700 dark:text-gray-300 uppercase truncate">{{ codeudor.nombre_completo }}</span>
                                <ChevronDownIcon class="h-3.5 w-3.5 text-gray-300 transition-transform" :class="{ 'rotate-180': codeudorAbiertoId === codeudor.id }" />
                            </button>
                            <div v-show="codeudorAbiertoId === codeudor.id" class="p-3 bg-gray-50/50 dark:bg-gray-900/30 border-t border-gray-50 dark:border-gray-700 text-[10px] space-y-2 animate-in slide-in-from-top-1">
                                <p class="text-gray-500 flex items-center gap-2 font-mono"><IdentificationIcon class="h-3 w-3" />{{ codeudor.numero_documento }}</p>
                                <p v-if="codeudor.celular" class="text-gray-500 flex items-center gap-2"><PhoneIcon class="h-3 w-3" />{{ codeudor.celular }}</p>
                                <p v-for="(addr, idx) in safeJsonParse(codeudor.addresses)" :key="idx" class="text-gray-500 flex items-start gap-2"><MapPinIcon class="h-3 w-3 mt-0.5 shrink-0" />{{ addr.address }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-2">Abogados Asignados</p>
                        <div class="flex flex-wrap gap-1.5">
                            <template v-if="caso.users?.length">
                                <span v-for="u in caso.users" :key="u.id" class="px-2 py-1 bg-slate-50 dark:bg-gray-900 rounded-lg border border-gray-100 dark:border-gray-700 text-[9px] font-bold text-gray-600 dark:text-gray-400 uppercase">{{ u.name }}</span>
                            </template>
                            <span v-else class="text-[10px] text-gray-400 italic">No asignado</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECORDATORIO -->
            <div class="bg-indigo-900 p-6 rounded-xl shadow-lg relative overflow-visible group z-20">
                <div class="relative z-10 space-y-4">
                    <h3 class="text-xs font-bold text-white uppercase tracking-widest flex items-center gap-2">
                        <BellIcon class="h-4 w-4 text-amber-400" /> Próximo Recordatorio
                    </h3>
                    <form @submit.prevent="submitNotification" class="space-y-3">
                        <DateTimePicker v-model="notifForm.fecha_programada" class="!bg-white/10 !border-white/10 !text-white !rounded-lg text-xs" />
                        <Textarea v-model="notifForm.mensaje" class="!bg-white/10 !border-white/10 !text-white !rounded-lg text-[11px]" rows="2" placeholder="Nota rápida..." required />
                        <PrimaryButton :disabled="notifForm.processing" class="w-full !bg-white !text-indigo-900 !rounded-lg !py-2.5 !text-[10px] !font-bold uppercase tracking-widest shadow-md">
                            <PlusIcon class="h-3 w-3 mr-1.5" /> Agendar
                        </PrimaryButton>
                    </form>
                </div>
            </div>

        </div>
    </div>
</template>
