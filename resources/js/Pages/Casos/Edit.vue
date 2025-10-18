<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import axios from 'axios';

// --- DEFINICIÓN DE PROPS ---
const props = defineProps({
    caso: { type: Object, required: true },
    // ===== CAMBIO REALIZADO: Se recibe la nueva estructura de datos =====
    tiposYSubtipos: { type: Array, default: () => [] },
    etapas_procesales: { type: Array, default: () => [] },
});

const user = usePage().props.auth.user;

// --- ESTADO DEL CASO (CERRADO/ACTIVO) ---
const isClosed = computed(() => props.caso.estado_proceso === 'cerrado');

// --- LÓGICA PARA EL MODAL DE CIERRE ---
const showCloseModal = ref(false);
const closeForm = useForm({ nota_cierre: '' });
const openCloseModal = () => {
  closeForm.reset();
  showCloseModal.value = true;
};
const closeTheCase = () => {
  closeForm.patch(route('casos.close', props.caso.id), {
    preserveScroll: true,
    onSuccess: () => {
      showCloseModal.value = false;
      closeForm.reset();
    },
  });
};

// --- LÓGICA PARA REABRIR CASO ---
const showReopenModal = ref(false);
const reopenForm = useForm({});
const openReopenModal = () => (showReopenModal.value = true);
const reopenTheCase = () => {
    reopenForm.patch(route('casos.reopen', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => (showReopenModal.value = false),
    });
};

// --- LÓGICA REUTILIZABLE PARA BÚSQUEDA ASÍNCRONA ---
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

// --- TRANSFORMADORES DE DATOS DE API ---
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

// --- CONFIGURACIÓN DE SELECTORES ---
const { options: cooperativasOptions, onSearch: searchCooperativas, message: cooperativasMessage } = useDebouncedSearch('cooperativas.search', mapGeneric);
const { options: abogadosOptions, onSearch: searchAbogados, message: abogadosMessage } = useDebouncedSearch('users.search', mapUser);
const { options: juzgadosOptions, onSearch: searchJuzgados, message: juzgadosMessage } = useDebouncedSearch('juzgados.search', mapGeneric);
const { options: personasOptions, onSearch: searchPersonas, message: personasMessage } = useDebouncedSearch('personas.search', mapPersonas);

// --- INICIALIZACIÓN DEL FORMULARIO ---
const form = useForm({
    _method: 'PATCH',
    cooperativa_id: props.caso.cooperativa_id,
    user_id: props.caso.user_id,
    deudor_id: props.caso.deudor_id,
    codeudor1_id: props.caso.codeudor1_id,
    codeudor2_id: props.caso.codeudor2_id,
    juzgado_id: props.caso.juzgado_id,
    referencia_credito: props.caso.referencia_credito,
    tipo_proceso: props.caso.tipo_proceso,
    estado_proceso: props.caso.estado_proceso,
    tipo_garantia_asociada: props.caso.tipo_garantia_asociada,
    fecha_apertura: props.caso.fecha_apertura,
    fecha_vencimiento: props.caso.fecha_vencimiento,
    monto_total: props.caso.monto_total,
    tasa_interes_corriente: props.caso.tasa_interes_corriente,
    tasa_moratoria: props.caso.tasa_moratoria,
    origen_documental: props.caso.origen_documental,
    subtipo_proceso: props.caso.subtipo_proceso || '',
    etapa_procesal: props.caso.etapa_procesal || '',
    etapa_actual: props.caso.etapa_actual,
    bloqueado: !!props.caso.bloqueado,
    motivo_bloqueo: props.caso.motivo_bloqueo ?? '',
    medio_contacto: props.caso.medio_contacto,
    notas_legales: props.caso.notas_legales,
});

// --- LÓGICA ESPECIAL DEL FORMULARIO DE EDICIÓN ---
const isFormDisabled = computed(() => (props.caso.bloqueado && user.tipo_usuario !== 'admin') || isClosed.value);
watch(() => form.bloqueado, v => { if (!v) form.motivo_bloqueo = ''; });

// ===== INICIO DE LÓGICA PARA SELECTORES EN CASCADA =====
const tiposProcesoOptions = computed(() => props.tiposYSubtipos.map(tipo => tipo.nombre));

const subtiposProcesoOptions = computed(() => {
    if (!form.tipo_proceso) return [];
    const tipoSeleccionado = props.tiposYSubtipos.find(t => t.nombre === form.tipo_proceso);
    return tipoSeleccionado && tipoSeleccionado.subtipos ? tipoSeleccionado.subtipos.map(s => s.nombre) : [];
});

watch(() => form.tipo_proceso, (newTipo, oldTipo) => {
    if (newTipo !== oldTipo) {
        // Verifica si el subtipo actual sigue siendo válido para el nuevo tipo
        const tipoActual = props.tiposYSubtipos.find(t => t.nombre === newTipo);
        const subtiposValidos = tipoActual && tipoActual.subtipos ? tipoActual.subtipos.map(s => s.nombre) : [];
        if (!subtiposValidos.includes(form.subtipo_proceso)) {
            form.subtipo_proceso = '';
        }
    }
});
// ===== FIN DE LÓGICA PARA SELECTORES EN CASCADA =====

// --- VARIABLES Y LÓGICA PARA V-SELECT ---
const selectedCooperativa = ref(null);
const selectedAbogado = ref(null);
const selectedDeudor = ref(null);
const selectedCodeudor1 = ref(null);
const selectedCodeudor2 = ref(null);
const selectedJuzgado = ref(null);

watch(selectedCooperativa, (newValue) => form.cooperativa_id = newValue?.id ?? null);
watch(selectedAbogado, (newValue) => form.user_id = newValue?.id ?? null);
watch(selectedDeudor, (newValue) => form.deudor_id = newValue?.id ?? null);
watch(selectedCodeudor1, (newValue) => form.codeudor1_id = newValue?.id ?? null);
watch(selectedCodeudor2, (newValue) => form.codeudor2_id = newValue?.id ?? null);
watch(selectedJuzgado, (newValue) => form.juzgado_id = newValue?.id ?? null);

const initSelectData = () => {
    if (props.caso.cooperativa) {
        selectedCooperativa.value = {id: props.caso.cooperativa.id, nombre: props.caso.cooperativa.nombre};
        cooperativasOptions.value = [selectedCooperativa.value];
    }
    if (props.caso.user) {
        selectedAbogado.value = {id: props.caso.user.id, name: props.caso.user.name};
        abogadosOptions.value = [selectedAbogado.value];
    }
    if (props.caso.juzgado) {
        selectedJuzgado.value = {id: props.caso.juzgado.id, nombre: props.caso.juzgado.nombre};
        juzgadosOptions.value = [selectedJuzgado.value];
    }

    const initialPersonas = [];
    if (props.caso.deudor) {
        selectedDeudor.value = mapPersonas(props.caso.deudor);
        initialPersonas.push(selectedDeudor.value);
    }
    if (props.caso.codeudor1) {
        selectedCodeudor1.value = mapPersonas(props.caso.codeudor1);
        initialPersonas.push(selectedCodeudor1.value);
    }
    if (props.caso.codeudor2) {
        selectedCodeudor2.value = mapPersonas(props.caso.codeudor2);
        initialPersonas.push(selectedCodeudor2.value);
    }
    personasOptions.value = [...new Map(initialPersonas.filter(Boolean).map(item => [item.id, item])).values()];
};
initSelectData();

// --- ENVÍO DEL FORMULARIO ---
const submit = () => {
    form.patch(route('casos.update', props.caso.id));
};
</script>

<template>
    <Head :title="'Editar Caso #' + caso.id" />

    <AuthenticatedLayout>
        <template #header>
             <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                            Editando Caso <span class="text-indigo-500">#{{ caso.id }}</span>
                        </h2>
                        <span v-if="caso.bloqueado" class="px-2 py-0.5 text-xs rounded bg-red-100 text-red-700 font-medium">Bloqueado</span>
                        <span class="px-2 py-0.5 text-xs rounded font-bold capitalize" :class="{
                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': !isClosed,
                            'bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-200': isClosed,
                        }">
                            {{ caso.estado_proceso }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('casos.show', caso.id)">
                        <SecondaryButton>Cancelar</SecondaryButton>
                    </Link>
                    <PrimaryButton @click="submit" :disabled="form.processing || isFormDisabled" :class="{ 'opacity-25': form.processing || isFormDisabled }">
                        Actualizar Caso
                    </PrimaryButton>
                    <DangerButton v-if="!isClosed" @click="openCloseModal">Cerrar Caso</DangerButton>
                    <PrimaryButton v-if="isClosed" @click="openReopenModal" class="!bg-blue-600 hover:!bg-blue-700 focus:!bg-blue-700 active:!bg-blue-800 focus:!ring-blue-500">
                        Reabrir Caso
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                
                <div v-if="isClosed" class="bg-yellow-50 dark:bg-gray-800 border-l-4 border-yellow-400 p-4 mb-8 rounded-r-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.21 3.03-1.742 3.03H4.42c-1.532 0-2.492-1.696-1.742-3.03l5.58-9.92zM10 13a1 1 0 110-2 1 1 0 010 2zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Este caso está cerrado.</p>
                            <div v-if="caso.nota_cierre" class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                <p class="font-semibold">Nota de cierre:</p>
                                <p class="whitespace-pre-wrap">{{ caso.nota_cierre }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit" class="space-y-8">
                            <fieldset :disabled="isFormDisabled" class="space-y-8">
                                <section>
                                    <h3 class="text-lg font-medium border-b pb-2 mb-6">Partes Involucradas</h3>
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
                                        <div>
                                            <InputLabel for="codeudor1" value="Codeudor 1 (Opcional)" />
                                            <v-select id="codeudor1" v-model="selectedCodeudor1" :options="personasOptions" label="nombre_completo" placeholder="Escribe para buscar..." @search="searchPersonas" :filterable="false" :appendToBody="true" class="mt-1 block w-full vue-select-style">
                                               <template #option="{ nombre_completo, numero_documento }">
                                                    <span>{{ nombre_completo }} ({{ numero_documento }})</span>
                                                </template>
                                                <template #no-options>{{ personasMessage }}</template>
                                            </v-select>
                                            <InputError :message="form.errors.codeudor1_id" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel for="codeudor2" value="Codeudor 2 (Opcional)" />
                                            <v-select id="codeudor2" v-model="selectedCodeudor2" :options="personasOptions" label="nombre_completo" placeholder="Escribe para buscar..." @search="searchPersonas" :filterable="false" :appendToBody="true" class="mt-1 block w-full vue-select-style">
                                                <template #option="{ nombre_completo, numero_documento }">
                                                    <span>{{ nombre_completo }} ({{ numero_documento }})</span>
                                                </template>
                                                <template #no-options>{{ personasMessage }}</template>
                                            </v-select>
                                            <InputError :message="form.errors.codeudor2_id" class="mt-2" />
                                        </div>
                                    </div>
                                </section>

                                <section>
                                    <h3 class="text-lg font-medium border-b pb-2 mb-6">Información del Crédito y Proceso</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <div>
                                            <InputLabel for="referencia_credito" value="Referencia del Crédito" />
                                            <TextInput v-model="form.referencia_credito" id="referencia_credito" type="text" class="mt-1 block w-full" />
                                            <InputError :message="form.errors.referencia_credito" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel for="monto_total" value="Monto Total *" />
                                            <TextInput v-model="form.monto_total" id="monto_total" type="number" step="0.01" class="mt-1 block w-full" />
                                            <InputError :message="form.errors.monto_total" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel for="tasa_interes_corriente" value="Tasa Interés Corriente (%)" />
                                            <TextInput v-model="form.tasa_interes_corriente" id="tasa_interes_corriente" type="number" step="0.01" class="mt-1 block w-full" />
                                            <InputError :message="form.errors.tasa_interes_corriente" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel for="tasa_moratoria" value="Tasa Moratoria (%)" />
                                            <TextInput v-model="form.tasa_moratoria" id="tasa_moratoria" type="number" step="0.01" class="mt-1 block w-full" />
                                            <InputError :message="form.errors.tasa_moratoria" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel for="fecha_apertura" value="Fecha de Apertura *" />
                                            <TextInput v-model="form.fecha_apertura" id="fecha_apertura" type="date" class="mt-1 block w-full" />
                                            <InputError :message="form.errors.fecha_apertura" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel for="fecha_vencimiento" value="Fecha de Vencimiento" />
                                            <TextInput v-model="form.fecha_vencimiento" id="fecha_vencimiento" type="date" class="mt-1 block w-full" />
                                            <InputError :message="form.errors.fecha_vencimiento" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel for="tipo_proceso" value="Tipo de Proceso *" />
                                            <select v-model="form.tipo_proceso" id="tipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                                <option v-for="tipo in tiposProcesoOptions" :key="tipo" :value="tipo">{{ tipo }}</option>
                                            </select>
                                            <InputError :message="form.errors.tipo_proceso" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel for="subtipo_proceso" value="Subtipo de Proceso" />
                                            <select v-model="form.subtipo_proceso" id="subtipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm" :disabled="!subtiposProcesoOptions.length">
                                                <option value="">-- Seleccione un subtipo --</option>
                                                <option v-for="subtipo in subtiposProcesoOptions" :key="subtipo" :value="subtipo">{{ subtipo }}</option>
                                            </select>
                                            <InputError class="mt-2" :message="form.errors.subtipo_proceso" />
                                        </div>
                                        <div>
                                            <InputLabel for="etapa_procesal" value="Etapa Procesal" />
                                            <select v-model="form.etapa_procesal" id="etapa_procesal" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                                <option value="">-- Sin especificar --</option>
                                                <option v-for="etapa in etapas_procesales" :key="etapa" :value="etapa">{{ etapa }}</option>
                                            </select>
                                            <InputError class="mt-2" :message="form.errors.etapa_procesal" />
                                        </div>
                                        <div class="lg:col-span-3">
                                            <InputLabel for="juzgado" value="Juzgado" />
                                            <v-select id="juzgado" v-model="selectedJuzgado" :options="juzgadosOptions" label="nombre" placeholder="Escribe para buscar un juzgado..." @search="searchJuzgados" :filterable="false" :appendToBody="true" class="mt-1 block w-full vue-select-style">
                                                <template #no-options>{{ juzgadosMessage }}</template>
                                            </v-select>
                                            <InputError class="mt-2" :message="form.errors.juzgado_id" />
                                        </div>
                                    </div>
                                </section>
                            </fieldset>

                            <section>
                                <h3 class="text-lg font-medium border-b pb-2 mb-6">Estado y Control del Caso</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="estado_proceso" value="Estado del Proceso *" />
                                        <select v-model="form.estado_proceso" id="estado_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm" :disabled="isFormDisabled">
                                            <option value="prejurídico">Prejurídico</option>
                                            <option value="demandado">Demandado</option>
                                            <option value="en ejecución">En Ejecución</option>
                                            <option value="sentencia">Sentencia</option>
                                            <option value="cerrado">Cerrado</option>
                                        </select>
                                        <InputError :message="form.errors.estado_proceso" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="etapa_actual" value="Etapa Actual del Proceso" />
                                        <TextInput v-model="form.etapa_actual" id="etapa_actual" type="text" class="mt-1 block w-full" :disabled="isFormDisabled" />
                                        <InputError :message="form.errors.etapa_actual" class="mt-2" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <InputLabel for="notas_legales" value="Notas Legales / Internas" />
                                        <Textarea v-model="form.notas_legales" id="notas_legales" class="mt-1 block w-full" rows="4" :disabled="isFormDisabled" />
                                        <InputError :message="form.errors.notas_legales" class="mt-2" />
                                    </div>
                                    <div class="md:col-span-2 rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-900/40">
                                        <div class="flex items-start gap-3">
                                            <Checkbox id="bloqueado" v-model:checked="form.bloqueado" :disabled="user.tipo_usuario !== 'admin' || isClosed" />
                                            <div class="grow">
                                                <InputLabel for="bloqueado" value="Caso bloqueado" />
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    Cuando está bloqueado solo un administrador puede modificar el caso.
                                                </p>
                                                <InputError :message="form.errors.bloqueado" class="mt-2" />
                                            </div>
                                        </div>
                                        <div v-if="form.bloqueado" class="mt-4">
                                            <InputLabel for="motivo_bloqueo" value="Motivo del bloqueo *" />
                                            <Textarea id="motivo_bloqueo" v-model.trim="form.motivo_bloqueo" class="mt-1 block w-full resize-y min-h-[96px]" rows="3" maxlength="1000" placeholder="Describe la razón del bloqueo…" :disabled="user.tipo_usuario !== 'admin' || isClosed"/>
                                            <InputError :message="form.errors.motivo_bloqueo" class="mt-2" />
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <div v-if="!isFormDisabled" class="flex items-center justify-end mt-8 border-t pt-6">
                                <Link :href="route('casos.show', caso.id)" class="text-sm text-gray-600 hover:underline mr-4">Cancelar</Link>
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Actualizar Caso
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <Modal :show="showCloseModal" @close="showCloseModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Cerrar Caso</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Al cerrar el caso, su estado cambiará a "Cerrado" y se deshabilitará la edición. Por favor, añade una nota de cierre.
                </p>
                <div class="mt-6">
                    <InputLabel for="nota_cierre" value="Nota de Cierre *" required />
                    <Textarea v-model="closeForm.nota_cierre" id="nota_cierre" class="mt-1 block w-full" rows="4" />
                    <InputError :message="closeForm.errors.nota_cierre" class="mt-2" />
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showCloseModal = false">Cancelar</SecondaryButton>
                    <DangerButton @click="closeTheCase" :disabled="closeForm.processing" :class="{ 'opacity-25': closeForm.processing }">
                        {{ closeForm.processing ? 'Cerrando...' : 'Confirmar Cierre' }}
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showReopenModal" @close="showReopenModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Reabrir Caso</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    ¿Estás seguro de que quieres reabrir este caso? El estado volverá a "prejurídico" y la nota de cierre será eliminada permanentemente.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showReopenModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton @click="reopenTheCase" :disabled="reopenForm.processing" :class="{ 'opacity-25': reopenForm.processing }" class="!bg-blue-600 hover:!bg-blue-700 focus:!bg-blue-700 active:!bg-blue-800 focus:!ring-blue-500">
                        {{ reopenForm.processing ? 'Reabriendo...' : 'Sí, Reabrir' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style>
.vue-select-style .vs__dropdown-toggle {
    border-color: #d1d5db;
    background-color: white;
    border-radius: 0.375rem;
}
.dark .vue-select-style .vs__dropdown-toggle {
    border-color: #4b5563;
    background-color: #1f2937;
}
.vue-select-style .vs__search, .vue-select-style .vs__selected {
    color: #111827;
}
.dark .vue-select-style .vs__search, .dark .vue-select-style .vs__selected {
    color: #d1d5db;
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
    color: #9ca3af;
}
.vue-select-style .vs__option--highlight {
    background-color: #6366f1;
    color: white;
}
</style>