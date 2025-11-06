<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, watch, ref } from 'vue';
import { 
    BriefcaseIcon, ShieldCheckIcon, BuildingOffice2Icon, UserIcon, ArrowLeftIcon, 
    DocumentTextIcon, MapPinIcon, LinkIcon, BuildingOfficeIcon, AcademicCapIcon,
    ArrowUpOnSquareIcon, PaperClipIcon, TrashIcon, DocumentPlusIcon, ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';

// --- PROPS ---
const props = defineProps({
    user: Object,
});

// --- MENSAJES FLASH ---
const page = usePage();
const flash = ref({ success: '', error: '' });

watch(() => page.props.flash, (newFlash) => {
    flash.value.success = newFlash.success;
    flash.value.error = newFlash.error;
    if (newFlash.success || newFlash.error) {
        setTimeout(() => {
            flash.value.success = '';
            flash.value.error = '';
        }, 4000);
    }
}, { immediate: true });

// --- FORMULARIO #1: TARJETA PROFESIONAL ---
const cardForm = useForm({
    tarjeta_profesional: null,
});

function submitProfessionalCard() {
    cardForm.post(route('admin.users.upload.card', props.user.id), {
        preserveScroll: true,
        onSuccess: () => cardForm.reset(),
    });
}

// --- FORMULARIO #2: DOCUMENTOS GENERALES ---
const documentForm = useForm({
    name: '',
    file: null,
});

function submitGeneralDocument() {
    documentForm.post(route('admin.users.documents.store', props.user.id), {
        preserveScroll: true,
        onSuccess: () => documentForm.reset(),
    });
}

// --- ACCIÓN: ELIMINAR DOCUMENTO ---
function deleteDocument(documentId) {
    if (confirm('¿Estás seguro de que deseas eliminar este documento? Esta acción no se puede deshacer.')) {
        router.delete(route('admin.users.documents.destroy', documentId), {
            preserveScroll: true,
        });
    }
}

// --- LÓGICA DE ROLES Y HELPERS ---
const roleDisplay = {
    admin: { icon: ShieldCheckIcon, color: 'text-red-500', label: 'Administrador' },
    abogado: { icon: BriefcaseIcon, color: 'text-sky-500', label: 'Abogado' },
    gestor: { icon: BuildingOffice2Icon, color: 'text-teal-500', label: 'Gestor' },
    cliente: { icon: UserIcon, color: 'text-gray-400', label: 'Cliente' },
};

const getInitials = (name) => {
    if (!name) return '';
    const names = name.split(' ');
    if (names.length > 1 && names[1]) return `${names[0][0]}${names[1][0]}`.toUpperCase();
    return `${names[0][0]}`.toUpperCase();
};

// --- PROPIEDADES COMPUTADAS PARA URLS ---
const professionalCardUrl = computed(() => {
    return props.user.tarjeta_profesional_url ? `/storage/${props.user.tarjeta_profesional_url}` : null;
});

// Función para generar la URL de un documento general
const getDocumentUrl = (path) => {
    return path ? `/storage/${path}` : null;
};
</script>

<template>
    <Head :title="'Detalle de ' + user.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center space-x-4">
                 <Link :href="route('admin.users.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-2 rounded-full">
                     <ArrowLeftIcon class="h-6 w-6" />
                 </Link>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Perfil de Usuario
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div v-if="flash.success" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ flash.success }}</span>
                </div>
                <div v-if="flash.error" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ flash.error }}</span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-1 space-y-8">
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 flex flex-col items-center text-center">
                            <div class="h-24 w-24 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center mb-4">
                                <span class="text-3xl font-bold text-indigo-600 dark:text-indigo-300">{{ getInitials(user.name) }}</span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ user.name }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</p>

                            <div class="mt-4 flex items-center text-sm">
                                <component :is="roleDisplay[user.tipo_usuario].icon" :class="roleDisplay[user.tipo_usuario].color" class="h-5 w-5 mr-2" />
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ roleDisplay[user.tipo_usuario].label }}</span>
                            </div>
                            
                            <div class="mt-2">
                                <span :class="user.estado_activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-3 py-1 text-xs font-semibold rounded-full">
                                    {{ user.estado_activo ? 'Activo' : 'Suspendido' }}
                                </span>
                            </div>
                             <div class="mt-6 w-full">
                                 <Link :href="route('admin.users.edit', user.id)" class="w-full text-center inline-flex items-center justify-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase hover:bg-indigo-500">
                                     Editar Información
                                 </Link>
                             </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2 space-y-8">
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Información General</h3>
                            <dl class="mt-4 divide-y divide-gray-200 dark:divide-gray-700">
                                
                                <!-- --- INICIO: SECCIÓN DE DIRECCIONES MODIFICADA --- -->
                                <div class="py-4 grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Direcciones</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100 col-span-2">
                                        <ul v-if="user.addresses && user.addresses.length > 0" class="space-y-2">
                                            <li v-for="(addr, index) in user.addresses" :key="index">
                                                <span class="block font-medium">{{ addr.address || 'N/A' }}, {{ addr.city || 'N/A' }}</span>
                                                <span v-if="addr.details" class="block text-xs text-gray-500">{{ addr.details }}</span>
                                            </li>
                                        </ul>
                                        <span v-else class="text-gray-500 dark:text-gray-400">No especificadas</span>
                                    </dd>
                                </div>
                                <!-- --- FIN: SECCIÓN DE DIRECCIONES MODIFICADA --- -->

                                <div class="py-4 grid grid-cols-3 gap-4"><dt class="text-sm font-medium text-gray-500">Cooperativas</dt><dd class="text-sm text-gray-900 dark:text-gray-100 col-span-2">{{ user.cooperativas_display || 'Ninguna' }}</dd></div>
                                <div class="py-4 grid grid-cols-3 gap-4"><dt class="text-sm font-medium text-gray-500">Especialidades</dt><dd class="text-sm text-gray-900 dark:text-gray-100 col-span-2">{{ user.especialidades_display || 'Ninguna' }}</dd></div>
                            </dl>
                        </div>

                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Gestor de Documentos</h3>
                            
                            <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                                <form @submit.prevent="submitProfessionalCard">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tarjeta Profesional</label>
                                    <div v-if="professionalCardUrl" class="text-xs text-indigo-600 my-1">
                                        <a :href="professionalCardUrl" target="_blank" class="hover:underline">Ver documento actual</a>
                                    </div>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <input type="file" @input="cardForm.tarjeta_profesional = $event.target.files[0]" class="block w-full text-sm text-gray-500 file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-gray-100 dark:file:bg-gray-700 file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-200"/>
                                        <button type="submit" :disabled="cardForm.processing" class="inline-flex items-center p-2 bg-gray-800 border rounded-md font-semibold text-xs text-white hover:bg-gray-700 disabled:opacity-25"><ArrowUpOnSquareIcon class="h-4 w-4"/></button>
                                    </div>
                                    <div v-if="cardForm.errors.tarjeta_profesional" class="text-red-500 text-xs mt-1">{{ cardForm.errors.tarjeta_profesional }}</div>
                                </form>
                            </div>

                            <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                                <h4 class="text-md font-medium text-gray-800 dark:text-gray-200">Documentos Generales</h4>
                                
                                <form @submit.prevent="submitGeneralDocument" class="mt-2 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                                    <div class="grid grid-cols-1 gap-4">
                                        <div>
                                            <label for="doc_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre del Documento</label>
                                            <input v-model="documentForm.name" type="text" id="doc_name" class="mt-1 block w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 rounded-md shadow-sm" placeholder="Ej: Cédula de Ciudadanía" required>
                                            <div v-if="documentForm.errors.name" class="text-red-500 text-xs mt-1">{{ documentForm.errors.name }}</div>
                                        </div>
                                        <div>
                                            <label for="doc_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Archivo</label>
                                            <input type="file" @input="documentForm.file = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-gray-100 dark:file:bg-gray-700 file:text-gray-700 dark:file:text-gray-200" required>
                                            <div v-if="documentForm.errors.file" class="text-red-500 text-xs mt-1">{{ documentForm.errors.file }}</div>
                                        </div>
                                    </div>
                                    <div class="mt-4 text-right">
                                        <button type="submit" :disabled="documentForm.processing" class="inline-flex items-center px-4 py-2 bg-indigo-600 border rounded-md font-semibold text-xs text-white hover:bg-indigo-500 disabled:opacity-50">
                                            <DocumentPlusIcon class="h-4 w-4 mr-2"/>Añadir Documento
                                        </button>
                                    </div>
                                </form>

                                <ul v-if="user.documents && user.documents.length > 0" class="mt-4 space-y-2">
                                    <li v-for="doc in user.documents" :key="doc.id" class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700/50 rounded-md">
                                        <a :href="getDocumentUrl(doc.path)" target="_blank" class="flex-1 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline truncate" :title="doc.name">
                                            <PaperClipIcon class="h-4 w-4 mr-2 inline-block"/>{{ doc.name }}
                                        </a>
                                        <button @click="deleteDocument(doc.id)" class="ml-4 text-gray-400 hover:text-red-500">
                                            <TrashIcon class="h-4 w-4"/>
                                        </button>
                                    </li>
                                </ul>
                                <div v-else class="mt-4 text-center text-sm text-gray-500 p-4 border-2 border-dashed rounded-lg">
                                    No hay documentos generales adjuntos.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
