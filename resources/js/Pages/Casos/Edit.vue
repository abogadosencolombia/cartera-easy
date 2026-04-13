<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Modal from '@/Components/Modal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head, Link, useForm, usePage, useRemember, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import { formatRadicado, addDaysToDate, addMonthsToDate, toUpperCase, calculateDV } from '@/Utils/formatters';
import { 
    TrashIcon, InformationCircleIcon, ScaleIcon, UsersIcon, LockClosedIcon, 
    PlusIcon, ChevronUpIcon, ChevronDownIcon, ArchiveBoxXMarkIcon, ArrowPathIcon,
    BriefcaseIcon, BuildingOfficeIcon, CheckCircleIcon, ClockIcon, DocumentTextIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: { type: Object, required: true },
    estructuraProcesal: { type: Array, default: () => [] },
    etapas_procesales: { type: Array, default: () => [] },
});

const validTabs = ['info-principal', 'proceso-judicial', 'codeudores', 'control-notas'];
const getInitialTab = () => {
    if (typeof window === 'undefined') return 'info-principal';
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    return (tabParam && validTabs.includes(tabParam)) ? tabParam : 'info-principal';
};

const activeTab = useRemember(getInitialTab(), `EditCasoTab:${props.caso.id}`);
const user = usePage().props.auth.user;

// --- SINCRONIZACIÓN CON URL ---
const setActiveTab = (tab) => {
    activeTab.value = tab;
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
};

// --- LÓGICA DE CIERRE/REAPERTURA ---
const showCloseModal = ref(false);
const closeForm = useForm({ nota_cierre: '' });
const confirmClose = () => { closeForm.nota_cierre = ''; showCloseModal.value = true; };
const submitClose = () => {
    closeForm.patch(route('casos.close', props.caso.id), { onSuccess: () => showCloseModal.value = false, preserveScroll: true });
};
const submitReopen = () => {
    if (confirm('¿Reabrir este caso?')) router.patch(route('casos.reopen', props.caso.id), {}, { preserveScroll: true });
};

const isFormDisabled = computed(() => (user.tipo_usuario !== 'admin' && props.caso.bloqueado));

const formatDateForInput = (d) => d ? new Date(d).toISOString().split('T')[0] : null;
const safeJsonParse = (s) => { if (!s) return []; try { const p = JSON.parse(s); return Array.isArray(p) ? p : []; } catch (e) { return []; } };

const form = useForm(`EditCaso:${props.caso.id}`, {
    _method: 'PATCH',
    cooperativa_id: props.caso.cooperativa ? { id: props.caso.cooperativa.id, nombre: props.caso.cooperativa.nombre } : null,
    user_id: props.caso.users?.length > 0 
        ? props.caso.users.map(u => ({ id: u.id, name: u.name })) 
        : (props.caso.user ? [{ id: props.caso.user.id, name: props.caso.user.name }] : []),
    deudor_id: props.caso.deudor_id,
    deudor: {
        id: props.caso.deudor_id,
        selected: props.caso.deudor ? { id: props.caso.deudor.id, nombre_completo: props.caso.deudor.nombre_completo, numero_documento: props.caso.deudor.numero_documento } : null,
        is_new: false,
        nombre_completo: '', tipo_documento: 'CC', numero_documento: '', dv: '', celular_1: '', correo_1: '', cooperativas_ids: [], abogados_ids: []
    },
    codeudores: props.caso.codeudores?.map(c => ({
        id: c.id, nombre_completo: c.nombre_completo || '', tipo_documento: c.tipo_documento || 'CC', numero_documento: c.numero_documento || '',
        dv: c.dv || '',
        celular: c.celular || '', correo: c.correo || '', addresses: safeJsonParse(c.addresses), social_links: safeJsonParse(c.social_links), showDetails: true
    })) || [],
    juzgado_id: props.caso.juzgado ? { id: props.caso.juzgado.id, nombre: props.caso.juzgado.nombre } : null,
    referencia_credito: props.caso.referencia_credito,
    especialidad_id: props.caso.especialidad_id,
    tipo_proceso: props.caso.tipo_proceso,
    subtipo_proceso: props.caso.subtipo_proceso,
    subproceso: props.caso.subproceso,
    etapa_procesal: props.caso.etapa_procesal,
    radicado: props.caso.radicado ?? '',
    tipo_garantia_asociada: props.caso.tipo_garantia_asociada,
    origen_documental: props.caso.origen_documental,
    medio_contacto: props.caso.medio_contacto,
    fecha_apertura: formatDateForInput(props.caso.fecha_apertura),
    fecha_vencimiento: formatDateForInput(props.caso.fecha_vencimiento),
    fecha_inicio_credito: formatDateForInput(props.caso.fecha_inicio_credito),
    monto_total: props.caso.monto_total,
    monto_deuda_actual: props.caso.monto_deuda_actual,
    monto_total_pagado: props.caso.monto_total_pagado,
    tasa_interes_corriente: props.caso.tasa_interes_corriente,
    bloqueado: !!props.caso.bloqueado,
    motivo_bloqueo: props.caso.motivo_bloqueo ?? '',
    notas_legales: props.caso.notas_legales,
    link_drive: props.caso.link_drive || '',
    link_expediente: props.caso.link_expediente || '',
});

// --- AUTO-FORMATO ---
watch(() => form.deudor.numero_documento, (newVal) => {
    if (form.deudor.is_new && form.deudor.tipo_documento === 'NIT' && newVal) {
        form.deudor.dv = calculateDV(newVal).toString();
    }
});

const addCodeudor = () => { form.codeudores.push({ id: null, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', celular: '', correo: '', addresses: [], social_links: [], showDetails: true }); activeTab.value = 'codeudores'; };
const removeCodeudor = (idx) => form.codeudores.splice(idx, 1);
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

// --- CASCADA ---
const formatLabel = (text) => text?.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, c => c.toUpperCase()) || '';
const especialidades = computed(() => props.estructuraProcesal);
const tiposDisponibles = ref([]);
const subtiposDisponibles = ref([]);
const subprocesosDisponibles = ref([]);

// Opciones rápidas con iconos (Diseño Premium)
const opcionesRapidasEsp = computed(() => {
    const iconos = {
        'CIVIL': ScaleIcon,
        'LABORAL': BriefcaseIcon,
        'FAMILIA': UsersIcon,
        'COMERCIAL': BuildingOfficeIcon
    };
    return especialidades.value
        .filter(e => iconos[e.nombre])
        .map(e => ({ ...e, icono: iconos[e.nombre] }))
        .sort((a, b) => a.nombre.localeCompare(b.nombre));
});

const etapasRapidas = [
    { id: 'DEMANDA PRESENTADA', icono: DocumentTextIcon },
    { id: 'MANDAMIENTO DE PAGO', icono: CheckCircleIcon },
    { id: 'AUDIENCIA INICIAL (ART. 372 CGP)', icono: ClockIcon },
    { id: 'TERMINADO POR PAGO TOTAL', icono: ArrowPathIcon }
];

watch(() => form.especialidad_id, (id, oldId) => {
    const esp = especialidades.value.find(e => e.id === id);
    tiposDisponibles.value = esp ? esp.tipos_proceso : [];
    if (oldId !== undefined) {
        form.tipo_proceso = null;
        form.subtipo_proceso = null;
        form.subproceso = null;
    }
}, { immediate: true });

watch(() => form.tipo_proceso, (val, oldVal) => {
    const t = tiposDisponibles.value.find(x => x.nombre === val);
    subtiposDisponibles.value = t ? t.subtipos : [];
    if (oldVal !== undefined) {
        form.subtipo_proceso = null;
        form.subproceso = null;
    }
}, { immediate: true });

watch(() => form.subtipo_proceso, (val, oldVal) => {
    const s = subtiposDisponibles.value.find(x => x.nombre === val);
    subprocesosDisponibles.value = s ? s.subprocesos : [];
    if (oldVal !== undefined) {
        form.subproceso = null;
    }
}, { immediate: true });

const submit = () => {
    form.transform(data => ({
        ...data,
        cooperativa_id: data.cooperativa_id?.id ?? null,
        user_id: Array.isArray(data.user_id) ? data.user_id.map(u => u.id) : [],
        juzgado_id: data.juzgado_id?.id ?? null,
        deudor: data.deudor.is_new ? { 
            ...data.deudor, 
            is_new: true,
            cooperativas_ids: data.deudor.cooperativas_ids.map(c => c.id), 
            abogados_ids: data.deudor.abogados_ids.map(a => a.id) 
        } : { is_new: false, id: data.deudor.selected?.id },
        deudor_id: data.deudor.is_new ? null : data.deudor.selected?.id,
    })).patch(route('casos.update', props.caso.id));
};
</script>

<template>
    <Head :title="'Editar Caso #' + caso.id" />
    <AuthenticatedLayout>
        <template #header>
             <div class="flex items-center justify-between">
                 <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Editando Caso <span class="text-indigo-500">#{{ caso.id }}</span></h2>
                 <div class="flex gap-3">
                     <Link :href="route('casos.show', caso.id)"><SecondaryButton>Cancelar</SecondaryButton></Link>
                     <PrimaryButton @click="submit" :disabled="form.processing || isFormDisabled">Actualizar Caso</PrimaryButton>
                 </div>
             </div>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-visible">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-6 px-6 overflow-x-auto">
                            <button @click="setActiveTab('info-principal')" :class="[activeTab === 'info-principal' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500']" class="py-4 px-1 border-b-2 font-medium text-sm flex items-center"><InformationCircleIcon class="h-5 w-5 mr-2"/> Info Principal</button>
                            <button @click="setActiveTab('proceso-judicial')" :class="[activeTab === 'proceso-judicial' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500']" class="py-4 px-1 border-b-2 font-medium text-sm flex items-center"><ScaleIcon class="h-5 w-5 mr-2"/> Proceso Judicial</button>
                            <button @click="setActiveTab('codeudores')" :class="[activeTab === 'codeudores' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500']" class="py-4 px-1 border-b-2 font-medium text-sm flex items-center"><UsersIcon class="h-5 w-5 mr-2"/> Codeudores</button>
                            <button @click="setActiveTab('control-notas')" :class="[activeTab === 'control-notas' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500']" class="py-4 px-1 border-b-2 font-medium text-sm flex items-center"><LockClosedIcon class="h-5 w-5 mr-2"/> Control y Notas</button>
                        </nav>
                    </div>

                    <div class="p-8">
                        <fieldset :disabled="isFormDisabled">
                            <!-- TAB 1 -->
                            <section v-show="activeTab === 'info-principal'" class="space-y-8">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div><InputLabel value="Cooperativa / Empresa *" /><AsyncSelect v-model="form.cooperativa_id" :endpoint="route('cooperativas.search')" placeholder="Asignar a..." label-key="nombre" /><InputError :message="form.errors.cooperativa_id" /></div>
                                    <div><InputLabel value="Abogado(s) a Cargo *" /><AsyncSelect v-model="form.user_id" :endpoint="route('users.search')" multiple placeholder="Asignar gestores..." label-key="name" /><InputError :message="form.errors.user_id" /></div>
                                    <div class="md:col-span-2 space-y-4">
                                        <div class="flex justify-between items-center"><InputLabel value="Deudor Principal *" /><button type="button" @click="form.deudor.is_new = !form.deudor.is_new" class="text-[10px] font-bold text-indigo-600 uppercase">{{ form.deudor.is_new ? '← Buscar' : '+ Nuevo' }}</button></div>
                                        <div v-if="!form.deudor.is_new">
                                            <AsyncSelect v-model="form.deudor.selected" :endpoint="route('personas.search')" label-key="nombre_completo" />
                                            <InputError :message="form.errors.deudor_id" />
                                            <InputError :message="form.errors['deudor.id']" />
                                        </div>
                                        <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 border rounded-lg dark:border-gray-700">
                                            <div>
                                                <TextInput v-model="form.deudor.nombre_completo" @blur="form.deudor.nombre_completo = toUpperCase(form.deudor.nombre_completo)" placeholder="Nombre completo" class="w-full" />
                                                <InputError :message="form.errors['deudor.nombre_completo']" />
                                            </div>
                                            <div>
                                                <select v-model="form.deudor.tipo_documento" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm"><option>CC</option><option>NIT</option><option>CE</option></select>
                                                <InputError :message="form.errors['deudor.tipo_documento']" />
                                            </div>
                                            <div>
                                                <div :class="form.deudor.tipo_documento === 'NIT' ? 'grid grid-cols-4 gap-2' : ''">
                                                    <div :class="form.deudor.tipo_documento === 'NIT' ? 'col-span-3' : ''">
                                                        <TextInput v-model="form.deudor.numero_documento" placeholder="Documento" class="w-full" />
                                                    </div>
                                                    <div v-if="form.deudor.tipo_documento === 'NIT'">
                                                        <TextInput v-model="form.deudor.dv" maxlength="1" placeholder="DV" class="w-full text-center px-0" />
                                                    </div>
                                                </div>
                                                <InputError :message="form.errors['deudor.numero_documento']" />
                                                <InputError :message="form.errors['deudor.dv']" />
                                            </div>
                                            <div>
                                                <TextInput v-model="form.deudor.celular_1" placeholder="Celular" class="w-full" />
                                                <InputError :message="form.errors['deudor.celular_1']" />
                                            </div>
                                            <div class="md:col-span-2">
                                                <TextInput v-model="form.deudor.correo_1" type="email" placeholder="Correo Electrónico" class="w-full" />
                                                <InputError :message="form.errors['deudor.correo_1']" />
                                            </div>
                                            <div class="col-span-3 grid grid-cols-2 gap-4 mt-2">
                                                <AsyncSelect v-model="form.deudor.cooperativas_ids" :endpoint="route('cooperativas.search')" multiple label-key="nombre" placeholder="Cooperativas..." />
                                                <AsyncSelect v-model="form.deudor.abogados_ids" :endpoint="route('users.search')" multiple label-key="name" placeholder="Abogados..." />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6 border-t dark:border-gray-700">
                                    <div><InputLabel value="Número De Pagaré" /><TextInput v-model="form.referencia_credito" class="w-full" /><InputError :message="form.errors.referencia_credito" /></div>
                                    <div><InputLabel value="Monto de Crédito *" /><TextInput v-model="form.monto_total" type="number" step="0.01" class="w-full" /><InputError :message="form.errors.monto_total" /></div>
                                    <div><InputLabel value="Monto Deuda Actual" /><TextInput v-model="form.monto_deuda_actual" type="number" step="0.01" class="w-full" /><InputError :message="form.errors.monto_deuda_actual" /></div>
                                    <div><InputLabel value="Total Pagado" /><TextInput v-model="form.monto_total_pagado" type="number" step="0.01" class="w-full" /><InputError :message="form.errors.monto_total_pagado" /></div>
                                    <div><InputLabel value="Tasa Interés Corriente *" /><TextInput v-model="form.tasa_interes_corriente" type="number" step="0.01" class="w-full" /><InputError :message="form.errors.tasa_interes_corriente" /></div>
                                    <div>
                                        <InputLabel value="Fecha de Demanda *" />
                                        <DatePicker v-model="form.fecha_apertura" class="w-full" />
                                        <div class="mt-1 flex gap-1">
                                            <button type="button" @click="addDays('fecha_apertura', 3)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+3d</button>
                                            <button type="button" @click="addDays('fecha_apertura', 5)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+5d</button>
                                            <button type="button" @click="addMonths('fecha_apertura', 1)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+1m</button>
                                        </div>
                                        <InputError :message="form.errors.fecha_apertura" />
                                    </div>
                                    <div>
                                        <InputLabel value="Fecha Vencimiento" />
                                        <DatePicker v-model="form.fecha_vencimiento" class="w-full" />
                                        <div class="mt-1 flex gap-1">
                                            <button type="button" @click="addMonths('fecha_vencimiento', 6)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+6m</button>
                                            <button type="button" @click="addMonths('fecha_vencimiento', 12)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+1a</button>
                                        </div>
                                        <InputError :message="form.errors.fecha_vencimiento" />
                                    </div>
                                    <div><InputLabel value="Fecha Inicio Crédito" /><DatePicker v-model="form.fecha_inicio_credito" class="w-full" /><InputError :message="form.errors.fecha_inicio_credito" /></div>
                                </div>
                            </section>

                            <!-- TAB 2 -->
                            <section v-show="activeTab === 'proceso-judicial'" class="space-y-8">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel value="Radicado" />
                                        <TextInput 
                                            v-model="form.radicado" 
                                            @input="handleRadicadoInput"
                                            class="w-full font-mono" 
                                            placeholder="XXXXX-XX-XX-XXX-XXXX-XXXXX-XX"
                                        />
                                        <InputError :message="form.errors.radicado" />
                                    </div>
                                    <div><InputLabel value="Juzgado" /><AsyncSelect v-model="form.juzgado_id" :endpoint="route('juzgados.search')" label-key="nombre" /><InputError :message="form.errors.juzgado_id" /></div>
                                    <div>
                                        <InputLabel value="Especialidad *" />
                                        <SearchableSelect
                                            v-model="form.especialidad_id"
                                            :options="especialidades"
                                            :featured-options="[8, 12, 10, 11]"
                                            value-key="id"
                                            label-key="nombre"
                                            :format-label="formatLabel"
                                            placeholder="Seleccione especialidad..."
                                        />
                                        <InputError :message="form.errors.especialidad_id" />
                                    </div>
                                    <div>
                                        <InputLabel value="Tipo de Proceso *" />
                                        <SearchableSelect
                                            v-model="form.tipo_proceso"
                                            :options="tiposDisponibles"
                                            value-key="nombre"
                                            label-key="nombre"
                                            :format-label="formatLabel"
                                            :disabled="!form.especialidad_id"
                                            placeholder="Seleccione tipo..."
                                        />
                                        <InputError :message="form.errors.tipo_proceso" />
                                    </div>
                                    <div>
                                        <InputLabel value="Proceso *" />
                                        <SearchableSelect
                                            v-model="form.subtipo_proceso"
                                            :options="subtiposDisponibles"
                                            value-key="nombre"
                                            label-key="nombre"
                                            :format-label="formatLabel"
                                            :disabled="!form.tipo_proceso"
                                            placeholder="Seleccione proceso..."
                                        />
                                        <InputError :message="form.errors.subtipo_proceso" />
                                    </div>
                                    <div>
                                        <InputLabel value="Subproceso (Detalle)" />
                                        <SearchableSelect
                                            v-model="form.subproceso"
                                            :options="subprocesosDisponibles"
                                            value-key="nombre"
                                            label-key="nombre"
                                            :disabled="!form.subtipo_proceso"
                                            placeholder="Seleccione subproceso..."
                                        />
                                        <InputError :message="form.errors.subproceso" />
                                    </div>
                                    <div>
                                        <InputLabel value="Etapa Procesal" />
                                        <SearchableSelect
                                            v-model="form.etapa_procesal"
                                            :options="etapas_procesales.map(e => ({ id: e, nombre: e }))"
                                            :featured-options="[1220, 1233, 1281]"
                                            value-key="id"
                                            label-key="nombre"
                                            :format-label="formatLabel"
                                            placeholder="Seleccione etapa..."
                                        />
                                        <InputError :message="form.errors.etapa_procesal" />
                                    </div>
                                    <div><InputLabel value="Tipo de Garantía *" /><select v-model="form.tipo_garantia_asociada" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm"><option value="codeudor">Codeudor</option><option value="hipotecaria">Hipotecaria</option><option value="prendaria">Prendaria</option><option value="sin garantía">Sin garantía</option></select><InputError :message="form.errors.tipo_garantia_asociada" /></div>
                                    <div><InputLabel value="Origen Documental *" /><select v-model="form.origen_documental" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm"><option value="pagaré">Pagaré</option><option value="libranza">Libranza</option><option value="contrato">Contrato</option><option value="otro">Otro</option></select><InputError :message="form.errors.origen_documental" /></div>
                                    <div><InputLabel value="Medio de Contacto" /><select v-model="form.medio_contacto" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm"><option :value="null">-- Seleccione --</option><option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="telefono">Teléfono</option></select><InputError :message="form.errors.medio_contacto" /></div>
                                    <div class="md:col-span-1"><InputLabel value="URL Carpeta Drive (Opcional)" /><TextInput v-model="form.link_drive" type="url" class="w-full" placeholder="https://drive.google.com/..." /><InputError :message="form.errors.link_drive" /></div>
                                    <div class="md:col-span-1"><InputLabel value="URL Expediente Digital (Opcional)" /><TextInput v-model="form.link_expediente" type="url" class="w-full" placeholder="https://expediente.justicia.gov.co/..." /><InputError :message="form.errors.link_expediente" /></div>
                                </div>
                            </section>

                            <!-- TAB 3 -->
                            <section v-show="activeTab === 'codeudores'" class="space-y-6">
                                <div class="flex justify-between items-center"><h3 class="font-bold">Lista de Codeudores</h3><PrimaryButton type="button" @click="addCodeudor">+ Añadir</PrimaryButton></div>
                                <div v-for="(c, i) in form.codeudores" :key="i" class="p-6 border rounded-lg dark:border-gray-700 bg-gray-50/20 relative">
                                    <button @click="removeCodeudor(i)" class="absolute top-4 right-4 text-red-500 hover:text-red-700"><TrashIcon class="h-5 w-5"/></button>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div class="md:col-span-1"><InputLabel value="Nombre Completo *" /><TextInput v-model="c.nombre_completo" @blur="c.nombre_completo = toUpperCase(c.nombre_completo)" class="mt-1 block w-full" required/><InputError :message="form.errors[`codeudores.${i}.nombre_completo`]" /></div>
                                        <div><InputLabel value="Tipo Documento" /><select v-model="c.tipo_documento" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm"><option value="CC">Cédula de Ciudadanía</option><option value="NIT">NIT</option><option value="CE">Cédula de Extranjería</option></select></div>
                                        <div>
                                            <InputLabel value="Número Documento *" />
                                            <div :class="c.tipo_documento === 'NIT' ? 'grid grid-cols-4 gap-2' : ''">
                                                <div :class="c.tipo_documento === 'NIT' ? 'col-span-3' : ''">
                                                    <TextInput v-model="c.numero_documento" class="mt-1 block w-full" required/>
                                                </div>
                                                <div v-if="c.tipo_documento === 'NIT'">
                                                    <TextInput v-model="c.dv" maxlength="1" placeholder="DV" class="mt-1 block w-full text-center px-0" />
                                                </div>
                                            </div>
                                            <InputError :message="form.errors[`codeudores.${i}.numero_documento`]" />
                                            <InputError :message="form.errors[`codeudores.${i}.dv`]" />
                                        </div>
                                        <div><InputLabel value="Celular" /><TextInput v-model="c.celular" class="mt-1 block w-full" placeholder="Ej: 3001234567" /></div>
                                        <div class="md:col-span-2"><InputLabel value="Correo Electrónico" /><TextInput v-model="c.correo" type="email" class="mt-1 block w-full" placeholder="correo@ejemplo.com" /></div>
                                    </div>
                                </div>
                            </section>

                            <!-- TAB 4 -->
                            <section v-show="activeTab === 'control-notas'" class="space-y-8">
                                <div><InputLabel value="Notas Legales / Internas" /><Textarea v-model="form.notas_legales" rows="4" class="w-full" /><InputError :message="form.errors.notas_legales" /></div>
                                <div v-if="user.tipo_usuario === 'admin'" class="p-4 border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/20">
                                    <div class="flex items-center justify-between">
                                        <div><h4 class="font-bold text-red-800 dark:text-red-200">{{ caso.nota_cierre ? 'Reabrir' : 'Cerrar' }} Caso</h4></div>
                                        <PrimaryButton v-if="caso.nota_cierre" @click="submitReopen" class="bg-blue-600">Reabrir</PrimaryButton>
                                        <DangerButton v-else @click="confirmClose">Cerrar</DangerButton>
                                    </div>
                                </div>
                            </section>
                        </fieldset>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showCloseModal" @close="showCloseModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold">Cierre del Caso</h2>
                <div class="mt-4"><InputLabel value="Nota Final" /><Textarea v-model="closeForm.nota_cierre" class="w-full" rows="4" /></div>
                <div class="mt-6 flex justify-end gap-3"><SecondaryButton @click="showCloseModal = false">Cerrar</SecondaryButton><DangerButton @click="submitClose">Confirmar Cierre</DangerButton></div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style>
/* Estilos adicionales si fueran necesarios */
</style>
