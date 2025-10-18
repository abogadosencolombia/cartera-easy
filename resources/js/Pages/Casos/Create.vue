<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import axios from 'axios';

// --- DEFINICIÓN DE PROPS ---
const props = defineProps({
    casoAClonar: { type: Object, default: null },
    // ===== Se recibe una estructura anidada de datos =====
    tiposYSubtipos: { type: Array, default: () => [] },
    etapas_procesales: { type: Array, default: () => [] },
});

// --- LÓGICA DE BÚSQUEDA ASÍNCRONA (Sin cambios) ---
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
    cooperativa_id: props.casoAClonar?.cooperativa_id || null,
    user_id: props.casoAClonar?.user_id || null,
    deudor_id: props.casoAClonar?.deudor_id || null,
    codeudor1_id: props.casoAClonar?.codeudor1_id || null,
    codeudor2_id: props.casoAClonar?.codeudor2_id || null,
    juzgado_id: props.casoAClonar?.juzgado_id || null,
    referencia_credito: props.casoAClonar?.referencia_credito || '',
    tipo_proceso: props.casoAClonar?.tipo_proceso || (props.tiposYSubtipos.length > 0 ? props.tiposYSubtipos[0].nombre : ''),
    estado_proceso: 'prejurídico',
    tipo_garantia_asociada: props.casoAClonar?.tipo_garantia_asociada || 'codeudor',
    fecha_apertura: new Date().toISOString().slice(0, 10),
    fecha_vencimiento: props.casoAClonar?.fecha_vencimiento || '',
    monto_total: props.casoAClonar?.monto_total?.toString() || '0',
    tasa_interes_corriente: props.casoAClonar?.tasa_interes_corriente?.toString() || '0',
    tasa_moratoria: props.casoAClonar?.tasa_moratoria?.toString() || '0',
    origen_documental: props.casoAClonar?.origen_documental || 'pagaré',
    clonado_de_id: props.casoAClonar?.id || null,
    subtipo_proceso: props.casoAClonar?.subtipo_proceso || '',
    etapa_procesal: props.casoAClonar?.etapa_procesal || '',
});

// --- LÓGICA PARA SELECTORES EN CASCADA ---
const tiposProcesoOptions = computed(() => props.tiposYSubtipos.map(tipo => tipo.nombre));

const subtiposProcesoOptions = computed(() => {
    if (!form.tipo_proceso) return [];
    
    const tipoSeleccionado = props.tiposYSubtipos.find(t => t.nombre === form.tipo_proceso);
    
    return tipoSeleccionado && tipoSeleccionado.subtipos 
        ? tipoSeleccionado.subtipos.map(s => s.nombre) 
        : [];
});

watch(() => form.tipo_proceso, (newTipo, oldTipo) => {
    if (newTipo !== oldTipo) {
        form.subtipo_proceso = '';
    }
});

// --- VARIABLES DE AYUDA PARA V-SELECT ---
const selectedCooperativa = ref(null);
const selectedAbogado = ref(null);
const selectedDeudor = ref(null);
const selectedCodeudor1 = ref(null);
const selectedCodeudor2 = ref(null);
const selectedJuzgado = ref(null);

// --- SINCRONIZACIÓN DE V-SELECT ---
watch(selectedCooperativa, (newValue) => form.cooperativa_id = newValue?.id ?? null);
watch(selectedAbogado, (newValue) => form.user_id = newValue?.id ?? null);
watch(selectedDeudor, (newValue) => form.deudor_id = newValue?.id ?? null);
watch(selectedCodeudor1, (newValue) => form.codeudor1_id = newValue?.id ?? null);
watch(selectedCodeudor2, (newValue) => form.codeudor2_id = newValue?.id ?? null);
watch(selectedJuzgado, (newValue) => form.juzgado_id = newValue?.id ?? null);

// --- LÓGICA PARA CLONAR UN CASO ---
if (props.casoAClonar) {
    if (props.casoAClonar.cooperativa) {
        selectedCooperativa.value = mapGeneric({id: props.casoAClonar.cooperativa.id, label: props.casoAClonar.cooperativa.nombre});
        cooperativasOptions.value = [selectedCooperativa.value];
    }
    if (props.casoAClonar.user) {
        selectedAbogado.value = mapUser({id: props.casoAClonar.user.id, label: props.casoAClonar.user.name});
        abogadosOptions.value = [selectedAbogado.value];
    }
    if (props.casoAClonar.juzgado) {
        selectedJuzgado.value = mapGeneric({id: props.casoAClonar.juzgado.id, label: props.casoAClonar.juzgado.nombre});
        juzgadosOptions.value = [selectedJuzgado.value];
    }
    
    const initialPersonas = [];
    if (props.casoAClonar.deudor) {
        selectedDeudor.value = mapPersonas(props.casoAClonar.deudor);
        initialPersonas.push(selectedDeudor.value);
    }
    if (props.casoAClonar.codeudor1) {
        selectedCodeudor1.value = mapPersonas(props.casoAClonar.codeudor1);
        initialPersonas.push(selectedCodeudor1.value);
    }
     if (props.casoAClonar.codeudor2) {
        selectedCodeudor2.value = mapPersonas(props.casoAClonar.codeudor2);
        initialPersonas.push(selectedCodeudor2.value);
    }
    personasOptions.value = [...new Map(initialPersonas.filter(Boolean).map(item => [item.id, item])).values()];
}

// --- PROPIEDADES COMPUTADAS ---
const pageTitle = computed(() => props.casoAClonar ? 'Clonando Caso' : 'Registrar Nuevo Caso');
const headerTitle = computed(() => props.casoAClonar ? `Clonando Caso #${props.casoAClonar.id}` : 'Registrar Nuevo Caso');
const submitButtonText = computed(() => props.casoAClonar ? 'Crear Copia del Caso' : 'Guardar Nuevo Caso');

// --- ENVÍO DEL FORMULARIO ---
const submit = () => {
    form.post(route('casos.store'));
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
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit" class="space-y-8">

                            <section>
                                <h3 class="text-lg font-medium border-b pb-2 mb-6">Partes Involucradas</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="cooperativa" value="Cooperativa *" />
                                        <v-select
                                            id="cooperativa"
                                            v-model="selectedCooperativa"
                                            :options="cooperativasOptions"
                                            label="nombre"
                                            placeholder="Escribe para buscar..."
                                            @search="searchCooperativas"
                                            :filterable="false"
                                            :appendToBody="true"
                                            class="mt-1 block w-full vue-select-style"
                                        >
                                            <template #no-options>{{ cooperativasMessage }}</template>
                                        </v-select>
                                        <InputError :message="form.errors.cooperativa_id" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="abogado" value="Abogado/Gestor a Cargo *" />
                                         <v-select
                                            id="abogado"
                                            v-model="selectedAbogado"
                                            :options="abogadosOptions"
                                            label="name"
                                            placeholder="Escribe para buscar..."
                                            @search="searchAbogados"
                                            :filterable="false"
                                            :appendToBody="true"
                                            class="mt-1 block w-full vue-select-style"
                                        >
                                            <template #no-options>{{ abogadosMessage }}</template>
                                        </v-select>
                                        <InputError :message="form.errors.user_id" class="mt-2" />
                                    </div>

                                    <div class="md:col-span-2">
                                        <InputLabel for="deudor" value="Deudor Principal *" />
                                        <v-select
                                            id="deudor"
                                            v-model="selectedDeudor"
                                            :options="personasOptions"
                                            label="nombre_completo"
                                            placeholder="Escribe para buscar..."
                                            @search="searchPersonas"
                                            :filterable="false"
                                            :appendToBody="true"
                                            class="mt-1 block w-full vue-select-style"
                                        >
                                            <template #option="{ nombre_completo, numero_documento }">
                                                <span>{{ nombre_completo }} ({{ numero_documento }})</span>
                                            </template>
                                            <template #no-options>{{ personasMessage }}</template>
                                        </v-select>
                                        <InputError :message="form.errors.deudor_id" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="codeudor1" value="Codeudor 1 (Opcional)" />
                                        <v-select
                                            id="codeudor1"
                                            v-model="selectedCodeudor1"
                                            :options="personasOptions"
                                            label="nombre_completo"
                                            placeholder="Escribe para buscar..."
                                            @search="searchPersonas"
                                            :filterable="false"
                                            :appendToBody="true"
                                            class="mt-1 block w-full vue-select-style"
                                        >
                                           <template #option="{ nombre_completo, numero_documento }">
                                                <span>{{ nombre_completo }} ({{ numero_documento }})</span>
                                            </template>
                                            <template #no-options>{{ personasMessage }}</template>
                                        </v-select>
                                        <InputError :message="form.errors.codeudor1_id" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="codeudor2" value="Codeudor 2 (Opcional)" />
                                        <v-select
                                            id="codeudor2"
                                            v-model="selectedCodeudor2"
                                            :options="personasOptions"
                                            label="nombre_completo"
                                            placeholder="Escribe para buscar..."
                                            @search="searchPersonas"
                                            :filterable="false"
                                            :appendToBody="true"
                                            class="mt-1 block w-full vue-select-style"
                                        >
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
                                        <InputLabel for="tasa_interes_corriente" value="Tasa Interés Corriente (%) *" />
                                        <TextInput v-model="form.tasa_interes_corriente" id="tasa_interes_corriente" type="number" step="0.01" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.tasa_interes_corriente" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="tasa_moratoria" value="Tasa Moratoria (%) *" />
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
                                        <select v-model="form.tipo_proceso" id="tipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option v-for="tipo in tiposProcesoOptions" :key="tipo" :value="tipo">{{ tipo }}</option>
                                        </select>
                                        <InputError :message="form.errors.tipo_proceso" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="subtipo_proceso" value="Subtipo de Proceso" />
                                        <select v-model="form.subtipo_proceso" id="subtipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!subtiposProcesoOptions.length">
                                            <option value="">-- Seleccione un subtipo --</option>
                                            <option v-for="subtipo in subtiposProcesoOptions" :key="subtipo" :value="subtipo">{{ subtipo }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.subtipo_proceso" />
                                    </div>
                                    <div>
                                        <InputLabel for="etapa_procesal" value="Etapa Procesal" />
                                        <select v-model="form.etapa_procesal" id="etapa_procesal" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">-- Sin especificar --</option>
                                            <option v-for="etapa in etapas_procesales" :key="etapa" :value="etapa">{{ etapa }}</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.etapa_procesal" />
                                    </div>
                                    <div class="lg:col-span-3">
                                        <InputLabel for="juzgado" value="Juzgado" />
                                        <v-select
                                            id="juzgado"
                                            v-model="selectedJuzgado"
                                            :options="juzgadosOptions"
                                            label="nombre"
                                            placeholder="Escribe para buscar un juzgado..."
                                            @search="searchJuzgados"
                                            :filterable="false"
                                            :appendToBody="true"
                                            class="mt-1 block w-full vue-select-style"
                                        >
                                            <template #no-options>{{ juzgadosMessage }}</template>
                                        </v-select>
                                        <InputError class="mt-2" :message="form.errors.juzgado_id" />
                                    </div>
                                    <div>
                                        <InputLabel for="tipo_garantia_asociada" value="Tipo de Garantía *" />
                                        <select v-model="form.tipo_garantia_asociada" id="tipo_garantia_asociada" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option>codeudor</option><option>hipotecaria</option><option>prendaria</option><option>sin garantía</option>
                                        </select>
                                        <InputError :message="form.errors.tipo_garantia_asociada" class="mt-2" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <InputLabel for="origen_documental" value="Origen Documental *" />
                                        <select v-model="form.origen_documental" id="origen_documental" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option>pagaré</option><option>libranza</option><option>contrato</option><option>otro</option>
                                        </select>
                                        <InputError :message="form.errors.origen_documental" class="mt-2" />
                                    </div>
                                </div>
                            </section>

                            <div class="flex items-center justify-end mt-8 border-t pt-6">
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
.vue-select-style .vs__dropdown-toggle {
    border-color: #d1d5db; /* gray-300 */
    background-color: white;
    border-radius: 0.375rem; /* rounded-md */
    min-height: 42px;
}
.dark .vue-select-style .vs__dropdown-toggle {
    border-color: #4b5563; /* gray-600 */
    background-color: #1f2937; /* gray-800 */
}
.vue-select-style .vs__search, .vue-select-style .vs__selected {
    color: #111827; /* gray-900 */
    margin-top: 0;
    padding-left: 0.25rem;
}
.dark .vue-select-style .vs__search, .dark .vue-select-style .vs__selected {
    color: #f3f4f6; /* gray-100 */
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
    color: #374151; /* gray-700 */
}
.dark .vue-select-style .vs__option {
    color: #d1d5db; /* gray-300 */
}
.vue-select-style .vs__option--highlight {
    background-color: #4f46e5; /* indigo-600 */
    color: white;
}
.vue-select-style .vs__clear, .vue-select-style .vs__open-indicator {
    fill: #6b7280; /* gray-500 */
}
.dark .vue-select-style .vs__clear, .dark .vue-select-style .vs__open-indicator {
    fill: #9ca3af; /* gray-400 */
}
.vue-select-style .vs__selected-options {
    padding-left: 0.25rem;
}
</style>