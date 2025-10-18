<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue'; // Asumo que tienes un componente Textarea
import SelectInput from '@/Components/SelectInput.vue'; // Y uno para Select
import { Head, useForm } from '@inertiajs/vue3';

// Recibimos los usuarios que el Controlador nos envió
const props = defineProps({
    usuarios: Array,
});

// Usamos el hook de formularios de Inertia. Es la forma moderna de manejarlo.
const form = useForm({
    asunto: '',
    descripcion: '',
    usuario_responsable_id: '',
    origen: 'manual', // Valor por defecto
});

// Función que se llama al enviar el formulario
const submit = () => {
    form.post(route('admin.incidentes-juridicos.store'), {
        onFinish: () => form.reset('asunto', 'descripcion', 'usuario_responsable_id'),
    });
};
</script>

<template>
    <Head title="Registrar Incidente Jurídico" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Registrar Nuevo Incidente Jurídico o Disciplinario
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        
                        <form @submit.prevent="submit">
                            <div>
                                <InputLabel for="asunto" value="Asunto del Incidente" />
                                <TextInput id="asunto" type="text" class="mt-1 block w-full" v-model="form.asunto" required autofocus />
                                <InputError class="mt-2" :message="form.errors.asunto" />
                            </div>

                            <div class="mt-4">
                                <InputLabel for="descripcion" value="Descripción Detallada" />
                                <Textarea id="descripcion" class="mt-1 block w-full" v-model="form.descripcion" required rows="5" />
                                <InputError class="mt-2" :message="form.errors.descripcion" />
                            </div>

                            <div class="mt-4">
                                <InputLabel for="usuario_responsable_id" value="Usuario Principalmente Implicado" />
                                <SelectInput id="usuario_responsable_id" class="mt-1 block w-full" v-model="form.usuario_responsable_id" required>
                                    <option value="" disabled>-- Seleccione un usuario --</option>
                                    <option v-for="usuario in usuarios" :key="usuario.id" :value="usuario.id">
                                        {{ usuario.name }}
                                    </option>
                                </SelectInput>
                                <InputError class="mt-2" :message="form.errors.usuario_responsable_id" />
                            </div>

                            <div class="flex items-center justify-end mt-6">
                                <PrimaryButton class="ms-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Registrar Incidente
                                </PrimaryButton>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>