<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Textarea from '@/Components/Textarea.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Dropdown from '@/Components/Dropdown.vue';
import SelectInput from '@/Components/SelectInput.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { 
    TrashIcon, 
    ChevronDownIcon, 
    ScaleIcon, 
    UsersIcon, 
    BriefcaseIcon, 
    BuildingOfficeIcon,
    CheckCircleIcon,
    ClockIcon,
    DocumentTextIcon,
    ArrowPathIcon,
    ArrowLeftIcon,
    PlusIcon,
    IdentificationIcon,
    BanknotesIcon,
    CreditCardIcon,
    MapPinIcon,
    AtSymbolIcon,
    DevicePhoneMobileIcon,
    ClipboardDocumentCheckIcon,
    LinkIcon,
    GlobeAltIcon,
    HashtagIcon,
    CalendarDaysIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';
import { Head, Link, useForm, useRemember } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { debounce } from 'lodash';
import { formatRadicado, addDaysToDate, addMonthsToDate, toUpperCase, calculateDV } from '@/Utils/formatters';
import { useFormDraft } from '@/composables/useFormDraft';

const props = defineProps({
    casoAClonar: { type: Object, default: null },
    estructuraProcesal: { type: Array, default: () => [] },
    etapas_procesales: { type: Array, default: () => [] },
});

const safeParseJson = (jsonString) => {
  if (!jsonString) return [];
  try {
    const parsed = JSON.parse(jsonString);
    return Array.isArray(parsed) ? parsed : [];
  } catch (e) {
    return [];
  }
};

const initialData = {
    cooperativa_id: props.casoAClonar?.cooperativa || null,
    user_id: props.casoAClonar?.user ? [{ id: props.casoAClonar.user.id, name: props.casoAClonar.user.name }] : [],
    deudor_id: props.casoAClonar?.deudor_id || null,
    deudor: {
        id: props.casoAClonar?.deudor_id || null,
        selected: props.casoAClonar?.deudor ? { id: props.casoAClonar.deudor.id, nombre_completo: props.casoAClonar.deudor.nombre_completo, numero_documento: props.casoAClonar.deudor.numero_documento } : null,
        is_new: false,
        nombre_completo: '',
        tipo_documento: 'CC',
        numero_documento: '',
        dv: '',
        celular_1: '',
        correo_1: '',
        cooperativas_ids: [],
        abogados_ids: []
    },
    codeudores: props.casoAClonar?.codeudores?.map(c => ({
        ...c,
        dv: c.dv || '',
        addresses: safeParseJson(c.addresses), 
        social_links: safeParseJson(c.social_links)
    })) || [],
    
    radicado: props.casoAClonar?.radicado || '',
    juzgado_id: props.casoAClonar?.juzgado || null,
    referencia_credito: props.casoAClonar?.referencia_credito || '',
    
    especialidad_id: props.casoAClonar?.especialidad_id || null,
    tipo_proceso: props.casoAClonar?.tipo_proceso || null,
    subtipo_proceso: props.casoAClonar?.subtipo_proceso || null,
    subproceso: props.casoAClonar?.subproceso || null,
    
    etapa_procesal: props.casoAClonar?.etapa_procesal || null,
    tipo_garantia_asociada: props.casoAClonar?.tipo_garantia_asociada || 'codeudor',
    
    fecha_apertura: new Date().toISOString().slice(0, 10),
    fecha_vencimiento: props.casoAClonar?.fecha_vencimiento || '',
    fecha_inicio_credito: props.casoAClonar?.fecha_inicio_credito || '',
    
    monto_total: props.casoAClonar?.monto_total?.toString() || '0',
    monto_deuda_actual: props.casoAClonar?.monto_deuda_actual?.toString() || '0',
    monto_total_pagado: props.casoAClonar?.monto_total_pagado?.toString() || '0',
    
    tasa_interes_corriente: props.casoAClonar?.tasa_interes_corriente?.toString() || '0',
    origen_documental: props.casoAClonar?.origen_documental || 'pagaré',
    medio_contacto: props.casoAClonar?.medio_contacto || null,
    link_drive: props.casoAClonar?.link_drive || '',
    link_expediente: props.casoAClonar?.link_expediente || '',
    clonado_de_id: props.casoAClonar?.id || null,
    sin_codeudores: !!props.casoAClonar?.sin_codeudores,
};

const selectedJuzgado = ref(null);
watch(selectedJuzgado, (val) => {
    form.juzgado_id = val?.id || null;
});

const form = useForm(initialData);
const { clearDraft } = useFormDraft(form, `draft:create:casos:${props.casoAClonar?.id || 'nuevo'}`);

watch(() => props.casoAClonar, (newCaso) => {
    form.defaults({
        cooperativa_id: newCaso?.cooperativa || null,
        user_id: newCaso?.user ? [{ id: newCaso.user.id, name: newCaso.user.name }] : [],
        deudor_id: newCaso?.deudor_id || null,
        deudor: {
            id: newCaso?.deudor_id || null,
            selected: newCaso?.deudor ? { id: newCaso.deudor.id, nombre_completo: newCaso.deudor.nombre_completo, numero_documento: newCaso.deudor.numero_documento } : null,
            is_new: false,
            nombre_completo: '',
            tipo_documento: 'CC',
            numero_documento: '',
            dv: '',
            celular_1: '',
            correo_1: '',
            cooperativas_ids: [],
            abogados_ids: []
        },
        codeudores: newCaso?.codeudores?.map(c => ({
            ...c,
            dv: c.dv || '',
            addresses: safeParseJson(c.addresses), 
            social_links: safeParseJson(c.social_links)
        })) || [],
        
        radicado: newCaso?.radicado || '',
        juzgado_id: newCaso?.juzgado || null,
        referencia_credito: newCaso?.referencia_credito || '',
        
        especialidad_id: newCaso?.especialidad_id || null,
        tipo_proceso: newCaso?.tipo_proceso || null,
        subtipo_proceso: newCaso?.subtipo_proceso || null,
        subproceso: newCaso?.subproceso || null,
        
        etapa_procesal: newCaso?.etapa_procesal || null,
        tipo_garantia_asociada: newCaso?.tipo_garantia_asociada || 'codeudor',
        
        fecha_apertura: new Date().toISOString().slice(0, 10),
        fecha_vencimiento: newCaso?.fecha_vencimiento || '',
        fecha_inicio_credito: newCaso?.fecha_inicio_credito || '',
        
        monto_total: newCaso?.monto_total?.toString() || '0',
        monto_deuda_actual: newCaso?.monto_deuda_actual?.toString() || '0',
        monto_total_pagado: newCaso?.monto_total_pagado?.toString() || '0',
        
        tasa_interes_corriente: newCaso?.tasa_interes_corriente?.toString() || '0',
        origen_documental: newCaso?.origen_documental || 'pagaré',
        medio_contacto: newCaso?.medio_contacto || null,
        link_drive: newCaso?.link_drive || '',
        link_expediente: newCaso?.link_expediente || '',
        clonado_de_id: newCaso?.id || null,
        sin_codeudores: !!newCaso?.sin_codeudores,
    });
    form.reset();
}, { immediate: true });

// --- AUTO-FORMATO ---
watch(() => form.deudor.numero_documento, (newVal) => {
    if (form.deudor.is_new && form.deudor.tipo_documento === 'NIT' && newVal) {
        form.deudor.dv = calculateDV(newVal).toString();
    }
});

// --- HELPERS DINÁMICOS ---
const addCodeudor = () => {
    form.sin_codeudores = false;
    form.codeudores.push({ nombre_completo: '', tipo_documento: 'CC', numero_documento: '', celular: '', correo: '', addresses: [], social_links: [] });
};
const removeCodeudor = (index) => form.codeudores.splice(index, 1);
const addAddress = (idx) => form.codeudores[idx].addresses.push({ label: 'Casa', address: '', city: '' });
const removeAddress = (idx, addrIdx) => form.codeudores[idx].addresses.splice(addrIdx, 1);

const handleRadicadoInput = (e) => {
    const formatted = formatRadicado(e.target.value);
    form.radicado = formatted;
    e.target.value = formatted;
};

const addDays = (field, days) => {
    form[field] = addDaysToDate(form[field], days);
};

const addMonths = (field, months) => {
    form[field] = addMonthsToDate(form[field], months);
};

// --- CASCADA PROCESOS ---
const formatLabel = (text) => text?.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, c => c.toUpperCase()) || '';
const especialidades = computed(() => props.estructuraProcesal);
const tiposDisponibles = ref([]);
const subtiposDisponibles = ref([]);
const subprocesosDisponibles = ref([]);

watch(() => form.especialidad_id, (newId, oldId) => {
    const esp = especialidades.value.find(e => e.id === newId);
    tiposDisponibles.value = esp ? esp.tipos_proceso : [];
    if (oldId !== undefined) {
        form.tipo_proceso = null; 
        form.subtipo_proceso = null; 
        form.subproceso = null;
    }
}, { immediate: true });

watch(() => form.tipo_proceso, (newVal, oldVal) => {
    const tipo = tiposDisponibles.value.find(t => t.nombre === newVal);
    subtiposDisponibles.value = tipo ? tipo.subtipos : [];
    if (oldVal !== undefined) {
        form.subtipo_proceso = null; 
        form.subproceso = null;
    }
}, { immediate: true });

watch(() => form.subtipo_proceso, (newVal, oldVal) => {
    const subtipo = subtiposDisponibles.value.find(s => s.nombre === newVal);
    subprocesosDisponibles.value = subtipo ? subtipo.subprocesos : [];
    if (oldVal !== undefined) {
        form.subproceso = null;
    }
}, { immediate: true });

watch([() => form.radicado, () => form.referencia_credito, () => form.asunto], debounce(() => {
    const url = new URL(window.location);
    // Solo guardamos campos de texto simples para evitar URLs gigantes
    if (form.radicado) url.searchParams.set('radicado', form.radicado);
    else url.searchParams.delete('radicado');
    
    if (form.referencia_credito) url.searchParams.set('ref', form.referencia_credito);
    else url.searchParams.delete('ref');
    
    if (form.asunto) url.searchParams.set('asunto', form.asunto);
    else url.searchParams.delete('asunto');
    
    window.history.replaceState({}, '', url);
}, 500));

// Mantener deudor_id sincronizado con la selección
const casosExistentes = ref([]);
const buscandoCasos = ref(false);

const faltaIdentificacion = computed(() => !form.radicado && !form.referencia_credito);

const checkDuplicados = debounce(() => {
    // Si ambos están vacíos, no buscamos duplicados pero mostramos aviso de "incompleto"
    if (!form.radicado && !form.referencia_credito) {
        if (!form.deudor.selected) casosExistentes.value = [];
        return;
    }
    
    buscandoCasos.value = true;
    axios.get(route('casos.verificar_duplicados'), {
        params: {
            radicado: form.radicado,
            referencia_credito: form.referencia_credito
        }
    })
    .then(res => {
        // Combinamos con los del deudor si existen
        const idsExistentes = new Set(casosExistentes.value.map(c => c.id));
        res.data.forEach(caso => {
            if (!idsExistentes.has(caso.id)) {
                casosExistentes.value.push(caso);
            }
        });
    })
    .catch(err => console.error('Error buscando duplicados:', err))
    .finally(() => buscandoCasos.value = false);
}, 500);

watch(() => form.radicado, checkDuplicados);
watch(() => form.referencia_credito, checkDuplicados);

watch(() => form.deudor.selected, (newVal) => {
    if (newVal && !form.deudor.is_new) {
        form.deudor_id = newVal.id;
        form.deudor.id = newVal.id;
        
        // Consultar casos existentes
        buscandoCasos.value = true;
        axios.get(route('personas.casos_existentes', newVal.id))
            .then(res => {
                casosExistentes.value = res.data;
            })
            .catch(err => console.error('Error buscando casos:', err))
            .finally(() => buscandoCasos.value = false);
    } else {
        casosExistentes.value = [];
    }
}, { deep: true });

const submit = () => {
    form.transform(data => ({
        ...data,
        cooperativa_id: data.cooperativa_id?.id ?? data.cooperativa_id,
        user_id: Array.isArray(data.user_id) ? data.user_id.map(u => u.id ?? u) : [],
        juzgado_id: data.juzgado_id?.id ?? data.juzgado_id,
        deudor: data.deudor.is_new ? {
            ...data.deudor,
            is_new: true,
            cooperativas_ids: data.deudor.cooperativas_ids.map(c => c.id ?? c),
            abogados_ids: data.deudor.abogados_ids.map(a => a.id ?? a),
        } : { 
            is_new: false, 
            id: data.deudor.selected?.id ?? data.deudor_id 
        },
        deudor_id: data.deudor.is_new ? null : (data.deudor.selected?.id ?? data.deudor_id),
    })).post(route('casos.store'), {
        onSuccess: clearDraft,
    });
};
</script>

<template>
    <Head :title="casoAClonar ? 'Clonando Caso' : 'Registrar Nuevo Caso'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('casos.index')" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 text-gray-400 hover:text-indigo-600 transition-all">
                    <ArrowLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">
                        {{ casoAClonar ? 'Clonando Expediente' : 'Registrar Nuevo Caso' }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ casoAClonar ? `Creando una copia basada en el caso #${casoAClonar.id}` : 'Complete la información para la apertura del proceso jurídico.' }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-8">

                    <!-- SECCIÓN 1: PARTES -->
                    <div class="bg-white dark:bg-gray-800 shadow-xl shadow-slate-200/50 dark:shadow-none rounded-3xl border border-gray-100 dark:border-gray-700 overflow-visible">
                        <div class="p-6 bg-gray-50/50 dark:bg-gray-700/30 border-b border-gray-100 dark:border-gray-700 flex items-center">
                            <div class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm mr-3">
                                <UsersIcon class="h-5 w-5 text-indigo-600" />
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">Partes Involucradas</h3>
                        </div>
                        
                        <div class="p-8 space-y-8">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <InputLabel value="Cooperativa / Empresa *" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <AsyncSelect v-model="form.cooperativa_id" :endpoint="route('cooperativas.search')" placeholder="Seleccione empresa..." label-key="nombre" class="bg-gray-50/50 focus-within:bg-white transition-all rounded-xl" />
                                    <InputError :message="form.errors.cooperativa_id" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Abogado(s) a Cargo *" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <AsyncSelect v-model="form.user_id" :endpoint="route('users.search')" multiple placeholder="Asignar responsables..." label-key="name" class="bg-gray-50/50 focus-within:bg-white transition-all rounded-xl" />
                                    <InputError :message="form.errors.user_id" class="mt-2" />
                                </div>
                            </div>

                            <div class="md:col-span-2 pt-6 border-t dark:border-gray-700 space-y-6">
                                <div class="flex justify-between items-center">
                                    <InputLabel value="Deudor Principal *" class="font-bold text-xs uppercase text-gray-400" />
                                    <div class="flex bg-gray-100 dark:bg-gray-700 p-1 rounded-xl">
                                        <button type="button" @click="form.deudor.is_new = false" 
                                            :class="!form.deudor.is_new ? 'bg-white dark:bg-gray-800 shadow-sm text-indigo-600 ring-1 ring-gray-200 dark:ring-gray-600' : 'text-gray-500 hover:text-gray-700'"
                                            class="px-4 py-1.5 text-[10px] font-black uppercase rounded-lg transition-all">
                                            Buscar Existente
                                        </button>
                                        <button type="button" @click="form.deudor.is_new = true" 
                                            :class="form.deudor.is_new ? 'bg-white dark:bg-gray-800 shadow-sm text-indigo-600 ring-1 ring-gray-200 dark:ring-gray-600' : 'text-gray-500 hover:text-gray-700'"
                                            class="px-4 py-1.5 text-[10px] font-black uppercase rounded-lg transition-all">
                                            Registrar Nuevo
                                        </button>
                                    </div>
                                </div>

                                <div v-if="!form.deudor.is_new" class="animate-in fade-in slide-in-from-top-2">
                                    <AsyncSelect v-model="form.deudor.selected" :endpoint="route('personas.search')" placeholder="Buscar por nombre o documento..." label-key="nombre_completo" class="bg-gray-50/50 focus-within:bg-white transition-all rounded-xl" />
                                    
                                    <!-- Listado de Casos Existentes -->
                                    <div v-if="buscandoCasos" class="mt-3 flex items-center gap-2 text-[10px] text-gray-400 font-bold uppercase tracking-widest italic pl-1">
                                        <ArrowPathIcon class="w-3 h-3 animate-spin" /> Analizando antecedentes...
                                    </div>
                                    
                                    <div v-if="casosExistentes.length > 0" class="mt-6 p-6 bg-amber-50 dark:bg-amber-900/10 border border-amber-200/50 dark:border-amber-800/50 rounded-3xl animate-in zoom-in-95">
                                        <div class="flex items-center gap-3 mb-4">
                                            <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                                                <ClockIcon class="w-5 h-5 text-amber-600" />
                                            </div>
                                            <div>
                                                <h4 class="text-xs font-black text-amber-800 dark:text-amber-300 uppercase tracking-widest">Alerta de Duplicados o Antecedentes</h4>
                                                <p class="text-[10px] text-amber-600 dark:text-amber-500 font-bold uppercase tracking-tighter">Se detectaron {{ casosExistentes.length }} coincidencias por Radicado, Pagaré o Persona</p>
                                            </div>
                                        </div>
                                        <div class="space-y-3 max-h-64 overflow-y-auto pr-2 custom-scrollbar">
                                            <div v-for="(item, idx) in casosExistentes" :key="idx" class="flex items-center justify-between bg-white/80 dark:bg-gray-800/80 p-4 rounded-2xl border border-amber-100/50 dark:border-amber-900/50 shadow-sm group hover:border-amber-400 transition-colors">
                                                <div class="flex flex-col">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded-md uppercase tracking-tighter">{{ item.tipo }}</span>
                                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">ID #{{ item.id }}</span>
                                                        <span v-if="item.radicado === form.radicado && form.radicado" class="text-[9px] font-black text-red-600 bg-red-50 px-1.5 rounded uppercase">Mismo Radicado</span>
                                                        <span v-if="item.referencia_credito === form.referencia_credito && form.referencia_credito" class="text-[9px] font-black text-red-600 bg-red-50 px-1.5 rounded uppercase">Mismo Pagaré</span>
                                                    </div>
                                                    <span class="text-sm font-black text-gray-700 dark:text-gray-200">{{ item.radicado || 'SIN RADICADO' }}</span>
                                                    <div class="flex flex-col gap-0.5 mt-1">
                                                        <span class="flex items-center gap-1 text-[9px] text-gray-500 font-bold uppercase"><UserCircleIcon class="w-3 h-3" /> {{ item.deudor }}</span>
                                                        <div class="flex items-center gap-3">
                                                            <span class="flex items-center gap-1 text-[9px] text-gray-500 font-bold uppercase"><BuildingOfficeIcon class="w-3 h-3" /> {{ item.cooperativa }}</span>
                                                            <span class="flex items-center gap-1 text-[9px] text-gray-500 font-bold uppercase"><CalendarDaysIcon class="w-3 h-3" /> {{ item.fecha }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <a :href="item.link" target="_blank" class="p-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 rounded-xl opacity-0 group-hover:opacity-100 transition-all hover:bg-indigo-600 hover:text-white shadow-lg shadow-indigo-200 dark:shadow-none">
                                                    <DocumentTextIcon class="w-5 h-5" />
                                                </a>
                                            </div>
                                        </div>
                                        <p class="mt-4 text-[10px] text-amber-700/60 dark:text-amber-500/60 font-medium italic text-center">Utilice el icono de documento para abrir el expediente en una pestaña nueva.</p>
                                    </div>

                                    <InputError :message="form.errors.deudor_id" class="mt-2" />
                                    <InputError :message="form.errors['deudor.id']" class="mt-2" />
                                </div>

                                <!-- Notificación de Falta de Identificación -->
                                <div v-if="faltaIdentificacion" class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800/50 rounded-2xl flex items-center gap-3 animate-pulse">
                                    <InformationCircleIcon class="w-5 h-5 text-blue-500" />
                                    <p class="text-[10px] font-black text-blue-700 dark:text-blue-400 uppercase tracking-widest">
                                        Nota: El expediente se registrará sin Pagaré ni Radicado. Podrá asignarlos luego.
                                    </p>
                                </div>

                                <div v-if="form.deudor.is_new" class="animate-in fade-in slide-in-from-top-2 space-y-6 p-8 bg-gray-50/50 dark:bg-gray-900/30 rounded-3xl border-2 border-dashed border-gray-200 dark:border-gray-700">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div class="md:col-span-2">
                                            <InputLabel value="Nombre Completo *" class="text-[10px] font-black text-gray-400 uppercase mb-1" />
                                            <TextInput v-model="form.deudor.nombre_completo" @blur="form.deudor.nombre_completo = toUpperCase(form.deudor.nombre_completo)" placeholder="EJ: PEDRO PÉREZ" class="w-full bg-white transition-all" />
                                            <InputError :message="form.errors['deudor.nombre_completo']" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel value="Tipo Documento" class="text-[10px] font-black text-gray-400 uppercase mb-1" />
                                            <SelectInput v-model="form.deudor.tipo_documento">
                                                <option>CC</option>
                                                <option>NIT</option>
                                                <option>CE</option>
                                            </SelectInput>
                                            <InputError :message="form.errors['deudor.tipo_documento']" class="mt-2" />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div class="md:col-span-1">
                                            <InputLabel value="Número Documento *" class="text-[10px] font-black text-gray-400 uppercase mb-1" />
                                            <div :class="form.deudor.tipo_documento === 'NIT' ? 'grid grid-cols-4 gap-2' : ''">
                                                <div :class="form.deudor.tipo_documento === 'NIT' ? 'col-span-3' : ''">
                                                    <TextInput v-model="form.deudor.numero_documento" placeholder="Número *" class="w-full bg-white transition-all" />
                                                </div>
                                                <div v-if="form.deudor.tipo_documento === 'NIT'">
                                                    <TextInput v-model="form.deudor.dv" maxlength="1" placeholder="DV" class="w-full text-center px-0 bg-white transition-all font-black" />
                                                </div>
                                            </div>
                                            <InputError :message="form.errors['deudor.numero_documento']" class="mt-2" />
                                            <InputError :message="form.errors['deudor.dv']" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel value="Celular *" class="text-[10px] font-black text-gray-400 uppercase mb-1" />
                                            <TextInput v-model="form.deudor.celular_1" placeholder="300 000 0000" class="w-full bg-white transition-all" />
                                            <InputError :message="form.errors['deudor.celular_1']" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel value="Correo Electrónico *" class="text-[10px] font-black text-gray-400 uppercase mb-1" />
                                            <TextInput v-model="form.deudor.correo_1" type="email" placeholder="correo@ejemplo.com" class="w-full bg-white transition-all" />
                                            <InputError :message="form.errors['deudor.correo_1']" class="mt-2" />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <InputLabel value="Vincular a Cooperativas *" class="text-[10px] font-black text-gray-400 uppercase mb-1" />
                                            <AsyncSelect v-model="form.deudor.cooperativas_ids" :endpoint="route('cooperativas.search')" placeholder="Asignar cooperativas..." multiple label-key="nombre" class="bg-white transition-all rounded-xl" />
                                            <InputError :message="form.errors['deudor.cooperativas_ids']" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel value="Asignar Abogados" class="text-[10px] font-black text-gray-400 uppercase mb-1" />
                                            <AsyncSelect v-model="form.deudor.abogados_ids" :endpoint="route('users.search')" placeholder="Asignar abogados..." multiple label-key="name" class="bg-white transition-all rounded-xl" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: CRÉDITO Y PROCESO -->
                    <div class="bg-white dark:bg-gray-800 shadow-xl shadow-slate-200/50 dark:shadow-none rounded-3xl border border-gray-100 dark:border-gray-700 overflow-visible">
                        <div class="p-6 bg-gray-50/50 dark:bg-gray-700/30 border-b border-gray-100 dark:border-gray-700 flex items-center">
                            <div class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm mr-3">
                                <ScaleIcon class="h-5 w-5 text-indigo-600" />
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">Información del Crédito y Proceso</h3>
                        </div>
                        
                        <div class="p-8 space-y-10">
                            <!-- Fila 1: Identificadores -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                                <div>
                                    <InputLabel value="Número De Pagaré" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <ClipboardDocumentCheckIcon class="h-4 w-4 text-gray-400" />
                                        </div>
                                        <TextInput v-model="form.referencia_credito" class="pl-10 block w-full bg-gray-50/50 focus:bg-white transition-all" />
                                    </div>
                                    <InputError :message="form.errors.referencia_credito" class="mt-2" />
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <InputLabel value="Número de Radicado" class="font-bold text-xs uppercase text-gray-400" />
                                        <label class="flex items-center gap-1.5 cursor-pointer group">
                                            <input type="checkbox" v-model="form.es_spoa_nunc" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-3 h-3" />
                                            <span class="text-[9px] font-black uppercase text-gray-400 group-hover:text-indigo-600 transition-colors">¿Es SPOA/NUNC?</span>
                                        </label>
                                    </div>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <HashtagIcon class="h-4 w-4" :class="form.es_spoa_nunc ? 'text-indigo-500' : 'text-gray-400'" />
                                        </div>
                                        <TextInput 
                                            v-model="form.radicado" 
                                            @input="handleRadicadoInput"
                                            class="pl-10 block w-full font-mono bg-gray-50/50 focus:bg-white transition-all" 
                                            :placeholder="form.es_spoa_nunc ? '21 dígitos (Sistema Penal)' : '23 dígitos numéricos (Sin puntos ni guiones)'"
                                        />
                                    </div>
                                    <InputError :message="form.errors.radicado" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Tasa Interés Corriente *" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-400 font-black text-sm">%</span>
                                        </div>
                                        <TextInput v-model="form.tasa_interes_corriente" type="number" step="0.01" class="pl-10 block w-full bg-gray-50/50 focus:bg-white transition-all" />
                                    </div>
                                    <InputError :message="form.errors.tasa_interes_corriente" class="mt-2" />
                                </div>
                            </div>

                            <!-- Fila 2: Valores Financieros -->
                            <div class="p-8 bg-indigo-50/30 dark:bg-indigo-900/10 rounded-3xl border border-indigo-100 dark:border-indigo-800/50 grid grid-cols-1 md:grid-cols-3 gap-8">
                                <div>
                                    <InputLabel value="Monto de Crédito *" class="font-bold text-[10px] uppercase text-indigo-400 mb-2" />
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <BanknotesIcon class="h-4 w-4 text-indigo-500" />
                                        </div>
                                        <TextInput v-model="form.monto_total" type="number" step="0.01" class="pl-10 block w-full bg-white transition-all font-black text-indigo-700" />
                                    </div>
                                    <InputError :message="form.errors.monto_total" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Monto Deuda Actual" class="font-bold text-[10px] uppercase text-indigo-400 mb-2" />
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <CreditCardIcon class="h-4 w-4 text-rose-400" />
                                        </div>
                                        <TextInput v-model="form.monto_deuda_actual" type="number" step="0.01" class="pl-10 block w-full bg-white transition-all font-black text-rose-600" />
                                    </div>
                                    <InputError :message="form.errors.monto_deuda_actual" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Total Pagado" class="font-bold text-[10px] uppercase text-indigo-400 mb-2" />
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <CheckCircleIcon class="h-4 w-4 text-emerald-500" />
                                        </div>
                                        <TextInput v-model="form.monto_total_pagado" type="number" step="0.01" class="pl-10 block w-full bg-white transition-all font-black text-emerald-600" />
                                    </div>
                                    <InputError :message="form.errors.monto_total_pagado" class="mt-2" />
                                </div>
                            </div>

                            <!-- Fila 3: Fechas -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                                <div>
                                    <InputLabel value="Fecha Inicio Crédito" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <DatePicker v-model="form.fecha_inicio_credito" class="block w-full bg-gray-50/50 focus-within:bg-white transition-all" />
                                    <InputError :message="form.errors.fecha_inicio_credito" class="mt-2" />
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <InputLabel value="Fecha de Demanda *" class="font-bold text-xs uppercase text-gray-400" />
                                        <div class="flex gap-1">
                                            <button type="button" @click="addDays('fecha_apertura', 3)" class="text-[9px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded font-black hover:bg-indigo-600 hover:text-white transition-colors">+3D</button>
                                            <button type="button" @click="addMonths('fecha_apertura', 1)" class="text-[9px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded font-black hover:bg-indigo-600 hover:text-white transition-colors">+1M</button>
                                        </div>
                                    </div>
                                    <DatePicker v-model="form.fecha_apertura" class="block w-full bg-gray-50/50 focus-within:bg-white transition-all" />
                                    <InputError :message="form.errors.fecha_apertura" class="mt-2" />
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <InputLabel value="Fecha de Vencimiento" class="font-bold text-xs uppercase text-gray-400" />
                                        <div class="flex gap-1">
                                            <button type="button" @click="addMonths('fecha_vencimiento', 6)" class="text-[9px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded font-black hover:bg-indigo-600 hover:text-white transition-colors">+6M</button>
                                            <button type="button" @click="addMonths('fecha_vencimiento', 12)" class="text-[9px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded font-black hover:bg-indigo-600 hover:text-white transition-colors">+1A</button>
                                        </div>
                                    </div>
                                    <DatePicker v-model="form.fecha_vencimiento" class="block w-full bg-gray-50/50 focus-within:bg-white transition-all" />
                                    <InputError :message="form.errors.fecha_vencimiento" class="mt-2" />
                                </div>
                            </div>

                            <!-- Fila 4: Clasificación Procesal -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 pt-6 border-t dark:border-gray-700">
                                <div>
                                    <InputLabel value="Especialidad *" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <SearchableSelect
                                        v-model="form.especialidad_id"
                                        :options="especialidades"
                                        :featured-options="[8, 12, 10, 11]" 
                                        value-key="id"
                                        label-key="nombre"
                                        :format-label="formatLabel"
                                        placeholder="Seleccione..."
                                        class="bg-gray-50/50 rounded-xl"
                                    />
                                    <InputError :message="form.errors.especialidad_id" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Tipo de Proceso *" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <SearchableSelect
                                        v-model="form.tipo_proceso"
                                        :options="tiposDisponibles"
                                        value-key="nombre"
                                        label-key="nombre"
                                        :format-label="formatLabel"
                                        :disabled="!form.especialidad_id"
                                        placeholder="Seleccione..."
                                        class="bg-gray-50/50 rounded-xl"
                                    />
                                    <InputError :message="form.errors.tipo_proceso" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Proceso *" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <SearchableSelect
                                        v-model="form.subtipo_proceso"
                                        :options="subtiposDisponibles"
                                        value-key="nombre"
                                        label-key="nombre"
                                        :format-label="formatLabel"
                                        :disabled="!form.tipo_proceso"
                                        placeholder="Seleccione..."
                                        class="bg-gray-50/50 rounded-xl"
                                    />
                                    <InputError :message="form.errors.subtipo_proceso" class="mt-2" />
                                </div>
                                <div class="md:col-span-2">
                                    <InputLabel value="Subproceso (Detalle)" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <SearchableSelect
                                        v-model="form.subproceso"
                                        :options="subprocesosDisponibles"
                                        value-key="nombre"
                                        label-key="nombre"
                                        :disabled="!form.subtipo_proceso"
                                        placeholder="Especifique subproceso..."
                                        class="bg-gray-50/50 rounded-xl"
                                    />
                                    <InputError :message="form.errors.subproceso" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Etapa Procesal Actual" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <SearchableSelect
                                        v-model="form.etapa_procesal"
                                        :options="etapas_procesales.map(e => ({ id: e, nombre: e }))"
                                        :featured-options="[1220, 1233, 1281]"
                                        value-key="id"
                                        label-key="nombre"
                                        :format-label="formatLabel"
                                        placeholder="Definir etapa..."
                                        class="bg-gray-50/50 rounded-xl"
                                    />
                                    <InputError :message="form.errors.etapa_procesal" class="mt-2" />
                                </div>
                            </div>

                            <!-- Fila 5: Ubicación y Garantías -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                                <div class="lg:col-span-3">
                                    <InputLabel value="Juzgado / Despacho Judicial" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <AsyncSelect v-model="selectedJuzgado" :endpoint="route('juzgados.search')" placeholder="Escriba el nombre del despacho..." label-key="nombre" class="bg-gray-50/50 focus-within:bg-white transition-all rounded-xl" />
                                    <InputError :message="form.errors.juzgado_id" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel value="Tipo de Garantía *" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <SelectInput v-model="form.tipo_garantia_asociada">
                                        <option value="codeudor">Codeudor</option>
                                        <option value="hipotecaria">Hipotecaria</option>
                                        <option value="prendaria">Prendaria</option>
                                        <option value="sin garantía">Sin garantía</option>
                                    </SelectInput>
                                    <InputError :message="form.errors.tipo_garantia_asociada" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Origen Documental *" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <SelectInput v-model="form.origen_documental">
                                        <option value="pagaré">Pagaré</option>
                                        <option value="libranza">Libranza</option>
                                        <option value="contrato">Contrato</option>
                                        <option value="otro">Otro</option>
                                    </SelectInput>
                                    <InputError :message="form.errors.origen_documental" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Canal de Notificación" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <SelectInput v-model="form.medio_contacto">
                                        <option :value="null">-- No especificado --</option>
                                        <option value="email">Correo Electrónico</option>
                                        <option value="whatsapp">WhatsApp Business</option>
                                        <option value="telefono">Llamada Telefónica</option>
                                    </SelectInput>
                                    <InputError :message="form.errors.medio_contacto" class="mt-2" />
                                </div>
                            </div>

                            <!-- Fila 6: Enlaces Digitales -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-6 border-t dark:border-gray-700">
                                <div>
                                    <InputLabel value="URL Carpeta Drive (Repositorio)" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <LinkIcon class="h-4 w-4 text-indigo-400" />
                                        </div>
                                        <TextInput v-model="form.link_drive" type="url" class="pl-10 block w-full bg-gray-50/50 focus:bg-white transition-all text-xs" placeholder="https://drive.google.com/..." />
                                    </div>
                                    <InputError :message="form.errors.link_drive" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="URL Expediente Digital (Tyba/Samai)" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <GlobeAltIcon class="h-4 w-4 text-emerald-400" />
                                        </div>
                                        <TextInput v-model="form.link_expediente" type="url" class="pl-10 block w-full bg-gray-50/50 focus:bg-white transition-all text-xs" placeholder="https://procesos.ramajudicial.gov.co/..." />
                                    </div>
                                    <InputError :message="form.errors.link_expediente" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: CODEUDORES -->
                    <div class="bg-white dark:bg-gray-800 shadow-xl shadow-slate-200/50 dark:shadow-none rounded-3xl border border-gray-100 dark:border-gray-700 overflow-visible">
                        <div class="p-6 bg-gray-50/50 dark:bg-gray-700/30 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm mr-3">
                                    <BriefcaseIcon class="h-5 w-5 text-indigo-600" />
                                </div>
                                <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">Codeudores Adicionales</h3>
                            </div>
                            <button type="button" @click="addCodeudor" class="text-[10px] font-black uppercase tracking-widest bg-indigo-600 text-white px-4 py-2 rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200 dark:shadow-none flex items-center gap-2">
                                <PlusIcon class="h-3 w-3" /> Añadir Codeudor
                            </button>
                        </div>

                        <div class="p-8 space-y-8">
                            <!-- Alerta de "Sin Codeudores" -->
                            <div v-if="form.codeudores.length === 0 && !form.sin_codeudores" class="p-4 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800/50 rounded-2xl flex items-center justify-between gap-4 animate-in fade-in slide-in-from-top-2">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                                        <ExclamationTriangleIcon class="w-5 h-5 text-amber-600" />
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-black text-amber-800 dark:text-amber-300 uppercase tracking-widest">¿Este proceso no tiene codeudores?</h4>
                                        <p class="text-[10px] text-amber-600 dark:text-amber-500 font-bold uppercase tracking-tighter">Se recomienda vincular al menos uno o marcar la casilla de "No aplica".</p>
                                    </div>
                                </div>
                                <button type="button" @click="form.sin_codeudores = true" class="px-4 py-2 bg-white dark:bg-gray-800 border border-amber-200 dark:border-gray-700 rounded-xl text-[10px] font-black uppercase text-amber-600 hover:bg-amber-600 hover:text-white transition-all shadow-sm">
                                    Marcar que no tiene
                                </button>
                            </div>

                            <div v-if="form.sin_codeudores" class="p-4 bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-100 dark:border-emerald-800/50 rounded-2xl flex items-center justify-between gap-4 animate-in zoom-in-95">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                                        <CheckCircleIcon class="w-5 h-5 text-emerald-600" />
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-black text-emerald-800 dark:text-emerald-300 uppercase tracking-widest">Confirmado: Sin Codeudores</h4>
                                        <p class="text-[10px] text-emerald-600 dark:text-emerald-500 font-bold uppercase tracking-tighter">El proceso se registrará oficialmente como "Sin garantía de codeudor".</p>
                                    </div>
                                </div>
                                <button type="button" @click="form.sin_codeudores = false" class="text-[10px] font-bold text-emerald-600 uppercase hover:underline">Cambiar</button>
                            </div>

                            <div v-if="form.codeudores.length === 0 && !form.sin_codeudores" class="py-16 border-2 border-dashed border-gray-100 dark:border-gray-700 rounded-3xl text-center">
                                <div class="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-full w-fit mx-auto mb-4 text-gray-300">
                                    <UsersIcon class="h-10 w-10" />
                                </div>
                                <p class="text-sm text-gray-400 font-bold uppercase tracking-widest px-8">No se han vinculado codeudores</p>
                                <p class="text-xs text-gray-400/60 mt-1 italic">Haga clic en el botón superior para agregar un deudor solidario.</p>
                            </div>

                            <div v-for="(codeudor, index) in form.codeudores" :key="index" class="p-8 bg-gray-50/50 dark:bg-gray-900/40 rounded-3xl border border-gray-200 dark:border-gray-700 relative animate-in zoom-in-95 group/card">
                                <button type="button" @click="removeCodeudor(index)" class="absolute -top-3 -right-3 bg-rose-500 text-white p-2 rounded-full shadow-lg hover:bg-rose-600 transition-transform hover:scale-110 opacity-0 group-hover/card:opacity-100">
                                    <TrashIcon class="h-5 w-5" />
                                </button>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                                    <div class="md:col-span-1">
                                        <InputLabel :for="'co_nombre_' + index" value="Nombre Completo *" class="text-[10px] font-black text-gray-400 uppercase mb-1" />
                                        <TextInput :id="'co_nombre_' + index" v-model="codeudor.nombre_completo" @blur="codeudor.nombre_completo = toUpperCase(codeudor.nombre_completo)" class="mt-1 block w-full bg-white font-bold" placeholder="Nombre completo" required />
                                        <InputError :message="form.errors[`codeudores.${index}.nombre_completo`]" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel :for="'co_tipo_doc_' + index" value="Tipo Documento" class="text-[10px] font-black text-gray-400 uppercase mb-1" />
                                        <SelectInput :id="'co_tipo_doc_' + index" v-model="codeudor.tipo_documento">
                                            <option value="CC">Cédula de Ciudadanía</option>
                                            <option value="NIT">NIT</option>
                                            <option value="CE">Cédula de Extranjería</option>
                                        </SelectInput>
                                    </div>
                                    <div>
                                        <InputLabel :for="'co_doc_' + index" value="Número Documento *" class="text-[10px] font-black text-gray-400 uppercase mb-1" />
                                        <div :class="codeudor.tipo_documento === 'NIT' ? 'grid grid-cols-4 gap-2' : ''">
                                            <div :class="codeudor.tipo_documento === 'NIT' ? 'col-span-3' : ''">
                                                <TextInput :id="'co_doc_' + index" v-model="codeudor.numero_documento" class="mt-1 block w-full bg-white font-bold" placeholder="Documento" required />
                                            </div>
                                            <div v-if="codeudor.tipo_documento === 'NIT'">
                                                <TextInput v-model="codeudor.dv" maxlength="1" placeholder="DV" class="mt-1 block w-full text-center px-0 bg-white font-black" />
                                            </div>
                                        </div>
                                        <InputError :message="form.errors[`codeudores.${index}.numero_documento`]" class="mt-2" />
                                        <InputError :message="form.errors[`codeudores.${index}.dv`]" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel :for="'co_celular_' + index" value="Celular" class="text-[10px] font-black text-gray-400 uppercase mb-1" />
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <DevicePhoneMobileIcon class="h-4 w-4 text-gray-400" />
                                            </div>
                                            <TextInput :id="'co_celular_' + index" v-model="codeudor.celular" class="pl-10 mt-1 block w-full bg-white" placeholder="Ej: 3001234567" />
                                        </div>
                                    </div>
                                    <div class="md:col-span-2">
                                        <InputLabel :for="'co_email_' + index" value="Correo Electrónico" class="text-[10px] font-black text-gray-400 uppercase mb-1" />
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <AtSymbolIcon class="h-4 w-4 text-gray-400" />
                                            </div>
                                            <TextInput :id="'co_email_' + index" v-model="codeudor.correo" type="email" class="pl-10 mt-1 block w-full bg-white" placeholder="correo@ejemplo.com" />
                                        </div>
                                    </div>
                                </div>

                                <!-- --- DETALLE ADICIONAL: DIRECCIONES Y REDES --- -->
                                <div class="mt-10 grid grid-cols-1 lg:grid-cols-2 gap-10 pt-8 border-t border-gray-200/50 dark:border-gray-700/50">
                                    <!-- Direcciones -->
                                    <div class="space-y-4">
                                        <div class="flex justify-between items-center bg-white/50 dark:bg-gray-800/50 p-2 pl-4 rounded-xl border border-gray-100 dark:border-gray-700">
                                            <h4 class="text-[10px] font-black text-indigo-400 uppercase tracking-widest flex items-center gap-2">
                                                <MapPinIcon class="h-4 w-4" /> Ubicaciones
                                            </h4>
                                            <button type="button" @click="addAddress(index)" class="text-[9px] font-black uppercase tracking-widest bg-white dark:bg-gray-700 text-indigo-600 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-900 shadow-sm hover:bg-indigo-600 hover:text-white transition-all">
                                                + Nueva Dirección
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-1 gap-4">
                                            <div v-for="(addr, aIdx) in codeudor.addresses" :key="aIdx" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm relative group animate-in slide-in-from-left-2">
                                                <div class="col-span-1">
                                                    <InputLabel value="Referencia" class="!text-[9px] font-black text-gray-400" />
                                                    <TextInput v-model="addr.label" placeholder="Ej: Oficina" class="!text-xs w-full bg-gray-50/50" />
                                                </div>
                                                <div class="col-span-2">
                                                    <InputLabel value="Dirección y Ciudad" class="!text-[9px] font-black text-gray-400" />
                                                    <TextInput v-model="addr.address" placeholder="Calle... Medellín" class="!text-xs w-full bg-gray-50/50" />
                                                </div>
                                                <button type="button" @click="removeAddress(index, aIdx)" class="absolute -top-2 -right-2 bg-rose-500 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition shadow-lg">
                                                    <TrashIcon class="h-3 w-3" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Redes Sociales -->
                                    <div class="space-y-4">
                                        <div class="flex justify-between items-center bg-white/50 dark:bg-gray-800/50 p-2 pl-4 rounded-xl border border-gray-100 dark:border-gray-700">
                                            <h4 class="text-[10px] font-black text-sky-400 uppercase tracking-widest flex items-center gap-2">
                                                <AtSymbolIcon class="h-4 w-4" /> Presencia Digital
                                            </h4>
                                            <button type="button" @click="codeudor.social_links.push({ label: 'Facebook', url: '' })" class="text-[9px] font-black uppercase tracking-widest bg-white dark:bg-gray-700 text-sky-600 px-3 py-1.5 rounded-lg border border-sky-100 dark:border-sky-900 shadow-sm hover:bg-sky-600 hover:text-white transition-all">
                                                + Nuevo Enlace
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-1 gap-4">
                                            <div v-for="(link, sIdx) in codeudor.social_links" :key="sIdx" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm relative group animate-in slide-in-from-right-2">
                                                <div class="col-span-1">
                                                    <InputLabel value="Red / Portal" class="!text-[9px] font-black text-gray-400" />
                                                    <TextInput v-model="link.label" placeholder="EJ: LINKEDIN" class="!text-xs w-full bg-gray-50/50" />
                                                </div>
                                                <div class="col-span-2">
                                                    <InputLabel value="Dirección URL" class="!text-[9px] font-black text-gray-400" />
                                                    <TextInput v-model="link.url" type="url" placeholder="https://..." class="!text-xs w-full bg-gray-50/50" />
                                                </div>
                                                <button type="button" @click="codeudor.social_links.splice(sIdx, 1)" class="absolute -top-2 -right-2 bg-rose-500 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition shadow-lg">
                                                    <TrashIcon class="h-3 w-3" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BOTÓN FINAL -->
                    <div class="flex flex-col md:flex-row items-center justify-between p-6 bg-indigo-950 rounded-[2.5rem] shadow-2xl shadow-indigo-300 dark:shadow-none animate-in fade-in slide-in-from-bottom-4">
                        <div class="flex items-center gap-4 mb-4 md:mb-0 pl-4">
                            <div class="p-3 bg-white/10 rounded-2xl">
                                <CheckCircleIcon class="h-6 w-6 text-emerald-400" />
                            </div>
                            <div>
                                <h4 class="text-white text-sm font-black uppercase tracking-widest">Listo para procesar</h4>
                                <p class="text-indigo-300 text-[10px] font-bold uppercase">Verifique los datos financieros y el radicado antes de guardar.</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-6 pr-2">
                            <Link :href="route('casos.index')" class="text-xs font-black text-indigo-300 uppercase tracking-widest hover:text-white transition-colors">Cancelar Registro</Link>
                            <PrimaryButton class="!bg-white !text-indigo-950 !rounded-3xl !px-10 !py-5 !text-xs !font-black !shadow-xl hover:!scale-105 active:!scale-95 transition-all !uppercase" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                APERTURAR EXPEDIENTE
                            </PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
/* Estilos adicionales para la barra de desplazamiento personalizada si es necesario */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #334155;
}
</style>
