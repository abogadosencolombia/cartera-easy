<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { TrashIcon, BellAlertIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    reglas: Array,
    cooperativas: Array,
    tipos_proceso: Array,
    tipos_alerta: Array,
});

const page = usePage();

const form = useForm({
    cooperativa_id: props.cooperativas.length > 0 ? props.cooperativas[0].id : null,
    tipo: props.tipos_alerta[0],
    dias: 30,
});

const submit = () => {
    form.post(route('admin.reglas-alerta.store'), {
        onSuccess: () => form.reset('dias'),
    });
};

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
</script>

<template>
    <Head title="Gestión de Reglas de Alerta" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Gestión de Reglas de Alerta Automática
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-8">

                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center">
                            <BellAlertIcon class="h-6 w-6 mr-2 text-indigo-500" />
                            Crear Nueva Regla
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Defina cuándo se deben generar las alertas automáticas.
                        </p>

                        <form @submit.prevent="submit" class="mt-6 space-y-6">
                            <div>
                                <InputLabel for="cooperativa_id" value="Para la Cooperativa" />
                                <select v-model="form.cooperativa_id" id="cooperativa_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                    <option v-for="coop in cooperativas" :key="coop.id" :value="coop.id">{{ coop.nombre }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.cooperativa_id" />
                            </div>

                            <div>
                                <InputLabel for="tipo_alerta" value="Tipo de Alerta" />
                                <select v-model="form.tipo" id="tipo_alerta" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm capitalize">
                                    <option v-for="tipo in tipos_alerta" :key="tipo" :value="tipo">{{ tipo.replace('_', ' ') }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.tipo" />
                            </div>

                            <div>
                                <InputLabel for="dias" value="Generar Alerta a los (días)" />
                                <TextInput id="dias" type="number" class="mt-1 block w-full" v-model="form.dias" required min="1" />
                                <InputError class="mt-2" :message="form.errors.dias" />
                            </div>

                            <div class="flex items-center justify-end">
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Guardar Regla
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <div v-if="page.props.flash.success" class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md shadow-md" role="alert">
                        <p>{{ page.props.flash.success }}</p>
                    </div>
                     <div v-if="page.props.flash.error" class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md shadow-md" role="alert">
                        <p>{{ page.props.flash.error }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <h3 class="text-lg font-medium mb-4">Reglas Actuales</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cooperativa</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tipo de Alerta</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Condición (Días)</th>
                                            <th class="relative px-6 py-3"><span class="sr-only">Eliminar</span></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-if="reglas.length === 0">
                                            <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                                No hay reglas de alerta definidas.
                                            </td>
                                        </tr>
                                        <tr v-else v-for="regla in reglas" :key="regla.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ regla.cooperativa.nombre }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 capitalize">{{ regla.tipo.replace('_', ' ') }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ regla.dias }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button @click="confirmDeletion(regla)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
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

        <Modal :show="confirmingDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">¿Eliminar Regla de Alerta?</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400" v-if="itemToDelete">
                    Estás a punto de eliminar esta regla. El sistema dejará de generar alertas automáticas de tipo <span class="font-bold capitalize">{{ itemToDelete.tipo.replace('_', ' ') }}</span> para la cooperativa <span class="font-bold">{{ itemToDelete.cooperativa.nombre }}</span>.
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal"> Cancelar </SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteItem">Sí, Eliminar</DangerButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>
