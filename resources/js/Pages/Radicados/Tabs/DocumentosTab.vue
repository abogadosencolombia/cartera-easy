<script setup>
import { ref, computed } from 'vue';
import {
    CloudArrowUpIcon,
    DocumentTextIcon,
    TrashIcon,
    EyeIcon,
    FolderPlusIcon,
    ArrowPathIcon,
    XMarkIcon,
    PlusIcon,
    CalendarDaysIcon,
    ClipboardDocumentListIcon,
    LockClosedIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    proceso: { type: Object, required: true },
    files: { type: Array, default: () => [] },
    isClosed: { type: Boolean, default: false },
    formatDate: { type: Function, required: true },
});

const emit = defineEmits(['upload', 'delete']);

const selectedFiles = ref([]);
const processing = ref(false);
const fileInput = ref(null);

const documentosList = computed(() => props.files || []);
const latestDocument = computed(() => documentosList.value[0] || null);
const selectedTotalSize = computed(() => selectedFiles.value.reduce((total, item) => total + (item.archivo?.size || 0), 0));

const formatBytes = (bytes = 0) => {
    if (!bytes) return '0 KB';
    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / (1024 ** index);
    return `${value.toFixed(value >= 10 || index === 0 ? 0 : 1)} ${units[index]}`;
};

const baseName = (fileName) => {
    const parts = String(fileName || '').split('.');
    if (parts.length > 1) parts.pop();
    return parts.join('.') || fileName;
};

const onPickFiles = (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;

    files.forEach(file => {
        selectedFiles.value.push({
            archivo: file,
            nombre: baseName(file.name),
            nota: '',
        });
    });

    if (fileInput.value) fileInput.value.value = null;
};

const removeSelectedFile = (index) => {
    selectedFiles.value.splice(index, 1);
};

const clearSelectedFiles = () => {
    selectedFiles.value = [];
    processing.value = false;
};

const handleUpload = () => {
    if (!selectedFiles.value.length || props.isClosed || processing.value) return;

    processing.value = true;
    emit('upload', { documentos: selectedFiles.value });
};

defineExpose({ clearSelectedFiles });

const summaryCards = computed(() => [
    {
        label: 'Repositorio',
        value: documentosList.value.length,
        subtext: documentosList.value.length === 1 ? 'archivo cargado' : 'archivos cargados',
        icon: FolderPlusIcon,
        iconClass: 'text-indigo-500',
    },
    {
        label: 'Último soporte',
        value: latestDocument.value ? props.formatDate(latestDocument.value.created_at) : 'Sin archivos',
        subtext: latestDocument.value?.file_name || 'Carga pendiente',
        icon: CalendarDaysIcon,
        iconClass: 'text-amber-500',
    },
    {
        label: 'Selección actual',
        value: selectedFiles.value.length,
        subtext: selectedFiles.value.length ? formatBytes(selectedTotalSize.value) : 'Sin archivos seleccionados',
        icon: ClipboardDocumentListIcon,
        iconClass: 'text-emerald-500',
    },
]);
</script>

<template>
    <div class="space-y-5 animate-in fade-in duration-500">
        <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div
                v-for="item in summaryCards"
                :key="item.label"
                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex items-start gap-3">
                    <div class="shrink-0 rounded-lg bg-gray-50 p-2 dark:bg-gray-900">
                        <component :is="item.icon" class="h-5 w-5" :class="item.iconClass" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ item.label }}</p>
                        <p class="mt-1 break-words text-sm font-black leading-5 text-gray-900 dark:text-white">{{ item.value }}</p>
                        <p class="mt-0.5 break-words text-[10px] font-semibold text-gray-600 dark:text-gray-400">{{ item.subtext }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div v-if="isClosed" class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-900/10">
            <div class="flex items-start gap-3">
                <LockClosedIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-300" />
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-amber-800 dark:text-amber-200">Expediente cerrado</p>
                    <p class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">La consulta del repositorio sigue disponible, pero la carga y eliminación de documentos está bloqueada.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-5">
                <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <CloudArrowUpIcon class="h-5 w-5 text-indigo-500" />
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">Carga de soportes</h3>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-600 dark:text-gray-400">Añadir documentos al expediente</p>
                        </div>
                    </div>
                    <button
                        v-if="selectedFiles.length"
                        type="button"
                        @click="clearSelectedFiles"
                        class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-rose-700 transition-colors hover:bg-rose-100 dark:border-rose-900/40 dark:bg-rose-900/10 dark:text-rose-300"
                    >
                        <XMarkIcon class="h-4 w-4" />
                        Limpiar
                    </button>
                </div>

                <fieldset :disabled="isClosed" class="mt-4 space-y-4 disabled:opacity-60">
                    <label class="block cursor-pointer rounded-lg border-2 border-dashed border-indigo-200 bg-indigo-50/60 p-6 text-center transition-colors hover:border-indigo-400 hover:bg-indigo-50 dark:border-indigo-900/50 dark:bg-indigo-900/10 dark:hover:border-indigo-700">
                        <input ref="fileInput" type="file" @change="onPickFiles" class="hidden" multiple />
                        <div class="flex flex-col items-center gap-2">
                            <div class="rounded-lg bg-white p-3 text-indigo-600 shadow-sm dark:bg-gray-900 dark:text-indigo-300">
                                <PlusIcon class="h-7 w-7" />
                            </div>
                            <p class="text-[11px] font-black uppercase tracking-widest text-indigo-700 dark:text-indigo-300">
                                {{ selectedFiles.length ? 'Añadir más archivos' : 'Seleccionar archivos' }}
                            </p>
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-400">Puedes seleccionar varios soportes en una sola carga.</p>
                        </div>
                    </label>

                    <div v-if="selectedFiles.length" class="max-h-[430px] space-y-3 overflow-y-auto pr-2 custom-scrollbar">
                        <article v-for="(item, idx) in selectedFiles" :key="idx" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-[11px] font-black text-gray-800 dark:text-gray-200" :title="item.archivo.name">{{ item.archivo.name }}</p>
                                    <p class="mt-0.5 text-[10px] font-semibold text-gray-500 dark:text-gray-400">{{ formatBytes(item.archivo.size) }}</p>
                                </div>
                                <button type="button" @click="removeSelectedFile(idx)" class="rounded-lg border border-gray-200 bg-white p-1.5 text-gray-500 transition-colors hover:border-rose-300 hover:text-rose-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300" title="Quitar archivo">
                                    <XMarkIcon class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <label class="mb-1 block text-[9px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Nombre visible</label>
                                    <input
                                        v-model="item.nombre"
                                        type="text"
                                        class="w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-800 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        placeholder="Nombre descriptivo"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[9px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Nota o descripción</label>
                                    <textarea
                                        v-model="item.nota"
                                        rows="2"
                                        class="w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-xs text-gray-800 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        placeholder="Contexto del soporte, origen o utilidad procesal"
                                    ></textarea>
                                </div>
                            </div>
                        </article>
                    </div>

                    <button
                        type="button"
                        @click="handleUpload"
                        :disabled="selectedFiles.length === 0 || isClosed || processing"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-xs font-black uppercase tracking-widest text-white shadow-sm transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <ArrowPathIcon v-if="processing" class="h-4 w-4 animate-spin" />
                        <CloudArrowUpIcon v-else class="h-4 w-4" />
                        Cargar {{ selectedFiles.length }} documento{{ selectedFiles.length !== 1 ? 's' : '' }}
                    </button>
                </fieldset>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-7">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-5 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <FolderPlusIcon class="h-5 w-5 text-indigo-500" />
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">Repositorio digital</h3>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-600 dark:text-gray-400">Soportes y pruebas del proceso</p>
                        </div>
                    </div>
                    <span class="w-fit rounded-full bg-gray-100 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        {{ documentosList.length }} archivo{{ documentosList.length !== 1 ? 's' : '' }}
                    </span>
                </div>

                <div class="p-5">
                    <div v-if="!documentosList.length" class="flex min-h-80 flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white text-center dark:border-gray-700 dark:bg-gray-900/30">
                        <DocumentTextIcon class="mb-3 h-12 w-12 text-gray-400 dark:text-gray-500" />
                        <p class="text-[11px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">El expediente está vacío</p>
                    </div>

                    <div v-else class="grid grid-cols-1 gap-3">
                        <article v-for="doc in documentosList" :key="doc.id" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition-colors hover:border-indigo-200 dark:border-gray-700 dark:bg-gray-900/30">
                            <div class="flex items-start gap-4">
                                <div class="rounded-lg bg-gray-50 p-3 text-gray-500 dark:bg-gray-800 dark:text-gray-300">
                                    <DocumentTextIcon class="h-6 w-6" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <h4 class="break-words text-sm font-black text-gray-900 dark:text-white" :title="doc.file_name">{{ doc.file_name }}</h4>
                                            <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cargado: {{ formatDate(doc.created_at) }}</p>
                                        </div>
                                        <div class="flex shrink-0 gap-2">
                                            <a :href="route('documentos-proceso.show', doc.id)" target="_blank" class="rounded-lg border border-indigo-200 bg-indigo-50 p-2 text-indigo-700 transition-colors hover:bg-indigo-600 hover:text-white dark:border-indigo-900/40 dark:bg-indigo-900/10 dark:text-indigo-300" title="Visualizar">
                                                <EyeIcon class="h-4 w-4" />
                                            </a>
                                            <button v-if="!isClosed" type="button" @click="emit('delete', doc)" class="rounded-lg border border-gray-200 bg-white p-2 text-gray-500 transition-colors hover:border-rose-300 hover:text-rose-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300" title="Eliminar">
                                                <TrashIcon class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="doc.descripcion" class="mt-3 rounded-lg border-l-4 border-indigo-300 bg-indigo-50/60 p-3 text-xs font-medium leading-5 text-gray-700 dark:border-indigo-700 dark:bg-indigo-900/10 dark:text-gray-300">
                                        {{ doc.descripcion }}
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(79, 70, 229, 0.25); border-radius: 10px; }
</style>
