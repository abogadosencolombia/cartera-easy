<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    cooperativa: { type: Object, required: true },
    can: { type: Object, required: true },
});

const page = usePage();

// --- Lógica para la Modal de Subida de Documentos ---
const confirmingDocumentUpload = ref(false);
const docForm = useForm({
    tipo_documento: 'Poder',
    fecha_expedicion: '',
    fecha_vencimiento: '',
    archivo: null,
});

const openUploadModal = () => {
    confirmingDocumentUpload.value = true;
};

const closeUploadModal = () => {
    confirmingDocumentUpload.value = false;
    docForm.reset();
};

const submitDocument = () => {
    docForm.post(route('cooperativas.documentos.store', props.cooperativa.id), {
        preserveScroll: true,
        onSuccess: () => closeUploadModal(),
    });
};

// --- Lógica para la Modal de Confirmación de Borrado ---
const confirmingDocumentDeletion = ref(false);
const documentToDelete = ref(null);

const confirmDocumentDeletion = (documento) => {
    documentToDelete.value = documento;
    confirmingDocumentDeletion.value = true;
};

const closeDeleteModal = () => {
    confirmingDocumentDeletion.value = false;
    documentToDelete.value = null;
};

const deleteDocument = () => {
    useForm({}).delete(route('documentos.destroy', documentToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};

// --- Funciones de formato y lógica de semáforo ---
const formatDate = (dateString) => {
    if (!dateString) return 'No especificada';
    const date = new Date(dateString);
    const userTimezoneOffset = date.getTimezoneOffset() * 60000;
    const correctedDate = new Date(date.getTime() + userTimezoneOffset);
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return correctedDate.toLocaleDateString('es-CO', options);
};

const usaLibranzaTexto = computed(() => props.cooperativa.usa_libranza ? 'Sí' : 'No');
const requiereCartaInstruccionesTexto = computed(() => props.cooperativa.requiere_carta_instrucciones ? 'Sí' : 'No');
const estadoActivoTexto = computed(() => props.cooperativa.estado_activo ? 'Activa' : 'Inactiva');

const semaforoGeneral = computed(() => {
    const documentos = props.cooperativa.documentos;
    if (!documentos || documentos.length === 0) {
        return { text: 'Sin Documentos', color: 'gray', textColor: 'gray' };
    }
    if (documentos.some(d => d.status === 'vencido')) {
        return { text: 'Documentación Vencida', color: 'red', textColor: 'red' };
    }
    if (documentos.some(d => d.status === 'por_vencer')) {
        return { text: 'Documentación por Vencer', color: 'yellow', textColor: 'yellow' };
    }
    return { text: 'Documentación al Día', color: 'green', textColor: 'green' };
});

const statusColors = {
    vigente: 'bg-green-500',
    por_vencer: 'bg-yellow-500',
    vencido: 'bg-red-500',
};
</script>

<template>
    <Head :title="'Ficha de ' + cooperativa.nombre" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Ficha de la Cooperativa
                </h2>
                <div class="flex items-center space-x-4">
                    <Link
                        v-if="can.update"
                        :href="route('cooperativas.edit', cooperativa.id)"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                    >
                        Editar Cooperativa
                    </Link>
                    <Link :href="route('cooperativas.index')" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition">
                        &larr; Volver al Listado
                    </Link>
                </div>
            </div>
        </template>
        
        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <div v-if="page.props.flash.success" class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md shadow-md" role="alert">
                    <p class="font-bold">¡Éxito!</p>
                    <p>{{ page.props.flash.success }}</p>
                </div>

                <!-- Panel de Información de la Cooperativa -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 md:p-8 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between items-start">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ cooperativa.nombre }}</h1>
                                <p class="mt-1 text-md text-indigo-600 dark:text-indigo-400 font-semibold">NIT: {{ cooperativa.NIT }}</p>
                            </div>
                            <span :class="`bg-${semaforoGeneral.color}-100 text-${semaforoGeneral.color}-800 text-sm font-medium me-2 px-3 py-1.5 rounded-full dark:bg-${semaforoGeneral.color}-900 dark:text-${semaforoGeneral.color}-300`">
                                {{ semaforoGeneral.text }}
                            </span>
                        </div>
                    </div>
                    <div class="p-6 md:p-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12">
                            <div class="flex flex-col gap-y-8">
                                <div>
                                    <h3 class="text-sm font-bold uppercase text-gray-500 dark:text-gray-400 tracking-wider">Información General</h3>
                                    <dl class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">Vigilancia</dt><dd class="text-gray-900 dark:text-white text-right">{{ cooperativa.tipo_vigilancia }}</dd></div>
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">Constitución</dt><dd class="text-gray-900 dark:text-white text-right">{{ formatDate(cooperativa.fecha_constitucion) }}</dd></div>
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">Matrícula</dt><dd class="text-gray-900 dark:text-white text-right">{{ cooperativa.numero_matricula_mercantil || 'N/A' }}</dd></div>
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">Estado</dt><dd class="font-bold text-right" :class="cooperativa.estado_activo ? 'text-green-500' : 'text-red-500'">{{ estadoActivoTexto }}</dd></div>
                                    </dl>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold uppercase text-gray-500 dark:text-gray-400 tracking-wider">Políticas de Cobranza</h3>
                                    <dl class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">¿Usa Libranza?</dt><dd class="text-gray-900 dark:text-white text-right">{{ usaLibranzaTexto }}</dd></div>
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">¿Requiere Carta?</dt><dd class="text-gray-900 dark:text-white text-right">{{ requiereCartaInstruccionesTexto }}</dd></div>
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">Garantía Frecuente</dt><dd class="text-gray-900 dark:text-white text-right">{{ cooperativa.tipo_garantia_frecuente }}</dd></div>
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">Tasa de Usura</dt><dd class="text-gray-900 dark:text-white text-right">{{ cooperativa.tasa_maxima_moratoria }}</dd></div>
                                    </dl>
                                </div>
                            </div>
                            <div class="flex flex-col gap-y-8 mt-8 md:mt-0">
                                <div>
                                    <h3 class="text-sm font-bold uppercase text-gray-500 dark:text-gray-400 tracking-wider">Representante Legal</h3>
                                    <dl class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">Nombre</dt><dd class="text-gray-900 dark:text-white text-right">{{ cooperativa.representante_legal_nombre }}</dd></div>
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">Cédula</dt><dd class="text-gray-900 dark:text-white text-right">{{ cooperativa.representante_legal_cedula }}</dd></div>
                                    </dl>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold uppercase text-gray-500 dark:text-gray-400 tracking-wider">Contacto y Notificaciones</h3>
                                    <dl class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">Nombre Contacto</dt><dd class="text-gray-900 dark:text-white text-right">{{ cooperativa.contacto_nombre }}</dd></div>
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">Teléfono</dt><dd class="text-gray-900 dark:text-white text-right">{{ cooperativa.contacto_telefono }}</dd></div>
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">Email Contacto</dt><dd class="text-gray-900 dark:text-white text-right">{{ cooperativa.contacto_correo }}</dd></div>
                                        <div class="py-3 flex justify-between text-sm font-medium"><dt class="text-gray-500 dark:text-gray-400">Email Judicial</dt><dd class="text-gray-900 dark:text-white text-right">{{ cooperativa.correo_notificaciones_judiciales }}</dd></div>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel de Documentos Legales -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 md:p-8">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Documentos Legales</h3>
                            <button @click="openUploadModal" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Subir Documento
                            </button>
                        </div>
                        <div v-if="!cooperativa.documentos || cooperativa.documentos.length === 0" class="text-center py-10 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                            <p class="text-gray-500 dark:text-gray-400 text-lg">No hay documentos para esta cooperativa.</p>
                            <p class="text-gray-400 dark:text-gray-500 mt-2">¡Sube el primero para empezar a gestionar!</p>
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="w-12 px-6 py-3"></th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tipo de Documento</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fecha de Expedición</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fecha de Vencimiento</th>
                                        <th scope="col" class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="documento in cooperativa.documentos" :key="documento.id" class="hover:bg-gray-100 dark:hover:bg-gray-900 transition-colors duration-200">
                                        <td class="px-6 py-4">
                                            <span class="h-3 w-3 rounded-full block" :class="statusColors[documento.status]" :title="documento.status"></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ documento.tipo_documento }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(documento.fecha_expedicion) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(documento.fecha_vencimiento) || 'No vence' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <!-- LA LÍNEA CORREGIDA: Cambiamos <Link> por <a> -->
                                            <a :href="route('documentos-legales.show', documento.id)" target="_blank" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-4">Ver</a>
                                            <button @click="confirmDocumentDeletion(documento)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Eliminar</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Subida -->
        <Modal :show="confirmingDocumentUpload" @close="closeUploadModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Subir Nuevo Documento Legal</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Complete la información y seleccione el archivo a subir (PDF, JPG, PNG - Máx 128MB).</p>
                <form @submit.prevent="submitDocument" class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="tipo_documento" value="Tipo de Documento" />
                        <select id="tipo_documento" v-model="docForm.tipo_documento" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                            <option>Poder</option>
                            <option>Certificado Existencia</option>
                            <option>Carta Autorización</option>
                            <option>Protocolo Interno</option>
                        </select>
                        <InputError :message="docForm.errors.tipo_documento" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="fecha_expedicion" value="Fecha de Expedición" />
                        <TextInput id="fecha_expedicion" type="date" class="mt-1 block w-full" v-model="docForm.fecha_expedicion" required />
                        <InputError :message="docForm.errors.fecha_expedicion" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="fecha_vencimiento" value="Fecha de Vencimiento (Opcional)" />
                        <TextInput id="fecha_vencimiento" type="date" class="mt-1 block w-full" v-model="docForm.fecha_vencimiento" />
                        <InputError :message="docForm.errors.fecha_vencimiento" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="archivo" value="Archivo" />
                        <input id="archivo" type="file" @input="docForm.archivo = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                        <progress v-if="docForm.progress" :value="docForm.progress.percentage" max="100" class="w-full mt-2">{{ docForm.progress.percentage }}%</progress>
                        <InputError :message="docForm.errors.archivo" class="mt-2" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeUploadModal"> Cancelar </SecondaryButton>
                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': docForm.processing }" :disabled="docForm.processing">Guardar Documento</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal de Confirmación de Borrado -->
        <Modal :show="confirmingDocumentDeletion" @close="closeDeleteModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    ¿Estás seguro de que quieres eliminar este documento?
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" v-if="documentToDelete">
                    Estás a punto de eliminar permanentemente el documento: <span class="font-medium">{{ documentToDelete.tipo_documento }}</span>. Esta acción no se puede deshacer.
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeDeleteModal"> Cancelar </SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteDocument" :class="{ 'opacity-25': useForm({}).processing }" :disabled="useForm({}).processing">
                        Sí, Eliminar Documento
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

