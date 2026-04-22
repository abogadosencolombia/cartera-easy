<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import { 
    CloudArrowUpIcon, 
    ArrowLeftIcon, 
    TableCellsIcon, 
    CheckCircleIcon, 
    ExclamationTriangleIcon,
    XMarkIcon,
    ArrowDownTrayIcon,
    ArrowPathIcon,
    UserIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';

const props = defineProps({
    juzgados: Array,
    tiposProceso: Array,
    abogados: Array,
});

const file = ref(null);
const fileInput = ref(null);
const results = ref(null);
const processing = ref(false);
const confirmed = ref(false);

// Selector de Abogado Responsable (Fallback)
const selectedAbogadoId = ref(usePage().props.auth.user.id);

const handleFileChange = (e) => {
    file.value = e.target.files[0];
    confirmed.value = false;
    if (file.value) uploadFile();
};

const uploadFile = () => {
    const formData = new FormData();
    formData.append('file', file.value);
    
    processing.value = true;
    axios.post(route('procesos.import.validate'), formData)
        .then(res => {
            results.value = res.data;
        })
        .catch(err => {
            Swal.fire('Error', 'El archivo no tiene el formato correcto o está corrupto.', 'error');
        })
        .finally(() => processing.value = false);
};

const confirmImport = () => {
    if (!confirmed.value) {
        Swal.fire('Atención', 'Debe marcar la casilla de revisión para continuar.', 'warning');
        return;
    }

    if (!results.value || !results.value.rows) {
        Swal.fire('Error', 'No hay datos válidos para importar.', 'error');
        return;
    }

    processing.value = true;
    
    router.post(route('procesos.import.store'), { 
        data: results.value.rows,
        abogado_id: selectedAbogadoId.value 
    }, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: '¡Éxito!',
                text: 'Los expedientes han sido procesados correctamente.',
                icon: 'success',
                confirmButtonColor: '#4f46e5'
            });
        },
        onError: (err) => {
            console.error('Error en importación:', err);
            Swal.fire('Error', 'Ocurrió un fallo al guardar los datos. Revise la consola.', 'error');
        },
        onFinish: () => {
            processing.value = false;
        }
    });
};

const getRowStatusClass = (status) => {
    if (status === 'error') return 'bg-red-50 border-red-100 text-red-700';
    if (status === 'warning') return 'bg-indigo-50 border-indigo-100 text-indigo-700 font-black';
    return 'bg-emerald-50 border-emerald-100 text-emerald-700';
};
</script>

<template>
    <Head title="Importación de Expedientes Judiciales" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('procesos.index')" class="p-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-400 hover:text-indigo-600 transition-all shadow-sm">
                        <ArrowLeftIcon class="w-6 h-6" />
                    </Link>
                    <div>
                        <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">Importación Masiva de Radicados</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Actualice sus procesos judiciales mediante archivos Excel.</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <a :href="route('procesos.exportar')" class="inline-flex items-center px-5 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-xs font-black uppercase tracking-widest text-gray-600 dark:text-gray-300 hover:bg-gray-50 transition-all shadow-sm">
                        <ArrowDownTrayIcon class="w-4 h-4 mr-2" /> Exportar Actuales
                    </a>
                    <a :href="route('procesos.import.template')" class="inline-flex items-center px-5 py-2.5 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-100 dark:border-indigo-900/50 rounded-xl text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 transition-all shadow-sm">
                        <TableCellsIcon class="w-4 h-4 mr-2" /> Plantilla Vacía
                    </a>
                </div>
            </div>
        </template>

        <div class="py-12 bg-gray-50/50 dark:bg-gray-900/50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <div v-if="!results" class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm p-12 text-center">
                    <div class="max-w-xl mx-auto space-y-6">
                        <div class="p-6 bg-indigo-50 dark:bg-indigo-900/30 rounded-full inline-block">
                            <CloudArrowUpIcon class="w-16 h-16 text-indigo-600" />
                        </div>
                        <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tight">Cargar Excel de Procesos</h3>
                        <p class="text-gray-500 dark:text-gray-400 font-medium leading-relaxed">
                            Suba el archivo exportado o la plantilla oficial. El sistema auditará automáticamente cada radicado contra la base de datos para detectar cambios.
                        </p>
                        
                        <div @click="fileInput.click()" class="mt-8 cursor-pointer p-16 border-4 border-dashed border-indigo-100 dark:border-indigo-900/30 bg-indigo-50/30 dark:bg-indigo-900/10 rounded-[3rem] hover:border-indigo-400 transition-all group">
                            <input ref="fileInput" type="file" @change="handleFileChange" class="hidden" accept=".xlsx, .xls" />
                            <div class="flex flex-col items-center gap-4">
                                <ArrowPathIcon v-if="processing" class="w-12 h-12 text-indigo-600 animate-spin" />
                                <CloudArrowUpIcon v-else class="w-12 h-12 text-indigo-400 group-hover:scale-110 transition-transform" />
                                <span class="text-sm font-black uppercase tracking-widest text-indigo-600">
                                    {{ processing ? 'Analizando integridad...' : 'Seleccionar Archivo' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="space-y-6 animate-in fade-in duration-500">
                    <div class="bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col md:flex-row items-center justify-between gap-8">
                        <div class="flex items-center gap-6">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Filas Excel</span>
                                <span class="text-2xl font-black text-gray-900 dark:text-white">{{ results.total_rows }}</span>
                            </div>
                            <div class="h-10 w-px bg-gray-100 dark:bg-gray-700"></div>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black text-indigo-500 uppercase tracking-widest">Validado/Cambios</span>
                                <span class="text-2xl font-black text-indigo-600">{{ results.warning_rows }}</span>
                            </div>
                        </div>

                        <!-- Selector de Abogado Responsable (Nuevo Requerimiento) -->
                        <div class="flex-1 max-w-xs bg-indigo-50/50 dark:bg-indigo-900/20 p-4 rounded-2xl border border-indigo-100 dark:border-indigo-900/30">
                            <label class="block text-[10px] font-black text-indigo-600 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <UserIcon class="w-3 h-3" /> Abogado Responsable
                            </label>
                            <SelectInput 
                                v-model="selectedAbogadoId"
                                class="w-full text-xs font-bold border-none bg-transparent focus:ring-0 text-gray-700 dark:text-gray-300 p-0"
                            >
                                <option v-for="a in abogados" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </SelectInput>
                        </div>

                        <div class="flex-1 max-w-md bg-amber-50 dark:bg-amber-900/20 p-4 rounded-2xl border border-amber-100 dark:border-amber-900/30">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input v-model="confirmed" type="checkbox" class="mt-1 rounded text-amber-600 focus:ring-amber-500 border-amber-300" />
                                <span class="text-xs font-bold text-amber-900 dark:text-amber-300">
                                    He revisado los avisos de actualización. El abogado seleccionado será asignado a los radicados que no tengan un responsable válido en el Excel.
                                </span>
                            </label>
                        </div>

                        <div class="flex gap-3">
                            <button @click="results = null" class="px-6 py-2.5 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-300 rounded-xl font-bold text-xs uppercase tracking-widest">Cancelar</button>
                            <PrimaryButton @click="confirmImport" :disabled="processing || !confirmed" class="!bg-indigo-600 !rounded-xl !px-10 !shadow-xl !shadow-indigo-200">
                                <span v-if="processing" class="animate-spin mr-2"><ArrowPathIcon class="w-4 h-4"/></span>
                                Procesar Importación
                            </PrimaryButton>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase">Estado</th>
                                        <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase">Radicado / Asunto</th>
                                        <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase">Entidad y Partes</th>
                                        <th class="px-6 py-4 text-left text-[10px] font-black text-gray-400 uppercase">Resumen de Auditoría</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                    <tr v-for="(row, idx) in results.rows" :key="idx" :class="row.status === 'error' ? 'bg-red-50/30' : (row.status === 'warning' ? 'bg-indigo-50/10' : '')">
                                        <td class="px-6 py-4">
                                            <component :is="row.status === 'error' ? XMarkIcon : (row.status === 'warning' ? ExclamationTriangleIcon : CheckCircleIcon)" 
                                                :class="row.status === 'error' ? 'text-red-500' : (row.status === 'warning' ? 'text-indigo-500' : 'text-emerald-500')"
                                                class="w-6 h-6" />
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-xs font-black font-mono text-gray-900 dark:text-white">{{ row.radicado }}</p>
                                            <p class="text-[10px] text-gray-500 line-clamp-1">{{ row.asunto }}</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-[10px] font-bold text-indigo-600">{{ row.juzgado_nombre || 'Sin Entidad' }}</p>
                                            <div class="flex items-center gap-1 mt-1">
                                                <span v-if="row.demandantes_nombres" class="text-[9px] bg-gray-100 px-1 rounded font-black text-gray-400">DTE: {{ row.demandantes_nombres.substring(0, 30) }}...</span>
                                                <span v-else class="text-[9px] bg-gray-100 px-1 rounded font-black text-amber-500 italic">DTE: Sin registrar</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-1.5">
                                                <span v-for="msg in row.messages" :key="msg" 
                                                    class="px-3 py-1 rounded-lg text-[10px] border leading-relaxed"
                                                    :class="getRowStatusClass(row.status)">
                                                    {{ msg }}
                                                </span>
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
