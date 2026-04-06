<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import {
    TrashIcon,
    MagnifyingGlassIcon,
    BuildingOffice2Icon,
    IdentificationIcon,
    UserCircleIcon,
    EnvelopeIcon,
    ArrowDownTrayIcon,
    PlusIcon,
    EyeIcon,
    InboxIcon,
    XMarkIcon,
    ChevronRightIcon,
    BuildingOfficeIcon
} from '@heroicons/vue/24/outline';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';

const props = defineProps({
    cooperativas: Object,
    filters: Object,
    can: Object,
});

const page = usePage();

// --- Lógica de Búsqueda Dinámica ---
const search = ref(props.filters.search || '');

const isDirty = computed(() => search.value !== '');

watch(search, debounce((value) => {
    router.get(route('cooperativas.index'), { search: value }, {
        preserveState: true,
        replace: true,
    });
}, 300));

const clearFilters = () => {
    search.value = '';
};

// --- Lógica para eliminación profesional ---
const deleteItem = (item) => {
    Swal.fire({
        title: '¿Eliminar cooperativa?',
        text: `Estás a punto de eliminar "${item.nombre}". Esta acción es permanente y afectará a todos los registros vinculados.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sí, eliminar permanentemente',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('cooperativas.destroy', item.id), {
                onSuccess: () => {
                    Swal.fire({
                        title: '¡Eliminado!',
                        text: 'La entidad ha sido removida del sistema.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const getRandomColor = (id) => {
  const colors = ['bg-indigo-100 text-indigo-700', 'bg-emerald-100 text-emerald-700', 'bg-violet-100 text-violet-700', 'bg-amber-100 text-amber-700', 'bg-rose-100 text-red-700'];
  return colors[id % colors.length];
};
</script>

<template>
    <Head title="Módulo de Cooperativas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div>
                    <h2 class="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <BuildingOfficeIcon class="w-8 h-8 text-indigo-500" />
                        Módulo de Cooperativas
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Gestión de entidades, empresas y cooperativas aliadas.
                    </p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <Link
                        v-if="can.create_cooperativas"
                        :href="route('cooperativas.create')"
                        class="flex-1 md:flex-none inline-flex items-center justify-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-sm transition-all shadow-sm shadow-indigo-200 dark:shadow-none"
                    >
                        <PlusIcon class="w-5 h-5 mr-2" />
                        Crear Entidad
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12 bg-gray-50/50 dark:bg-gray-900/50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- BARRA DE HERRAMIENTAS -->
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="relative w-full sm:max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <MagnifyingGlassIcon class="h-4 w-4 text-gray-400" />
                        </div>
                        <input
                            v-model="search"
                            type="text"
                            class="block w-full pl-10 pr-3 py-2 border border-gray-200 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white font-medium"
                            placeholder="Buscar por nombre, NIT o correo..."
                        />
                    </div>
                    
                    <button 
                        v-if="isDirty"
                        @click="clearFilters"
                        class="inline-flex items-center text-[10px] font-black text-red-500 hover:text-red-700 uppercase tracking-widest transition-colors"
                    >
                        <XMarkIcon class="w-4 h-4 mr-1" />
                        Limpiar Búsqueda
                    </button>
                </div>

                <!-- GRILLA DE TARJETAS -->
                <div v-if="cooperativas.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div v-for="coop in cooperativas.data" :key="coop.id"
                         class="bg-white dark:bg-gray-800 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col transition-all duration-300 hover:shadow-xl hover:-translate-y-1 group">

                        <!-- Cuerpo de la tarjeta -->
                        <div class="p-8 flex-grow">
                            <div class="flex items-center gap-4 mb-6">
                                <div :class="`w-12 h-12 rounded-2xl flex items-center justify-center text-white font-black text-lg shadow-lg ${getRandomColor(coop.id)}`" class="tracking-tighter">
                                    {{ coop.nombre[0] }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-black text-lg text-gray-900 dark:text-white truncate" :title="coop.nombre">{{ coop.nombre }}</h3>
                                    <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">Entidad Aliada</p>
                                </div>
                            </div>

                            <div class="space-y-4 pt-4 border-t border-gray-50 dark:border-gray-700">
                                <div class="flex items-center group/item">
                                    <div class="p-2 bg-gray-50 dark:bg-gray-900 rounded-lg mr-3 group-hover/item:bg-indigo-50 transition-colors">
                                        <IdentificationIcon class="h-4 w-4 text-gray-400 group-hover/item:text-indigo-600" />
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-tighter">Identificación Tributaria</p>
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300">NIT: {{ coop.NIT }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center group/item">
                                    <div class="p-2 bg-gray-50 dark:bg-gray-900 rounded-lg mr-3 group-hover/item:bg-indigo-50 transition-colors">
                                        <UserCircleIcon class="h-4 w-4 text-gray-400 group-hover/item:text-indigo-600" />
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-tighter">Representante Legal</p>
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300 truncate block max-w-[180px]">{{ coop.representante_legal_nombre || 'No registra' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center group/item">
                                    <div class="p-2 bg-gray-50 dark:bg-gray-900 rounded-lg mr-3 group-hover/item:bg-indigo-50 transition-colors">
                                        <EnvelopeIcon class="h-4 w-4 text-gray-400 group-hover/item:text-indigo-600" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-tighter">Contacto Principal</p>
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300 truncate block" :title="coop.contacto_correo">{{ coop.contacto_correo || 'Sin correo' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pie de la tarjeta con acciones -->
                        <div class="p-6 bg-gray-50/50 dark:bg-gray-800/50 border-t border-gray-50 dark:border-gray-700 flex items-center justify-between rounded-b-[2rem]">
                            <Link :href="route('cooperativas.show', coop.id)"
                                  class="inline-flex items-center gap-2 px-5 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl font-black text-[10px] uppercase tracking-widest text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                <EyeIcon class="w-4 h-4" />
                                Gestionar
                            </Link>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Link :href="route('cooperativas.edit', coop.id)" class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all">
                                    <PencilSquareIcon class="w-5 h-5" />
                                </Link>
                                <button v-if="can.delete_cooperativas" @click="deleteItem(coop)"
                                        class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all">
                                    <TrashIcon class="w-5 h-5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MENSAJE SI NO HAY COOPERATIVAS -->
                <div v-else class="text-center py-32 px-6 bg-white dark:bg-gray-800 rounded-[3rem] shadow-xl border border-gray-100 dark:border-gray-700">
                    <div class="p-6 bg-gray-50 dark:bg-gray-700 rounded-full inline-block mb-6">
                        <InboxIcon class="h-16 w-16 text-gray-300" />
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-wider">Sin coincidencias</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-xs mx-auto font-medium">
                        No encontramos cooperativas que coincidan con "{{ search }}".
                    </p>
                    <button @click="clearFilters" class="mt-8 px-8 py-3 bg-indigo-50 text-indigo-600 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all shadow-lg shadow-indigo-100">Resetear Búsqueda</button>
                </div>

                <!-- PAGINACIÓN -->
                <div v-if="cooperativas.links.length > 3" class="mt-12 flex justify-center">
                    <div class="flex items-center gap-1.5 p-1.5 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                        <Link v-for="(link, i) in cooperativas.links" :key="i" :href="link.url || '#'" v-html="link.label" 
                            :class="[link.active ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100 dark:shadow-none' : 'text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700/50']" 
                            class="px-4 py-2 text-[11px] font-black uppercase rounded-xl transition-all" />
                    </div>
                </div>
            </div>
        </div>
</AuthenticatedLayout>
</template>

