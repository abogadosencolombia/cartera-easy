<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
// ===== ¡LA CORRECCIÓN ESTÁ AQUÍ! =====
// Añadimos 'router' a la lista de importaciones desde Inertia.
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
// Añade estas líneas junto a tus otras importaciones de componentes
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// Define props received from the controller
const props = defineProps({
    tokens: Object,
});

// Flash message handling
const flash = ref(usePage().props.flash);
watch(() => usePage().props.flash, (newFlash) => {
    flash.value = newFlash;
    if (newFlash.success) {
        setTimeout(() => {
            flash.value.success = null;
        }, 3000);
    }
});

// Confirmation modal for deletion
const showConfirmModal = ref(false);
const tokenToDelete = ref(null);

const confirmDeletion = (token) => {
    tokenToDelete.value = token;
    showConfirmModal.value = true;
};

const deleteToken = () => {
    if (tokenToDelete.value) {
        // Ahora 'router' está definido y esta línea funcionará.
        router.delete(route('admin.tokens.destroy', tokenToDelete.value.id), {
            onFinish: () => {
                showConfirmModal.value = false;
                tokenToDelete.value = null;
            }
        });
    }
};
</script>

<template>
    <!-- El resto del template sigue exactamente igual, no necesita cambios -->
    <Head title="Gestión de Credenciales" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Gestión de Credenciales de Integración
                </h2>
                <Link :href="route('admin.tokens.create')" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Añadir Credencial
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Flash Message -->
                <div v-if="flash.success" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ flash.success }}</span>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Proveedor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Última Actualización</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                <tr v-for="token in tokens.data" :key="token.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">{{ token.proveedor }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span :class="token.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            {{ token.activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(token.updated_at).toLocaleString() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">
                                        <div class="flex justify-end items-center space-x-2">
                                            <Link :href="route('admin.tokens.edit', token.id)"
                                                class="inline-flex items-center px-3 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none disabled:opacity-25 transition ease-in-out duration-150">
                                                Editar
                                            </Link>
                                            
                                            <DangerButton @click="confirmDeletion(token)" class="!px-3 !py-1 !text-xs">
                                                Eliminar
                                            </DangerButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deletion Confirmation Modal -->
        <Modal :show="showConfirmModal" @close="showConfirmModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Confirmar Eliminación
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400" v-if="tokenToDelete">
                    ¿Estás seguro de que deseas eliminar la credencial para "<span class="font-bold">{{ tokenToDelete.proveedor }}</span>"? Esta acción no se puede deshacer.
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showConfirmModal = false"> Cancelar </SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteToken">
                        Sí, Eliminar
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
