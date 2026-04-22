<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { 
    TrashIcon, 
    BellAlertIcon, 
    ClockIcon, 
    DocumentMagnifyingGlassIcon, 
    ExclamationCircleIcon,
    ShieldCheckIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    reglas: Array,
    cooperativas: Array,
    tipos_proceso: Array,
    tipos_alerta: Array,
});

const page = usePage();

// Formulario reactivo
const form = useForm({
    cooperativa_id: props.cooperativas.length > 0 ? props.cooperativas[0].id : null,
    tipo: props.tipos_alerta.length > 0 ? props.tipos_alerta[0] : '',
    dias: 30,
});

const submit = () => {
    form.post(route('admin.reglas-alerta.store'), {
        onSuccess: () => {
            form.reset('dias');
            // Opcional: Mostrar toast de éxito aquí
        },
    });
};

// Lógica de eliminación
const confirmingDeletion = ref(false);
const itemToDelete = ref(null);

const confirmDeletion = (regla) => {
    itemToDelete.value = regla;
    confirmingDeletion.value = true;
};

const deleteItem = () => {
    useForm({}).delete(route('admin.reglas-alerta.destroy', itemToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};

const closeModal = () => {
    confirmingDeletion.value = false;
    itemToDelete.value = null;
};

// Helpers visuales
const getAlertIcon = (tipo) => {
    switch (tipo) {
        case 'mora': return ExclamationCircleIcon;
        case 'vencimiento': return ClockIcon;
        case 'documento_faltante': return DocumentMagnifyingGlassIcon;
        default: return BellAlertIcon;
    }
};

const getAlertColor = (tipo) => {
    switch (tipo) {
        case 'mora': return 'text-red-600 bg-red-100 dark:bg-red-900/30';
        case 'vencimiento': return 'text-amber-600 bg-amber-100 dark:bg-amber-900/30';
        case 'inactividad': return 'text-gray-600 bg-gray-100 dark:bg-gray-700';
        default: return 'text-blue-600 bg-blue-100 dark:bg-blue-900/30';
    }
};

const getHumanDescription = (tipo, dias) => {
    switch (tipo) {
        case 'mora': return `Alertar cuando un crédito tenga ${dias} días de retraso.`;
        case 'vencimiento': return `Avisar ${dias} días antes de que se venza una etapa procesal.`;
        case 'inactividad': return `Notificar si un caso no tiene movimientos en ${dias} días.`;
        case 'documento_faltante': return `Recordar documentos pendientes cada ${dias} días.`;
        default: return `Generar alerta cada ${dias} días.`;
    }
};
</script>

<template>
    <Head title="Gestión de Reglas de Alerta" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <BellAlertIcon class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                <h2 class="font-semibold text-xl text-blue-500 dark:text-gray-200 leading-tight">
                    Configuración de Alertas Automáticas
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Mensajes Flash -->
                <div v-if="$page.props.flash.success" class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-r shadow-sm flex items-center">
                    <ShieldCheckIcon class="h-5 w-5 mr-2" />
                    <p>{{ $page.props.flash.success }}</p>
                </div>
                <div v-if="$page.props.flash.error" class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-r shadow-sm flex items-center">
                    <ExclamationCircleIcon class="h-5 w-5 mr-2" />
                    <p>{{ $page.props.flash.error }}</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- PANEL DE CREACIÓN -->
                    <div class="lg:col-span-1">
                        <div class="bg-white dark:bg-gray-800 shadow-md sm:rounded-xl border border-gray-100 dark:border-gray-700 sticky top-6">
                            <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                    Nueva Regla
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Configura los criterios para que el sistema te avise automáticamente.
                                </p>
                            </div>

                            <div class="p-6">
                                <form @submit.prevent="submit" class="space-y-5">
                                    <!-- Selección de Cooperativa -->
                                    <div>
                                        <InputLabel for="cooperativa_id" value="Cooperativa / Entidad" />
                                        <SelectInput v-model="form.cooperativa_id" id="cooperativa_id" 
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            <option v-for="coop in cooperativas" :key="coop.id" :value="coop.id">{{ coop.nombre }}</option>
                                        </SelectInput>
                                        <InputError class="mt-2" :message="form.errors.cooperativa_id" />
                                    </div>

                                    <!-- Tipo de Alerta -->
                                    <div>
                                        <InputLabel for="tipo_alerta" value="¿Qué quieres vigilar?" />
                                        <SelectInput v-model="form.tipo" id="tipo_alerta" 
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-lg shadow-sm capitalize focus:ring-indigo-500 focus:border-indigo-500">
                                            <option v-for="tipo in tipos_alerta" :key="tipo" :value="tipo">
                                                {{ tipo.replace('_', ' ') }}
                                            </option>
                                        </SelectInput>
                                        <InputError class="mt-2" :message="form.errors.tipo" />
                                    </div>

                                    <!-- Condición de Tiempo -->
                                    <div>
                                        <InputLabel for="dias" value="Condición de tiempo (Días)" />
                                        <div class="relative mt-1 rounded-md shadow-sm">
                                            <TextInput id="dias" type="number" 
                                                class="block w-full pr-12 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg" 
                                                v-model="form.dias" required min="1" placeholder="30" />
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">días</span>
                                            </div>
                                        </div>
                                        <p class="mt-2 text-xs text-gray-500 italic bg-indigo-50 dark:bg-indigo-900/20 p-2 rounded border border-indigo-100 dark:border-indigo-800">
                                            💡 {{ getHumanDescription(form.tipo, form.dias || 'X') }}
                                        </p>
                                        <InputError class="mt-2" :message="form.errors.dias" />
                                    </div>

                                    <div class="pt-2">
                                        <PrimaryButton class="w-full justify-center py-3" :class="{ 'opacity-75': form.processing }" :disabled="form.processing">
                                            Guardar Configuración
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- LISTADO DE REGLAS -->
                    <div class="lg:col-span-2">
                        <div class="bg-white dark:bg-gray-800 shadow-md sm:rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                    Reglas Activas
                                </h3>
                                <span class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                    {{ reglas.length }} configuradas
                                </span>
                            </div>

                            <div v-if="reglas.length === 0" class="p-12 text-center">
                                <BellAlertIcon class="mx-auto h-12 w-12 text-gray-300" />
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No hay reglas definidas</h3>
                                <p class="mt-1 text-sm text-gray-500">Comienza creando una regla en el panel izquierdo.</p>
                            </div>

                            <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                                <li v-for="regla in reglas" :key="regla.id" class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition duration-150 ease-in-out">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-start space-x-4">
                                            <div class="flex-shrink-0">
                                                <span class="inline-flex items-center justify-center h-10 w-10 rounded-full" :class="getAlertColor(regla.tipo)">
                                                    <component :is="getAlertIcon(regla.tipo)" class="h-6 w-6" />
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ regla.cooperativa?.nombre || 'Cooperativa no encontrada' }}
                                                </p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400 capitalize font-semibold">
                                                    {{ regla.tipo.replace('_', ' ') }}
                                                </p>
                                                <p class="text-xs text-gray-400 mt-1">
                                                    {{ getHumanDescription(regla.tipo, regla.dias) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center ml-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 mr-4">
                                                {{ regla.dias }} días
                                            </span>
                                            <button @click="confirmDeletion(regla)" class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors p-2 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20" title="Eliminar regla">
                                                <TrashIcon class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Confirmación -->
        <Modal :show="confirmingDeletion" @close="closeModal">
            <div class="p-6 text-center">
                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10 mb-4 mx-auto">
                    <ExclamationCircleIcon class="h-6 w-6 text-red-600" aria-hidden="true" />
                </div>
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    ¿Detener esta alerta?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400" v-if="itemToDelete">
                    El sistema dejará de vigilar la condición de <span class="font-bold text-gray-800 dark:text-gray-200">{{ itemToDelete.tipo.replace('_', ' ') }}</span> ({{ itemToDelete.dias }} días) para la cooperativa <span class="font-bold">{{ itemToDelete.cooperativa?.nombre }}</span>.
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <SecondaryButton @click="closeModal"> Cancelar </SecondaryButton>
                    <DangerButton class="ml-3" @click="deleteItem">
                        Sí, Eliminar Regla
                    </DangerButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>