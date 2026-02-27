<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Pagination from '@/Components/Pagination.vue'; 
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import { 
    MagnifyingGlassIcon, 
    PencilSquareIcon, 
    TrashIcon, 
    PlusIcon, 
    BuildingOffice2Icon,
    ArrowUpTrayIcon
} from '@heroicons/vue/24/outline';

const props = defineProps(['juzgados', 'filters']);

// --- Lógica del Buscador ---
const search = ref(props.filters?.search || '');

watch(search, debounce((value) => {
    router.get(route('juzgados.index'), { search: value }, { 
        preserveState: true, 
        replace: true,
        preserveScroll: true 
    });
}, 300));

// --- Lógica para Eliminar ---
const deleteForm = useForm({});
const deleteJuzgado = (id, nombre) => {
    if (confirm(`¿Estás seguro de que deseas eliminar a "${nombre}"?`)) {
        deleteForm.delete(route('juzgados.destroy', id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Directorio de Juzgados" />

    <AuthenticatedLayout>
        <template v-slot:header>
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center gap-2">
                        <BuildingOffice2Icon class="h-6 w-6 text-indigo-600" />
                        Directorio de Juzgados
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Gestión de entidades judiciales y despachos</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <!-- Botón Crear Manualmente -->
                    <Link :href="route('juzgados.create')" v-if="$page.props.auth.user.tipo_usuario === 'admin'">
                        <PrimaryButton class="bg-indigo-600 hover:bg-indigo-700 flex items-center gap-2">
                            <PlusIcon class="h-4 w-4" />
                            Crear Nuevo
                        </PrimaryButton>
                    </Link>
                    
                    <!-- Botón Importar Excel -->
                    <Link :href="route('juzgados.import.form')" v-if="$page.props.auth.user.tipo_usuario === 'admin'">
                        <PrimaryButton class="bg-green-600 hover:bg-green-700 flex items-center gap-2">
                            <ArrowUpTrayIcon class="h-4 w-4" />
                            Subir Excel
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Buscador -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-100 dark:border-gray-700">
                    <div class="relative max-w-xl">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
                        </div>
                        <TextInput 
                            v-model="search" 
                            type="text" 
                            placeholder="Buscar por nombre, ciudad, email o teléfono..." 
                            class="pl-10 w-full"
                        />
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Entidad / Nombre</th>
                                    <th scope="col" class="px-6 py-3">Ubicación</th>
                                    <th scope="col" class="px-6 py-3">Contacto</th>
                                    <th scope="col" class="px-6 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="juzgado in juzgados.data" :key="juzgado.id" class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600/50">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900 dark:text-white text-base">
                                            {{ juzgado.nombre }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            Distrito: {{ juzgado.distrito || 'No registrado' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ juzgado.municipio }}</span>
                                            <span class="text-xs text-gray-500">{{ juzgado.departamento }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-1">
                                            <div v-if="juzgado.email" class="flex items-center gap-1 text-indigo-600 dark:text-indigo-400 font-medium">
                                                <span>✉</span> <a :href="`mailto:${juzgado.email}`" class="hover:underline">{{ juzgado.email }}</a>
                                            </div>
                                            <div v-else class="text-gray-400 text-xs italic">Sin correo</div>
                                            
                                            <div v-if="juzgado.telefono" class="text-gray-600 dark:text-gray-400 text-xs">
                                                📞 {{ juzgado.telefono }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2" v-if="$page.props.auth.user.tipo_usuario === 'admin'">
                                            <Link :href="route('juzgados.edit', juzgado.id)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-full dark:hover:bg-gray-700" title="Editar">
                                                <PencilSquareIcon class="w-5 h-5" />
                                            </Link>
                                            <button @click="deleteJuzgado(juzgado.id, juzgado.nombre)" class="p-2 text-red-600 hover:bg-red-50 rounded-full dark:hover:bg-gray-700" title="Eliminar">
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="juzgados.data.length === 0">
                                    <td colspan="4" class="px-6 py-10 text-center text-gray-500">
                                        No se encontraron resultados para "{{ search }}".
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <div v-if="juzgados.links.length > 3" class="p-4 border-t border-gray-200 dark:border-gray-700">
                         <div class="flex justify-center gap-1 flex-wrap">
                            <template v-for="(link, k) in juzgados.links" :key="k">
                                <Link 
                                    v-if="link.url"
                                    :href="link.url"
                                    class="px-3 py-1 border rounded text-sm hover:bg-gray-100 dark:hover:bg-gray-700 dark:border-gray-600"
                                    :class="{'bg-indigo-600 text-white border-indigo-600': link.active, 'text-gray-600 dark:text-gray-300': !link.active}"
                                    v-html="link.label"
                                />
                                <span v-else v-html="link.label" class="px-3 py-1 text-sm text-gray-400"></span>
                            </template>
                         </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>