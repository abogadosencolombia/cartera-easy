<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';

// --- PROPS ---
// Se definen las propiedades que el controlador de Laravel envía al componente.
defineProps({
    allCooperativas: Array,
    allEspecialidades: Array,
    personas: Array,
});

// --- FORMULARIO ---
// Se inicializa el formulario con todos los campos necesarios.
const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    tipo_usuario: 'cliente', // El rol por defecto es 'cliente'
    cooperativas: [],
    especialidades: [], // Nuevo campo para las especialidades
    persona_id: null,
});

// --- SUBMIT ---
// Lógica para enviar el formulario al backend.
const submit = () => {
    form.post(route('admin.users.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<!-- Estilos para Multiselect y su adaptación al tema oscuro -->
<style src="vue-multiselect/dist/vue-multiselect.css"></style>
<style>
/* Personalización para que el multiselect se integre con el tema oscuro y los colores de la app */
.multiselect__tags {
    background-color: transparent !important;
    border-color: #4b5563; /* Corresponde a border-gray-600 en tema oscuro */
}
.multiselect__input, .multiselect__single {
    background-color: transparent !important;
}
.multiselect__content-wrapper {
    background-color: #1f2937; /* Corresponde a bg-gray-800 */
    color: #d1d5db; /* Corresponde a text-gray-300 */
    border-color: #4b5563; /* Corresponde a border-gray-600 */
}
.multiselect__option--highlight {
    background-color: #4f46e5; /* Corresponde a bg-indigo-600 */
}
.multiselect__option--selected {
    background-color: #3730a3; /* Corresponde a bg-indigo-800 */
}
.multiselect__option--selected.multiselect__option--highlight {
    background-color: #4f46e5;
}
.multiselect__tag {
    background-color: #4338ca; /* Corresponde a bg-indigo-700 */
    color: #e0e7ff; /* Corresponde a text-indigo-100 */
}
.multiselect__tag-icon:focus,
.multiselect__tag-icon:hover {
    background-color: #312e81; /* Corresponde a bg-indigo-900 */
}
.multiselect__tag-icon::after {
    color: #a5b4fc; /* Corresponde a text-indigo-300 */
}
</style>


<template>
    <Head title="Crear Usuario" />

    <AuthenticatedLayout>
        <!-- Encabezado de la página -->
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Registrar Nuevo Usuario
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">
                        
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                                Complete los datos del nuevo usuario
                            </h3>
                            <Link :href="route('admin.users.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">
                                &larr; Volver al listado
                            </Link>
                        </div>

                        <form @submit.prevent="submit" class="mt-6 space-y-6">
                            
                            <!-- SECCIÓN 1: DATOS DE ACCESO -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Datos de Acceso</h3>
                                <div class="mt-4 space-y-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <InputLabel for="name" value="Nombre Completo" />
                                            <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
                                            <InputError class="mt-2" :message="form.errors.name" />
                                        </div>
                                        <div>
                                            <InputLabel for="email" value="Email" />
                                            <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required />
                                            <InputError class="mt-2" :message="form.errors.email" />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <InputLabel for="password" value="Contraseña" />
                                            <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" required />
                                            <InputError class="mt-2" :message="form.errors.password" />
                                        </div>
                                        <div>
                                            <InputLabel for="password_confirmation" value="Confirmar Contraseña" />
                                            <TextInput id="password_confirmation" type="password" class="mt-1 block w-full" v-model="form.password_confirmation" required />
                                            <InputError class="mt-2" :message="form.errors.password_confirmation" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SECCIÓN 2: ROL Y ASIGNACIONES -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Rol y Asignaciones</h3>
                                <div class="mt-4 space-y-6">
                                    <div>
                                        <InputLabel for="tipo_usuario" value="Tipo de Usuario" />
                                        <select id="tipo_usuario" v-model="form.tipo_usuario" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                            <option value="admin">Administrador</option>
                                            <option value="gestor">Gestor</option>
                                            <option value="abogado">Abogado</option>
                                            <option value="cliente">Cliente</option>
                                        </select>
                                        <InputError class="mt-2" :message="form.errors.tipo_usuario" />
                                    </div>
                                    
                                    <div v-if="form.tipo_usuario === 'abogado' || form.tipo_usuario === 'gestor'">
                                        <InputLabel value="Especialidad(es)" />
                                        <Multiselect
                                            v-model="form.especialidades"
                                            :options="allEspecialidades.map(e => e.id)"
                                            :custom-label="opt => allEspecialidades.find(e => e.id === opt)?.nombre"
                                            :multiple="true"
                                            placeholder="Seleccione una o más especialidades"
                                        />
                                        <InputError :message="form.errors.especialidades" class="mt-2" />
                                    </div>

                                    <div v-if="form.tipo_usuario !== 'admin'">
                                        <InputLabel value="Asignar a Cooperativa(s)" />
                                        <Multiselect
                                            v-model="form.cooperativas"
                                            :options="allCooperativas.map(c => c.id)"
                                            :custom-label="opt => allCooperativas.find(c => c.id === opt)?.nombre"
                                            :multiple="true"
                                            placeholder="Seleccione una o más cooperativas"
                                        />
                                        <InputError :message="form.errors.cooperativas" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <!-- Botón de Registro -->
                            <div class="flex items-center justify-end mt-4 border-t border-gray-200 dark:border-gray-700 pt-6">
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Registrar Usuario
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

