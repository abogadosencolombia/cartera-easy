<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    PencilSquareIcon, ArrowLeftIcon, MapPinIcon, LinkIcon, 
    ArrowTopRightOnSquareIcon, PaperClipIcon, TrashIcon, 
    ArrowDownTrayIcon, DocumentPlusIcon, DocumentIcon,
    EyeIcon,
} from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
  persona: {
    type: Object,
    required: true,
  },
});

const isViewable = (mimeType) => {
    if (!mimeType) return false;
    const viewableTypes = [
        'application/pdf', 
        'image/jpeg', 
        'image/png', 
        'image/jpg', 
        'image/webp'
    ];
    return viewableTypes.includes(mimeType);
};

// Helper para verificar si hay datos en arrays
const hasData = (arr) => Array.isArray(arr) && arr.length > 0;

// Lógica de Información Incompleta
const missingInfo = computed(() => {
    const p = props.persona;
    const missing = [];
    if (!p.celular_1 && !p.correo_1) missing.push('Información de contacto (Celular o Correo)');
    if (!p.fecha_nacimiento) missing.push('Fecha de nacimiento');
    if (!p.fecha_expedicion) missing.push('Fecha de expedición del documento');
    if (!hasData(p.cooperativas)) missing.push('Asignación a Cooperativa/Empresa');
    if (!hasData(p.abogados)) missing.push('Asignación de Abogado/Gestor');
    return missing;
});

// Helper simple para formatear fecha
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('es-CO', { year: 'numeric', month: 'short', day: 'numeric' });
};

// Formato de tamaño de archivo
const formatBytes = (bytes, decimals = 2) => {
    if (!+bytes) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
};

// Lógica de Subida de Archivos
const formUpload = useForm({
    documento: null,
});

const fileInput = ref(null);

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileChange = (e) => {
    formUpload.documento = e.target.files[0];
    if (formUpload.documento) {
        submitUpload();
    }
};

const submitUpload = () => {
    formUpload.post(route('personas.upload_document', props.persona.id), {
        preserveScroll: true,
        onSuccess: () => {
            formUpload.reset();
            // Limpiar input file
            if (fileInput.value) fileInput.value.value = null;
        },
    });
};

const deleteDocument = (docId) => {
    if (confirm('¿Estás seguro de eliminar este documento? Esta acción no se puede deshacer.')) {
        useForm({}).delete(route('personas.delete_document', docId), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
  <Head :title="`Ver ${persona.nombre_completo}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <Link :href="route('personas.index')" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                <ArrowLeftIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
            </Link>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Detalles de la Persona
            </h2>
        </div>
        
        <Link :href="route('personas.edit', persona.id)">
            <button class="inline-flex items-center gap-2 rounded-md bg-blue-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 transition-colors">
                <PencilSquareIcon class="h-4 w-4" />
                Editar
            </button>
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">
        
        <!-- Alerta de Información Incompleta -->
        <div v-if="missingInfo.length > 0" class="rounded-md bg-amber-50 p-4 border border-amber-200 dark:bg-amber-900/20 dark:border-amber-800">
            <div class="flex">
                <div class="flex-shrink-0">
                    <ExclamationTriangleIcon class="h-5 w-5 text-amber-400" aria-hidden="true" />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-bold text-amber-800 dark:text-amber-200 uppercase tracking-wide">
                        Perfil con información pendiente
                    </h3>
                    <div class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                        <p>Para una gestión óptima, se recomienda completar:</p>
                        <ul role="list" class="list-disc pl-5 mt-1 space-y-1">
                            <li v-for="item in missingInfo" :key="item">{{ item }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- TARJETA PRINCIPAL: DATOS PERSONALES -->
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">Información Personal</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Datos básicos y de identificación.</p>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-700">
                <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                    <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nombre Completo</dt>
                        <dd class="mt-1 text-sm font-bold text-gray-900 dark:text-white sm:col-span-2 sm:mt-0 text-lg">{{ persona.nombre_completo }}</dd>
                    </div>
                    <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 bg-gray-50 dark:bg-gray-700/20">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Identificación</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 sm:col-span-2 sm:mt-0">
                            <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-900/30 dark:text-blue-400">
                                {{ persona.tipo_documento }}
                            </span>
                            <span class="ml-2 font-mono text-base">{{ persona.numero_documento }}</span>
                        </dd>
                    </div>
                    <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha Nacimiento</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 sm:col-span-2 sm:mt-0">
                            {{ persona.fecha_nacimiento || 'No registrada' }}
                        </dd>
                    </div>
                    <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 bg-gray-50 dark:bg-gray-700/20">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha Expedición</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 sm:col-span-2 sm:mt-0">
                            {{ persona.fecha_expedicion || 'No registrada' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- SEGUNDA FILA: CONTACTO Y EMPRESA -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- CONTACTO -->
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg h-full">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">Información de Contacto</h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <ul class="space-y-4">
                        <li class="flex flex-col">
                            <span class="text-xs font-medium text-gray-500 uppercase">Celulares</span>
                            <div class="mt-1 flex gap-4">
                                <span v-if="persona.celular_1" class="text-gray-900 dark:text-gray-100">{{ persona.celular_1 }}</span>
                                <span v-if="persona.celular_2" class="text-gray-500 dark:text-gray-400 border-l pl-4">{{ persona.celular_2 }}</span>
                                <span v-if="!persona.celular_1 && !persona.celular_2" class="text-gray-400 italic">Sin celulares</span>
                            </div>
                        </li>
                        <li class="flex flex-col">
                            <span class="text-xs font-medium text-gray-500 uppercase">Teléfono Fijo</span>
                            <span class="text-gray-900 dark:text-gray-100">{{ persona.telefono_fijo || 'N/A' }}</span>
                        </li>
                        <li class="flex flex-col">
                            <span class="text-xs font-medium text-gray-500 uppercase">Correos Electrónicos</span>
                            <div class="mt-1 space-y-1">
                                <div v-if="persona.correo_1">
                                    <a :href="`mailto:${persona.correo_1}`" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ persona.correo_1 }}</a>
                                </div>
                                <div v-if="persona.correo_2">
                                    <a :href="`mailto:${persona.correo_2}`" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ persona.correo_2 }}</a>
                                </div>
                                <span v-if="!persona.correo_1 && !persona.correo_2" class="text-gray-400 italic">Sin correos</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- EMPRESA Y OBSERVACIONES -->
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg h-full flex flex-col">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">Datos Laborales & Notas</h3>
                </div>
                <div class="px-4 py-5 sm:p-6 flex-1">
                    <div class="mb-6">
                        <span class="block text-xs font-medium text-gray-500 uppercase">Empresa / Cargo</span>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ persona.empresa || 'N/A' }}
                            <span v-if="persona.cargo" class="text-gray-500"> — {{ persona.cargo }}</span>
                        </p>
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-gray-500 uppercase">Observaciones</span>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line bg-gray-50 dark:bg-gray-700/30 p-3 rounded-md">
                            {{ persona.observaciones || 'Sin observaciones registradas.' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- TERCERA FILA: DIRECCIONES Y REDES -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- DIRECCIONES -->
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">Direcciones Físicas</h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <ul v-if="hasData(persona.addresses)" class="space-y-3 max-h-48 overflow-y-auto">
                        <li v-for="(addr, idx) in persona.addresses" :key="idx" class="flex items-start gap-3 p-3 rounded-md border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/20">
                            <MapPinIcon class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" />
                            <div>
                                <span class="block text-sm font-bold text-gray-800 dark:text-gray-200">{{ addr.label || 'Dirección' }}</span>
                                <span class="block text-sm text-gray-600 dark:text-gray-400">{{ addr.address }}</span>
                                <span class="block text-xs text-gray-500">{{ addr.city }}</span>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500 italic text-center py-4">No hay direcciones registradas.</p>
                </div>
            </div>

            <!-- REDES SOCIALES -->
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">Redes Sociales</h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <ul v-if="hasData(persona.social_links)" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-48 overflow-y-auto">
                        <li v-for="(link, idx) in persona.social_links" :key="idx">
                            <a :href="link.url" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 p-3 rounded-md border border-gray-200 dark:border-gray-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:border-indigo-200 transition group">
                                <LinkIcon class="w-4 h-4 text-indigo-500 group-hover:text-indigo-700" />
                                <div class="overflow-hidden">
                                    <span class="block text-sm font-bold text-gray-800 dark:text-gray-200 truncate">{{ link.label || 'Enlace' }}</span>
                                    <span class="block text-xs text-gray-500 truncate">{{ link.url }}</span>
                                </div>
                            </a>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500 italic text-center py-4">No hay redes sociales registradas.</p>
                </div>
            </div>
        </div>

        <!-- CUARTA FILA: RELACIONES (COOPERATIVAS Y ABOGADOS) -->
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30">
                <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">Relaciones y Asignaciones</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-gray-700">
                <!-- COOPERATIVAS -->
                <div class="p-6">
                    <h4 class="text-sm font-medium text-gray-500 uppercase mb-4">Cooperativas Asociadas</h4>
                    <div v-if="hasData(persona.cooperativas)" class="flex flex-wrap gap-2 max-h-32 overflow-y-auto">
                        <span v-for="coop in persona.cooperativas" :key="coop.id" class="inline-flex items-center rounded-full bg-green-50 px-3 py-1 text-sm font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-900/30 dark:text-green-400">
                            {{ coop.nombre }}
                        </span>
                    </div>
                    <p v-else class="text-sm text-gray-500 italic">No pertenece a ninguna cooperativa.</p>
                </div>

                <!-- ABOGADOS -->
                <div class="p-6">
                    <h4 class="text-sm font-medium text-gray-500 uppercase mb-4">Abogados / Gestores Asignados</h4>
                    <div v-if="hasData(persona.abogados)" class="flex flex-wrap gap-2 max-h-32 overflow-y-auto">
                        <span v-for="user in persona.abogados" :key="user.id" class="inline-flex items-center rounded-full bg-purple-50 px-3 py-1 text-sm font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10 dark:bg-purple-900/30 dark:text-purple-400">
                            {{ user.name }}
                        </span>
                    </div>
                    <p v-else class="text-sm text-gray-500 italic">No tiene abogados asignados.</p>
                </div>
            </div>
        </div>

        <!-- SECCIÓN DE DOCUMENTOS -->
        <div class="mt-6 overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-700/30">
                <div>
                    <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">Gestión Documental</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Archivos adjuntos (contratos, cédulas, soportes).</p>
                </div>
                <div>
                    <input 
                        type="file" 
                        ref="fileInput" 
                        class="hidden" 
                        @change="handleFileChange"
                    >
                    <button 
                        @click="triggerFileInput"
                        :disabled="formUpload.processing"
                        class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-white dark:ring-gray-600 dark:hover:bg-gray-600"
                    >
                        <DocumentPlusIcon v-if="!formUpload.processing" class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                        <span v-else class="animate-spin h-5 w-5 border-2 border-indigo-500 border-t-transparent rounded-full"></span>
                        {{ formUpload.processing ? 'Subiendo...' : 'Adjuntar Archivo' }}
                    </button>
                    <div v-if="formUpload.errors.documento" class="text-red-500 text-xs mt-1 text-right">
                        {{ formUpload.errors.documento }}
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-200 dark:border-gray-700">
                <ul v-if="hasData(persona.documentos)" role="list" class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="doc in persona.documentos" :key="doc.id" class="flex items-center justify-between gap-x-6 py-5 px-6 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <div class="min-w-0">
                            <div class="flex items-start gap-x-3">
                                <DocumentIcon class="h-6 w-6 text-gray-400" />
                                <div>
                                    <p class="text-sm font-semibold leading-6 text-gray-900 dark:text-white">{{ doc.nombre_original }}</p>
                                    <p class="mt-1 truncate text-xs leading-5 text-gray-500 dark:text-gray-400">
                                        {{ formatBytes(doc.size) }} · Subido por {{ doc.uploader?.name || 'Sistema' }} el {{ formatDate(doc.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-none items-center gap-x-4">
                            <a v-if="isViewable(doc.mime_type)" 
                                :href="route('personas.view_document', doc.id)" 
                                target="_blank"
                                class="rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-600 flex items-center gap-1"
                                title="Ver en nueva pestaña">
                                <EyeIcon class="h-4 w-4" />
                                Ver
                            </a>
                            <a :href="route('personas.download_document', doc.id)" class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-600 flex items-center gap-1">
                                <ArrowDownTrayIcon class="h-3 w-3" /> Descargar
                            </a>
                            <button @click="deleteDocument(doc.id)" class="rounded-full p-1 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                <TrashIcon class="h-5 w-5" />
                            </button>
                        </div>
                    </li>
                </ul>
                <p v-else class="text-sm text-gray-500 italic text-center py-8">No hay documentos adjuntos.</p>
            </div>
        </div>


        <!-- QUINTA SECCIÓN: GESTIÓN DE CASOS -->
        <div class="mt-6 overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-indigo-50 dark:bg-indigo-900/20">
                <div>
                    <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">Casos de Cobro</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Listado de casos recientes.</p>
                </div>
                <div class="flex items-center gap-3">
                    <span v-if="persona.casos_count" class="inline-flex items-center rounded-md bg-white px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-200 shadow-sm">
                        Total: {{ persona.casos_count }}
                    </span>
                    <!-- BOTÓN VER TODOS: Solo aparece si el total > casos mostrados -->
                    <Link v-if="persona.casos_count > (persona.casos?.length || 0)" 
                          :href="route('casos.index', { search: persona.numero_documento })" 
                          class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                        Ver todos
                        <ArrowTopRightOnSquareIcon class="w-4 h-4" />
                    </Link>
                </div>
            </div>
            
            <div class="border-t border-gray-200 dark:border-gray-700">
                <div v-if="hasData(persona.casos)" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-6">Nro. Caso</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Estado</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Abogado</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Fecha Creación</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                            <tr v-for="caso in persona.casos" :key="caso.id">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white sm:pl-6">
                                    {{ caso.numero_caso || ('Caso #' + caso.id) }}
                                    <div class="text-xs text-gray-500 font-normal">Rad: {{ caso.radicado || 'N/A' }}</div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="caso.estado === 'ACTIVO' ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-blue-50 text-blue-700 ring-blue-700/10'">
                                        {{ caso.estado || 'Sin Estado' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-300">
                                    {{ caso.user ? caso.user.name : 'Sin asignar' }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-300">
                                    {{ formatDate(caso.created_at) }}
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                    <a :href="route('casos.show', caso.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Ver</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-gray-500 italic text-center py-6">No hay casos recientes para esta persona.</p>
                
                <!-- Footer de la tabla si hay datos -->
                <div v-if="hasData(persona.casos)" class="bg-gray-50 dark:bg-gray-700/30 px-4 py-3 border-t border-gray-200 dark:border-gray-700 text-center">
                     <span class="text-xs text-gray-500">Mostrando los {{ persona.casos.length }} casos más recientes de {{ persona.casos_count }}.</span>
                </div>
            </div>
        </div>

        <!-- SEXTA SECCIÓN: EXPEDIENTES JUDICIALES (PROCESOS) -->
        <div class="mt-6 overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-blue-50 dark:bg-blue-900/20">
                <div>
                    <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">Expedientes Judiciales</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Seguimiento de procesos recientes.</p>
                </div>
                <div class="flex items-center gap-3">
                    <span v-if="persona.procesos_count" class="inline-flex items-center rounded-md bg-white px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-200 shadow-sm">
                        Total: {{ persona.procesos_count }}
                    </span>
                    <!-- BOTÓN VER TODOS: Redirige al buscador de procesos -->
                    <Link v-if="persona.procesos_count > (persona.procesos?.length || 0)" 
                          :href="route('procesos.index', { search: persona.numero_documento })" 
                          class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">
                        Ver todos
                        <ArrowTopRightOnSquareIcon class="w-4 h-4" />
                    </Link>
                </div>
            </div>
            
            <div class="border-t border-gray-200 dark:border-gray-700">
                <div v-if="hasData(persona.procesos)" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-6">Radicado / Asunto</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Juzgado</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Partes</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Estado</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                            <tr v-for="proceso in persona.procesos" :key="proceso.id">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white sm:pl-6">
                                    <div class="flex flex-col">
                                        <span class="font-mono text-indigo-600 dark:text-indigo-400 font-bold">{{ proceso.radicado }}</span>
                                        <span class="text-xs text-gray-500 truncate max-w-xs" :title="proceso.asunto">{{ proceso.asunto }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-300 max-w-xs truncate">
                                    <div class="flex items-start gap-1">
                                        <MapPinIcon class="w-4 h-4 mt-0.5 flex-shrink-0 text-gray-400" />
                                        <span class="truncate" :title="proceso.juzgado_entidad">{{ proceso.juzgado_entidad || 'N/A' }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-300">
                                    <div class="flex flex-col gap-1">
                                        <!-- Nota: Ajustamos esto para mostrar info genérica ya que es relación M:N -->
                                        <span class="text-xs italic text-gray-400">Ver detalle</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-900/30 dark:text-green-400">
                                        {{ proceso.tipo_proceso || 'Activo' }}
                                    </span>
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                    <a :href="route('procesos.show', proceso.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Ver</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-gray-500 italic text-center py-6">No hay expedientes judiciales recientes.</p>
                 <div v-if="hasData(persona.procesos)" class="bg-gray-50 dark:bg-gray-700/30 px-4 py-3 border-t border-gray-200 dark:border-gray-700 text-center">
                     <span class="text-xs text-gray-500">Mostrando los {{ persona.procesos.length }} procesos más recientes de {{ persona.procesos_count }}.</span>
                </div>
            </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>