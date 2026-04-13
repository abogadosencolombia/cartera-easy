<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Pagination from '@/Components/Pagination.vue'; 
import { reactive, watch, computed } from 'vue';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import { 
    MagnifyingGlassIcon, 
    PencilSquareIcon, 
    TrashIcon, 
    PlusIcon, 
    BuildingOffice2Icon,
    ArrowUpTrayIcon,
    MapPinIcon,
    EnvelopeIcon,
    PhoneIcon,
    XMarkIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps(['juzgados', 'filters']);

// --- Lógica de Filtros ---
const params = reactive({
    search: props.filters?.search || '',
});

const isDirty = computed(() => params.search !== '');

watch(params, debounce((value) => {
    router.get(route('juzgados.index'), { search: value.search }, { 
        preserveState: true, 
        replace: true,
        preserveScroll: true 
    });
}, 300));

const clearFilters = () => {
    params.search = '';
};

// --- Lógica para Eliminar ---
const deleteJuzgado = (id, nombre) => {
    Swal.fire({
        title: '¿Eliminar Despacho?',
        text: `¿Estás seguro de que deseas eliminar a "${nombre}"? Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('juzgados.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Eliminado',
                        text: 'El despacho ha sido eliminado correctamente.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const getInitials = (name) => {
    return name
        .split(' ')
        .map(n => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
};
</script>

<template>
    <Head title="Directorio de Juzgados" />

    <AuthenticatedLayout>
        <template v-slot:header>
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl">
                        <BuildingOffice2Icon class="h-8 w-8 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">
                            Directorio de Juzgados
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 font-medium">Gestión de entidades judiciales y despachos</p>
                    </div>
                </div>
                
                <div class="flex flex-wrap gap-3 w-full md:w-auto">
                    <template v-if="$page.props.auth.user.tipo_usuario === 'admin'">
                        <Link :href="route('juzgados.import.form')" class="flex-1 md:flex-none">
                            <SecondaryButton class="w-full flex justify-center items-center gap-2 !py-2.5">
                                <ArrowUpTrayIcon class="h-4 w-4" />
                                Importar Excel
                            </SecondaryButton>
                        </Link>
                        
                        <Link :href="route('juzgados.create')" class="flex-1 md:flex-none">
                            <PrimaryButton class="w-full flex justify-center items-center gap-2 !py-2.5 !bg-indigo-600 hover:!bg-indigo-700">
                                <PlusIcon class="h-4 w-4" />
                                Nuevo Despacho
                            </PrimaryButton>
                        </Link>
                    </template>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Buscador y Resumen -->
                <div class="flex flex-col md:flex-row gap-4 items-center justify-between bg-white dark:bg-gray-800 rounded-2xl shadow-sm p-4 border border-gray-100 dark:border-gray-700">
                    <div class="relative w-full md:max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput 
                            v-model="params.search" 
                            type="text" 
                            placeholder="Buscar por nombre, municipio, email..." 
                            class="pl-10 w-full !rounded-xl border-gray-200 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <button 
                            v-if="isDirty" 
                            @click="clearFilters"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                        >
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="flex items-center gap-2 text-sm text-gray-500 font-medium">
                        <InformationCircleIcon class="h-5 w-5 text-indigo-400" />
                        Mostrando {{ juzgados.data.length }} de {{ juzgados.total }} registros
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead>
                                <tr class="text-xs text-gray-500 uppercase bg-gray-50/50 dark:bg-gray-700/50 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                                    <th scope="col" class="px-6 py-4 font-bold">Despacho / Entidad</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-center">Ubicación</th>
                                    <th scope="col" class="px-6 py-4 font-bold">Información de Contacto</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                <tr v-for="juzgado in juzgados.data" :key="juzgado.id" class="group hover:bg-indigo-50/30 dark:hover:bg-indigo-900/10 transition-colors">
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-4">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white text-xs font-black shadow-sm group-hover:scale-110 transition-transform">
                                                {{ getInitials(juzgado.nombre) }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-900 dark:text-white text-base leading-tight group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                                    {{ juzgado.nombre }}
                                                </div>
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                                        {{ juzgado.distrito || 'Sin Distrito' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex flex-col items-center justify-center text-center">
                                            <div class="flex items-center gap-1 font-bold text-gray-800 dark:text-gray-200">
                                                <MapPinIcon class="h-3.5 w-3.5 text-red-400" />
                                                {{ juzgado.municipio }}
                                            </div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ juzgado.departamento }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="space-y-2">
                                            <div v-if="juzgado.email" class="flex items-center gap-2 group/link">
                                                <div class="p-1.5 bg-blue-50 dark:bg-blue-900/20 rounded-lg group-hover/link:bg-blue-100 transition-colors">
                                                    <EnvelopeIcon class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                                </div>
                                                <a :href="`mailto:${juzgado.email}`" class="text-sm text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline font-medium truncate max-w-[200px]">
                                                    {{ juzgado.email }}
                                                </a>
                                            </div>
                                            <div v-else class="flex items-center gap-2 text-amber-500 italic text-[11px] font-medium">
                                                <InformationCircleIcon class="h-3.5 w-3.5" />
                                                Correo no registrado
                                            </div>
                                            
                                            <div v-if="juzgado.telefono" class="flex items-center gap-2">
                                                <div class="p-1.5 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                                                    <PhoneIcon class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                                </div>
                                                <span class="text-sm text-gray-600 dark:text-gray-400 font-medium">{{ juzgado.telefono }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <div class="flex justify-end items-center gap-1" v-if="$page.props.auth.user.tipo_usuario === 'admin'">
                                            <Link 
                                                :href="route('juzgados.edit', juzgado.id)" 
                                                class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-xl transition-all" 
                                                title="Editar Información"
                                            >
                                                <PencilSquareIcon class="w-5 h-5" />
                                            </Link>
                                            <button 
                                                @click="deleteJuzgado(juzgado.id, juzgado.nombre)" 
                                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-xl transition-all" 
                                                title="Eliminar Registro"
                                            >
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Estado Vacío -->
                    <div v-if="juzgados.data.length === 0" class="py-20 text-center">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-50 dark:bg-gray-700/50 mb-4">
                            <MagnifyingGlassIcon class="w-10 h-10 text-gray-300 dark:text-gray-500" />
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">No se encontraron resultados</h3>
                        <p class="text-gray-500 dark:text-gray-400 mt-1 max-w-xs mx-auto">
                            No pudimos encontrar ningún despacho que coincida con "{{ params.search }}".
                        </p>
                        <SecondaryButton v-if="isDirty" @click="clearFilters" class="mt-6">
                            Limpiar filtros
                        </SecondaryButton>
                    </div>
                    
                    <!-- Paginación -->
                    <div class="px-6 py-4 bg-gray-50/50 dark:bg-gray-700/50 border-t border-gray-100 dark:border-gray-700">
                         <Pagination :links="juzgados.links" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
