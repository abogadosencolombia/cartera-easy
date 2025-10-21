<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    token: Object,
});

const form = useForm({
    proveedor: props.token.proveedor,
    api_key: '', 
    client_id: props.token.client_id,
    client_secret: '',
    // ===== ¡LA CORRECCIÓN ESTÁ AQUÍ! =====
    // Convertimos el valor (que puede ser 1 o 0) a un booleano (true o false).
    activo: Boolean(props.token.activo),
});

const submit = () => {
    form.patch(route('admin.tokens.update', props.token.id));
};
</script>

<template>
    <!-- El resto del template sigue exactamente igual, no necesita cambios -->
    <Head title="Editar Credencial" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Editar Credencial: {{ token.proveedor }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <InputLabel for="proveedor" value="Nombre del Proveedor" />
                                    <TextInput id="proveedor" type="text" class="mt-1 block w-full" v-model="form.proveedor" required />
                                    <InputError class="mt-2" :message="form.errors.proveedor" />
                                </div>

                                <div>
                                    <InputLabel for="api_key" value="Nueva API Key (dejar en blanco para no cambiar)" />
                                    <TextInput id="api_key" type="password" class="mt-1 block w-full" v-model="form.api_key" />
                                    <InputError class="mt-2" :message="form.errors.api_key" />
                                </div>

                                <div>
                                    <InputLabel for="client_id" value="Client ID" />
                                    <TextInput id="client_id" type="text" class="mt-1 block w-full" v-model="form.client_id" />
                                    <InputError class="mt-2" :message="form.errors.client_id" />
                                </div>

                                <div>
                                    <InputLabel for="client_secret" value="Nuevo Client Secret (dejar en blanco para no cambiar)" />
                                    <TextInput id="client_secret" type="password" class="mt-1 block w-full" v-model="form.client_secret" />
                                    <InputError class="mt-2" :message="form.errors.client_secret" />
                                </div>

                                <div class="block">
                                    <label class="flex items-center">
                                        <!-- El 'v-model' ya es suficiente. No necesitamos :checked. -->
                                        <input type="checkbox" v-model="form.activo" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" />
                                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Activo</span>
                                    </label>
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-6">
                                <Link :href="route('admin.tokens.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900">Cancelar</Link>
                                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Actualizar Credencial
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
