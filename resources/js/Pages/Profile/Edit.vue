<script setup>
import { ref, computed, nextTick } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// Importación de todos los componentes necesarios
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';

// --- PROPS ---
defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

// --- LÓGICA CENTRAL ---
const user = usePage().props.auth.user;
const isAdmin = computed(() => user.role === 'admin');
const activeTab = ref('profile'); // 'profile', 'password', 'notifications', 'admin'

const setActiveTab = (tab) => {
    activeTab.value = tab;
};

// --- LÓGICA PARA INFORMACIÓN DEL PERFIL ---
const profileForm = useForm({
    name: user.name,
    email: user.email,
});
const submitProfile = () => profileForm.patch(route('profile.update'), { preserveScroll: true });

// --- LÓGICA PARA PREFERENCIAS DE NOTIFICACIÓN ---
const notificationForm = useForm({
    preferencias_notificacion: {
        email: user.preferencias_notificacion?.email ?? true,
        system: user.preferencias_notificacion?.system ?? true,
    },
});
const submitNotifications = () => notificationForm.patch(route('profile.update'), { preserveScroll: true });

// --- LÓGICA PARA ACTUALIZAR CONTRASEÑA ---
const passwordInput = ref(null);
const currentPasswordInput = ref(null);
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const updatePassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => {
            if (passwordForm.errors.password) {
                passwordForm.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (passwordForm.errors.current_password) {
                passwordForm.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};

// --- LÓGICA PARA BORRAR CUENTA ---
const confirmingUserDeletion = ref(false);
const deletePasswordInput = ref(null);
const deleteForm = useForm({ password: '' });

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => deletePasswordInput.value.focus());
};
const deleteUser = () => {
    deleteForm.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => deletePasswordInput.value.focus(),
        onFinish: () => deleteForm.reset(),
    });
};
const closeModal = () => {
    confirmingUserDeletion.value = false;
    deleteForm.reset();
    deleteForm.clearErrors();
};
</script>

<template>
    <Head title="Perfil de Usuario" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Configuración del Perfil
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg overflow-hidden">
                    <div class="flex flex-col md:flex-row">
                        <!-- Barra Lateral de Navegación (Tabs) -->
                        <nav class="md:w-1/4 bg-gray-50 dark:bg-gray-800 p-6 border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-700">
                            <ul class="space-y-2">
                                <li>
                                    <button @click="setActiveTab('profile')" :class="['w-full text-left flex items-center gap-3 px-4 py-2 rounded-md transition-all duration-200', activeTab === 'profile' ? 'bg-indigo-500 text-white shadow-md' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700']">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg>
                                        <span>Perfil</span>
                                    </button>
                                </li>
                                <li>
                                    <button @click="setActiveTab('password')" :class="['w-full text-left flex items-center gap-3 px-4 py-2 rounded-md transition-all duration-200', activeTab === 'password' ? 'bg-indigo-500 text-white shadow-md' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700']">
                                       <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" /></svg>
                                        <span>Contraseña</span>
                                    </button>
                                </li>
                                <li>
                                    <button @click="setActiveTab('notifications')" :class="['w-full text-left flex items-center gap-3 px-4 py-2 rounded-md transition-all duration-200', activeTab === 'notifications' ? 'bg-indigo-500 text-white shadow-md' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700']">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" /></svg>
                                        <span>Notificaciones</span>
                                    </button>
                                </li>
                                <li v-if="isAdmin">
                                    <button @click="setActiveTab('admin')" :class="['w-full text-left flex items-center gap-3 px-4 py-2 rounded-md transition-all duration-200', activeTab === 'admin' ? 'bg-red-600 text-white shadow-md' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700']">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                        <span>Administración</span>
                                    </button>
                                </li>
                            </ul>
                        </nav>

                        <!-- Contenido Principal -->
                        <main class="w-full p-8 md:p-10">
                            <!-- SECCIÓN: PERFIL -->
                            <div v-show="activeTab === 'profile'">
                                <section>
                                    <header>
                                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Información del Perfil</h2>
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            Aquí puedes ver tu información personal. Solo un administrador puede modificarla.
                                        </p>
                                    </header>
                                    <form @submit.prevent="submitProfile" class="mt-8 space-y-6">
                                        <div>
                                            <InputLabel for="name" value="Nombre" />
                                            <TextInput id="name" type="text" class="mt-1 block w-full disabled:bg-gray-100 dark:disabled:bg-gray-700" v-model="profileForm.name" required autofocus autocomplete="name" :disabled="!isAdmin" />
                                            <InputError class="mt-2" :message="profileForm.errors.name" />
                                        </div>
                                        <div>
                                            <InputLabel for="email" value="Correo Electrónico" />
                                            <TextInput id="email" type="email" class="mt-1 block w-full disabled:bg-gray-100 dark:disabled:bg-gray-700" v-model="profileForm.email" required autocomplete="username" :disabled="!isAdmin" />
                                            <InputError class="mt-2" :message="profileForm.errors.email" />
                                        </div>
                                        <div v-if="isAdmin" class="flex items-center gap-4">
                                            <PrimaryButton :disabled="profileForm.processing">Guardar Cambios</PrimaryButton>
                                            <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                                                <p v-if="profileForm.recentlySuccessful" class="text-sm text-green-600 dark:text-green-400">Guardado.</p>
                                            </Transition>
                                        </div>
                                    </form>
                                </section>
                            </div>

                            <!-- SECCIÓN: CONTRASEÑA -->
                             <div v-show="activeTab === 'password'">
                                <section>
                                    <header>
                                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Actualizar Contraseña</h2>
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Asegúrate de que tu cuenta utiliza una contraseña larga y aleatoria para mantenerla segura.</p>
                                    </header>
                                    <form @submit.prevent="updatePassword" class="mt-8 space-y-6">
                                       <!-- Fields... -->
                                       <div>
                                            <InputLabel for="current_password" value="Contraseña Actual" />
                                            <TextInput id="current_password" ref="currentPasswordInput" v-model="passwordForm.current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                                            <InputError :message="passwordForm.errors.current_password" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel for="password" value="Nueva Contraseña" />
                                            <TextInput id="password" ref="passwordInput" v-model="passwordForm.password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                                            <InputError :message="passwordForm.errors.password" class="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel for="password_confirmation" value="Confirmar Contraseña" />
                                            <TextInput id="password_confirmation" v-model="passwordForm.password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                                            <InputError :message="passwordForm.errors.password_confirmation" class="mt-2" />
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <PrimaryButton :disabled="passwordForm.processing">Actualizar Contraseña</PrimaryButton>
                                            <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                                                <p v-if="passwordForm.recentlySuccessful" class="text-sm text-green-600 dark:text-green-400">Guardado.</p>
                                            </Transition>
                                        </div>
                                    </form>
                                </section>
                            </div>

                             <!-- SECCIÓN: NOTIFICACIONES -->
                             <div v-show="activeTab === 'notifications'">
                                <section>
                                    <header>
                                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Preferencias de Notificación</h2>
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Seleccione cómo desea recibir las alertas automáticas del sistema.</p>
                                    </header>
                                    <form @submit.prevent="submitNotifications" class="mt-8 space-y-6">
                                        <div class="space-y-4">
                                            <label class="flex items-center p-3 rounded-lg transition-colors" :class="[isAdmin ? 'hover:bg-gray-100 dark:hover:bg-gray-700' : '']">
                                                <Checkbox v-model:checked="notificationForm.preferencias_notificacion.system" :disabled="!isAdmin" />
                                                <span class="ms-3 text-sm text-gray-700 dark:text-gray-300">Notificaciones del Sistema</span>
                                            </label>
                                            <label class="flex items-center p-3 rounded-lg transition-colors" :class="[isAdmin ? 'hover:bg-gray-100 dark:hover:bg-gray-700' : '']">
                                                <Checkbox v-model:checked="notificationForm.preferencias_notificacion.email" :disabled="!isAdmin" />
                                                <span class="ms-3 text-sm text-gray-700 dark:text-gray-300">Notificaciones por Correo Electrónico</span>
                                            </label>
                                        </div>
                                        <div v-if="isAdmin" class="flex items-center gap-4 mt-6">
                                            <PrimaryButton :disabled="notificationForm.processing">Guardar Preferencias</PrimaryButton>
                                            <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                                                <p v-if="notificationForm.recentlySuccessful" class="text-sm text-green-600 dark:text-green-400">Guardado.</p>
                                            </Transition>
                                        </div>
                                    </form>
                                </section>
                            </div>

                            <!-- SECCIÓN: ADMINISTRACIÓN -->
                            <div v-show="activeTab === 'admin'">
                                <section class="space-y-6">
                                    <header>
                                        <h2 class="text-2xl font-bold text-red-600 dark:text-red-500">Zona de Peligro</h2>
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            Las acciones en esta sección son permanentes y no se pueden deshacer.
                                        </p>
                                    </header>
                                    <div class="p-4 border border-red-300 rounded-lg">
                                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">Borrar Cuenta de Usuario</h3>
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Una vez que la cuenta sea eliminada, todos sus recursos y datos serán borrados permanentemente.</p>
                                        <DangerButton @click="confirmUserDeletion" class="mt-4">Borrar Cuenta</DangerButton>
                                    </div>
                                    
                                    <Modal :show="confirmingUserDeletion" @close="closeModal">
                                        <div class="p-6 bg-white dark:bg-gray-800">
                                            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">¿Estás seguro?</h2>
                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Introduce tu contraseña para confirmar la eliminación permanente de esta cuenta.</p>
                                            <div class="mt-6">
                                                <InputLabel for="delete-password" value="Contraseña" class="sr-only" />
                                                <TextInput id="delete-password" ref="deletePasswordInput" v-model="deleteForm.password" type="password" class="mt-1 block w-3/4" placeholder="Contraseña" @keyup.enter="deleteUser" />
                                                <InputError :message="deleteForm.errors.password" class="mt-2" />
                                            </div>
                                            <div class="mt-6 flex justify-end">
                                                <SecondaryButton @click="closeModal"> Cancelar </SecondaryButton>
                                                <DangerButton class="ms-3" :class="{ 'opacity-25': deleteForm.processing }" :disabled="deleteForm.processing" @click="deleteUser">Confirmar Eliminación</DangerButton>
                                            </div>
                                        </div>
                                    </Modal>
                                </section>
                            </div>
                        </main>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
