<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { 
    UserIcon, BriefcaseIcon, ShieldCheckIcon, BuildingOffice2Icon, 
    PencilSquareIcon, LockClosedIcon, LockOpenIcon, EyeIcon, 
    ChevronDownIcon, PlusIcon, UsersIcon, MagnifyingGlassIcon
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

    if (roleFilter.value) {
        filtered = filtered.filter(user => user.tipo_usuario === roleFilter.value);
    }
    if (statusFilter.value !== '') {
        const isActive = statusFilter.value === '1';
        filtered = filtered.filter(user => user.estado_activo === isActive);
    }
    if (searchQuery.value) {
        filtered = filtered.filter(user =>
            user.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            user.email.toLowerCase().includes(searchQuery.value.toLowerCase())
        );
    }
    return filtered;
});

// --- ESTADÍSTICAS ---
const stats = computed(() => {
    return {
        total: props.users.length,
        activos: props.users.filter(u => u.estado_activo).length,
        suspendidos: props.users.filter(u => !u.estado_activo).length,
    };
});

// --- LÓGICA DE ROLES ---
const roleDisplay = {
    admin: { icon: ShieldCheckIcon, color: 'text-rose-500', bg: 'bg-rose-100 dark:bg-rose-900/30', label: 'Administrador' },
    abogado: { icon: BriefcaseIcon, color: 'text-indigo-500', bg: 'bg-indigo-100 dark:bg-indigo-900/30', label: 'Abogado' },
    gestor: { icon: BuildingOffice2Icon, color: 'text-emerald-500', bg: 'bg-emerald-100 dark:bg-emerald-900/30', label: 'Gestor' },
    cliente: { icon: UserIcon, color: 'text-slate-400', bg: 'bg-slate-100 dark:bg-slate-900/30', label: 'Cliente' },
};

// --- HELPERS ---
const getInitials = (name) => {
    if (!name) return '??';
    const names = name.split(' ');
    if (names.length > 1) return `${names[0][0]}${names[1][0]}`.toUpperCase();
    return `${names[0][0]}`.toUpperCase();
};

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
</script>

<template>
    <Head title="Gestión de Usuarios" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">
                        Usuarios del Sistema
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Administración central de accesos y perfiles.</p>
                </div>
                <Link :href="route('admin.users.create')" class="inline-flex items-center px-5 py-2.5 bg-indigo-600 border border-transparent rounded-xl font-bold text-sm text-white hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200 dark:shadow-none">
                    <PlusIcon class="h-5 w-5 mr-2"/>
                    Nuevo Usuario
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <!-- TARJETAS DE ESTADÍSTICAS -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center">
                        <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 mr-4">
                            <UsersIcon class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Total Usuarios</p>
                            <p class="text-2xl font-black text-gray-900 dark:text-white">{{ stats.total }}</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center">
                        <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 mr-4">
                            <ShieldCheckIcon class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Cuentas Activas</p>
                            <p class="text-2xl font-black text-gray-900 dark:text-white">{{ stats.activos }}</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center">
                        <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-900/20 mr-4">
                            <LockClosedIcon class="h-6 w-6 text-rose-600 dark:text-rose-400" />
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Suspendidos</p>
                            <p class="text-2xl font-black text-gray-900 dark:text-white">{{ stats.suspendidos }}</p>
                        </div>
                    </div>
                </div>

                <!-- ALERTAS -->
                <div v-if="flash.success" class="bg-emerald-50 border-l-4 border-emerald-400 p-4 rounded-r-xl shadow-sm flex items-center">
                    <ShieldCheckIcon class="h-5 w-5 text-emerald-500 mr-3" />
                    <span class="text-emerald-800 text-sm font-bold">{{ flash.success }}</span>
                </div>

                <!-- FILTROS Y TABLA -->
                <div class="bg-white dark:bg-gray-800 shadow-xl shadow-slate-200/50 dark:shadow-none sm:rounded-3xl overflow-visible border border-gray-100 dark:border-gray-700">
                    <div class="p-8 border-b border-gray-100 dark:border-gray-700 overflow-visible relative z-50">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                            <div class="md:col-span-2 relative">
                                <label class="block text-xs font-black text-gray-400 uppercase mb-2 ml-1">Buscador Inteligente</label>
                                <div class="relative">
                                    <TextInput id="search" type="text" class="block w-full pl-10 bg-gray-50 border-transparent focus:bg-white" v-model="searchQuery" placeholder="Nombre, email o cargo..." />
                                    <div class="absolute left-3.5 top-2.5">
                                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                                    </div>
                                </div>
                            </div>
                            
                            <div class="relative">
                                <label class="block text-xs font-black text-gray-400 uppercase mb-2 ml-1">Filtrar por Rol</label>
                                <Dropdown align="left" width="full">
                                    <template #trigger>
                                        <button type="button" class="flex w-full items-center justify-between rounded-xl border border-gray-200 dark:border-gray-700 dark:bg-gray-900 bg-white px-4 py-2.5 text-sm font-bold shadow-sm hover:border-indigo-500 transition-all dark:text-gray-300">
                                            <span>{{ roleFilter === '' ? 'Todos' : roleDisplay[roleFilter].label }}</span>
                                            <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                                        </button>
                                    </template>
                                    <template #content>
                                        <div class="py-1 bg-white dark:bg-gray-800 shadow-2xl ring-1 ring-black ring-opacity-5 rounded-xl">
                                            <button @click="roleFilter = ''" class="block w-full text-left px-4 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-900/20" :class="{ 'text-indigo-600 font-bold': roleFilter === '' }">Todos los Roles</button>
                                            <button v-for="(info, key) in roleDisplay" :key="key" @click="roleFilter = key" class="block w-full text-left px-4 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-900/20" :class="{ 'text-indigo-600 font-bold': roleFilter === key }">
                                                {{ info.label }}
                                            </button>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <div class="relative">
                                <label class="block text-xs font-black text-gray-400 uppercase mb-2 ml-1">Estado</label>
                                <Dropdown align="left" width="full">
                                    <template #trigger>
                                        <button type="button" class="flex w-full items-center justify-between rounded-xl border border-gray-200 dark:border-gray-700 dark:bg-gray-900 bg-white px-4 py-2.5 text-sm font-bold shadow-sm hover:border-indigo-500 transition-all dark:text-gray-300">
                                            <span>{{ statusFilter === '' ? 'Cualquiera' : (statusFilter === '1' ? 'Activos' : 'Suspendidos') }}</span>
                                            <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                                        </button>
                                    </template>
                                    <template #content>
                                        <div class="py-1 bg-white dark:bg-gray-800 shadow-2xl ring-1 ring-black ring-opacity-5 rounded-xl">
                                            <button @click="statusFilter = ''" class="block w-full text-left px-4 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-900/20" :class="{ 'text-indigo-600 font-bold': statusFilter === '' }">Cualquiera</button>
                                            <button @click="statusFilter = '1'" class="block w-full text-left px-4 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-900/20" :class="{ 'text-indigo-600 font-bold': statusFilter === '1' }">Activos</button>
                                            <button @click="statusFilter = '0'" class="block w-full text-left px-4 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-900/20" :class="{ 'text-indigo-600 font-bold': statusFilter === '0' }">Suspendidos</button>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>
                    </div>

                    <div class="relative z-0 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-700/30">
                                    <tr>
                                        <th class="px-8 py-5 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Perfil de Usuario</th>
                                        <th class="px-8 py-5 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Cargo y Cooperativas</th>
                                        <th class="px-8 py-5 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Estado</th>
                                        <th class="px-8 py-5 text-right text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                    <tr v-for="user in filteredUsers" :key="user.id" class="group hover:bg-indigo-50/30 dark:hover:bg-indigo-900/5 transition-all">
                                        <td class="px-8 py-5">
                                            <div class="flex items-center">
                                                <div :class="roleDisplay[user.tipo_usuario].bg" class="h-12 w-12 rounded-2xl flex items-center justify-center font-black text-lg shadow-sm border border-white/50">
                                                    <span :class="roleDisplay[user.tipo_usuario].color">{{ getInitials(user.name) }}</span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 transition-colors">{{ user.name }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-8 py-5">
                                            <div class="flex flex-col gap-1.5">
                                                <div class="flex items-center">
                                                    <component :is="roleDisplay[user.tipo_usuario].icon" :class="roleDisplay[user.tipo_usuario].color" class="h-4 w-4 mr-2 flex-shrink-0" />
                                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ roleDisplay[user.tipo_usuario].label }}</span>
                                                </div>
                                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter truncate max-w-[220px]">
                                                    {{ user.cooperativas_display || 'Sin cooperativa' }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-8 py-5">
                                            <span v-if="user.estado_activo" class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 mr-2 animate-pulse"></span>
                                                Activo
                                            </span>
                                            <span v-else class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400">
                                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500 mr-2"></span>
                                                Suspendido
                                            </span>
                                        </td>
                                        <td class="px-8 py-5 text-right">
                                            <div class="flex justify-end gap-3">
                                                <Link :href="route('admin.users.show', user.id)" class="p-2.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-xl transition-all shadow-sm bg-gray-50 dark:bg-gray-900/50" title="Ver Perfil">
                                                    <EyeIcon class="h-5 w-5"/>
                                                </Link>
                                                <Link :href="route('admin.users.edit', user.id)" class="p-2.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-xl transition-all shadow-sm bg-gray-50 dark:bg-gray-900/50" title="Editar">
                                                    <PencilSquareIcon class="h-5 w-5"/>
                                                </Link>
                                                <template v-if="user.id !== usePage().props.auth.user.id">
                                                    <Link :href="route('admin.users.toggle-status', user.id)" method="patch" as="button" class="p-2.5 transition-all rounded-xl shadow-sm bg-gray-50 dark:bg-gray-900/50" :class="user.estado_activo ? 'text-gray-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20' : 'text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20'" :title="user.estado_activo ? 'Suspender' : 'Activar'">
                                                        <component :is="user.estado_activo ? LockClosedIcon : LockOpenIcon" class="h-5 w-5" />
                                                    </Link>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredUsers.length === 0">
                                        <td colspan="4" class="px-6 py-20 text-center">
                                            <div class="flex flex-col items-center">
                                                <div class="p-6 rounded-full bg-slate-50 dark:bg-slate-900/40 mb-4 border border-dashed border-slate-200 dark:border-slate-700">
                                                    <UsersIcon class="h-12 w-12 text-slate-300" />
                                                </div>
                                                <h4 class="text-lg font-bold text-slate-900 dark:text-white">Sin resultados</h4>
                                                <p class="text-slate-500 dark:text-slate-400 text-sm max-w-xs mx-auto mt-1">No pudimos encontrar usuarios que coincidan con los criterios de búsqueda seleccionados.</p>
                                                <button @click="searchQuery = ''; roleFilter = ''; statusFilter = ''" class="mt-6 text-indigo-600 font-black hover:bg-indigo-50 px-4 py-2 rounded-lg transition-colors text-xs uppercase tracking-widest">Resetear Filtros</button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>