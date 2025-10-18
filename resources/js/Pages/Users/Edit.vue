<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Multiselect from 'vue-multiselect';

// --- PROPS ---
// Se definen las propiedades que el controlador de Laravel envía al componente.
const props = defineProps({
    user: Object,
    allCooperativas: Array,
    allEspecialidades: Array,
    personas: Array,
});

// --- LÓGICA DE EDICIÓN ---
// Se comprueba si el administrador está editando su propia cuenta para deshabilitar campos críticos.
const authUser = usePage().props.auth.user;
const isEditingSelf = computed(() => props.user.id === authUser.id);

// --- FORMULARIO ---
// Se inicializa el formulario con los datos existentes del usuario.
const form = useForm({
    _method: 'patch',
    name: props.user.name,
    email: props.user.email,
    tipo_usuario: props.user.tipo_usuario,
    estado_activo: props.user.estado_activo,
    password: '',
    password_confirmation: '',
    // Se mapean los arrays de objetos a arrays de IDs para el Multiselect.
    cooperativas: props.user.cooperativas.map(c => c.id),
    especialidades: props.user.especialidades.map(e => e.id),
    persona_id: props.user.persona_id,
    preferencias_notificacion: props.user.preferencias_notificacion || { 'email': true, 'in-app': true },
});

// --- SUBMIT ---
// Lógica para enviar el formulario de actualización.
const submit = () => {
    form.post(route('admin.users.update', props.user.id), {
        preserveScroll: true,
    });
};
</script>

<!-- Estilos para Multiselect y su adaptación al tema oscuro -->
<style src="vue-multiselect/dist/vue-multiselect.css"></style>
<style>
/* Personalización para que el multiselect se integre con el tema oscuro y los colores de la app */

/* --- Contenedor principal y campo de texto --- */
.multiselect__tags {
    background-color: transparent !important;
    border-color: #4b5563; /* Corresponde a border-gray-600 en tema oscuro */
}
.multiselect__input, .multiselect__single {
    background-color: transparent !important;
}

/* --- Lista desplegable de opciones --- */
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
    background-color: #4f46e5; /* Mantiene el color de hover incluso si está seleccionado */
}

/* --- Etiquetas de los elementos ya seleccionados --- */
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
    <Head :title="'Editar Usuario: ' + user.name" />

    <AuthenticatedLayout>
        <!-- Encabezado de la página -->
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Editando Usuario: <span class="font-bold">{{ user.name }}</span>
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                                Actualice los datos del usuario
                            </h3>
                            <Link :href="route('admin.users.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">
                                &larr; Volver al listado
                            </Link>
                        </div>
                        
                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Nombre y Email -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="name" value="Nombre Completo" />
                                    <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required />
                                    <InputError :message="form.errors.name" />
                                </div>
                                <div>
                                    <InputLabel for="email" value="Email" />
                                    <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required />
                                    <InputError :message="form.errors.email" />
                                </div>
                            </div>
                            
                            <!-- Selector de Rol -->
                            <div>
                                <InputLabel for="tipo_usuario" value="Tipo de Usuario" />
                                <SelectInput v-model="form.tipo_usuario" id="tipo_usuario" class="mt-1 block w-full" :disabled="isEditingSelf">
                                    <option value="admin">Administrador</option>
                                    <option value="abogado">Abogado</option>
                                    <option value="gestor">Gestor</option>
                                    <option value="cliente">Cliente</option>
                                </SelectInput>
                                <p v-if="isEditingSelf" class="text-xs text-gray-500 dark:text-gray-400 mt-1">No puedes cambiar tu propio rol.</p>
                                <InputError :message="form.errors.tipo_usuario" />
                            </div>

                            <!-- Especialidades (Visible solo para Abogado o Gestor) -->
                            <div v-if="form.tipo_usuario === 'abogado' || form.tipo_usuario === 'gestor'">
                                <InputLabel value="Especialidad(es)" />
                                <Multiselect
                                    v-model="form.especialidades"
                                    :options="allEspecialidades.map(e => e.id)"
                                    :custom-label="opt => allEspecialidades.find(e => e.id === opt)?.nombre"
                                    :multiple="true"
                                    placeholder="Seleccione una o más especialidades"
                                />
                                <InputError :message="form.errors.especialidades" />
                            </div>
                            
                            <!-- Cooperativas (Visible para Abogado, Gestor y Cliente) -->
                            <div v-if="form.tipo_usuario === 'abogado' || form.tipo_usuario === 'gestor' || form.tipo_usuario === 'cliente'">
                                <InputLabel value="Cooperativas Asignadas" />
                                <Multiselect
                                    v-model="form.cooperativas"
                                    :options="allCooperativas.map(c => c.id)"
                                    :custom-label="opt => allCooperativas.find(c => c.id === opt)?.nombre"
                                    :multiple="true"
                                    placeholder="Seleccione una o más cooperativas"
                                />
                                <InputError :message="form.errors.cooperativas" />
                            </div>

                            <!-- Selector de Persona Asociada (Visible solo para Cliente) -->
                             <div v-if="form.tipo_usuario === 'cliente'">
                                 <InputLabel for="persona_id" value="Persona Asociada a esta Cuenta"/>
                                 <SelectInput v-model="form.persona_id" id="persona_id" class="mt-1 block w-full">
                                     <option :value="null">Ninguna / Desvincular</option>
                                     <option v-for="p in personas" :key="p.id" :value="p.id">{{ p.nombre_completo }} ({{ p.numero_documento }})</option>
                                 </SelectInput>
                                 <InputError :message="form.errors.persona_id" class="mt-2" />
                             </div>

                            <!-- Preferencias de Notificación -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Preferencias de Notificación</h3>
                                <div class="mt-4 space-y-3">
                                    <label class="flex items-center">
                                        <Checkbox v-model:checked="form.preferencias_notificacion['in-app']" />
                                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-300">Recibir notificaciones en la aplicación</span>
                                    </label>
                                    <label class="flex items-center">
                                        <Checkbox v-model:checked="form.preferencias_notificacion.email" />
                                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-300">Recibir notificaciones por correo</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Contraseña y Estado -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                     <div>
                                         <InputLabel for="password" value="Nueva Contraseña (Opcional)" />
                                         <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" />
                                         <InputError :message="form.errors.password" />
                                     </div>
                                     <div>
                                         <InputLabel for="password_confirmation" value="Confirmar Nueva Contraseña" />
                                         <TextInput id="password_confirmation" type="password" class="mt-1 block w-full" v-model="form.password_confirmation" />
                                     </div>
                                </div>
                                <div class="block">
                                     <label class="flex items-center">
                                         <Checkbox v-model:checked="form.estado_activo" :disabled="isEditingSelf" />
                                         <span class="ms-2 text-sm text-gray-600 dark:text-gray-300">Usuario Activo</span>
                                     </label>
                                     <p v-if="isEditingSelf" class="text-xs text-gray-500 dark:text-gray-400 mt-1">No puedes desactivar tu propia cuenta.</p>
                                </div>
                            </div>

                            <!-- Botones de Acción -->
                            <div class="flex items-center justify-end mt-4">
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Actualizar Usuario
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

