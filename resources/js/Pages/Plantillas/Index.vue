<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
    TrashIcon, 
    DocumentDuplicateIcon, 
    PlusIcon, 
    FunnelIcon, 
    DocumentTextIcon, 
    ArrowPathIcon 
} from '@heroicons/vue/24/outline';

// Definición de props más robusta para evitar errores de compilación
const props = defineProps({
    plantillas: {
        type: Array,
        default: () => []
    },
    cooperativas: {
        type: Array,
        default: () => []
    },
    tipos_proceso: {
        type: Array,
        default: () => []
    },
    filtros: {
        type: Object,
        default: () => ({})
    },
    can: {
        type: Object,
        default: () => ({})
    },
});

const page = usePage();

// --- Estado de Modales ---
const showingCreateModal = ref(false);
const confirmingDeletion = ref(false);
const itemToDelete = ref(null);

// --- Formulario de Creación ---
const form = useForm({
    nombre: '',
    tipo: 'demanda',
    cooperativa_id: null,
    version: '',
    aplica_a: [],
    archivo: null,
});

// --- Filtros ---
const filtroForm = ref({
    tipo: props.filtros?.tipo || '',
    activa: props.filtros?.activa === '' || props.filtros?.activa === null ? '' : props.filtros.activa,
});

// --- Métodos de Creación ---
const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    showingCreateModal.value = true;
};

const closeCreateModal = () => {
    if (!form.processing) {
        showingCreateModal.value = false;
        form.reset();
    }
};

const submit = () => {
    form.post(route('plantillas.store'), {
        onSuccess: () => {
            closeCreateModal();
        },
        preserveScroll: true,
    });
};

// --- Métodos de Eliminación ---
const confirmDeletion = (plantilla) => {
    itemToDelete.value = plantilla;
    confirmingDeletion.value = true;
};

const deleteItem = () => {
    if (!itemToDelete.value) return;
    router.delete(route('plantillas.destroy', itemToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};

const closeModal = () => {
    confirmingDeletion.value = false;
    itemToDelete.value = null;
};

// --- Otros Métodos ---
const cloneItem = (plantilla) => {
    if (confirm(`¿Estás seguro de que quieres clonar la plantilla "${plantilla.nombre}"?`)) {
        router.post(route('plantillas.clonar', plantilla.id), {}, {
            preserveScroll: true,
        });
    }
};

const aplicarFiltros = () => {
    const query = { ...filtroForm.value };
    Object.keys(query).forEach(key => {
        if (query[key] === '' || query[key] === null) {
            delete query[key];
        }
    });
    router.get(route('plantillas.index'), query, {
        preserveState: true,
        replace: true,
    });
};

const limpiarFiltros = () => {
    filtroForm.value = { tipo: '', activa: '' };
    aplicarFiltros();
};

// --- Utilidad para nombre de archivo ---
const handleFileUpload = (event) => {
    form.archivo = event.target.files[0];
};
</script>

<template>
    <Head title="Gestión de Plantillas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-blue-500 dark:text-gray-200 leading-tight flex items-center gap-2">
                    <DocumentTextIcon class="w-6 h-6 text-indigo-500" />
                    Plantillas de Documentos
                </h2>
                
                <PrimaryButton 
                    v-if="can.create_plantillas" 
                    @click="openCreateModal"
                    class="w-full sm:w-auto justify-center"
                >
                    <PlusIcon class="w-5 h-5 mr-2" />
                    Nueva Plantilla
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Mensajes Flash -->
                <div v-if="page.props.flash.success" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md shadow-sm animate-fade-in-down" role="alert">
                    <p class="font-bold">¡Éxito!</p>
                    <p>{{ page.props.flash.success }}</p>
                </div>
                <div v-if="page.props.flash.error" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md shadow-sm animate-fade-in-down" role="alert">
                    <p class="font-bold">¡Error!</p>
                    <p>{{ page.props.flash.error }}</p>
                </div>

                <!-- Panel de Filtros Compacto -->
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 border-l-4 border-indigo-500">
                    <div class="flex flex-col lg:flex-row gap-4 items-center justify-between">
                        <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300 font-medium whitespace-nowrap">
                            <FunnelIcon class="w-5 h-5" />
                            <span>Filtros:</span>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto">
                            <select 
                                v-model="filtroForm.tipo" 
                                @change="aplicarFiltros" 
                                class="block w-full sm:w-48 text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">Todos los Tipos</option>
                                <option value="demanda">Demanda</option>
                                <option value="carta">Carta</option>
                                <option value="medida cautelar">Medida Cautelar</option>
                                <option value="notificación">Notificación</option>
                                <option value="otros">Otros</option>
                            </select>

                            <select 
                                v-model="filtroForm.activa" 
                                @change="aplicarFiltros" 
                                class="block w-full sm:w-48 text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">Todos los Estados</option>
                                <option value="1">Solo Activas</option>
                                <option value="0">Solo Inactivas</option>
                            </select>

                            <SecondaryButton @click="limpiarFiltros" class="justify-center" title="Limpiar filtros">
                                <ArrowPathIcon class="w-5 h-5" />
                            </SecondaryButton>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Plantillas -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nombre / Versión</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cooperativa</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tipo</th>
                                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Uso</th>
                                        <th class="relative px-6 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="plantillas.length === 0">
                                        <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center justify-center">
                                                <DocumentTextIcon class="h-12 w-12 text-gray-300 mb-2" />
                                                <p>No se encontraron plantillas con los filtros actuales.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-else v-for="plantilla in plantillas" :key="plantilla.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600">
                                                    <span class="font-bold text-xs">DOC</span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ plantilla.nombre }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">Ver. {{ plantilla.version || '1.0' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <span v-if="plantilla.cooperativa" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ plantilla.cooperativa.nombre }}
                                            </span>
                                            <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Global
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 capitalize">
                                            {{ plantilla.tipo }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span 
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                :class="plantilla.documentos_generados_count > 0 ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'"
                                            >
                                                {{ plantilla.documentos_generados_count }} Docs
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <button v-if="can.create_plantillas" @click="cloneItem(plantilla)" class="p-1 text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 rounded-full transition" title="Clonar Plantilla">
                                                <DocumentDuplicateIcon class="h-5 w-5" />
                                            </button>
                                            
                                            <button 
                                                v-if="can.create_plantillas" 
                                                @click="confirmDeletion(plantilla)" 
                                                :disabled="plantilla.documentos_generados_count > 0"
                                                class="p-1 rounded-full transition"
                                                :class="[plantilla.documentos_generados_count > 0 ? 'text-gray-300 cursor-not-allowed' : 'text-red-600 hover:text-red-900 hover:bg-red-50']"
                                                :title="plantilla.documentos_generados_count > 0 ? 'No se puede eliminar, plantilla en uso' : 'Eliminar Plantilla'">
                                                <TrashIcon class="h-5 w-5" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Crear Nueva Plantilla -->
        <Modal :show="showingCreateModal" @close="closeCreateModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 border-b pb-2 mb-4">
                    Cargar Nueva Plantilla
                </h2>
                
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Sube archivos <strong>.docx</strong> para usarlos como base para generar documentos automáticos. Tamaño máx: 20MB.
                </p>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Nombre -->
                    <div>
                        <InputLabel for="nombre" value="Nombre de la Plantilla" />
                        <TextInput v-model="form.nombre" id="nombre" type="text" class="mt-1 block w-full" required placeholder="Ej. Demanda Ejecutiva Singular" />
                        <InputError :message="form.errors.nombre" class="mt-2" />
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Cooperativa -->
                        <div>
                            <InputLabel for="cooperativa_id" value="Cooperativa (Opcional)" />
                            <select v-model="form.cooperativa_id" id="cooperativa_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option :value="null">-- Plantilla Global --</option>
                                <option v-for="coop in cooperativas" :key="coop.id" :value="coop.id">{{ coop.nombre }}</option>
                            </select>
                            <InputError :message="form.errors.cooperativa_id" class="mt-2" />
                        </div>

                        <!-- Tipo -->
                        <div>
                            <InputLabel for="tipo" value="Tipo de Documento" />
                            <select v-model="form.tipo" id="tipo" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="demanda">Demanda</option>
                                <option value="carta">Carta</option>
                                <option value="medida cautelar">Medida Cautelar</option>
                                <option value="notificación">Notificación</option>
                                <option value="otros">Otros</option>
                            </select>
                            <InputError :message="form.errors.tipo" class="mt-2" />
                        </div>
                    </div>
                    
                    <!-- Versión -->
                    <div>
                        <InputLabel for="version" value="Versión (Ej. 1.0, 2025-v1)" />
                        <TextInput v-model="form.version" id="version" type="text" class="mt-1 block w-full" placeholder="1.0" />
                        <InputError :message="form.errors.version" class="mt-2" />
                    </div>
                    
                    <!-- Aplica A -->
                    <div>
                        <InputLabel value="Aplica a Tipos de Proceso (Opcional)" class="mb-2 font-bold" />
                        <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto p-2 border rounded-md bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
                            <label v-for="tipo in tipos_proceso" :key="tipo" class="flex items-center p-1 hover:bg-gray-200 dark:hover:bg-gray-800 rounded cursor-pointer">
                                <Checkbox v-model:checked="form.aplica_a" :value="tipo" />
                                <span class="ms-2 text-sm text-gray-700 dark:text-gray-300 capitalize truncate" :title="tipo">{{ tipo }}</span>
                            </label>
                        </div>
                        <InputError :message="form.errors.aplica_a" class="mt-2" />
                    </div>

                    <!-- Archivo con Barra de Progreso -->
                    <div class="mt-4">
                        <InputLabel for="archivo" value="Archivo del Documento (.docx)" />
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-indigo-500 transition-colors bg-gray-50 dark:bg-gray-900 dark:border-gray-600">
                            <div class="space-y-1 text-center">
                                <DocumentTextIcon class="mx-auto h-12 w-12 text-gray-400" />
                                <div class="flex text-sm text-gray-600 dark:text-gray-400 justify-center">
                                    <label for="archivo" class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                        <span>Subir un archivo</span>
                                        <input id="archivo" type="file" @input="handleFileUpload" class="sr-only" accept=".docx" />
                                    </label>
                                    <p class="pl-1">o arrastrar y soltar</p>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    DOCX hasta 20MB
                                </p>
                                <p v-if="form.archivo" class="text-sm font-bold text-green-600 mt-2">
                                    Seleccionado: {{ form.archivo.name }}
                                </p>
                            </div>
                        </div>
                        <InputError :message="form.errors.archivo" class="mt-2" />

                        <!-- BARRA DE PROGRESO -->
                        <div v-if="form.progress" class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 mt-4 transition-all duration-300">
                            <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-300" :style="{ width: form.progress.percentage + '%' }"></div>
                            <p class="text-xs text-center mt-1 text-gray-500">Subiendo... {{ form.progress.percentage }}%</p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton @click="closeCreateModal" :disabled="form.processing"> Cancelar </SecondaryButton>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            <span v-if="form.processing">Guardando...</span>
                            <span v-else>Guardar Plantilla</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL: Eliminar -->
        <Modal :show="confirmingDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 text-red-600">
                    ¿Eliminar Plantilla?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400" v-if="itemToDelete">
                    Estás a punto de eliminar permanentemente la plantilla <span class="font-bold">{{ itemToDelete.nombre }}</span>. 
                    <br><br>
                    Esta acción no se puede deshacer y borrará el archivo físico del servidor.
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal"> Cancelar </SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteItem">
                        Sí, Eliminar
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>