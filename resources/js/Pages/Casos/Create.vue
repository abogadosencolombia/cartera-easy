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
import { TrashIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import axios from 'axios';

const props = defineProps({
    casoAClonar: { type: Object, default: null },
    estructuraProcesal: { type: Array, default: () => [] },
    etapas_procesales: { type: Array, default: () => [] },
});

// --- BÚSQUEDA ASÍNCRONA ---
const useDebouncedSearch = (routeName, mapResult = (item) => item) => {
    const options = ref([]);
    const message = ref('Escribe para buscar...');
    let searchTimeout = null;

    const onSearch = (search, loading) => {
        clearTimeout(searchTimeout);
        if (search.length < 3) {
            if (options.value.length > 0 && !search) return;
            options.value = [];
            message.value = 'Escribe al menos 3 caracteres...';
            return;
        }
        loading(true);
        message.value = 'Buscando...';

        searchTimeout = setTimeout(() => {
            try {
                const url = route(routeName, { q: search });
                axios.get(url)
                    .then(response => {
                        if (Array.isArray(response.data)) {
                            const mappedOptions = response.data.map(mapResult).filter(Boolean);
                            options.value = mappedOptions;
                            message.value = mappedOptions.length === 0 ? 'No se encontraron resultados.' : '';
                        } else {
                            options.value = [];
                            message.value = 'Error en la respuesta de la API.';
                        }
                    })
                    .catch(error => {
                        console.error(`Error buscando en ${routeName}:`, error);
                        options.value = [];
                        message.value = 'Ocurrió un error al buscar.';
                    })
                    .finally(() => loading(false));
            } catch (routeError) {
                console.error(`Error de Ziggy: La ruta '${routeName}' no está definida.`, routeError);
                options.value = [];
                message.value = 'Error de configuración en la ruta.';
            }
        }, 500);
    };

    return { options, onSearch, message };
};

// --- MAPPERS ---
const mapGeneric = (item) => item ? ({ id: item.id, nombre: item.label || item.nombre }) : null;
const mapUser = (item) => item ? ({ id: item.id, name: item.label || item.name }) : null;
const mapPersonas = (item) => {
    if (!item) return null;
    if (item.nombre_completo && item.numero_documento) return item;
    if (typeof item.label === 'string') {
        const match = item.label.match(/(.*) \((\S+)\)$/);
        if (match) return { id: item.id, nombre_completo: match[1].trim(), numero_documento: match[2] };
    }
    return { id: item.id, nombre_completo: item.label || item.nombre_completo || 'Dato no válido', numero_documento: 'N/A' };
};

const { options: cooperativasOptions, onSearch: searchCooperativas, message: cooperativasMessage } = useDebouncedSearch('cooperativas.search', mapGeneric);
const { options: abogadosOptions, onSearch: searchAbogados, message: abogadosMessage } = useDebouncedSearch('users.search', mapUser);
const { options: juzgadosOptions, onSearch: searchJuzgados, message: juzgadosMessage } = useDebouncedSearch('juzgados.search', mapGeneric);
const { options: personasOptions, onSearch: searchPersonas, message: personasMessage } = useDebouncedSearch('personas.search', mapPersonas);

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
    cooperativa_id: props.casoAClonar?.cooperativa_id || null,
    user_id: props.casoAClonar?.user_id || null,
    deudor_id: props.casoAClonar?.deudor_id || null,
    codeudores: props.casoAClonar?.codeudores?.map(c => ({
        ...c,
        addresses: safeParseJson(c.addresses), 
        social_links: safeParseJson(c.social_links)
    })) || [],
    
    radicado: props.casoAClonar?.radicado || '',
    juzgado_id: props.casoAClonar?.juzgado_id || null,
    referencia_credito: props.casoAClonar?.referencia_credito || '',
    
    especialidad_id: props.casoAClonar?.especialidad_id || null,
    tipo_proceso: props.casoAClonar?.tipo_proceso || null,
    subtipo_proceso: props.casoAClonar?.subtipo_proceso || null,
    subproceso: props.casoAClonar?.subproceso || null,
    
    etapa_procesal: props.casoAClonar?.etapa_procesal || null,
    // ELIMINADO: estado_proceso
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

// --- CODEUDORES ---
const nuevoCodeudorTemplate = () => ({
    nombre_completo: '',
    tipo_documento: 'CC',
    numero_documento: '',
    celular: '',
    correo: '',
    addresses: [],
    social_links: []
});

const addCodeudor = () => form.codeudores.push(nuevoCodeudorTemplate());
const removeCodeudor = (index) => form.codeudores.splice(index, 1);

const newAddressTemplate = () => ({ label: 'Casa', address: '', city: '' });
const newSocialTemplate = () => ({ type: 'Facebook', url: '' });

const addAddress = (codeudorIndex) => form.codeudores[codeudorIndex].addresses.push(newAddressTemplate());
const removeAddress = (codeudorIndex, addrIndex) => form.codeudores[codeudorIndex].addresses.splice(addrIndex, 1);
const addSocial = (codeudorIndex) => form.codeudores[codeudorIndex].social_links.push(newSocialTemplate());
const removeSocial = (codeudorIndex, socialIndex) => form.codeudores[codeudorIndex].social_links.splice(socialIndex, 1);

// --- CASCADA PROCESOS ---
const formatLabel = (text) => {
    if (!text) return '';
    return text.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, char => char.toUpperCase());
};

const especialidades = computed(() => props.estructuraProcesal);
const tiposDisponibles = ref([]);
const subtiposDisponibles = ref([]);
const subprocesosDisponibles = ref([]);

watch(() => form.especialidad_id, (newId, oldId) => {
    if (oldId !== undefined && newId !== oldId) {
        form.tipo_proceso = null;
        form.subtipo_proceso = null;
        form.subproceso = null;
    }
    tiposDisponibles.value = [];
    if (newId) {
        const esp = especialidades.value.find(e => e.id === newId);
        tiposDisponibles.value = esp ? esp.tipos_proceso : [];
    }
}, { immediate: true });

watch(() => form.tipo_proceso, (newNombre, oldNombre) => {
    if (oldNombre !== undefined && newNombre !== oldNombre) {
        form.subtipo_proceso = null;
        form.subproceso = null;
    }
    subtiposDisponibles.value = [];
    if (newNombre && tiposDisponibles.value.length > 0) {
        const tipo = tiposDisponibles.value.find(t => t.nombre === newNombre);
        subtiposDisponibles.value = tipo ? tipo.subtipos : [];
    }
}, { immediate: true });

watch(() => form.subtipo_proceso, (newNombre, oldNombre) => {
    if (oldNombre !== undefined && newNombre !== oldNombre) {
        form.subproceso = null;
    }
    subprocesosDisponibles.value = [];
    if (newNombre) {
        if (subtiposDisponibles.value && subtiposDisponibles.value.length > 0) {
            const subtipo = subtiposDisponibles.value.find(s => s.nombre === newNombre);
            subprocesosDisponibles.value = subtipo ? subtipo.subprocesos : [];
        } else if (form.tipo_proceso && tiposDisponibles.value.length > 0) {
             const currentTipo = tiposDisponibles.value.find(t => t.nombre === form.tipo_proceso);
             if (currentTipo && currentTipo.subtipos) {
                subtiposDisponibles.value = currentTipo.subtipos;
                const subtipo = subtiposDisponibles.value.find(s => s.nombre === newNombre);
                subprocesosDisponibles.value = subtipo ? subtipo.subprocesos : [];
             }
        }
    }
}, { immediate: true });

// --- V-SELECT SYNC ---
const selectedCooperativa = ref(null);
const selectedAbogado = ref(null);
const selectedDeudor = ref(null);
const selectedJuzgado = ref(null);

watch(selectedCooperativa, (newValue) => form.cooperativa_id = newValue?.id ?? null);
watch(selectedAbogado, (newValue) => form.user_id = newValue?.id ?? null);
watch(selectedDeudor, (newValue) => form.deudor_id = newValue?.id ?? null);
watch(selectedJuzgado, (newValue) => form.juzgado_id = newValue?.id ?? null);

if (props.casoAClonar) {
    if (props.casoAClonar.cooperativa) {
        selectedCooperativa.value = mapGeneric({ id: props.casoAClonar.cooperativa.id, label: props.casoAClonar.cooperativa.nombre });
        cooperativasOptions.value = [selectedCooperativa.value];
    }
    if (props.casoAClonar.user) {
        selectedAbogado.value = mapUser({ id: props.casoAClonar.user.id, label: props.casoAClonar.user.name });
        abogadosOptions.value = [selectedAbogado.value];
    }
    if (props.casoAClonar.juzgado) {
        selectedJuzgado.value = mapGeneric({ id: props.casoAClonar.juzgado.id, label: props.casoAClonar.juzgado.nombre });
        juzgadosOptions.value = [selectedJuzgado.value];
    }
    const initialPersonas = [];
    if (props.casoAClonar.deudor) {
        selectedDeudor.value = mapPersonas(props.casoAClonar.deudor);
        initialPersonas.push(selectedDeudor.value);
    }
    personasOptions.value = [...new Map(initialPersonas.filter(Boolean).map(item => [item.id, item])).values()];
}

const pageTitle = computed(() => props.casoAClonar ? 'Clonando Caso' : 'Registrar Nuevo Caso');
const headerTitle = computed(() => props.casoAClonar ? `Clonando Caso #${props.casoAClonar.id}` : 'Registrar Nuevo Caso');
const submitButtonText = computed(() => props.casoAClonar ? 'Crear Copia del Caso' : 'Guardar Nuevo Caso');

const submit = () => {
    form.transform(data => ({
        ...data,
        codeudores: data.codeudores.map(c => ({
            ...c,
            addresses: c.addresses || [],
            social_links: c.social_links || []
        }))
    })).post(route('casos.store'));
};
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ headerTitle }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit" class="space-y-8">

                            <section>
                                <h3 class="text-lg font-bold border-b dark:border-gray-700 pb-2 mb-6">Partes Involucradas</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="cooperativa" value="Cooperativa *" />
                                        <v-select id="cooperativa" v-model="selectedCooperativa" :options="cooperativasOptions" label="nombre" placeholder="Escribe para buscar..." @search="searchCooperativas" :filterable="false" :appendToBody="true" class="mt-1 block w-full vue-select-style">
                                            <template #no-options>{{ cooperativasMessage }}</template>
                                        </v-select>
                                        <InputError :message="form.errors.cooperativa_id" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="abogado" value="Abogado/Gestor a Cargo *" />
                                        <v-select id="abogado" v-model="selectedAbogado" :options="abogadosOptions" label="name" placeholder="Escribe para buscar..." @search="searchAbogados" :filterable="false" :appendToBody="true" class="mt-1 block w-full vue-select-style">
                                            <template #no-options>{{ abogadosMessage }}</template>
                                        </v-select>
                                        <InputError :message="form.errors.user_id" class="mt-2" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <InputLabel for="deudor" value="Deudor Principal *" />
                                        <v-select id="deudor" v-model="selectedDeudor" :options="personasOptions" label="nombre_completo" placeholder="Escribe para buscar..." @search="searchPersonas" :filterable="false" :appendToBody="true" class="mt-1 block w-full vue-select-style">
                                            <template #option="{ nombre_completo, numero_documento }">
                                                <span>{{ nombre_completo }} ({{ numero_documento }})</span>
                                            </template>
                                            <template #no-options>{{ personasMessage }}</template>
                                        </v-select>
                                        <InputError :message="form.errors.deudor_id" class="mt-2" />
                                    </div>
                                </div>
                            </section>

                            <section>
                                <div class="flex justify-between items-center border-b dark:border-gray-700 pb-2 mb-6">
                                    <h3 class="text-lg font-medium">Codeudores (Opcional)</h3>
                                    <PrimaryButton type="button" @click="addCodeudor">Añadir Codeudor</PrimaryButton>
                                </div>
                                <div v-if="form.codeudores.length === 0" class="text-center text-gray-500 dark:text-gray-400 py-6 border border-dashed dark:border-gray-700 rounded-lg">
                                    No se han añadido codeudores.
                                </div>
                                <div v-else class="space-y-6">
                                    <div v-for="(codeudor, codeudorIndex) in form.codeudores" :key="codeudorIndex" class="p-5 border dark:border-gray-700 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-800/50 relative">
                                        <div class="absolute top-4 right-4">
                                            <SecondaryButton type="button" @click="removeCodeudor(codeudorIndex)" class="!p-2 text-red-500 hover:bg-red-50 dark:hover:bg-gray-700">
                                                <span class="sr-only">Eliminar Codeudor</span>
                                                <TrashIcon class="h-5 w-5" />
                                            </SecondaryButton>
                                        </div>
                                        <h4 class="font-medium text-lg text-gray-800 dark:text-gray-200 mb-4">Datos del Codeudor {{ codeudorIndex + 1 }}</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <InputLabel :for="'codeudor_nombre_' + codeudorIndex" value="Nombre Completo *" />
                                                <TextInput v-model="codeudor.nombre_completo" :id="'codeudor_nombre_' + codeudorIndex" type="text" class="mt-1 block w-full" required />
                                                <InputError :message="form.errors['codeudores.' + codeudorIndex + '.nombre_completo']" class="mt-2" />
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <InputLabel :for="'codeudor_tipo_doc_' + codeudorIndex" value="Tipo Doc." />
                                                    <select v-model="codeudor.tipo_documento" :id="'codeudor_tipo_doc_' + codeudorIndex" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <option>CC</option><option>NIT</option><option>CE</option><option>PAS</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <InputLabel :for="'codeudor_num_doc_' + codeudorIndex" value="Nº Documento *" />
                                                    <TextInput v-model="codeudor.numero_documento" :id="'codeudor_num_doc_' + codeudorIndex" type="text" class="mt-1 block w-full" required />
                                                    <InputError :message="form.errors['codeudores.' + codeudorIndex + '.numero_documento']" class="mt-2" />
                                                </div>
                                            </div>
                                            <div>
                                                <InputLabel :for="'codeudor_celular_' + codeudorIndex" value="Celular" />
                                                <TextInput v-model="codeudor.celular" :id="'codeudor_celular_' + codeudorIndex" type="text" class="mt-1 block w-full" />
                                                <InputError :message="form.errors['codeudores.' + codeudorIndex + '.celular']" class="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel :for="'codeudor_correo_' + codeudorIndex" value="Correo Electrónico" />
                                                <TextInput v-model="codeudor.correo" :id="'codeudor_correo_' + codeudorIndex" type="email" class="mt-1 block w-full" />
                                                <InputError :message="form.errors['codeudores.' + codeudorIndex + '.correo']" class="mt-2" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h3 class="text-lg font-bold border-b dark:border-gray-700 pb-2 mb-6">Información del Crédito y Proceso</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div>
                                        <InputLabel for="referencia_credito" value="Numero De Pagare" />
                                        <TextInput v-model="form.referencia_credito" id="referencia_credito" type="text" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.referencia_credito" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="radicado" value="Número de Radicado" />
                                        <TextInput v-model="form.radicado" id="radicado" type="text" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.radicado" class="mt-2" />
                                    </div>

                                    <!-- FINANCIERO -->
                                    <div>
                                        <InputLabel for="monto_total" value="Monto de Crédito (Inicial) *" />
                                        <TextInput v-model="form.monto_total" id="monto_total" type="number" step="0.01" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.monto_total" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="monto_deuda_actual" value="Monto Deuda Actual" />
                                        <TextInput v-model="form.monto_deuda_actual" id="monto_deuda_actual" type="number" step="0.01" class="mt-1 block w-full" placeholder="Dejar en 0 si es igual al monto del crédito" />
                                        <InputError :message="form.errors.monto_deuda_actual" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="monto_total_pagado" value="Monto Total Pagado" />
                                        <TextInput v-model="form.monto_total_pagado" id="monto_total_pagado" type="number" step="0.01" class="mt-1 block w-full" placeholder="Pagos realizados antes de registrar" />
                                        <InputError :message="form.errors.monto_total_pagado" class="mt-2" />
                                    </div>
                                    
                                    <!-- FECHAS (SIN RESTRICCIONES) -->
                                    <div>
                                        <InputLabel for="fecha_inicio_credito" value="Fecha Inicio del Crédito" />
                                        <DatePicker v-model="form.fecha_inicio_credito" id="fecha_inicio_credito" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.fecha_inicio_credito" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="fecha_apertura" value="Fecha de Demanda" />
                                        <DatePicker v-model="form.fecha_apertura" id="fecha_apertura" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.fecha_apertura" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="fecha_vencimiento" value="Fecha de Vencimiento" />
                                        <DatePicker v-model="form.fecha_vencimiento" id="fecha_vencimiento" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.fecha_vencimiento" class="mt-2" />
                                    </div>

                                    <!-- CAMPO NUEVO: LINK DRIVE -->
                                    <div class="lg:col-span-3">
                                        <InputLabel for="link_drive" value="URL Carpeta Drive (Opcional)" />
                                        <div class="relative mt-1 rounded-md shadow-sm">
                                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                                <span class="text-gray-500 sm:text-sm">https://</span>
                                            </div>
                                            <TextInput v-model="form.link_drive" id="link_drive" type="url" class="block w-full pl-16" placeholder="drive.google.com/..." />
                                        </div>
                                        <InputError :message="form.errors.link_drive" class="mt-2" />
                                    </div>

                                    <!-- SELECTORES CASCADA -->
                                    <div>
                                        <InputLabel for="especialidad" value="Especialidad *" />
                                        <select v-model="form.especialidad_id" id="especialidad" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                            <option :value="null" disabled>Seleccione...</option>
                                            <option v-for="esp in especialidades" :key="esp.id" :value="esp.id">{{ formatLabel(esp.nombre) }}</option>
                                        </select>
                                        <InputError :message="form.errors.especialidad_id" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="tipo_proceso" value="Tipo de Proceso *" />
                                        <select v-model="form.tipo_proceso" id="tipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!form.especialidad_id" required>
                                            <option :value="null" disabled>Seleccione...</option>
                                            <option v-for="tipo in tiposDisponibles" :key="tipo.id" :value="tipo.nombre">{{ formatLabel(tipo.nombre) }}</option>
                                        </select>
                                        <InputError :message="form.errors.tipo_proceso" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="subtipo_proceso" value="Proceso *" />
                                        <select v-model="form.subtipo_proceso" id="subtipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!form.tipo_proceso" required>
                                            <option :value="null" disabled>Seleccione...</option>
                                            <option v-for="subtipo in subtiposDisponibles" :key="subtipo.id" :value="subtipo.nombre">{{ formatLabel(subtipo.nombre) }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.subtipo_proceso" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <InputLabel for="subproceso" value="Subproceso (Detalle)" />
                                        <select v-model="form.subproceso" id="subproceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!form.subtipo_proceso || subprocesosDisponibles.length === 0">
                                            <option :value="null">-- Sin detalle --</option>
                                            <option v-for="subproceso in subprocesosDisponibles" :key="subproceso.id" :value="subproceso.nombre">{{ subproceso.nombre }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.subproceso" />
                                    </div>

                                    <div>
                                        <InputLabel for="etapa_procesal" value="Etapa Procesal" />
                                        <Dropdown align="left" width="full">
                                            <template #trigger>
                                                <button type="button" class="mt-1 flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 bg-white px-3 py-2 text-sm shadow-sm hover:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all cursor-pointer dark:text-gray-300">
                                                    <span>{{ form.etapa_procesal ? formatLabel(form.etapa_procesal) : '-- Sin especificar --' }}</span>
                                                    <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                                                </button>
                                            </template>
                                            <template #content>
                                                <div class="py-1 bg-white dark:bg-gray-800 max-h-60 overflow-y-auto">
                                                    <button @click="form.etapa_procesal = null" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': form.etapa_procesal === null }">
                                                        -- Sin especificar --
                                                    </button>
                                                    <button v-for="etapa in etapas_procesales" :key="etapa" @click="form.etapa_procesal = etapa" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': form.etapa_procesal === etapa }">
                                                        {{ formatLabel(etapa) }}
                                                    </button>
                                                </div>
                                            </template>
                                        </Dropdown>
                                        <InputError class="mt-2" :message="form.errors.etapa_procesal" />
                                    </div>
                                    <div class="lg:col-span-3">
                                        <InputLabel for="juzgado" value="Juzgado" />
                                        <v-select id="juzgado" v-model="selectedJuzgado" :options="juzgadosOptions" label="nombre" placeholder="Escribe para buscar un juzgado..." @search="searchJuzgados" :filterable="false" :appendToBody="true" class="mt-1 block w-full vue-select-style">
                                            <template #no-options>{{ juzgadosMessage }}</template>
                                        </v-select>
                                        <InputError class="mt-2" :message="form.errors.juzgado_id" />
                                    </div>
                                    <div>
                                        <InputLabel for="tipo_garantia_asociada" value="Tipo de Garantía *" />
                                        <select v-model="form.tipo_garantia_asociada" id="tipo_garantia_asociada" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option :value="null">-- Sin especificar --</option>
                                            <option value="codeudor">Codeudor</option>
                                            <option value="hipotecaria">Hipotecaria</option>
                                            <option value="prendaria">Prendaria</option>
                                            <option value="sin garantía">Sin Garantía</option>
                                        </select>
                                        <InputError :message="form.errors.tipo_garantia_asociada" class="mt-2" />
                                    </div>
                                    <div class="md:col-span-1">
                                        <InputLabel for="origen_documental" value="Origen Documental *" />
                                        <select v-model="form.origen_documental" id="origen_documental" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option :value="null">-- Sin especificar --</option>
                                            <option value="pagaré">Pagaré</option>
                                            <option value="libranza">Libranza</option>
                                            <option value="contrato">Contrato</option>
                                            <option value="otro">Otro</option>
                                        </select>
                                        <InputError :message="form.errors.origen_documental" class="mt-2" />
                                    </div>
                                    <div class="lg:col-span-1">
                                        <InputLabel for="medio_contacto" value="Medio de Contacto" />
                                        <select v-model="form.medio_contacto" id="medio_contacto" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option :value="null">-- Sin especificar --</option>
                                            <option value="email">Email</option><option value="telefono">Teléfono</option><option value="whatsapp">WhatsApp</option><option value="visita">Visita</option><option value="otro">Otro</option>
                                        </select>
                                        <InputError :message="form.errors.medio_contacto" class="mt-2" />
                                    </div>
                                </div>
                            </section>

                            <div class="flex items-center justify-end mt-8 border-t dark:border-gray-700 pt-6">
                                <Link :href="route('casos.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:underline mr-4">Cancelar</Link>
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    {{ submitButtonText }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.vue-select-style .vs__dropdown-toggle { border-color: #d1d5db; background-color: white; border-radius: 0.375rem; min-height: 42px; }
.dark .vue-select-style .vs__dropdown-toggle { border-color: #4b5563; background-color: #1f2937; }
.vue-select-style .vs__search, .vue-select-style .vs__selected { color: #111827; margin-top: 0; padding-left: 0.25rem; }
.dark .vue-select-style .vs__search, .dark .vue-select-style .vs__selected { color: #f3f4f6; }
.vue-select-style .vs__dropdown-menu { background-color: white; border-color: #d1d5db; z-index: 50; }
.dark .vue-select-style .vs__dropdown-menu { background-color: #1f2937; border-color: #4b5563; }
.vue-select-style .vs__option { color: #374151; }
.dark .vue-select-style .vs__option { color: #d1d5db; }
.vue-select-style .vs__option--highlight { background-color: #4f46e5; color: white; }
.vue-select-style .vs__clear, .vue-select-style .vs__open-indicator { fill: #6b7280; }
.dark .vue-select-style .vs__clear, .dark .vue-select-style .vs__open-indicator { fill: #9ca3af; }
.vue-select-style .vs__selected-options { padding-left: 0.25rem; }
</style>