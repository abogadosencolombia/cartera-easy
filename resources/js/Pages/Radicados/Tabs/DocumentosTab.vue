<script setup>
import { ref, computed } from 'vue';
import { 
    CloudArrowUpIcon, 
    DocumentTextIcon, 
    TrashIcon, 
    EyeIcon, 
    ArrowDownTrayIcon,
    FolderPlusIcon,
    ArrowPathIcon,
    XMarkIcon,
    PlusIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps({
    proceso: { type: Object, required: true },
    files: { type: Array, default: () => [] },
    isClosed: { type: Boolean, default: false },
    formatDate: { type: Function, required: true },
});

const emit = defineEmits(['upload', 'delete']);

const selectedFiles = ref([]); // Lista de objetos { archivo, nombre, nota }
const processing = ref(false);
const fileInput = ref(null);

const onPickFiles = (e) => {
    const files = Array.from(e.target.files || []);
    if (files.length === 0) return;

    files.forEach(file => {
        // Evitar duplicados exactos (mismo archivo y tamaño) si se desea, 
        // pero aquí simplemente los añadimos.
        let nombreSugerido = file.name;
        const parts = file.name.split('.');
        if (parts.length > 1) parts.pop();
        nombreSugerido = parts.join('.') || file.name;

        selectedFiles.value.push({
            archivo: file,
            nombre: nombreSugerido,
            nota: ''
        });
    });

    // Limpiar el input para permitir volver a seleccionar los mismos archivos si se borran
    if (fileInput.value) fileInput.value.value = null;
};

const removeSelectedFile = (index) => {
    selectedFiles.value.splice(index, 1);
};

const handleUpload = () => {
    if (selectedFiles.value.length === 0) return;
    
    processing.value = true;
    emit('upload', { documentos: selectedFiles.value });
};

// Exponemos una forma de limpiar la lista desde el padre tras el éxito
defineExpose({
    clearSelectedFiles: () => {
        selectedFiles.value = [];
        processing.value = false;
    }
});
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 animate-in fade-in duration-500">
        
        <!-- PANEL DE CARGA (6/12 - Ajustado para dar más espacio a la lista) -->
        <div class="lg:col-span-6">
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm p-8 sticky top-8">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                            <CloudArrowUpIcon class="w-6 h-6 text-indigo-600" />
                        </div>
                        <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Subida De Archivos</h3>
                    </div>
                    <button 
                        v-if="selectedFiles.length > 0"
                        @click="selectedFiles = []" 
                        class="text-[10px] font-black text-red-500 uppercase tracking-widest hover:underline"
                    >
                        Limpiar Selección
                    </button>
                </div>

                <fieldset :disabled="isClosed" class="space-y-6">
                    <div class="space-y-2">
                        <label class="block w-full cursor-pointer p-8 border-2 border-dashed border-indigo-100 dark:border-indigo-900/30 bg-indigo-50/30 dark:bg-indigo-900/10 rounded-3xl text-center hover:border-indigo-400 transition-all group">
                            <input ref="fileInput" type="file" @change="onPickFiles" class="hidden" multiple />
                            <div class="flex flex-col items-center gap-2">
                                <PlusIcon class="w-10 h-10 text-indigo-400 group-hover:scale-110 transition-transform" />
                                <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">
                                    {{ selectedFiles.length > 0 ? 'Añadir más archivos' : 'Seleccionar Archivos / Arrastrar' }}
                                </p>
                            </div>
                        </label>
                    </div>

                    <!-- Lista de archivos seleccionados para parametrizar -->
                    <div v-if="selectedFiles.length > 0" class="max-h-[400px] overflow-y-auto space-y-4 pr-2 custom-scrollbar">
                        <div v-for="(item, idx) in selectedFiles" :key="idx" class="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-700 relative group/item">
                            <button @click="removeSelectedFile(idx)" class="absolute top-2 right-2 p-1 text-gray-400 hover:text-red-500 transition-colors">
                                <XMarkIcon class="w-4 h-4" />
                            </button>
                            
                            <div class="flex items-center gap-2 mb-3">
                                <DocumentTextIcon class="w-4 h-4 text-gray-400" />
                                <span class="text-[9px] font-bold text-gray-400 truncate max-w-[80%]">{{ item.archivo.name }}</span>
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <input 
                                        v-model="item.nombre" 
                                        type="text" 
                                        class="w-full h-8 px-3 rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-[11px] font-bold" 
                                        placeholder="Nombre descriptivo..." 
                                    />
                                </div>
                                <div>
                                    <textarea 
                                        v-model="item.nota" 
                                        rows="2" 
                                        class="w-full px-3 py-2 rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-[10px]" 
                                        placeholder="Descripción o nota adicional..."
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button 
                        @click="handleUpload"
                        :disabled="selectedFiles.length === 0 || isClosed || processing"
                        class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] shadow-lg shadow-indigo-100 dark:shadow-none transition-all disabled:opacity-30 flex items-center justify-center gap-2"
                    >
                        <ArrowPathIcon v-if="processing" class="w-4 h-4 animate-spin" />
                        <span v-else>Cargar {{ selectedFiles.length }} Documento{{ selectedFiles.length !== 1 ? 's' : '' }}</span>
                    </button>
                </fieldset>
            </div>
        </div>

        <!-- REPOSITORIO DE ARCHIVOS (6/12) -->
        <div class="lg:col-span-6">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <FolderPlusIcon class="w-6 h-6 text-indigo-500" /> Repositorio Digital
                    </h3>
                    <p class="text-xs text-gray-500 font-medium">Soportes y pruebas del proceso.</p>
                </div>
                <span class="px-4 py-1.5 bg-gray-100 dark:bg-gray-700 rounded-full text-[10px] font-black text-gray-500 tracking-widest border border-gray-200 dark:border-gray-600 uppercase">
                    {{ files.length }} Archivos
                </span>
            </div>

            <div v-if="!files.length" class="bg-gray-50 dark:bg-gray-900/30 rounded-[3rem] border-2 border-dashed border-gray-200 dark:border-gray-700 py-32 text-center">
                <DocumentTextIcon class="w-16 h-16 text-gray-200 mx-auto mb-4" />
                <p class="text-sm font-bold text-gray-400 uppercase tracking-[0.2em]">El expediente está vacío</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-4">
                <div v-for="doc in files" :key="doc.id" class="bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition-all group">
                    <div class="flex items-start gap-4">
                        <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-2xl group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 transition-colors">
                            <DocumentTextIcon class="w-6 h-6 text-gray-400 group-hover:text-indigo-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <h4 class="text-xs font-black text-gray-900 dark:text-white truncate pr-2" :title="doc.file_name">
                                    {{ doc.file_name }}
                                </h4>
                                <div class="flex gap-1">
                                    <a :href="route('documentos-proceso.show', doc.id)" target="_blank" class="p-1.5 text-gray-400 hover:text-indigo-600 transition-colors" title="Visualizar"><EyeIcon class="w-3.5 h-3.5" /></a>
                                    <button v-if="!isClosed" @click="emit('delete', doc)" class="p-1.5 text-gray-400 hover:text-red-600 transition-colors" title="Eliminar"><TrashIcon class="w-3.5 h-3.5" /></button>
                                </div>
                            </div>
                            <p class="text-[9px] text-gray-500 font-bold uppercase tracking-tighter mt-0.5">{{ formatDate(doc.created_at) }}</p>
                            
                            <div v-if="doc.descripcion" class="mt-2 p-2.5 bg-gray-50/50 dark:bg-gray-900/50 rounded-xl border-l-4 border-indigo-200 text-[10px] text-gray-600 italic leading-relaxed">
                                "{{ doc.descripcion }}"
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(79, 70, 229, 0.1);
    border-radius: 10px;
}
</style>
