<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
// --- INICIO: CAMBIOS ---
import SecondaryButton from '@/Components/SecondaryButton.vue'; // Importado para el botón "Eliminar"
import Textarea from '@/Components/Textarea.vue'; // Importado para los campos JSON
import { TrashIcon } from '@heroicons/vue/24/outline'; // Importado para eliminar
// --- FIN: CAMBIOS ---
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import axios from 'axios';

// --- DEFINICIÓN DE PROPS ---
const props = defineProps({
    casoAClonar: { type: Object, default: null },
    estructuraProcesal: { type: Array, default: () => [] }, // <-- Esto ahora debe venir con L4
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

// --- TRANSFORMADORES DE DATOS DE API (Sin cambios) ---
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

// --- CONFIGURACIÓN DE SELECTORES (Sin cambios) ---
const { options: cooperativasOptions, onSearch: searchCooperativas, message: cooperativasMessage } = useDebouncedSearch('cooperativas.search', mapGeneric);
const { options: abogadosOptions, onSearch: searchAbogados, message: abogadosMessage } = useDebouncedSearch('users.search', mapUser);
const { options: juzgadosOptions, onSearch: searchJuzgados, message: juzgadosMessage } = useDebouncedSearch('juzgados.search', mapGeneric);
const { options: personasOptions, onSearch: searchPersonas, message: personasMessage } = useDebouncedSearch('personas.search', mapPersonas);

// --- INICIALIZACIÓN DEL FORMULARIO (ACTUALIZADO) ---

// --- INICIO: CAMBIO (Helper para parsear JSON de codeudores al clonar) ---
const safeParseJson = (jsonString) => {
  if (!jsonString) return [];
  try {
    const parsed = JSON.parse(jsonString);
    // Asegurarse de que el resultado sea siempre un array
    return Array.isArray(parsed) ? parsed : [];
  } catch (e) {
    console.error("Error al parsear JSON de codeudor:", e, jsonString);
    return [];
  }
};
// --- FIN: CAMBIO ---

const form = useForm({
    cooperativa_id: props.casoAClonar?.cooperativa_id || null,
    user_id: props.casoAClonar?.user_id || null,
    deudor_id: props.casoAClonar?.deudor_id || null,
    // --- INICIO: CAMBIO (MODIFICADO PARA CLONAR CODEUDORES) ---
    // Ahora poblamos los codeudores desde la prop 'casoAClonar' si existe
    codeudores: props.casoAClonar?.codeudores?.map(c => ({
        ...c, // Copia todos los campos (nombre_completo, numero_documento, etc.)
        // Parsea los campos JSON a arrays nativos que el formulario espera
        addresses: safeParseJson(c.addresses), 
        social_links: safeParseJson(c.social_links)
    })) || [], // Si no hay casoAClonar o no hay codeudores, inicia como array vacío
    radicado: props.casoAClonar?.radicado || '', // <-- AÑADIDO
    // --- FIN: CAMBIO ---
    juzgado_id: props.casoAClonar?.juzgado_id || null,
    referencia_credito: props.casoAClonar?.referencia_credito || '',
    especialidad_id: props.casoAClonar?.especialidad_id || null,
    tipo_proceso: props.casoAClonar?.tipo_proceso || null,
    subtipo_proceso: props.casoAClonar?.subtipo_proceso || null,
    // --- INICIO: CAMBIO L4 ---
    subproceso: props.casoAClonar?.subproceso || null, // <-- AÑADIDO (L4)
    // --- FIN: CAMBIO L4 ---
    etapa_procesal: props.casoAClonar?.etapa_procesal || null,
    estado_proceso: 'prejurídico',
    tipo_garantia_asociada: props.casoAClonar?.tipo_garantia_asociada || 'codeudor',
    fecha_apertura: new Date().toISOString().slice(0, 10),
    fecha_vencimiento: props.casoAClonar?.fecha_vencimiento || '',
    monto_total: props.casoAClonar?.monto_total?.toString() || '0',
    tasa_interes_corriente: props.casoAClonar?.tasa_interes_corriente?.toString() || '0',
    // --- INICIO: CAMBIO TASA ---
    // tasa_moratoria: props.casoAClonar?.tasa_moratoria?.toString() || '0', // <-- ELIMINADO
    fecha_tasa_interes: props.casoAClonar?.fecha_tasa_interes || '', // <-- AÑADIDO
    // --- FIN: CAMBIO TASA ---
    origen_documental: props.casoAClonar?.origen_documental || 'pagaré',
    medio_contacto: props.casoAClonar?.medio_contacto || null, // <-- AÑADIDO
    clonado_de_id: props.casoAClonar?.id || null,
});

// --- INICIO: CAMBIO (Lógica de Codeudores Dinámicos Anti-JSON) ---
const nuevoCodeudorTemplate = () => ({
    nombre_completo: '',
    tipo_documento: 'CC',
    numero_documento: '',
    celular: '',
    correo: '',
    addresses: [], // Inicializar como array nativo
    social_links: [] // Inicializar como array nativo
});

const addCodeudor = () => {
    form.codeudores.push(nuevoCodeudorTemplate());
};

const removeCodeudor = (index) => {
    form.codeudores.splice(index, 1);
};

// --- Nuevos helpers para Direcciones y Redes Sociales ---
const newAddressTemplate = () => ({ label: 'Casa', address: '', city: '' });
const newSocialTemplate = () => ({ type: 'Facebook', url: '' });

const addAddress = (codeudorIndex) => {
    form.codeudores[codeudorIndex].addresses.push(newAddressTemplate());
};
const removeAddress = (codeudorIndex, addrIndex) => {
    form.codeudores[codeudorIndex].addresses.splice(addrIndex, 1);
};
const addSocial = (codeudorIndex) => {
    form.codeudores[codeudorIndex].social_links.push(newSocialTemplate());
};
const removeSocial = (codeudorIndex, socialIndex) => {
    form.codeudores[codeudorIndex].social_links.splice(socialIndex, 1);
};
// --- FIN: CAMBIO ---

// --- LÓGICA PARA SELECTORES EN CASCADA (ACTUALIZADO) ---
const formatLabel = (text) => {
    if (!text) return '';
    return text.replace(/_/g, ' ')
        .toLowerCase()
        .replace(/\b\w/g, char => char.toUpperCase());
};

const especialidades = computed(() => props.estructuraProcesal);

const tiposDisponibles = ref([]);
watch(() => form.especialidad_id, (newId, oldId) => {
    if (oldId !== undefined && newId !== oldId) {
        form.tipo_proceso = null;
        form.subtipo_proceso = null;
        // --- INICIO: CAMBIO L4 ---
        form.subproceso = null; // <-- RESET L4
        // --- FIN: CAMBIO L4 ---
    }
    tiposDisponibles.value = [];
    if (newId) {
        const esp = especialidades.value.find(e => e.id === newId);
        tiposDisponibles.value = esp ? esp.tipos_proceso : [];
    }
}, { immediate: true });

const subtiposDisponibles = ref([]);
watch(() => form.tipo_proceso, (newNombre, oldNombre) => {
    if (oldNombre !== undefined && newNombre !== oldNombre) {
        form.subtipo_proceso = null;
        // --- INICIO: CAMBIO L4 ---
        form.subproceso = null; // <-- RESET L4
        // --- FIN: CAMBIO L4 ---
    }
    subtiposDisponibles.value = [];
    if (newNombre) {
        if (tiposDisponibles.value && tiposDisponibles.value.length > 0) {
            const tipo = tiposDisponibles.value.find(t => t.nombre === newNombre);
            subtiposDisponibles.value = tipo ? tipo.subtipos : [];
        } else if (form.especialidad_id && especialidades.value.length > 0) {
            const currentEsp = especialidades.value.find(e => e.id === form.especialidad_id);
            if (currentEsp && currentEsp.tipos_proceso) {
                tiposDisponibles.value = currentEsp.tipos_proceso;
                const tipo = tiposDisponibles.value.find(t => t.nombre === newNombre);
                subtiposDisponibles.value = tipo ? tipo.subtipos : [];
            }
        }
    }
}, { immediate: true });

// --- INICIO: CAMBIO L4 (NUEVO WATCHER PARA L4) ---
const subprocesosDisponibles = ref([]);
watch(() => form.subtipo_proceso, (newNombre, oldNombre) => {
    // 1. Resetear L4 si L3 cambia
    if (oldNombre !== undefined && newNombre !== oldNombre) {
        form.subproceso = null;
    }
    subprocesosDisponibles.value = [];

    if (newNombre) {
        // 2. Lógica A: (Caso normal) Encontrar L4 usando L3
        if (subtiposDisponibles.value && subtiposDisponibles.value.length > 0) {
            const subtipo = subtiposDisponibles.value.find(s => s.nombre === newNombre);
            subprocesosDisponibles.value = subtipo ? subtipo.subprocesos : [];
        } 
        // 3. Lógica B: (Caso Clonación) Re-hidratar L3 y L2 para encontrar L4
        else if (form.tipo_proceso && tiposDisponibles.value.length > 0) {
            const currentTipo = tiposDisponibles.value.find(t => t.nombre === form.tipo_proceso);
            if (currentTipo && currentTipo.subtipos) {
                subtiposDisponibles.value = currentTipo.subtipos; // Re-poblar L3
                const subtipo = subtiposDisponibles.value.find(s => s.nombre === newNombre); // Encontrar L3 de nuevo
                subprocesosDisponibles.value = subtipo ? subtipo.subprocesos : []; // Poblar L4
            }
        }
        // 4. Lógica C: (Caso Clonación Extremo) Re-hidratar L1, L2, L3 para encontrar L4
        else if (form.especialidad_id && especialidades.value.length > 0) {
             const currentEsp = especialidades.value.find(e => e.id === form.especialidad_id);
             if (currentEsp && currentEsp.tipos_proceso) {
                tiposDisponibles.value = currentEsp.tipos_proceso; // Re-poblar L2
                const currentTipo = tiposDisponibles.value.find(t => t.nombre === form.tipo_proceso);
                if (currentTipo && currentTipo.subtipos) {
                    subtiposDisponibles.value = currentTipo.subtipos; // Re-poblar L3
                    const subtipo = subtiposDisponibles.value.find(s => s.nombre === newNombre); // Encontrar L3 de nuevo
                    subprocesosDisponibles.value = subtipo ? subtipo.subprocesos : []; // Poblar L4
                }
             }
        }
    }
}, { immediate: true });
// --- FIN: CAMBIO L4 ---


// --- VARIABLES DE AYUDA PARA V-SELECT (ACTUALIZADO) ---
const selectedCooperativa = ref(null);
const selectedAbogado = ref(null);
const selectedDeudor = ref(null);
const selectedJuzgado = ref(null);

// --- SINCRONIZACIÓN DE V-SELECT (ACTUALIZADO) ---
watch(selectedCooperativa, (newValue) => form.cooperativa_id = newValue?.id ?? null);
watch(selectedAbogado, (newValue) => form.user_id = newValue?.id ?? null);
watch(selectedDeudor, (newValue) => form.deudor_id = newValue?.id ?? null);
watch(selectedJuzgado, (newValue) => form.juzgado_id = newValue?.id ?? null);

// --- LÓGICA PARA CLONAR UN CASO (ACTUALIZADO) ---
if (props.casoAClonar) {
    // --- INICIO: CAMBIO (Estas líneas ahora funcionarán gracias al fix del controller) ---
    if (props.casoAClonar.cooperativa) {
        selectedCooperativa.value = mapGeneric({ id: props.casoAClonar.cooperativa.id, label: props.casoAClonar.cooperativa.nombre });
        cooperativasOptions.value = [selectedCooperativa.value];
    }
    if (props.casoAClonar.user) {
        selectedAbogado.value = mapUser({ id: props.casoAClonar.user.id, label: props.casoAClonar.user.name });
        abogadosOptions.value = [selectedAbogado.value];
    }
    // --- FIN: CAMBIO ---

    if (props.casoAClonar.juzgado) {
        selectedJuzgado.value = mapGeneric({ id: props.casoAClonar.juzgado.id, label: props.casoAClonar.juzgado.nombre });
        juzgadosOptions.value = [selectedJuzgado.value];
    }

    const initialPersonas = [];
    // --- INICIO: CAMBIO (Esta línea ahora funcionará) ---
    if (props.casoAClonar.deudor) {
        selectedDeudor.value = mapPersonas(props.casoAClonar.deudor);
        initialPersonas.push(selectedDeudor.value);
    }
    // --- FIN: CAMBIO ---
    
    // --- INICIO: CAMBIO (Comentario actualizado) ---
    // NOTA: Los codeudores se clonan directamente en la inicialización del 'form' (ver arriba).
    // Aquí solo nos aseguramos que el deudor esté en la lista de opciones de 'personas'.
    // --- FIN: CAMBIO ---
    personasOptions.value = [...new Map(initialPersonas.filter(Boolean).map(item => [item.id, item])).values()];
}

// --- PROPIEDADES COMPUTADAS (Sin cambios) ---
const pageTitle = computed(() => props.casoAClonar ? 'Clonando Caso' : 'Registrar Nuevo Caso');
const headerTitle = computed(() => props.casoAClonar ? `Clonando Caso #${props.casoAClonar.id}` : 'Registrar Nuevo Caso');
const submitButtonText = computed(() => props.casoAClonar ? 'Crear Copia del Caso' : 'Guardar Nuevo Caso');

// --- ENVÍO DEL FORMULARIO (ACTUALIZADO) ---
const submit = () => {
    // Transformamos los arrays nativos a JSON string ANTES de enviar
    // El nuevo campo 'form.subproceso' (L4) se envía automáticamente
    form.transform(data => ({
        ...data,
        codeudores: data.codeudores.map(c => ({
            ...c,
            addresses: JSON.stringify(c.addresses || []),
            social_links: JSON.stringify(c.social_links || [])
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
            <!-- Max-w-7xl para más espacio -->
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">
                        <form @submit.prevent="submit" class="space-y-8">

                            <section>
                                <h3 class="text-lg font-medium border-b dark:border-gray-700 pb-2 mb-6">Partes Involucradas</h3>
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

                                </div>
                            </section>

                            <!-- --- INICIO: CAMBIO (Nueva Sección Codeudores Anti-JSON) --- -->
                            <section>
                                <div class="flex justify-between items-center border-b dark:border-gray-700 pb-2 mb-6">
                                    <h3 class="text-lg font-medium">Codeudores (Opcional)</h3>
                                    <PrimaryButton type="button" @click="addCodeudor">
                                        Añadir Codeudor
                                    </PrimaryButton>
                                </div>

                                <div v-if="form.codeudores.length === 0" class="text-center text-gray-500 dark:text-gray-400 py-6 border border-dashed dark:border-gray-700 rounded-lg">
                                    No se han añadido codeudores.
                                </div>

                                <!-- Formulario dinámico para Codeudores -->
                                <div v-else class="space-y-6">
                                    <div v-for="(codeudor, codeudorIndex) in form.codeudores" :key="codeudorIndex" class="p-5 border dark:border-gray-700 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-800/50 relative">
                                        
                                        <!-- ***** INICIO: BOTÓN ELIMINAR MINIMALISTA ***** -->
                                        <div class="absolute top-4 right-4">
                                            <SecondaryButton type="button" @click="removeCodeudor(codeudorIndex)" class="!p-2 text-red-500 hover:bg-red-50 dark:hover:bg-gray-700">
                                                <span class="sr-only">Eliminar Codeudor</span>
                                                <TrashIcon class="h-5 w-5" />
                                            </SecondaryButton>
                                        </div>
                                        
                                        <h4 class="font-medium text-lg text-gray-800 dark:text-gray-200 mb-4">Datos del Codeudor {{ codeudorIndex + 1 }}</h4>
                                        <!-- ***** FIN: BOTÓN ELIMINAR MINIMALISTA ***** -->

                                        <!-- Campos Principales del Codeudor -->
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
                                                        <option>CC</option>
                                                        <option>NIT</option>
                                                        <option>CE</option>
                                                        <option>PAS</option>
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

                                        <!-- Formulario Dinámico para Direcciones -->
                                        <div class="mt-6 pt-4 border-t dark:border-gray-700">
                                            <div class="flex justify-between items-center mb-3">
                                                <h5 class="font-medium text-gray-700 dark:text-gray-300">Direcciones</h5>
                                                <SecondaryButton type="button" @click="addAddress(codeudorIndex)" class="!text-xs !py-1">Añadir Dirección</SecondaryButton>
                                            </div>
                                            <div v-if="!codeudor.addresses || codeudor.addresses.length === 0" class="text-xs text-center text-gray-500 dark:text-gray-400 py-2">Sin direcciones</div>
                                            <div v-else class="space-y-3">
                                                <div v-for="(address, addrIndex) in codeudor.addresses" :key="addrIndex" class="grid grid-cols-1 md:grid-cols-8 gap-3 items-end">
                                                    <div class="md:col-span-2">
                                                        <InputLabel :for="`addr_label_${codeudorIndex}_${addrIndex}`" class="!text-xs">Etiqueta (Ej. Casa)</InputLabel>
                                                        <TextInput v-model="address.label" :id="`addr_label_${codeudorIndex}_${addrIndex}`" type="text" class="mt-1 block w-full" />
                                                    </div>
                                                    <div class="md:col-span-3">
                                                        <InputLabel :for="`addr_addr_${codeudorIndex}_${addrIndex}`" class="!text-xs">Dirección</InputLabel>
                                                        <TextInput v-model="address.address" :id="`addr_addr_${codeudorIndex}_${addrIndex}`" type="text" class="mt-1 block w-full" />
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <InputLabel :for="`addr_city_${codeudorIndex}_${addrIndex}`" class="!text-xs">Ciudad</InputLabel>
                                                        <TextInput v-model="address.city" :id="`addr_city_${codeudorIndex}_${addrIndex}`" type="text" class="mt-1 block w-full" />
                                                    </div>
                                                    <div class="md:col-span-1 text-right">
                                                        <SecondaryButton type="button" @click="removeAddress(codeudorIndex, addrIndex)" class="!p-2 text-red-500 hover:bg-red-50 dark:hover:bg-gray-700">
                                                            <span class="sr-only">Eliminar</span>
                                                            <TrashIcon class="h-4 w-4" />
                                                        </SecondaryButton>
                                                    </div>
                                                </div>
                                            </div>
                                            <InputError :message="form.errors['codeudores.' + codeudorIndex + '.addresses']" class="mt-2" />
                                        </div>

                                        <!-- Formulario Dinámico para Redes Sociales -->
                                        <div class="mt-6 pt-4 border-t dark:border-gray-700">
                                            <div class="flex justify-between items-center mb-3">
                                                <h5 class="font-medium text-gray-700 dark:text-gray-300">Redes Sociales</h5>
                                                <SecondaryButton type="button" @click="addSocial(codeudorIndex)" class="!text-xs !py-1">Añadir Red Social</SecondaryButton>
                                            </div>
                                            <div v-if="!codeudor.social_links || codeudor.social_links.length === 0" class="text-xs text-center text-gray-500 dark:text-gray-400 py-2">Sin redes sociales</div>
                                            <div v-else class="space-y-3">
                                                <div v-for="(social, socialIndex) in codeudor.social_links" :key="socialIndex" class="grid grid-cols-1 md:grid-cols-8 gap-3 items-end">
                                                    <div class="md:col-span-3">
                                                        <InputLabel :for="`social_type_${codeudorIndex}_${socialIndex}`" class="!text-xs">Tipo (Ej. Facebook)</InputLabel>
                                                        <TextInput v-model="social.type" :id="`social_type_${codeudorIndex}_${socialIndex}`" type="text" class="mt-1 block w-full" />
                                                    </div>
                                                    <div class="md:col-span-4">
                                                        <InputLabel :for="`social_url_${codeudorIndex}_${socialIndex}`" class="!text-xs">URL / Usuario</InputLabel>
                                                        <TextInput v-model="social.url" :id="`social_url_${codeudorIndex}_${socialIndex}`" type="text" class="mt-1 block w-full" />
                                                    </div>
                                                    <div class="md:col-span-1 text-right">
                                                        <SecondaryButton type="button" @click="removeSocial(codeudorIndex, socialIndex)" class="!p-2 text-red-500 hover:bg-red-50 dark:hover:bg-gray-700">
                                                            <span class="sr-only">Eliminar</span>
                                                            <TrashIcon class="h-4 w-4" />
                                                        </SecondaryButton>
                                                    </div>
                                                </div>
                                            </div>
                                            <InputError :message="form.errors['codeudores.' + codeudorIndex + '.social_links']" class="mt-2" />
                                        </div>
                                    </div>
                                </div>
                            </section>
                            <!-- --- FIN: CAMBIO --- -->

                            <section>
                                <h3 class="text-lg font-medium border-b dark:border-gray-700 pb-2 mb-6">Información del Crédito y Proceso</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div>
                                        <InputLabel for="referencia_credito" value="Numero De Pagare" />
                                        <TextInput v-model="form.referencia_credito" id="referencia_credito" type="text" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.referencia_credito" class="mt-2" />
                                    </div>

                                    <!-- --- INICIO: CAMBIO (Añadido Radicado) --- -->
                                    <div>
                                        <InputLabel for="radicado" value="Número de Radicado" />
                                        <TextInput v-model="form.radicado" id="radicado" type="text" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.radicado" class="mt-2" />
                                    </div>
                                    <!-- --- FIN: CAMBIO --- -->

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
                                    
                                    <!-- --- INICIO: CAMBIO TASA --- -->
                                    <div>
                                        <InputLabel for="fecha_tasa_interes" value="Fecha Tasa Interés" />
                                        <TextInput v-model="form.fecha_tasa_interes" id="fecha_tasa_interes" type="date" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.fecha_tasa_interes" class="mt-2" />
                                    </div>
                                    <!-- --- FIN: CAMBIO TASA --- -->
                                    
                                    <div>
                                        <InputLabel for="fecha_apertura" value="Fecha de Demanda" />
                                        <TextInput v-model="form.fecha_apertura" id="fecha_apertura" type="date" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.fecha_apertura" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="fecha_vencimiento" value="Fecha de Vencimiento" />
                                        <TextInput v-model="form.fecha_vencimiento" id="fecha_vencimiento" type="date" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.fecha_vencimiento" class="mt-2" />
                                    </div>

                                    <!-- ===== INICIO: SECCIÓN DE PROCESO ACTUALIZADA ===== -->
                                    <div>
                                        <InputLabel for="especialidad" value="Especialidad *" />
                                        <select v-model="form.especialidad_id" id="especialidad" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                            <option :value="null" disabled>Seleccione una especialidad...</option>
                                            <option v-for="esp in especialidades" :key="esp.id" :value="esp.id">
                                                {{ formatLabel(esp.nombre) }}
                                            </option>
                                        </select>
                                        <InputError :message="form.errors.especialidad_id" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="tipo_proceso" value="Tipo de Proceso *" />
                                        <select v-model="form.tipo_proceso" id="tipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!form.especialidad_id" required>
                                            <option :value="null" disabled>Seleccione un tipo...</option>
                                            <option v-for="tipo in tiposDisponibles" :key="tipo.id" :value="tipo.nombre">
                                                {{ formatLabel(tipo.nombre) }}
                                            </option>
                                        </select>
                                        <InputError :message="form.errors.tipo_proceso" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="subtipo_proceso" value="Proceso *" />
                                        <select v-model="form.subtipo_proceso" id="subtipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!form.tipo_proceso" required>
                                            <option :value="null" disabled>Seleccione un proceso...</option>
                                            <option v-for="subtipo in subtiposDisponibles" :key="subtipo.id" :value="subtipo.nombre">
                                                {{ formatLabel(subtipo.nombre) }}
                                            </option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.subtipo_proceso" />
                                    </div>
                                    <!-- ===== FIN: SECCIÓN DE PROCESO ACTUALIZADA ===== -->

                                    <!-- --- INICIO: CAMBIO L4 (NUEVO DROPDOWN) --- -->
                                    <!-- Este dropdown ocupará 2 columnas en 'md' y 'lg' para alinearse con 'Etapa Procesal' -->
                                    <div class="md:col-span-2">
                                        <InputLabel for="subproceso" value="Subproceso (Detalle)" />
                                        <select v-model="form.subproceso" id="subproceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :disabled="!form.subtipo_proceso">
                                            <option :value="null">-- Sin detalle --</option>
                                            <!-- NO usamos formatLabel, los nombres ya son legibles -->
                                            <option v-for="subproceso in subprocesosDisponibles" :key="subproceso.id" :value="subproceso.nombre">
                                                {{ subproceso.nombre }}
                                            </option>
                                        </select>
                                        <!-- Añadimos el posible error para el nuevo campo -->
                                        <InputError class="mt-2" :message="form.errors.subproceso" />
                                    </div>
                                    <!-- --- FIN: CAMBIO L4 --- -->

                                    <div>
                                        <InputLabel for="etapa_procesal" value="Etapa Procesal" />
                                        <select v-model="form.etapa_procesal" id="etapa_procesal" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option :value="null">-- Sin especificar --</option>
                                            <option v-for="etapa in etapas_procesales" :key="etapa" :value="etapa">{{ formatLabel(etapa) }}</option>
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
                                            <option value="codeudor">Codeudor</option>
                                            <option value="hipotecaria">Hipotecaria</option>
                                            <option value="prendaria">Prendaria</option>
                                            <option value="sin garantía">Sin Garantía</option>
                                        </select>
                                        <InputError :message="form.errors.tipo_garantia_asociada" class="mt-2" />
                                    </div>
                                    <div class="md:col-span-1">
                                        <InputLabel for="origen_documental" value="Origen Documental *" />
                                        <select v-model="form.origen_documental" id="origen_documental" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="pagaré">Pagaré</option>
                                            <option value="libranza">Libranza</option>
                                            <option value="contrato">Contrato</option>
                                            <option valueK="otro">Otro</option>
                                        </select>
                                        <InputError :message="form.errors.origen_documental" class="mt-2" />
                                    </div>
                                    <!-- ===== CAMPO NUEVO (Medio Contacto) ===== -->
                                    <div class="lg:col-span-1">
                                        <InputLabel for="medio_contacto" value="Medio de Contacto" />
                                        <select v-model="form.medio_contacto" id="medio_contacto" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option :value="null">-- Sin especificar --</option>
                                            <option value="email">Email</option>
                                            <option value="telefono">Teléfono</option>
                                            <option value="whatsapp">WhatsApp</option>
                                            <option value="visita">Visita</option>
                                            <option value="otro">Otro</option>
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
/* Estilos para v-select (Añadidos) */
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
