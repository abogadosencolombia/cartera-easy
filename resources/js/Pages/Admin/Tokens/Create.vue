<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    proveedor: '',
    api_key: '',
    client_id: '',
    client_secret: '',
    activo: true,
});

const submit = () => {
    form.post(route('admin.tokens.store'));
};
</script>

<template>
    <Head title="Añadir Credencial" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Añadir Nueva Credencial de Integración
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
                                    <TextInput id="proveedor" type="text" class="mt-1 block w-full" v-model="form.proveedor" required autofocus />
                                    <InputError class="mt-2" :message="form.errors.proveedor" />
                                </div>

                                <div>
                                    <InputLabel for="api_key" value="API Key" />
                                    <TextInput id="api_key" type="password" class="mt-1 block w-full" v-model="form.api_key" />
                                    <InputError class="mt-2" :message="form.errors.api_key" />
                                </div>

                                <div>
                                    <InputLabel for="client_id" value="Client ID" />
                                    <TextInput id="client_id" type="text" class="mt-1 block w-full" v-model="form.client_id" />
                                    <InputError class="mt-2" :message="form.errors.client_id" />
                                </div>

                                <div>
                                    <InputLabel for="client_secret" value="Client Secret" />
                                    <TextInput id="client_secret" type="password" class="mt-1 block w-full" v-model="form.client_secret" />
                                    <InputError class="mt-2" :message="form.errors.client_secret" />
                                </div>

                                <div class="block">
                                    <label class="flex items-center">
                                        <input type="checkbox" v-model="form.activo" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" />
                                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Activo</span>
                                    </label>
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-6">
                                <Link :href="route('admin.tokens.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900">Cancelar</Link>
                                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Guardar Credencial
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
