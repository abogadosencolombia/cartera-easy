<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    nombre: '',
    email: '',
    telefono: '',
    municipio: '',
    departamento: '',
    distrito: '',
});

const submit = () => {
    form.post(route('juzgados.store'));
};
</script>

<template>
    <Head title="Crear Juzgado" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Crear Nuevo Despacho
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
                    
                    <form @submit.prevent="submit" class="space-y-6">
                        
                        <div>
                            <InputLabel for="nombre" value="Nombre del Despacho" />
                            <TextInput id="nombre" type="text" class="mt-1 block w-full" v-model="form.nombre" required autofocus placeholder="Ej: Juzgado 01 Civil Municipal de Bogotá" />
                            <InputError class="mt-2" :message="form.errors.nombre" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="email" value="Correo Electrónico" />
                                <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" placeholder="correo@cendoj.ramajudicial.gov.co" />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <div>
                                <InputLabel for="telefono" value="Teléfono" />
                                <TextInput id="telefono" type="text" class="mt-1 block w-full" v-model="form.telefono" />
                                <InputError class="mt-2" :message="form.errors.telefono" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <InputLabel for="municipio" value="Municipio" />
                                <TextInput id="municipio" type="text" class="mt-1 block w-full" v-model="form.municipio" />
                            </div>
                            <div>
                                <InputLabel for="departamento" value="Departamento" />
                                <TextInput id="departamento" type="text" class="mt-1 block w-full" v-model="form.departamento" />
                            </div>
                            <div>
                                <InputLabel for="distrito" value="Distrito Judicial" />
                                <TextInput id="distrito" type="text" class="mt-1 block w-full" v-model="form.distrito" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-4 border-t pt-4 border-gray-100 dark:border-gray-700">
                            <Link :href="route('juzgados.index')" class="text-sm text-gray-600 hover:underline">Cancelar</Link>
                            <PrimaryButton :disabled="form.processing">Guardar Juzgado</PrimaryButton>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>