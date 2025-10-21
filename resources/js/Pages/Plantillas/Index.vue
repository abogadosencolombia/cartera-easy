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
import { TrashIcon, CheckCircleIcon, XCircleIcon, DocumentDuplicateIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    plantillas: Array,
    cooperativas: Array,
    tipos_proceso: Array,
    filtros: Object,
    can: Object,
});

const page = usePage();

// --- Formularios ---
const form = useForm({
    nombre: '',
    tipo: 'demanda',
    cooperativa_id: null,
    version: '',
    aplica_a: [],
    archivo: null,
});

// El estado de los filtros se inicializa con los valores que vienen del controlador
const filtroForm = ref({
    tipo: props.filtros.tipo || '',
    activa: props.filtros.activa === '' || props.filtros.activa === null ? '' : props.filtros.activa,
});


// --- Métodos ---
const submit = () => {
    form.post(route('plantillas.store'), {
        onSuccess: () => form.reset(),
    });
};

const confirmingDeletion = ref(false);
const itemToDelete = ref(null);

const confirmDeletion = (plantilla) => {
    itemToDelete.value = plantilla;
    confirmingDeletion.value = true;
};

const deleteItem = () => {
    router.delete(route('plantillas.destroy', itemToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};

const closeModal = () => {
    confirmingDeletion.value = false;
    itemToDelete.value = null;
};

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
</script>

<template>
    <Head title="Gestión de Plantillas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Arsenal de Plantillas de Documentos
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Panel de Filtros -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                         <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Panel de Control del Arsenal</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="filtro_tipo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filtrar por Tipo</label>
                                <select v-model="filtroForm.tipo" @change="aplicarFiltros" id="filtro_tipo" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                    <option value="">Todos los Tipos</option>
                                    <option value="demanda">Demanda</option>
                                    <option value="carta">Carta</option>
                                    <option value="medida cautelar">Medida Cautelar</option>
                                    <option value="notificación">Notificación</option>
                                    <option value="otros">Otros</option>
                                </select>
                            </div>
                            <div>
                                <label for="filtro_activa" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filtrar por Estado</label>
                                <select v-model="filtroForm.activa" @change="aplicarFiltros" id="filtro_activa" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                    <option value="">Todos los Estados</option>
                                    <option value="1">Solo Activas</option>
                                    <option value="0">Solo Inactivas</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <SecondaryButton @click="limpiarFiltros" class="w-full justify-center h-10">Limpiar Filtros</SecondaryButton>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-1" v-if="can.create_plantillas">
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Cargar Nueva Plantilla
                            </h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Sube archivos .docx para usarlos como plantillas.
                            </p>
                            <form @submit.prevent="submit" class="mt-6 space-y-6">
                                <div>
                                    <InputLabel for="nombre" value="Nombre de la Plantilla" />
                                    <TextInput v-model="form.nombre" id="nombre" type="text" class="mt-1 block w-full" required />
                                    <InputError :message="form.errors.nombre" class="mt-2" />
                                </div>
                                
                                <div>
                                    <InputLabel for="cooperativa_id" value="Asignar a Cooperativa (Opcional)" />
                                    <select v-model="form.cooperativa_id" id="cooperativa_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                        <option :value="null">-- Plantilla Global --</option>
                                        <option v-for="coop in cooperativas" :key="coop.id" :value="coop.id">{{ coop.nombre }}</option>
                                    </select>
                                    <InputError :message="form.errors.cooperativa_id" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="tipo" value="Tipo de Documento" />
                                    <select v-model="form.tipo" id="tipo" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                        <option value="demanda">Demanda</option>
                                        <option value="carta">Carta</option>
                                        <option value="medida cautelar">Medida Cautelar</option>
                                        <option value="notificación">Notificación</option>
                                        <option value="otros">Otros</option>
                                    </select>
                                    <InputError :message="form.errors.tipo" class="mt-2" />
                                </div>
                                
                                <div>
                                    <InputLabel for="version" value="Versión (Opcional)" />
                                    <TextInput v-model="form.version" id="version" type="text" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.version" class="mt-2" />
                                </div>
                                
                                <div>
                                    <InputLabel value="Aplica a Tipos de Proceso (Opcional)" class="mb-2" />
                                    <div class="space-y-2">
                                        <label v-for="tipo in tipos_proceso" :key="tipo" class="flex items-center">
                                            <Checkbox v-model:checked="form.aplica_a" :value="tipo" />
                                            <span class="ms-2 text-sm text-gray-600 dark:text-gray-400 capitalize">{{ tipo }}</span>
                                        </label>
                                    </div>
                                    <InputError :message="form.errors.aplica_a" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="archivo" value="Archivo (.docx)" />
                                    <input id="archivo" type="file" @input="form.archivo = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                    <InputError :message="form.errors.archivo" class="mt-2" />
                                </div>

                                <div class="flex items-center justify-end">
                                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                        Guardar Plantilla
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div :class="[can.create_plantillas ? 'lg:col-span-2' : 'lg:col-span-3']">
                        <div v-if="page.props.flash.success" class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md shadow-md" role="alert">
                            <p class="font-bold">¡Éxito!</p>
                            <p>{{ page.props.flash.success }}</p>
                        </div>
                        <div v-if="page.props.flash.error" class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md shadow-md" role="alert">
                            <p class="font-bold">¡Acción Bloqueada!</p>
                            <p>{{ page.props.flash.error }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6 text-gray-900 dark:text-gray-100">
                                <h3 class="text-lg font-medium mb-4">Plantillas Disponibles</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nombre / Versión</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cooperativa</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tipo</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">En Uso</th>
                                                <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-if="plantillas.length === 0">
                                                <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                                    No se encontraron plantillas con los filtros aplicados.
                                                </td>
                                            </tr>
                                            <tr v-else v-for="plantilla in plantillas" :key="plantilla.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ plantilla.nombre }}</div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">Ver. {{ plantilla.version || 'N/A' }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ plantilla.cooperativa ? plantilla.cooperativa.nombre : 'Global' }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 capitalize">{{ plantilla.tipo }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <span v-if="plantilla.documentos_generados_count > 0" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        Sí ({{ plantilla.documentos_generados_count }})
                                                    </span>
                                                    <span v-else class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                        No
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-4">
                                                    <button v-if="can.create_plantillas" @click="cloneItem(plantilla)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300" title="Clonar Plantilla">
                                                        <DocumentDuplicateIcon class="h-5 w-5" />
                                                    </button>
                                                    <button 
                                                        v-if="can.create_plantillas" 
                                                        @click="confirmDeletion(plantilla)" 
                                                        :disabled="plantilla.documentos_generados_count > 0"
                                                        :class="[plantilla.documentos_generados_count > 0 ? 'text-gray-400 cursor-not-allowed' : 'text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300']"
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
            </div>
        </div>

        <Modal :show="confirmingDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    ¿Eliminar Plantilla?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400" v-if="itemToDelete">
                    Estás a punto de eliminar permanentemente la plantilla <span class="font-bold">{{ itemToDelete.nombre }}</span>. Esta acción no se puede deshacer.
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
