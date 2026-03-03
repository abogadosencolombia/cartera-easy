<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Textarea from '@/Components/Textarea.vue';
import Dropdown from '@/Components/Dropdown.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import { TrashIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

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

const form = useForm({
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
        celular_1: '',
        correo_1: '',
        cooperativas_ids: [],
        abogados_ids: []
    },
    codeudores: props.casoAClonar?.codeudores?.map(c => ({
        ...c,
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
    clonado_de_id: props.casoAClonar?.id || null,
});

// --- HELPERS DINÁMICOS ---
const addCodeudor = () => form.codeudores.push({ nombre_completo: '', tipo_documento: 'CC', numero_documento: '', celular: '', correo: '', addresses: [], social_links: [] });
const removeCodeudor = (index) => form.codeudores.splice(index, 1);
const addAddress = (idx) => form.codeudores[idx].addresses.push({ label: 'Casa', address: '', city: '' });
const removeAddress = (idx, addrIdx) => form.codeudores[idx].addresses.splice(addrIdx, 1);

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
            abogados_ids: data.deudor.abogados_ids.map(a => a.id),
        } : { is_new: false, id: data.deudor.selected?.id },
        deudor_id: data.deudor.is_new ? null : data.deudor.selected?.id,
    })).post(route('casos.store'));
};
</script>

<template>
    <Head :title="casoAClonar ? 'Clonando Caso' : 'Registrar Nuevo Caso'" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-blue-500 leading-tight">
                {{ casoAClonar ? `Clonando Caso #${casoAClonar.id}` : 'Registrar Nuevo Caso' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-visible shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8">
                        <form @submit.prevent="submit" class="space-y-10">

                            <!-- SECCIÓN 1: PARTES -->
                            <section>
                                <h3 class="text-lg font-bold border-b dark:border-gray-700 pb-2 mb-6">Partes Involucradas</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel value="Cooperativa / Empresa *" />
                                        <AsyncSelect v-model="form.cooperativa_id" :endpoint="route('cooperativas.search')" placeholder="Seleccione empresa..." label-key="nombre" />
                                        <InputError :message="form.errors.cooperativa_id" />
                                    </div>
                                    <div>
                                        <InputLabel value="Abogado(s) a Cargo *" />
                                        <AsyncSelect v-model="form.user_id" :endpoint="route('users.search')" multiple placeholder="Asignar responsables..." label-key="name" />
                                        <InputError :message="form.errors.user_id" />
                                    </div>
                                    <div class="md:col-span-2 space-y-4">
                                        <div class="flex justify-between items-center">
                                            <InputLabel value="Deudor Principal *" />
                                            <button type="button" @click="form.deudor.is_new = !form.deudor.is_new" class="text-[10px] font-bold uppercase text-indigo-600">
                                                {{ form.deudor.is_new ? '← Buscar existente' : '+ Registrar como nuevo' }}
                                            </button>
                                        </div>
                                        <div v-if="!form.deudor.is_new">
                                            <AsyncSelect v-model="form.deudor.selected" :endpoint="route('personas.search')" placeholder="Buscar por nombre o documento..." label-key="nombre_completo" />
                                            <InputError :message="form.errors.deudor_id" />
                                            <InputError :message="form.errors['deudor.id']" />
                                        </div>
                                        <div v-else class="space-y-4 p-4 border dark:border-gray-700 rounded-lg bg-gray-50/30">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div class="md:col-span-1">
                                                    <TextInput v-model="form.deudor.nombre_completo" placeholder="Nombre Completo *" class="w-full" />
                                                    <InputError :message="form.errors['deudor.nombre_completo']" />
                                                </div>
                                                <div>
                                                    <select v-model="form.deudor.tipo_documento" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                                        <option>CC</option>
                                                        <option>NIT</option>
                                                        <option>CE</option>
                                                    </select>
                                                    <InputError :message="form.errors['deudor.tipo_documento']" />
                                                </div>
                                                <div>
                                                    <TextInput v-model="form.deudor.numero_documento" placeholder="Número Documento *" class="w-full" />
                                                    <InputError :message="form.errors['deudor.numero_documento']" />
                                                </div>
                                                <div>
                                                    <TextInput v-model="form.deudor.celular_1" placeholder="Celular *" class="w-full" />
                                                    <InputError :message="form.errors['deudor.celular_1']" />
                                                </div>
                                                <div class="md:col-span-2">
                                                    <TextInput v-model="form.deudor.correo_1" type="email" placeholder="Correo Electrónico *" class="w-full" />
                                                    <InputError :message="form.errors['deudor.correo_1']" />
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <AsyncSelect v-model="form.deudor.cooperativas_ids" :endpoint="route('cooperativas.search')" placeholder="Asignar cooperativas..." multiple label-key="nombre" />
                                                <AsyncSelect v-model="form.deudor.abogados_ids" :endpoint="route('users.search')" placeholder="Asignar abogados..." multiple label-key="name" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- SECCIÓN 2: CRÉDITO Y PROCESO -->
                            <section>
                                <h3 class="text-lg font-bold border-b dark:border-gray-700 pb-2 mb-6">Información del Crédito y Proceso</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div>
                                        <InputLabel value="Número De Pagaré" />
                                        <TextInput v-model="form.referencia_credito" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.referencia_credito" />
                                    </div>
                                    <div>
                                        <InputLabel value="Número de Radicado" />
                                        <TextInput v-model="form.radicado" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.radicado" />
                                    </div>
                                    <div>
                                        <InputLabel value="Monto de Crédito *" />
                                        <TextInput v-model="form.monto_total" type="number" step="0.01" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.monto_total" />
                                    </div>
                                    <div>
                                        <InputLabel value="Monto Deuda Actual" />
                                        <TextInput v-model="form.monto_deuda_actual" type="number" step="0.01" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.monto_deuda_actual" />
                                    </div>
                                    <div>
                                        <InputLabel value="Total Pagado" />
                                        <TextInput v-model="form.monto_total_pagado" type="number" step="0.01" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.monto_total_pagado" />
                                    </div>
                                    <div>
                                        <InputLabel value="Tasa Interés Corriente *" />
                                        <TextInput v-model="form.tasa_interes_corriente" type="number" step="0.01" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.tasa_interes_corriente" />
                                    </div>
                                    <div>
                                        <InputLabel value="Fecha Inicio Crédito" />
                                        <DatePicker v-model="form.fecha_inicio_credito" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.fecha_inicio_credito" />
                                    </div>
                                    <div>
                                        <InputLabel value="Fecha de Demanda *" />
                                        <DatePicker v-model="form.fecha_apertura" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.fecha_apertura" />
                                    </div>
                                    <div>
                                        <InputLabel value="Fecha de Vencimiento" />
                                        <DatePicker v-model="form.fecha_vencimiento" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.fecha_vencimiento" />
                                    </div>
                                    
                                    <div>
                                        <InputLabel value="Especialidad *" />
                                        <select v-model="form.especialidad_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option :value="null">Seleccione...</option>
                                            <option v-for="esp in especialidades" :key="esp.id" :value="esp.id">{{ formatLabel(esp.nombre) }}</option>
                                        </select>
                                        <InputError :message="form.errors.especialidad_id" />
                                    </div>
                                    <div>
                                        <InputLabel value="Tipo de Proceso *" />
                                        <select v-model="form.tipo_proceso" :disabled="!form.especialidad_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option :value="null">Seleccione...</option>
                                            <option v-for="t in tiposDisponibles" :key="t.id" :value="t.nombre">{{ formatLabel(t.nombre) }}</option>
                                        </select>
                                        <InputError :message="form.errors.tipo_proceso" />
                                    </div>
                                    <div>
                                        <InputLabel value="Proceso *" />
                                        <select v-model="form.subtipo_proceso" :disabled="!form.tipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option :value="null">Seleccione...</option>
                                            <option v-for="s in subtiposDisponibles" :key="s.id" :value="s.nombre">{{ formatLabel(s.nombre) }}</option>
                                        </select>
                                        <InputError :message="form.errors.subtipo_proceso" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <InputLabel value="Subproceso (Detalle)" />
                                        <select v-model="form.subproceso" :disabled="!form.subtipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option :value="null">Seleccione...</option>
                                            <option v-for="sp in subprocesosDisponibles" :key="sp.id" :value="sp.nombre">{{ sp.nombre }}</option>
                                        </select>
                                        <InputError :message="form.errors.subproceso" />
                                    </div>
                                    
                                    <div>
                                        <InputLabel value="Etapa Procesal" />
                                        <Dropdown align="left" width="full">
                                            <template #trigger><button type="button" class="mt-1 flex w-full justify-between rounded-md border border-gray-300 dark:border-gray-700 p-2 text-sm dark:bg-gray-900"><span>{{ form.etapa_procesal ? formatLabel(form.etapa_procesal) : '-- Sin especificar --' }}</span><ChevronDownIcon class="h-4 w-4" /></button></template>
                                            <template #content><div class="py-1 bg-white dark:bg-gray-800 max-h-60 overflow-y-auto"><button type="button" v-for="e in etapas_procesales" :key="e" @click="form.etapa_procesal = e" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">{{ formatLabel(e) }}</button></div></template>
                                        </Dropdown>
                                        <InputError :message="form.errors.etapa_procesal" />
                                    </div>

                                    <div class="lg:col-span-3">
                                        <InputLabel value="Juzgado" />
                                        <AsyncSelect v-model="form.juzgado_id" :endpoint="route('juzgados.search')" placeholder="Buscar despacho..." label-key="nombre" />
                                        <InputError :message="form.errors.juzgado_id" />
                                    </div>

                                    <div>
                                        <InputLabel value="Tipo de Garantía *" />
                                        <select v-model="form.tipo_garantia_asociada" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option value="codeudor">Codeudor</option>
                                            <option value="hipotecaria">Hipotecaria</option>
                                            <option value="prendaria">Prendaria</option>
                                            <option value="sin garantía">Sin garantía</option>
                                        </select>
                                        <InputError :message="form.errors.tipo_garantia_asociada" />
                                    </div>
                                    <div>
                                        <InputLabel value="Origen Documental *" />
                                        <select v-model="form.origen_documental" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option value="pagaré">Pagaré</option>
                                            <option value="libranza">Libranza</option>
                                            <option value="contrato">Contrato</option>
                                            <option value="otro">Otro</option>
                                        </select>
                                        <InputError :message="form.errors.origen_documental" />
                                    </div>
                                    <div>
                                        <InputLabel value="Medio de Contacto" />
                                        <select v-model="form.medio_contacto" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option :value="null">-- Seleccione --</option>
                                            <option value="email">Email</option>
                                            <option value="whatsapp">WhatsApp</option>
                                            <option value="telefono">Teléfono</option>
                                        </select>
                                        <InputError :message="form.errors.medio_contacto" />
                                    </div>
                                    <div class="lg:col-span-3">
                                        <InputLabel value="URL Carpeta Drive (Opcional)" />
                                        <TextInput v-model="form.link_drive" type="url" class="mt-1 block w-full" placeholder="https://drive.google.com/..." />
                                        <InputError :message="form.errors.link_drive" />
                                    </div>
                                </div>
                            </section>

                            <!-- SECCIÓN 3: CODEUDORES -->
                            <section>
                                <div class="flex justify-between items-center border-b dark:border-gray-700 pb-2 mb-6">
                                    <h3 class="text-lg font-bold">Codeudores (Opcional)</h3>
                                    <SecondaryButton type="button" @click="addCodeudor" class="text-xs">
                                        + Añadir Codeudor
                                    </SecondaryButton>
                                </div>

                                <div v-if="form.codeudores.length === 0" class="text-center py-8 text-gray-500 bg-gray-50/30 rounded-lg border-2 border-dashed dark:border-gray-700">
                                    No se han añadido codeudores a este caso.
                                </div>

                                <div class="space-y-6">
                                    <div v-for="(codeudor, index) in form.codeudores" :key="index" class="p-6 border dark:border-gray-700 rounded-lg bg-gray-50/20 relative">
                                        <button type="button" @click="removeCodeudor(index)" class="absolute top-4 right-4 text-red-500 hover:text-red-700">
                                            <TrashIcon class="h-5 w-5" />
                                        </button>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div class="md:col-span-1">
                                                <InputLabel :for="'co_nombre_' + index" value="Nombre Completo *" />
                                                <TextInput :id="'co_nombre_' + index" v-model="codeudor.nombre_completo" class="mt-1 block w-full" placeholder="Nombre completo" required />
                                                <InputError :message="form.errors[`codeudores.${index}.nombre_completo`]" />
                                            </div>
                                            <div>
                                                <InputLabel :for="'co_tipo_doc_' + index" value="Tipo Documento" />
                                                <select :id="'co_tipo_doc_' + index" v-model="codeudor.tipo_documento" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm">
                                                    <option value="CC">Cédula de Ciudadanía</option>
                                                    <option value="NIT">NIT</option>
                                                    <option value="CE">Cédula de Extranjería</option>
                                                </select>
                                            </div>
                                            <div>
                                                <InputLabel :for="'co_doc_' + index" value="Número Documento *" />
                                                <TextInput :id="'co_doc_' + index" v-model="codeudor.numero_documento" class="mt-1 block w-full" placeholder="Documento" required />
                                                <InputError :message="form.errors[`codeudores.${index}.numero_documento`]" />
                                            </div>
                                            <div>
                                                <InputLabel :for="'co_celular_' + index" value="Celular" />
                                                <TextInput :id="'co_celular_' + index" v-model="codeudor.celular" class="mt-1 block w-full" placeholder="Ej: 3001234567" />
                                            </div>
                                            <div class="md:col-span-2">
                                                <InputLabel :for="'co_email_' + index" value="Correo Electrónico" />
                                                <TextInput :id="'co_email_' + index" v-model="codeudor.correo" type="email" class="mt-1 block w-full" placeholder="correo@ejemplo.com" />
                                            </div>
                                        </div>

                                        <!-- --- DETALLE ADICIONAL: DIRECCIONES Y REDES --- -->
                                        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-8 border-t dark:border-gray-700 pt-6">
                                            <!-- Direcciones -->
                                            <div class="space-y-4">
                                                <div class="flex justify-between items-center">
                                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Direcciones</h4>
                                                    <button type="button" @click="addAddress(index)" class="text-[10px] bg-indigo-50 text-indigo-600 px-2 py-1 rounded font-bold hover:bg-indigo-100 transition">
                                                        + Añadir Dirección
                                                    </button>
                                                </div>
                                                <div v-for="(addr, aIdx) in codeudor.addresses" :key="aIdx" class="grid grid-cols-3 gap-2 items-end bg-white dark:bg-gray-800 p-2 rounded border dark:border-gray-700 shadow-sm relative group">
                                                    <div class="col-span-1">
                                                        <InputLabel value="Etiqueta" class="!text-[10px]" />
                                                        <TextInput v-model="addr.label" placeholder="Ej: Oficina" class="!text-xs w-full" />
                                                    </div>
                                                    <div class="col-span-2">
                                                        <InputLabel value="Dirección y Ciudad" class="!text-[10px]" />
                                                        <TextInput v-model="addr.address" placeholder="Calle... Medellín" class="!text-xs w-full" />
                                                    </div>
                                                    <button type="button" @click="removeAddress(index, aIdx)" class="absolute -top-2 -right-2 bg-red-500 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition">
                                                        <TrashIcon class="h-3 w-3" />
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Redes Sociales -->
                                            <div class="space-y-4">
                                                <div class="flex justify-between items-center">
                                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Redes / Otros Enlaces</h4>
                                                    <button type="button" @click="codeudor.social_links.push({ label: 'Facebook', url: '' })" class="text-[10px] bg-blue-50 text-blue-600 px-2 py-1 rounded font-bold hover:bg-blue-100 transition">
                                                        + Añadir Enlace
                                                    </button>
                                                </div>
                                                <div v-for="(link, sIdx) in codeudor.social_links" :key="sIdx" class="grid grid-cols-3 gap-2 items-end bg-white dark:bg-gray-800 p-2 rounded border dark:border-gray-700 shadow-sm relative group">
                                                    <div class="col-span-1">
                                                        <TextInput v-model="link.label" placeholder="Red" class="!text-xs w-full" />
                                                    </div>
                                                    <div class="col-span-2">
                                                        <TextInput v-model="link.url" type="url" placeholder="https://..." class="!text-xs w-full" />
                                                    </div>
                                                    <button type="button" @click="codeudor.social_links.splice(sIdx, 1)" class="absolute -top-2 -right-2 bg-red-500 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition">
                                                        <TrashIcon class="h-3 w-3" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <div class="flex items-center justify-end mt-8 border-t dark:border-gray-700 pt-6">
                                <Link :href="route('casos.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:underline mr-4">Cancelar</Link>
                                <PrimaryButton class="bg-blue-500 hover:bg-blue-600" :disabled="form.processing">Guardar Nuevo Caso</PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
