<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue'; // ref añadido para el estado del modal
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    tasas: { type: Array, default: () => [] }
});

// Helper para formatear fechas
const fmtDate  = (d) => d ? new Date(d.replace(/-/g, '/')).toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';

// Formulario para añadir nueva tasa
const form = useForm({
    tasa_ea: '',
    fecha_vigencia: new Date().toISOString().slice(0, 10),
});

const submit = () => {
    form.post(route('admin.tasas.store'), {
        onSuccess: () => {
            form.reset('tasa_ea');
        },
    });
};

// --- INICIO: LÓGICA DEL MODAL DE ELIMINACIÓN ---
const isDeleteModalOpen = ref(false);
const tasaToDelete = ref(null);

const openDeleteModal = (tasa) => {
    tasaToDelete.value = tasa;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    isDeleteModalOpen.value = false;
    tasaToDelete.value = null;
};

const deleteTasa = () => {
    if (!tasaToDelete.value) return;
    router.delete(route('admin.tasas.destroy', tasaToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};
// --- FIN: LÓGICA DEL MODAL DE ELIMINACIÓN ---

</script>

<template>
    <Head title="Gestión de Tasas de Interés" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Gestión de Tasas de Interés de Mora
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <!-- Formulario para Añadir Nueva Tasa -->
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold">Registrar Nueva Tasa (E.A.)</h3>
                         <p class="text-sm text-gray-500 mt-1">
                            Añade una nueva tasa y la fecha exacta (pasada o futura) desde la cual será válida.
                         </p>
                    </div>
                    <form @submit.prevent="submit" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                            <div class="md:col-span-1">
                                <InputLabel for="tasa_ea" value="Tasa Efectiva Anual (%)" />
                                <TextInput id="tasa_ea" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.tasa_ea" required placeholder="Ej: 31.5" />
                                <InputError class="mt-2" :message="form.errors.tasa_ea" />
                            </div>
                            <div class="md:col-span-1">
                                <InputLabel for="fecha_vigencia" value="Válida Desde" />
                                <TextInput id="fecha_vigencia" type="date" class="mt-1 block w-full" v-model="form.fecha_vigencia" required />
                                <InputError class="mt-2" :message="form.errors.fecha_vigencia" />
                            </div>
                            <div class="md:col-span-1 pt-6">
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Guardar Nueva Tasa
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Historial de Tasas Registradas -->
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold">Historial Completo de Tasas</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr class="text-left text-gray-600 dark:text-gray-300 font-medium">
                                    <th class="py-2 px-4">Tasa (E.A.)</th>
                                    <th class="py-2 px-4">Fecha de Vigencia</th>
                                    <th class="py-2 px-4">Fecha de Registro</th>
                                    <th class="py-2 px-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-if="!props.tasas.length">
                                    <td colspan="4" class="text-center py-8 text-gray-500">No hay tasas registradas.</td>
                                </tr>
                                <tr v-for="tasa in props.tasas" :key="tasa.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="py-3 px-4 font-bold">{{ tasa.tasa_ea }}%</td>
                                    <td class="py-3 px-4">{{ fmtDate(tasa.fecha_vigencia) }}</td>
                                    <td class="py-3 px-4 text-gray-500">{{ fmtDate(tasa.created_at) }}</td>
                                    <td class="py-3 px-4 text-right">
                                        <button @click="openDeleteModal(tasa)" class="text-red-600 hover:text-red-800 font-semibold text-xs py-1 px-2 rounded-md bg-red-50 hover:bg-red-100 dark:bg-red-900/50 dark:hover:bg-red-900 transition-colors">
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== INICIO: MODAL DE CONFIRMACIÓN DE ELIMINACIÓN ===== -->
        <div v-if="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md">
                <div class="p-6 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <h3 class="mt-4 font-semibold text-lg text-gray-800 dark:text-gray-100">
                        ¿Estás seguro de eliminar esta tasa?
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        La tasa del <strong class="font-bold">{{ tasaToDelete?.tasa_ea }}%</strong> con vigencia desde el <strong class="font-bold">{{ fmtDate(tasaToDelete?.fecha_vigencia) }}</strong> será eliminada permanentemente. Esta acción no se puede deshacer.
                    </p>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-center gap-4 rounded-b-xl">
                    <button @click="closeDeleteModal" class="w-full px-4 py-2 rounded-md bg-white dark:bg-gray-700 border dark:border-gray-600 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-600">
                        Cancelar
                    </button>
                    <button @click="deleteTasa" class="w-full px-4 py-2 rounded-md bg-red-600 text-white text-sm font-semibold hover:bg-red-700 disabled:opacity-50">
                        Sí, eliminar
                    </button>
                </div>
            </div>
        </div>
        <!-- ===== FIN: MODAL DE CONFIRMACIÓN DE ELIMINACIÓN ===== -->

    </AuthenticatedLayout>
</template>

