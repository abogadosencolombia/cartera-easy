<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import {
    TrashIcon,
    MagnifyingGlassIcon,
    BuildingOffice2Icon,
    IdentificationIcon,
    UserCircleIcon,
    EnvelopeIcon
} from '@heroicons/vue/24/outline';
import { debounce } from 'lodash';

const props = defineProps({
    cooperativas: Object,
    filters: Object,
    can: Object,
});

const page = usePage();

// --- Lógica de Búsqueda Dinámica ---
const search = ref(props.filters.search || '');

watch(search, debounce((value) => {
    router.get(route('cooperativas.index'), { search: value }, {
        preserveState: true,
        replace: true,
    });
}, 300));

// --- Lógica para el modal de confirmación de borrado ---
const confirmingDeletion = ref(false);
const itemToDelete = ref(null);
const form = useForm({});

const confirmDeletion = (item) => {
    itemToDelete.value = item;
    confirmingDeletion.value = true;
};

const deleteItem = () => {
    form.delete(route('cooperativas.destroy', itemToDelete.value.id), {
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
    <Head title="Módulo de Cooperativas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Módulo de Cooperativas
                </h2>
                <div class="flex items-center gap-4 w-full sm:w-auto">
                    <!-- CAMPO DE BÚSQUEDA -->
                    <div class="relative w-full sm:w-64">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                            <MagnifyingGlassIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                        </div>
                        <input
                            v-model="search"
                            type="text"
                            class="block w-full p-2.5 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                            placeholder="Buscar cooperativa..."
                            autocomplete="off"
                        />
                    </div>
                    <Link v-if="can.create_cooperativas" :href="route('cooperativas.create')" class="inline-flex items-center px-4 py-2.5 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 whitespace-nowrap">
                        Crear Cooperativa
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="page.props.flash.success" class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md shadow-md" role="alert">
                    <p class="font-bold">¡Éxito!</p>
                    <p>{{ page.props.flash.success }}</p>
                </div>

                <!-- Contenedor principal para tarjetas o mensaje de "no encontrados" -->
                <div v-if="cooperativas.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- INICIO DEL BUCLE DE TARJETAS -->
                    <div v-for="coop in cooperativas.data" :key="coop.id"
                         class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border dark:border-gray-700 flex flex-col transition-all duration-300 hover:shadow-lg hover:-translate-y-1">

                        <!-- Cuerpo de la tarjeta -->
                        <div class="p-6 flex-grow">
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white truncate">{{ coop.nombre }}</h3>
                            <div class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-400">
                                <div class="flex items-center">
                                    <IdentificationIcon class="h-5 w-5 mr-3 flex-shrink-0 text-gray-400" />
                                    <span class="truncate">NIT: {{ coop.NIT }}</span>
                                </div>
                                <div class="flex items-center">
                                    <UserCircleIcon class="h-5 w-5 mr-3 flex-shrink-0 text-gray-400" />
                                    <span class="truncate">{{ coop.representante_legal_nombre }}</span>
                                </div>
                                <div class="flex items-center">
                                    <EnvelopeIcon class="h-5 w-5 mr-3 flex-shrink-0 text-gray-400" />
                                    <span class="truncate">{{ coop.contacto_correo }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Pie de la tarjeta con acciones -->
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border-t dark:border-gray-700 flex items-center justify-between rounded-b-lg">
                            <Link :href="route('cooperativas.show', coop.id)"
                                  class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition">
                                Ver Detalles
                            </Link>
                            <button v-if="can.delete_cooperativas" @click="confirmDeletion(coop)"
                                    class="p-2 rounded-full text-gray-400 hover:text-red-500 hover:bg-red-100 dark:hover:bg-red-800/50 transition-colors">
                                <span class="sr-only">Eliminar</span>
                                <TrashIcon class="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                    <!-- FIN DEL BUCLE DE TARJETAS -->
                </div>

                <!-- MENSAJE SI NO HAY COOPERATIVAS -->
                <div v-else class="text-center py-20 px-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm border dark:border-gray-700">
                    <BuildingOffice2Icon class="h-16 w-16 mx-auto text-gray-300 dark:text-gray-600" />
                    <h3 class="mt-4 text-lg font-semibold text-gray-800 dark:text-gray-200">No se encontraron cooperativas</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Intenta con otro término de búsqueda o registra una nueva cooperativa.
                    </p>
                </div>

                <!-- PAGINACIÓN -->
                <div v-if="cooperativas.data.length > 0" class="mt-8">
                    <Pagination :links="cooperativas.links" />
                </div>
            </div>
        </div>

        <!-- MODAL DE CONFIRMACIÓN DE ELIMINACIÓN -->
        <Modal :show="confirmingDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    ¿Estás seguro de que quieres eliminar esta cooperativa?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400" v-if="itemToDelete">
                    Estás a punto de eliminar permanentemente la cooperativa <span class="font-bold">{{ itemToDelete.nombre }}</span>. Todos sus casos, usuarios y documentos asociados también serán eliminados. Esta acción no se puede deshacer.
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal"> Cancelar </SecondaryButton>
                    <DangerButton class="ms-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="deleteItem">
                        Sí, Eliminar Cooperativa
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

