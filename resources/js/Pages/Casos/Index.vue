<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { DocumentDuplicateIcon, TrashIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { debounce } from 'lodash';

const props = defineProps({
    casos: Object,      // Paginador de Inertia: { data, links, ... }
    can: Object,
    filters: Object,    // { search?: string }
});

// --- Lógica de Búsqueda Dinámica ---
const search = ref(props.filters?.search ?? '');

watch(search, debounce((value) => {
    router.get(route('casos.index'), { search: value }, {
        preserveState: true,
        replace: true,
    });
}, 300)); // Espera 300ms después de que el usuario deja de escribir

// --- Lógica para el Modal de Eliminación ---
const confirmingCaseDeletion = ref(false);
const caseToDelete = ref(null);
const form = useForm({});

const confirmCaseDeletion = (caso) => {
    caseToDelete.value = caso;
    confirmingCaseDeletion.value = true;
};

const deleteCase = () => {
    form.delete(route('casos.destroy', caseToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};

const closeModal = () => {
    confirmingCaseDeletion.value = false;
    caseToDelete.value = null;
};

// --- Ayudantes de Estilo y Formato ---
const statusColorClasses = {
    red: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    green: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    gray: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};

// --- FUNCIÓN DE FECHA MEJORADA ---
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    // new Date() puede interpretar directamente el formato de Laravel (YYYY-MM-DD HH:MM:SS)
    const date = new Date(dateString);

    // Buena práctica: verificar si la fecha es válida después de crearla.
    if (isNaN(date.getTime())) {
        return 'Fecha inválida';
    }

    return date.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'long', // 'long' se ve mejor (ej. "octubre")
        day: 'numeric'
    });
};
</script>

<template>
    <Head title="Gestión de Casos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Gestión de Casos
                </h2>
                <div class="flex items-center gap-4 w-full sm:w-auto">
                    <div class="relative w-full sm:w-72">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                            <MagnifyingGlassIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                        </div>
                        <input
                            v-model="search"
                            type="text"
                            name="search"
                            class="block w-full p-2.5 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                            placeholder="Buscar caso..."
                            autocomplete="off"
                        />
                    </div>
                    <Link
                        :href="route('casos.create')"
                        class="inline-flex items-center px-4 py-2.5 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        Registrar Nuevo Caso
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div v-if="casos.data.length > 0" class="space-y-4">
                            <div v-for="caso in casos.data" :key="caso.id" class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg shadow-sm transition hover:shadow-md">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-3">
                                            <Link :href="route('casos.show', caso.id)" class="text-indigo-600 dark:text-indigo-400 font-bold truncate hover:underline">
                                                {{ caso.deudor?.nombre_completo ?? 'Sin deudor' }}
                                            </Link>
                                            <span
                                                v-if="caso.semaforo"
                                                class="px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full flex-shrink-0"
                                                :class="statusColorClasses[caso.semaforo.color] || statusColorClasses['gray']"
                                            >
                                                {{ caso.semaforo.text }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 truncate">
                                            <span>Caso #{{ caso.id }}</span>
                                            <span class="mx-1.5">&middot;</span>
                                            <span>Creado el {{ formatDate(caso.created_at) }}</span>
                                            <span class="mx-1.5">&middot;</span>
                                            <span>Abogado: {{ caso.user ? caso.user.name : 'N/A' }}</span>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <Link
                                            :href="route('casos.show', caso.id)"
                                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                        >
                                            Ver Detalles
                                        </Link>
                                        <button
                                            v-if="can.delete_cases"
                                            @click="confirmCaseDeletion(caso)"
                                            class="p-2 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 transition"
                                        >
                                            <TrashIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="!casos.data || casos.data.length === 0" class="text-center py-16">
                             <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Sin resultados</h3>
                             <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Intenta con otro término de búsqueda.</p>
                        </div>

                        <div v-if="casos.links?.length > 1" class="mt-6 flex flex-wrap justify-center gap-2">
                            <Link
                                v-for="(link, idx) in casos.links"
                                :key="idx"
                                :href="link.url || '#'"
                                v-html="link.label"
                                class="px-3 py-2 text-sm rounded border dark:border-gray-700"
                                :class="{
                                    'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 pointer-events-none': !link.url,
                                    'bg-indigo-600 text-white': link.active,
                                    'hover:bg-gray-100 dark:hover:bg-gray-600': link.url
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="confirmingCaseDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    ¿Estás seguro de que quieres eliminar este caso?
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" v-if="caseToDelete">
                    Estás a punto de eliminar permanentemente el Caso #{{ caseToDelete.id }} del deudor
                    <span class="font-bold">{{ caseToDelete.deudor?.nombre_completo ?? 'N/A' }}</span>. Esta acción no se puede deshacer.
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">Cancelar</SecondaryButton>
                    <DangerButton class="ms-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="deleteCase">
                        Sí, Eliminar Caso
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>