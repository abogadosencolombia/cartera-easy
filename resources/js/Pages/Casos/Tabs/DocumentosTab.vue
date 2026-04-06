<script setup>
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Textarea from '@/Components/Textarea.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    LockClosedIcon,
    DocumentDuplicateIcon,
    ArrowDownTrayIcon,
    BellAlertIcon,
    UsersIcon,
    TrashIcon,
    ChatBubbleLeftEllipsisIcon,
    DocumentArrowUpIcon,
    EyeIcon,
    DocumentTextIcon,
    CloudArrowUpIcon,
    SparklesIcon,
    FolderPlusIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps({
    caso: Object,
    plantillas: Array,
    puedeEditar: Boolean,
});

const formatDate = (s) => {
    if (!s) return 'N/A';
    const date = new Date(s);
    return isNaN(date.getTime()) ? 'N/A' : date.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const docsGenerados = computed(() =>
    [...(props.caso.documentos_generados || [])].sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
);
const adjuntos = computed(() =>
    [...(props.caso.documentos || [])].sort((a, b) => new Date(b.fecha_carga) - new Date(a.fecha_carga))
);

// Lógica Modal Subir
const confirmingDocumentUpload = ref(false);
const docFormProgress = ref(null);
const docForm = useForm({
    tipo_documento: 'pagaré',
    fecha_carga: new Date().toISOString().slice(0, 10),
    archivo: null,
    asociado_a: props.caso.deudor ? `persona-${props.caso.deudor.id}` : null,
    nota: '',
});

const openUploadModal = () => { docForm.reset(); confirmingDocumentUpload.value = true; };
const closeUploadModal = () => { confirmingDocumentUpload.value = false; docForm.reset(); };

const submitDocument = () => {
    docForm.post(route('casos.documentos.store', props.caso.id), {
        preserveScroll: true,
        onProgress: (e) => (docFormProgress.value = e?.percentage ?? null),
        onSuccess: () => {
            closeUploadModal();
            Swal.fire({ title: '¡Subido!', text: 'Documento adjuntado con éxito.', icon: 'success', timer: 1500, showConfirmButton: false });
        },
    });
};

// Lógica Modal Eliminar
const confirmingDocumentDeletion = ref(false);
const documentToDelete = ref(null);
const confirmDocumentDeletion = (documento) => { documentToDelete.value = documento; confirmingDocumentDeletion.value = true; };
const deleteDocument = () => {
    useForm({}).delete(route('documentos-caso.destroy', documentToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingDocumentDeletion.value = false;
            Swal.fire('Eliminado', 'El archivo ha sido borrado.', 'success');
        },
    });
};

// Lógica Generar
const mostrandoModalGenerar = ref(false);
const generarDocForm = useForm({ plantilla_id: null, caso_id: props.caso.id, es_confidencial: false, observaciones: '' });
const submitGenerarDocumento = () => {
    generarDocForm.post(route('documentos.generar'), {
        preserveScroll: true,
        onSuccess: () => {
            mostrandoModalGenerar.value = false;
            Swal.fire({ title: '¡Generado!', text: 'El documento se está procesando.', icon: 'info', timer: 2000, showConfirmButton: false });
        },
    });
};
</script>

<template>
    <div class="space-y-12">
        
        <!-- SECCIÓN: DOCUMENTOS GENERADOS (IA / PLANTILLAS) -->
        <section>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <SparklesIcon class="w-6 h-6 text-indigo-500" /> Documentos Inteligentes
                    </h3>
                    <p class="text-xs text-gray-500 font-medium">Archivos generados automáticamente a partir de plantillas legales.</p>
                </div>
                <button v-if="puedeEditar" @click="mostrandoModalGenerar = true" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg shadow-indigo-100 dark:shadow-none">
                    <DocumentDuplicateIcon class="w-4 h-4 mr-2" /> Generar Nuevo
                </button>
            </div>

            <div v-if="!docsGenerados.length" class="bg-gray-50 dark:bg-gray-900/30 rounded-3xl border-2 border-dashed border-gray-200 dark:border-gray-700 py-12 text-center">
                <DocumentTextIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                <p class="text-sm font-bold text-gray-400">No se han generado documentos aún.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="doc in docsGenerados" :key="doc.id" class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition-all group">
                    <div class="flex items-start justify-between gap-2">
                        <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                            <DocumentTextIcon class="w-6 h-6 text-indigo-600" />
                        </div>
                        <div class="flex gap-1">
                            <a :href="route('documentos.descargar.docx', doc.id)" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Descargar Word">
                                <ArrowDownTrayIcon class="w-4 h-4" />
                            </a>
                            <a :href="route('documentos.descargar.pdf', doc.id)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Descargar PDF">
                                <ArrowDownTrayIcon class="w-4 h-4" />
                            </a>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h4 class="text-sm font-black text-gray-900 dark:text-white truncate" :title="doc.nombre_base">{{ doc.nombre_base }}</h4>
                        <div class="mt-1 flex items-center gap-2 text-[10px] text-gray-500 font-bold uppercase tracking-tighter">
                            <span>v{{ doc.version_plantilla }}</span>
                            <span>•</span>
                            <span>{{ formatDate(doc.created_at) }}</span>
                        </div>
                        <div v-if="doc.es_confidencial" class="mt-2 inline-flex items-center px-2 py-0.5 bg-amber-50 text-amber-700 rounded text-[9px] font-black uppercase">
                            <LockClosedIcon class="w-3 h-3 mr-1" /> Confidencial
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECCIÓN: ARCHIVOS ADJUNTOS (SOPORTES) -->
        <section>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                        <CloudArrowUpIcon class="w-6 h-6 text-indigo-500" /> Repositorio de Soportes
                    </h3>
                    <p class="text-xs text-gray-500 font-medium">Expediente digital, pruebas y documentos cargados manualmente.</p>
                </div>
                <button v-if="puedeEditar" @click="openUploadModal" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl font-bold text-xs text-gray-600 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 transition-all shadow-sm">
                    <CloudArrowUpIcon class="w-4 h-4 mr-2" /> Subir Archivo
                </button>
            </div>

            <div v-if="!adjuntos.length" class="bg-gray-50 dark:bg-gray-900/30 rounded-3xl border-2 border-dashed border-gray-200 dark:border-gray-700 py-12 text-center">
                <FolderPlusIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                <p class="text-sm font-bold text-gray-400">El repositorio de adjuntos está vacío.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="doc in adjuntos" :key="doc.id" class="flex gap-4 bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-200 transition-all">
                    <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-xl h-fit">
                        <DocumentArrowUpIcon class="w-8 h-8 text-gray-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-[9px] font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">{{ doc.tipo_documento }}</span>
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white truncate">Archivo Cargado</h4>
                            </div>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a :href="route('documentos-caso.view', doc.id)" target="_blank" class="p-1.5 text-gray-400 hover:text-indigo-600 rounded-lg"><EyeIcon class="w-4 h-4" /></a>
                                <button v-if="puedeEditar" @click="confirmDocumentDeletion(doc)" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg"><TrashIcon class="w-4 h-4" /></button>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center gap-3 text-[10px] text-gray-500 font-medium">
                            <span class="flex items-center gap-1"><UsersIcon class="w-3 h-3" /> {{ doc.persona?.nombre_completo || doc.codeudor?.nombre_completo || 'Caso' }}</span>
                            <span>•</span>
                            <span>{{ formatDate(doc.fecha_carga) }}</span>
                        </div>
                        <div v-if="doc.nota" class="mt-3 p-2 bg-gray-50/50 dark:bg-gray-900/50 rounded-lg border-l-2 border-gray-200 text-[10px] text-gray-600 italic">
                            "{{ doc.nota }}"
                        </div>
                        <div class="mt-4 flex gap-2">
                            <a :href="route('documentos-caso.view', doc.id)" target="_blank" class="text-[10px] font-black uppercase text-indigo-600 hover:underline">Visualizar</a>
                            <button v-if="puedeEditar" @click="confirmDocumentDeletion(doc)" class="text-[10px] font-black uppercase text-red-500 hover:underline">Eliminar</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- MODAL: GENERAR -->
        <Modal :show="mostrandoModalGenerar" @close="mostrandoModalGenerar = false">
            <div class="p-8">
                <h2 class="text-xl font-black text-gray-900 mb-6">Generar Documento Inteligente</h2>
                <form @submit.prevent="submitGenerarDocumento" class="space-y-6">
                    <div>
                        <InputLabel value="Seleccione la Plantilla Legal *" class="font-bold text-xs uppercase" />
                        <select v-model="generarDocForm.plantilla_id" class="mt-1 block w-full rounded-xl border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900" required>
                            <option :value="null">-- Seleccione --</option>
                            <option v-for="p in plantillas" :key="p.id" :value="p.id">{{ p.nombre }} (v{{ p.version }})</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Observaciones Adicionales" class="font-bold text-xs uppercase" />
                        <Textarea v-model="generarDocForm.observaciones" rows="3" class="mt-1 block w-full rounded-xl border-gray-200" placeholder="Información opcional para incluir..." />
                    </div>
                    <div class="flex items-center gap-3 p-4 bg-indigo-50 rounded-xl">
                        <Checkbox v-model:checked="generarDocForm.es_confidencial" />
                        <span class="text-xs font-bold text-indigo-900 uppercase">Marcar como confidencial (Solo equipo interno)</span>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <SecondaryButton @click="mostrandoModalGenerar = false" class="!rounded-xl !px-6">Cancelar</SecondaryButton>
                        <PrimaryButton class="!bg-indigo-600 !rounded-xl !px-10 !font-black" :disabled="generarDocForm.processing">Generar Archivo</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL: SUBIR -->
        <Modal :show="confirmingDocumentUpload" @close="closeUploadModal">
            <div class="p-8">
                <h2 class="text-xl font-black text-gray-900 mb-6">Cargar Soporte al Expediente</h2>
                <form @submit.prevent="submitDocument" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel value="Categoría del Archivo *" class="font-bold text-xs uppercase" />
                            <select v-model="docForm.tipo_documento" class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50" required>
                                <option>pagaré</option><option>demanda</option><option>autos</option><option>memorial</option><option>otros</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Fecha del Documento *" class="font-bold text-xs uppercase" />
                            <TextInput v-model="docForm.fecha_carga" type="date" class="mt-1 block w-full rounded-xl" required />
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Vincular a *" class="font-bold text-xs uppercase" />
                        <select v-model="docForm.asociado_a" class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50">
                            <option v-if="caso.deudor" :value="`persona-${caso.deudor.id}`">Deudor: {{ caso.deudor.nombre_completo }}</option>
                            <option v-for="c in caso.codeudores" :key="c.id" :value="`codeudor-${c.id}`">Codeudor: {{ c.nombre_completo }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Nota / Descripción" class="font-bold text-xs uppercase" />
                        <TextInput v-model="docForm.nota" class="mt-1 block w-full rounded-xl" placeholder="Breve contexto..." />
                    </div>
                    <div>
                        <label class="block w-full cursor-pointer p-8 border-2 border-dashed border-indigo-200 bg-indigo-50/30 rounded-2xl text-center hover:border-indigo-400 transition-all">
                            <input type="file" @input="docForm.archivo = $event.target.files[0]" class="hidden" />
                            <CloudArrowUpIcon class="w-10 h-10 text-indigo-400 mx-auto mb-2" />
                            <p class="text-xs font-black text-indigo-600 uppercase tracking-widest">{{ docForm.archivo ? docForm.archivo.name : 'Seleccionar Archivo (Máx 128MB)' }}</p>
                        </label>
                        <InputError :message="docForm.errors.archivo" class="mt-2" />
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <SecondaryButton @click="closeUploadModal" class="!rounded-xl !px-6">Cancelar</SecondaryButton>
                        <PrimaryButton class="!bg-indigo-600 !rounded-xl !px-10 !font-black" :disabled="docForm.processing">Guardar en Nube</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

    </div>
</template>