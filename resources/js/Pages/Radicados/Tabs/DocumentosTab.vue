<script setup>
import { ref } from 'vue';
import { 
    CloudArrowUpIcon, 
    DocumentTextIcon, 
    TrashIcon, 
    EyeIcon, 
    ArrowDownTrayIcon,
    FolderPlusIcon,
    ArrowPathIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    proceso: { type: Object, required: true },
    files: { type: Array, default: () => [] },
    isClosed: { type: Boolean, default: false },
    formatDate: { type: Function, required: true },
});

const emit = defineEmits(['upload', 'delete']);

const uploadForm = ref({
    archivo: null,
    nombre: '',
    nota: '',
    processing: false
});

const fileInput = ref(null);

const onPickFile = (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  uploadForm.value.archivo = file;
  if (!uploadForm.value.nombre) {
    const parts = file.name.split('.');
    if (parts.length > 1) parts.pop();
    uploadForm.value.nombre = parts.join('.') || file.name;
  }
};

const handleUpload = () => {
    if (!uploadForm.value.archivo) return;
    emit('upload', { ...uploadForm.value });
    // El padre se encarga de limpiar tras el éxito
};
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 animate-in fade-in duration-500">
        
        <!-- PANEL DE CARGA (4/12) -->
        <div class="lg:col-span-4">
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm p-8 sticky top-8">
                <div class="flex items-center gap-3 mb-8">
                    <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                        <CloudArrowUpIcon class="w-6 h-6 text-indigo-600" />
                    </div>
                    <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Cargar Soporte</h3>
                </div>

                <fieldset :disabled="isClosed" class="space-y-6">
                    <div class="space-y-2">
                        <label class="block w-full cursor-pointer p-10 border-2 border-dashed border-indigo-100 dark:border-indigo-900/30 bg-indigo-50/30 dark:bg-indigo-900/10 rounded-3xl text-center hover:border-indigo-400 transition-all group">
                            <input ref="fileInput" type="file" @change="onPickFile" class="hidden" />
                            <div class="flex flex-col items-center gap-2">
                                <CloudArrowUpIcon class="w-10 h-10 text-indigo-400 group-hover:scale-110 transition-transform" />
                                <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">{{ uploadForm.archivo ? uploadForm.archivo.name : 'Seleccionar Archivo' }}</p>
                            </div>
                        </label>
                    </div>

                    <div class="space-y-1.5">
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Nombre Descriptivo</span>
                        <input v-model="uploadForm.nombre" type="text" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-sm font-bold" placeholder="Ej: Demanda Inicial..." />
                    </div>

                    <div class="space-y-1.5">
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Nota Adicional</span>
                        <textarea v-model="uploadForm.nota" rows="3" class="w-full rounded-2xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs" placeholder="Contexto sobre el archivo..."></textarea>
                    </div>

                    <button 
                        @click="handleUpload"
                        :disabled="!uploadForm.archivo || isClosed"
                        class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] shadow-lg shadow-indigo-100 dark:shadow-none transition-all disabled:opacity-30 flex items-center justify-center gap-2"
                    >
                        <ArrowPathIcon v-if="uploadForm.processing" class="w-4 h-4 animate-spin" />
                        <span v-else>Subir al Expediente</span>
                    </button>
                </fieldset>
            </div>
        </div>

        <!-- REPOSITORIO DE ARCHIVOS (8/12) -->
        <div class="lg:col-span-8">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <FolderPlusIcon class="w-6 h-6 text-indigo-500" /> Repositorio Digital
                    </h3>
                    <p class="text-xs text-gray-500 font-medium">Soportes, pruebas y documentos del proceso radicados.</p>
                </div>
                <span class="px-4 py-1.5 bg-gray-100 dark:bg-gray-700 rounded-full text-[10px] font-black text-gray-500 tracking-widest border border-gray-200 dark:border-gray-600 uppercase">
                    {{ files.length }} Archivos
                </span>
            </div>

            <div v-if="!files.length" class="bg-gray-50 dark:bg-gray-900/30 rounded-[3rem] border-2 border-dashed border-gray-200 dark:border-gray-700 py-32 text-center">
                <DocumentTextIcon class="w-16 h-16 text-gray-200 mx-auto mb-4" />
                <p class="text-sm font-bold text-gray-400 uppercase tracking-[0.2em]">El expediente está vacío</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="doc in files" :key="doc.id" class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition-all group">
                    <div class="flex items-start gap-4">
                        <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-2xl group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 transition-colors">
                            <DocumentTextIcon class="w-8 h-8 text-gray-400 group-hover:text-indigo-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <h4 class="text-sm font-black text-gray-900 dark:text-white truncate pr-2" :title="doc.file_name">
                                    {{ doc.file_name }}
                                </h4>
                                <div class="flex gap-1">
                                    <a :href="route('documentos-proceso.show', doc.id)" target="_blank" class="p-1.5 text-gray-400 hover:text-indigo-600 transition-colors" title="Visualizar"><EyeIcon class="w-4 h-4" /></a>
                                    <button v-if="!isClosed" @click="emit('delete', doc)" class="p-1.5 text-gray-400 hover:text-red-600 transition-colors" title="Eliminar"><TrashIcon class="w-4 h-4" /></button>
                                </div>
                            </div>
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-tighter mt-1">{{ formatDate(doc.created_at) }}</p>
                            
                            <div v-if="doc.descripcion" class="mt-3 p-3 bg-gray-50/50 dark:bg-gray-900/50 rounded-xl border-l-4 border-indigo-200 text-[11px] text-gray-600 italic leading-relaxed">
                                "{{ doc.descripcion }}"
                            </div>

                            <div class="mt-4 flex items-center gap-3">
                                <a :href="route('documentos-proceso.show', doc.id)" target="_blank" class="text-[10px] font-black uppercase text-indigo-600 hover:underline">Ver Documento</a>
                                <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                <span class="text-[10px] font-bold text-gray-400">PDF / DOCX</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>
