<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { 
    PencilSquareIcon, ArrowLeftIcon, MapPinIcon, LinkIcon, 
    ArrowTopRightOnSquareIcon, PaperClipIcon, TrashIcon, 
    ArrowDownTrayIcon, DocumentPlusIcon, DocumentIcon,
    EyeIcon, ExclamationTriangleIcon, UserIcon, PhoneIcon,
    EnvelopeIcon, BuildingOfficeIcon, BriefcaseIcon,
    IdentificationIcon, GlobeAltIcon, FolderIcon,
    ScaleIcon, CheckCircleIcon, UserGroupIcon, CloudArrowUpIcon
} from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  persona: { type: Object, required: true },
});

const isViewable = (mimeType) => {
    if (!mimeType) return false;
    return ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'].includes(mimeType);
};

const hasData = (arr) => Array.isArray(arr) && arr.length > 0;

const missingInfo = computed(() => {
    const p = props.persona;
    const missing = [];
    if (!p.celular_1 && !p.correo_1) missing.push('Contacto (Celular/Correo)');
    if (!p.fecha_nacimiento) missing.push('Fecha de nacimiento');
    if (!p.fecha_expedicion) missing.push('Fecha de expedición');
    if (!hasData(p.cooperativas)) missing.push('Asignación de Empresa');
    if (!hasData(p.abogados)) missing.push('Asignación de Responsable');
    return missing;
});

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return isNaN(date.getTime()) ? dateString : date.toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' });
};

const formatBytes = (bytes, decimals = 2) => {
    if (!+bytes) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
};

const formUpload = useForm({ documento: null });
const fileInput = ref(null);

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    
    Swal.fire({
        title: '¿Subir documento?',
        text: `Se adjuntará "${file.name}" al expediente.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, subir ahora',
        confirmButtonColor: '#4f46e5'
    }).then((result) => {
        if (result.isConfirmed) {
            formUpload.documento = file;
            formUpload.post(route('personas.upload_document', props.persona.id), {
                preserveScroll: true,
                onSuccess: () => {
                    formUpload.reset();
                    if (fileInput.value) fileInput.value.value = null;
                    Swal.fire('¡Éxito!', 'Documento cargado correctamente.', 'success');
                },
            });
        } else {
            if (fileInput.value) fileInput.value.value = null;
        }
    });
};

const deleteDocument = (docId) => {
    Swal.fire({
        title: '¿Eliminar archivo?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('personas.delete_document', docId), {
                onSuccess: () => Swal.fire('Eliminado', 'El archivo ha sido borrado.', 'success')
            });
        }
    });
};

const getRandomColor = (id) => {
  const colors = ['bg-indigo-600', 'bg-emerald-600', 'bg-violet-600', 'bg-amber-600', 'bg-rose-600'];
  return colors[id % colors.length];
};
</script>

<template>
  <Head :title="persona.nombre_completo" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-5">
            <Link :href="route('personas.index')" class="p-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-400 hover:text-indigo-600 transition-all shadow-sm">
                <ArrowLeftIcon class="w-6 h-6" />
            </Link>
            <div class="flex items-center gap-4">
                <div :class="`w-16 h-16 rounded-2xl flex items-center justify-center text-white text-2xl font-black shadow-xl ${getRandomColor(persona.id)}`">
                    {{ persona.nombre_completo[0] }}
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 text-[10px] font-black uppercase rounded-md border border-indigo-200">SUJETO REGISTRADO</span>
                        <span v-if="persona.es_demandado" class="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] font-black uppercase rounded-md border border-red-200">Demandado</span>
                        <span v-else class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase rounded-md border border-emerald-200">Cliente</span>
                    </div>
                    <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">
                        {{ persona.nombre_completo }}
                    </h2>
                </div>
            </div>
        </div>
        
        <div class="flex items-center gap-3 w-full md:w-auto">
            <Link :href="route('personas.edit', persona.id)" class="flex-1 md:flex-none inline-flex items-center justify-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg shadow-indigo-200 dark:shadow-none">
                <PencilSquareIcon class="w-4 h-4 mr-2" /> Editar Perfil
            </Link>
        </div>
      </div>
    </template>

    <div class="py-12 bg-gray-50/50 dark:bg-gray-900/50 min-h-screen">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
        
        <!-- DASHBOARD DE ESTADÍSTICAS RÁPIDAS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-4 group hover:border-indigo-200 transition-all">
                <div class="p-4 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl">
                    <FolderIcon class="w-8 h-8 text-indigo-600" />
                </div>
                <div>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Casos de Cobro</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white">{{ persona.casos_count || 0 }}</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-4 group hover:border-blue-200 transition-all">
                <div class="p-4 bg-blue-50 dark:bg-blue-900/30 rounded-2xl">
                    <ScaleIcon class="w-8 h-8 text-blue-600" />
                </div>
                <div>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Procesos Judiciales</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white">{{ persona.procesos_count || 0 }}</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-4 group hover:border-emerald-200 transition-all">
                <div class="p-4 bg-emerald-50 dark:bg-emerald-900/30 rounded-2xl">
                    <PaperClipIcon class="w-8 h-8 text-emerald-600" />
                </div>
                <div>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Archivos Digitales</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white">{{ persona.documentos?.length || 0 }}</p>
                </div>
            </div>
        </div>

        <!-- Alerta de Información Incompleta -->
        <div v-if="missingInfo.length > 0" class="bg-amber-50 dark:bg-amber-900/20 border-2 border-amber-200 dark:border-amber-800 p-6 rounded-[2rem] flex items-start gap-4 animate-in fade-in duration-500">
            <div class="p-3 bg-white dark:bg-gray-800 rounded-2xl shadow-sm">
                <ExclamationTriangleIcon class="w-8 h-8 text-amber-500" />
            </div>
            <div>
                <h3 class="text-sm font-black text-amber-900 dark:text-amber-200 uppercase tracking-widest">Atención: Perfil con datos pendientes</h3>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1 font-medium">Se recomienda completar la siguiente información para una gestión jurídica integral:</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span v-for="item in missingInfo" :key="item" class="px-2.5 py-1 bg-amber-100 dark:bg-amber-800 text-amber-800 dark:text-amber-200 text-[10px] font-bold rounded-lg border border-amber-200 dark:border-amber-700">{{ item }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- COLUMNA IZQUIERDA: INFORMACIÓN CORE (8/12) -->
            <div class="lg:col-span-8 space-y-8">
                
                <!-- Card: Información Personal y Contacto -->
                <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-10 py-6 bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                        <IdentificationIcon class="w-5 h-5 text-indigo-500" />
                        <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Identificación y Bio</h3>
                    </div>
                    <div class="p-10">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-10">
                            <div class="space-y-1">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Documento de Identidad</span>
                                <div class="flex items-center gap-3">
                                    <span class="px-2 py-1 bg-indigo-50 text-indigo-700 text-[10px] font-black rounded-lg border border-indigo-100 uppercase">{{ persona.tipo_documento }}</span>
                                    <p class="text-lg font-mono font-black text-gray-900 dark:text-white">{{ persona.numero_documento }}<span v-if="persona.dv" class="text-indigo-500">-{{ persona.dv }}</span></p>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Fechas Clave</span>
                                <div class="flex flex-col gap-1">
                                    <p class="text-sm font-bold text-gray-700 dark:text-gray-300">Nacimiento: <span class="font-normal">{{ formatDate(persona.fecha_nacimiento) }}</span></p>
                                    <p class="text-sm font-bold text-gray-700 dark:text-gray-300">Expedición: <span class="font-normal">{{ formatDate(persona.fecha_expedicion) }}</span></p>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Canales de Contacto</span>
                                <div class="space-y-2 pt-1">
                                    <div v-if="persona.celular_1" class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-gray-200">
                                        <div class="p-1.5 bg-emerald-50 rounded-lg"><PhoneIcon class="w-4 h-4 text-emerald-600" /></div> {{ persona.celular_1 }}
                                    </div>
                                    <div v-if="persona.correo_1" class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-gray-200">
                                        <div class="p-1.5 bg-blue-50 rounded-lg"><EnvelopeIcon class="w-4 h-4 text-blue-600" /></div> {{ persona.correo_1 }}
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Perfil Laboral</span>
                                <div class="flex items-start gap-3 pt-1">
                                    <div class="p-1.5 bg-amber-50 rounded-lg"><BriefcaseIcon class="w-4 h-4 text-amber-600" /></div>
                                    <div>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white leading-tight">{{ persona.empresa || 'Empresa no registrada' }}</p>
                                        <p class="text-xs text-gray-500 font-medium">{{ persona.cargo || 'Cargo no especificado' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-12 pt-10 border-t border-gray-50 dark:border-gray-700">
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-4">Notas y Observaciones</span>
                            <div class="p-6 bg-gray-50 dark:bg-gray-900/50 rounded-[2rem] border border-gray-100 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400 leading-relaxed italic">
                                "{{ persona.observaciones || 'Sin anotaciones adicionales en el perfil.' }}"
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Gestión Documental -->
                <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-10 py-6 bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <CloudArrowUpIcon class="w-5 h-5 text-indigo-500" />
                            <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Repositorio de Archivos</h3>
                        </div>
                        <div>
                            <input type="file" ref="fileInput" class="hidden" @change="handleFileChange">
                            <button @click="fileInput.click()" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl font-black text-[10px] uppercase tracking-widest text-indigo-600 hover:bg-indigo-50 transition-all shadow-sm">
                                <DocumentPlusIcon class="w-4 h-4 mr-2" /> Adjuntar Soportes
                            </button>
                        </div>
                    </div>
                    
                    <div class="p-10">
                        <div v-if="!hasData(persona.documentos)" class="text-center py-16 opacity-40">
                            <DocumentIcon class="w-16 h-16 mx-auto text-gray-300 mb-4" />
                            <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-400">Sin documentos vinculados</p>
                        </div>
                        <ul v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <li v-for="doc in persona.documentos" :key="doc.id" class="p-5 bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-200 transition-all flex items-start gap-4 group">
                                <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-2xl group-hover:bg-indigo-50 transition-colors">
                                    <DocumentIcon class="w-8 h-8 text-gray-400 group-hover:text-indigo-600" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start">
                                        <h4 class="text-xs font-black text-gray-900 dark:text-white truncate pr-2" :title="doc.nombre_original">{{ doc.nombre_original }}</h4>
                                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <a :href="route('personas.view_document', doc.id)" target="_blank" class="p-1.5 text-gray-400 hover:text-indigo-600"><EyeIcon class="w-4 h-4" /></a>
                                            <button @click="deleteDocument(doc.id)" class="p-1.5 text-gray-400 hover:text-red-600"><TrashIcon class="w-4 h-4" /></button>
                                        </div>
                                    </div>
                                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-tighter mt-1">{{ formatBytes(doc.size) }} • {{ formatDate(doc.created_at) }}</p>
                                    <div class="mt-4 flex items-center gap-2">
                                        <a :href="route('personas.download_document', doc.id)" class="text-[9px] font-black uppercase text-indigo-600 hover:underline">Descargar</a>
                                        <span class="w-1 h-1 bg-gray-200 rounded-full"></span>
                                        <span class="text-[9px] font-bold text-gray-400">{{ doc.mime_type.split('/')[1].toUpperCase() }}</span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Card: Historial Judicial (Casos y Procesos) -->
                <div class="space-y-8">
                    <!-- Casos de Cobro -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-10 py-6 border-b border-gray-50 dark:border-gray-700 flex justify-between items-center bg-indigo-50/30 dark:bg-indigo-900/20">
                            <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Historial de Cobro Carteras</h3>
                            <Link v-if="persona.casos_count > persona.casos?.length" :href="route('casos.index', { search: persona.numero_documento })" class="text-[10px] font-black text-indigo-600 uppercase hover:underline">Ver todos los casos</Link>
                        </div>
                        <div class="p-0">
                            <div v-if="!hasData(persona.casos)" class="p-16 text-center opacity-40 font-bold text-gray-400 uppercase text-xs tracking-widest">Sin registros de cobro</div>
                            <table v-else class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-900/40">
                                    <tr class="text-[9px] font-black text-gray-400 uppercase tracking-[0.2em]">
                                        <th class="px-10 py-4 text-left">Expediente</th>
                                        <th class="px-6 py-4 text-left">Estado</th>
                                        <th class="px-6 py-4 text-left">Responsable</th>
                                        <th class="px-10 py-4 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="caso in persona.casos" :key="caso.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-10 py-4 text-sm font-bold text-gray-900 dark:text-white">#{{ caso.id }} <br/><span class="text-[10px] font-normal text-gray-500">{{ caso.radicado || 'Sin radicar' }}</span></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-0.5 text-[10px] font-black rounded-lg border uppercase tracking-widest" :class="caso.estado === 'ACTIVO' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-blue-50 text-blue-600 border-blue-100'">{{ caso.estado }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-xs font-medium text-gray-600">{{ caso.user?.name.split(' ')[0] || '—' }}</td>
                                        <td class="px-10 py-4 text-right">
                                            <Link :href="route('casos.show', caso.id)" class="text-[10px] font-black text-indigo-600 uppercase hover:underline">Ver Ficha</Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Procesos Judiciales -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-10 py-6 border-b border-gray-50 dark:border-gray-700 flex justify-between items-center bg-blue-50/30 dark:bg-blue-900/20">
                            <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Expedientes Judiciales (Radicados)</h3>
                            <Link v-if="persona.procesos_count > persona.procesos?.length" :href="route('procesos.index', { search: persona.numero_documento })" class="text-[10px] font-black text-blue-600 uppercase hover:underline">Ver todos los radicados</Link>
                        </div>
                        <div class="p-0">
                            <div v-if="!hasData(persona.procesos)" class="p-16 text-center opacity-40 font-bold text-gray-400 uppercase text-xs tracking-widest">Sin procesos registrados</div>
                            <table v-else class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-900/40">
                                    <tr class="text-[9px] font-black text-gray-400 uppercase tracking-[0.2em]">
                                        <th class="px-10 py-4 text-left">Radicado / Asunto</th>
                                        <th class="px-6 py-4 text-left">Rol</th>
                                        <th class="px-6 py-4 text-left">Estado</th>
                                        <th class="px-10 py-4 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="proc in persona.procesos" :key="proc.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-10 py-4">
                                            <p class="text-sm font-black text-gray-900 dark:text-white leading-tight truncate max-w-[200px]" :title="proc.radicado">{{ proc.radicado || 'S.R' }}</p>
                                            <p class="text-[10px] text-gray-500 truncate max-w-[200px]">{{ proc.asunto || 'Sin asunto' }}</p>
                                        </td>
                                        <td class="px-6 py-4"><span class="text-[10px] font-bold uppercase text-gray-500">{{ proc.pivot?.tipo || 'Vinculado' }}</span></td>
                                        <td class="px-6 py-4"><span class="px-2 py-0.5 text-[10px] font-black rounded-lg border border-blue-100 bg-blue-50 text-blue-600 uppercase tracking-widest">{{ proc.estado || 'Activo' }}</span></td>
                                        <td class="px-10 py-4 text-right">
                                            <Link :href="route('procesos.show', proc.id)" class="text-[10px] font-black text-indigo-600 uppercase hover:underline">Ver Proceso</Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUMNA DERECHA: RELACIONES Y LOCALIZACIÓN (4/12) -->
            <div class="lg:col-span-4 space-y-8">
                
                <!-- Card: Direcciones -->
                <div class="bg-white dark:bg-gray-800 rounded-[2rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex items-center gap-3">
                        <MapPinIcon class="w-5 h-5 text-red-500" />
                        <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-[10px]">Ubicaciones Registradas</h3>
                    </div>
                    <div class="p-8">
                        <ul v-if="hasData(persona.addresses)" class="space-y-4">
                            <li v-for="(addr, idx) in persona.addresses" :key="idx" class="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-700 relative group">
                                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-1">{{ addr.label || 'Dirección' }}</span>
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-200 leading-tight">{{ addr.address }}</p>
                                <p class="text-[10px] text-gray-500 mt-1 font-medium">{{ addr.city }}</p>
                            </li>
                        </ul>
                        <div v-else class="text-center py-10 opacity-30">
                            <MapPinIcon class="w-10 h-10 mx-auto text-gray-300 mb-2" />
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Sin direcciones</p>
                        </div>
                    </div>
                </div>

                <!-- Card: Redes y Enlaces -->
                <div class="bg-white dark:bg-gray-800 rounded-[2rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex items-center gap-3">
                        <GlobeAltIcon class="w-5 h-5 text-blue-500" />
                        <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-[10px]">Redes y Perfiles Digitales</h3>
                    </div>
                    <div class="p-8">
                        <ul v-if="hasData(persona.social_links)" class="space-y-3">
                            <li v-for="(link, idx) in persona.social_links" :key="idx">
                                <a :href="link.url" target="_blank" class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-transparent hover:border-blue-200 hover:bg-blue-50 transition-all group">
                                    <div class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm"><LinkIcon class="w-4 h-4 text-blue-500" /></div>
                                    <div class="min-w-0">
                                        <span class="text-[10px] font-black text-gray-900 dark:text-white uppercase tracking-tighter truncate block">{{ link.label || 'Enlace' }}</span>
                                        <span class="text-[9px] text-gray-400 truncate block">{{ link.url }}</span>
                                    </div>
                                </a>
                            </li>
                        </ul>
                        <div v-else class="text-center py-10 opacity-30">
                            <GlobeAltIcon class="w-10 h-10 mx-auto text-gray-300 mb-2" />
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Sin enlaces digitales</p>
                        </div>
                    </div>
                </div>

                <!-- Card: Relaciones Corporativas -->
                <div class="bg-white dark:bg-gray-800 rounded-[2rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex items-center gap-3">
                        <ShieldCheckIcon class="w-5 h-5 text-emerald-500" />
                        <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-[10px]">Control y Asignaciones</h3>
                    </div>
                    <div class="p-8 space-y-8">
                        <div>
                            <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-3">Cooperativas / Empresas</span>
                            <div v-if="hasData(persona.cooperativas)" class="flex flex-wrap gap-2">
                                <span v-for="c in persona.cooperativas" :key="c.id" class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-black rounded-lg border border-emerald-100 uppercase tracking-tighter">{{ c.nombre }}</span>
                            </div>
                            <p v-else class="text-[10px] font-bold text-gray-400 italic">No hay empresas vinculadas</p>
                        </div>
                        <div class="pt-6 border-t border-gray-50 dark:border-gray-700">
                            <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-3">Abogados / Gestores Responsables</span>
                            <div v-if="hasData(persona.abogados)" class="flex flex-wrap gap-2">
                                <span v-for="a in persona.abogados" :key="a.id" class="px-2.5 py-1 bg-violet-50 text-violet-700 text-[10px] font-black rounded-lg border border-violet-100 uppercase tracking-tighter">{{ a.name }}</span>
                            </div>
                            <p v-else class="text-[10px] font-bold text-gray-400 italic">Sin responsables asignados</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar-horizontal::-webkit-scrollbar { height: 4px; }
.custom-scrollbar-horizontal::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
.animate-in { animation-duration: 0.4s; animation-fill-mode: both; }
</style>