<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { LockClosedIcon, ArrowDownTrayIcon, ArrowPathIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DatePicker from '@/Components/DatePicker.vue';

const props = defineProps({
    documentos: Object,
    usuarios: Array,
    filtros: Object,
});

const form = ref({
    usuario_id: props.filtros.usuario_id || '',
    tipo_plantilla: props.filtros.tipo_plantilla || '',
    fecha_desde: props.filtros.fecha_desde || '',
    fecha_hasta: props.filtros.fecha_hasta || '',
});

const aplicarFiltros = () => {
    const query = { ...form.value };
    Object.keys(query).forEach(key => { if (!query[key]) delete query[key]; });
    router.get(route('documentos-generados.index'), query, {
        preserveState: true,
        replace: true,
    });
};

const limpiarFiltros = () => {
    form.value = { usuario_id: '', tipo_plantilla: '', fecha_desde: '', fecha_hasta: '' };
    aplicarFiltros();
};

const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleString('es-CO', options);
};
</script>

<template>
    <Head title="Auditoría de Documentos" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Vista de Halcón: Auditoría de Documentos Generados
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Filtros de Búsqueda</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6">
                            <div>
                                <label for="usuario_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Usuario</label>
                                <Dropdown align="left" width="full">
                                    <template #trigger>
                                        <button type="button" class="mt-1 flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 bg-white px-3 py-2 text-sm shadow-sm hover:border-indigo-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all cursor-pointer dark:text-gray-300">
                                            <span class="truncate">{{ form.usuario_id ? usuarios.find(u => u.id === form.usuario_id)?.name : 'Todos' }}</span>
                                            <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                                        </button>
                                    </template>
                                    <template #content>
                                        <div class="py-1 bg-white dark:bg-gray-800 max-h-60 overflow-y-auto">
                                            <button @click="form.usuario_id = ''; aplicarFiltros()" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': form.usuario_id === '' }">Todos</button>
                                            <button v-for="user in usuarios" :key="user.id" @click="form.usuario_id = user.id; aplicarFiltros()" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': form.usuario_id === user.id }">
                                                {{ user.name }}
                                            </button>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                            <div>
                                <label for="tipo_plantilla" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo Plantilla</label>
                                <Dropdown align="left" width="full">
                                    <template #trigger>
                                        <button type="button" class="mt-1 flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 bg-white px-3 py-2 text-sm shadow-sm hover:border-indigo-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all cursor-pointer dark:text-gray-300">
                                            <span class="truncate">{{ form.tipo_plantilla === '' ? 'Todos' : form.tipo_plantilla.charAt(0).toUpperCase() + form.tipo_plantilla.slice(1) }}</span>
                                            <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                                        </button>
                                    </template>
                                    <template #content>
                                        <div class="py-1 bg-white dark:bg-gray-800">
                                            <button @click="form.tipo_plantilla = ''; aplicarFiltros()" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': form.tipo_plantilla === '' }">Todos</button>
                                            <button @click="form.tipo_plantilla = 'demanda'; aplicarFiltros()" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': form.tipo_plantilla === 'demanda' }">Demanda</button>
                                            <button @click="form.tipo_plantilla = 'carta'; aplicarFiltros()" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': form.tipo_plantilla === 'carta' }">Carta</button>
                                            <button @click="form.tipo_plantilla = 'medida cautelar'; aplicarFiltros()" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': form.tipo_plantilla === 'medida cautelar' }">Medida Cautelar</button>
                                            <button @click="form.tipo_plantilla = 'notificación'; aplicarFiltros()" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': form.tipo_plantilla === 'notificación' }">Notificación</button>
                                            <button @click="form.tipo_plantilla = 'otros'; aplicarFiltros()" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': form.tipo_plantilla === 'otros' }">Otros</button>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                            <div>
                                <label for="fecha_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Desde</label>
                                <DatePicker v-model="form.fecha_desde" id="fecha_desde" class="w-full" />
                            </div>
                            <div>
                                <label for="fecha_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hasta</label>
                                <DatePicker v-model="form.fecha_hasta" id="fecha_hasta" class="w-full" />
                            </div>
                            <div class="flex items-end space-x-2">
                                <PrimaryButton @click="aplicarFiltros" class="w-full justify-center h-10">Filtrar</PrimaryButton>
                                <SecondaryButton @click="limpiarFiltros" class="w-full justify-center h-10" title="Limpiar Filtros"><ArrowPathIcon class="h-5 w-5"/></SecondaryButton>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Documento / Caso</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Usuario Generador</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Plantilla Usada</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fecha de Creación</th>
                                        <th class="relative px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="documentos.data.length === 0">
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No se encontraron documentos con los filtros aplicados.</td>
                                    </tr>
                                    <tr v-else v-for="doc in documentos.data" :key="doc.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <LockClosedIcon v-if="doc.es_confidencial" class="h-5 w-5 text-yellow-500 mr-3 flex-shrink-0" title="Documento Confidencial" />
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ doc.nombre_base }}.docx</div>
                                                    <Link v-if="doc.caso" :href="route('casos.show', doc.caso.id)" class="text-sm text-indigo-500 hover:underline">Caso #{{ doc.caso.id }} - {{ doc.caso.deudor.nombre_completo }}</Link>
                                                    <span v-else class="text-sm text-gray-400">Caso eliminado</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ doc.usuario.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <span v-if="doc.plantilla" class="capitalize">{{ doc.plantilla.nombre }} (v{{ doc.version_plantilla }})</span>
                                            <span v-else>Plantilla Eliminada</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDateTime(doc.created_at) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-4">
                                            <a :href="route('documentos.descargar.docx', doc.id)" class="inline-flex items-center text-blue-600 hover:text-blue-800" title="Descargar DOCX"><ArrowDownTrayIcon class="h-5 w-5" /></a>
                                            <a :href="route('documentos.descargar.pdf', doc.id)" class="inline-flex items-center text-red-600 hover:text-red-800" title="Descargar PDF"><ArrowDownTrayIcon class="h-5 w-5" /></a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div v-if="documentos.links.length > 3" class="mt-6 flex justify-between items-center">
                           <div class="text-sm text-gray-700 dark:text-gray-400">Mostrando {{ documentos.from }} a {{ documentos.to }} de {{ documentos.total }} resultados</div>
                           <div class="flex flex-wrap">
                               <template v-for="(link, key) in documentos.links" :key="key">
                                   <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded" v-html="link.label" />
                                   <Link v-else class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500" :class="{ 'bg-white dark:bg-gray-600': link.active }" :href="link.url" v-html="link.label" />
                               </template>
                           </div>
                       </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
