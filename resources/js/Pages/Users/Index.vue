<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { 
    UserIcon, BriefcaseIcon, ShieldCheckIcon, BuildingOffice2Icon, // Roles
    PencilSquareIcon, LockClosedIcon, LockOpenIcon, EyeIcon // <-- 1. ICONO AÑADIDO
} from '@heroicons/vue/24/outline';

// --- PROPS ---
const props = defineProps({
    users: Array,
});

// --- FILTROS Y BÚSQUEDA ---
const searchQuery = ref('');
const roleFilter = ref('');
const statusFilter = ref('');

const filteredUsers = computed(() => {
    let filtered = props.users;

    // Filtro por Rol
    if (roleFilter.value) {
        filtered = filtered.filter(user => user.tipo_usuario === roleFilter.value);
    }
    // Filtro por Estado
    if (statusFilter.value !== '') {
        const isActive = statusFilter.value === '1';
        filtered = filtered.filter(user => user.estado_activo === isActive);
    }
    // Filtro por Búsqueda de texto
    if (searchQuery.value) {
        filtered = filtered.filter(user =>
            user.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            user.email.toLowerCase().includes(searchQuery.value.toLowerCase())
        );
    }
    return filtered;
});

// --- LÓGICA DE ROLES ---
const roleDisplay = {
    admin: { icon: ShieldCheckIcon, color: 'text-red-500', label: 'Administrador' },
    abogado: { icon: BriefcaseIcon, color: 'text-sky-500', label: 'Abogado' },
    gestor: { icon: BuildingOffice2Icon, color: 'text-teal-500', label: 'Gestor' },
    cliente: { icon: UserIcon, color: 'text-gray-400', label: 'Cliente' },
};

// --- MENSAJES FLASH ---
const flash = ref(usePage().props.flash);
watch(() => usePage().props.flash, (newFlash) => {
    flash.value = newFlash;
    if (newFlash.success || newFlash.error) {
        setTimeout(() => {
            flash.value.success = null;
            flash.value.error = null;
        }, 3000);
    }
});

// --- HELPERS ---
const getInitials = (name) => {
    const names = name.split(' ');
    if (names.length > 1) {
        return `${names[0][0]}${names[1][0]}`.toUpperCase();
    }
    return `${names[0][0]}`.toUpperCase();
};
</script>

<template>
    <Head title="Gestión de Usuarios" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Gestión de Usuarios del Sistema
                </h2>
                <Link :href="route('admin.users.create')" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Añadir Usuario
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="flash.success" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ flash.success }}</span>
                </div>
                <div v-if="flash.error" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ flash.error }}</span>
                </div>
                
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <TextInput 
                            id="search" 
                            type="text" 
                            class="block w-full md:col-span-1" 
                            v-model="searchQuery" 
                            placeholder="Buscar por nombre o email..." 
                        />
                        <SelectInput v-model="roleFilter" class="block w-full">
                            <option value="">Filtrar por Rol</option>
                            <option value="admin">Administrador</option>
                            <option value="abogado">Abogado</option>
                            <option value="gestor">Gestor</option>
                            <option value="cliente">Cliente</option>
                        </SelectInput>
                         <SelectInput v-model="statusFilter" class="block w-full">
                            <option value="">Filtrar por Estado</option>
                            <option value="1">Activo</option>
                            <option value="0">Suspendido</option>
                        </SelectInput>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Usuario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Rol y Especialidades</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Cooperativa(s)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Estado</th>
                                    <th class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-if="filteredUsers.length === 0">
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No se encontraron usuarios que coincidan con los filtros.
                                    </td>
                                </tr>
                                <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center">
                                                    <span class="font-bold text-indigo-600 dark:text-indigo-300">{{ getInitials(user.name) }}</span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center text-sm">
                                            <component :is="roleDisplay[user.tipo_usuario].icon" :class="roleDisplay[user.tipo_usuario].color" class="h-5 w-5 mr-2 flex-shrink-0" />
                                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ roleDisplay[user.tipo_usuario].label }}</span>
                                        </div>
                                        <div v-if="user.especialidades_display !== 'Ninguna'" class="text-xs text-gray-500 dark:text-gray-400 mt-1 pl-7">
                                            {{ user.especialidades_display }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ user.cooperativas_display }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="user.estado_activo ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            {{ user.estado_activo ? 'Activo' : 'Suspendido' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end items-center space-x-2">
                                            <Link :href="route('admin.users.show', user.id)" class="text-gray-400 hover:text-sky-600 dark:hover:text-sky-400" title="Ver Detalles">
                                                <EyeIcon class="h-5 w-5"/>
                                            </Link>
                                            
                                            <Link :href="route('admin.users.edit', user.id)" class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400" title="Editar">
                                                <PencilSquareIcon class="h-5 w-5"/>
                                            </Link>
                                            
                                            <template v-if="user.id !== usePage().props.auth.user.id">
                                                <Link v-if="user.estado_activo" :href="route('admin.users.toggle-status', user.id)" method="patch" as="button" class="text-gray-400 hover:text-yellow-600 dark:hover:text-yellow-400" title="Suspender">
                                                    <LockClosedIcon class="h-5 w-5"/>
                                                </Link>
                                                <Link v-else :href="route('admin.users.toggle-status', user.id)" method="patch" as="button" class="text-gray-400 hover:text-green-600 dark:hover:text-green-400" title="Reactivar">
                                                    <LockOpenIcon class="h-5 w-5"/>
                                                </Link>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>