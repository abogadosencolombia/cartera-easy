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
    UsersIcon,
    TrashIcon,
    DocumentArrowUpIcon,
    EyeIcon,
    DocumentTextIcon,
    CloudArrowUpIcon,
    SparklesIcon,
    FolderPlusIcon,
    ClockIcon,
    PlusIcon
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
    return date.toLocaleDateString('es-CO', { year: 'numeric', month: 'short', day: 'numeric' });
};

const docsGenerados = computed(() => [...(props.caso.documentos_generados || [])].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)));
const adjuntos = computed(() => [...(props.caso.documentos || [])].sort((a, b) => new Date(b.fecha_carga) - new Date(a.fecha_carga)));

const confirmingDocumentUpload = ref(false);
const docForm = useForm({ tipo_documento: 'pagaré', fecha_carga: new Date().toISOString().slice(0, 10), archivo: null, asociado_a: props.caso.deudor ? `persona-${props.caso.deudor.id}` : null, nota: '' });

const openUploadModal = () => { docForm.reset(); confirmingDocumentUpload.value = true; };
const closeUploadModal = () => { confirmingDocumentUpload.value = false; docForm.reset(); };

const submitDocument = () => {
    docForm.post(route('casos.documentos.store', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => { closeUploadModal(); Swal.fire({ title: '¡Subido!', icon: 'success', timer: 1500, showConfirmButton: false }); },
    });
};

const confirmingDocumentDeletion = ref(false);
const documentToDelete = ref(null);
const confirmDocumentDeletion = (documento) => { documentToDelete.value = documento; confirmingDocumentDeletion.value = true; };
const deleteDocument = () => {
    useForm({}).delete(route('documentos-caso.destroy', documentToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => { confirmingDocumentDeletion.value = false; Swal.fire('Eliminado', '', 'success'); },
    });
};

const mostrandoModalGenerar = ref(false);
const generarDocForm = useForm({ plantilla_id: null, caso_id: props.caso.id, es_confidencial: false, observaciones: '' });
const submitGenerarDocumento = () => {
    generarDocForm.post(route('documentos.generar'), {
        preserveScroll: true,
        onSuccess: () => { mostrandoModalGenerar.value = false; Swal.fire({ title: '¡Generando!', icon: 'info', timer: 2000, showConfirmButton: false }); },
    });
};
</script>

<template>
    <div class="space-y-10 animate-in fade-in duration-500">
        
        <!-- DOCUMENTOS INTELIGENTES -->
        <section>
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-2">
                    <SparklesIcon class="w-5 h-5 text-indigo-500" />
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-tight">Documentos del Sistema</h3>
                </div>
                <button v-if="puedeEditar" @click="mostrandoModalGenerar = true" class="text-[10px] font-bold bg-indigo-600 text-white px-3 py-1.5 rounded-lg uppercase tracking-wider hover:bg-indigo-700 transition-all shadow-sm">
                    Generar
                </button>
            </div>

            <div v-if="!docsGenerados.length" class="bg-gray-50 dark:bg-gray-900/30 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700 py-10 text-center">
                <DocumentTextIcon class="w-10 h-10 text-gray-300 mx-auto mb-2" />
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Sin documentos generados</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="doc in docsGenerados" :key="doc.id" class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:border-indigo-200 transition-all group relative">
                    <div v-if="doc.es_confidencial" class="absolute top-2 right-2"><LockClosedIcon class="w-3 h-3 text-amber-500" /></div>
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-indigo-600"><DocumentTextIcon class="w-6 h-6" /></div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white truncate uppercase tracking-tight" :title="doc.nombre_base">{{ doc.nombre_base }}</h4>
                            <p class="text-[9px] text-gray-400 font-bold mt-1 uppercase">v{{ doc.version_plantilla }} · {{ formatDate(doc.created_at) }}</p>
                            <div class="mt-3 flex gap-2">
                                <a :href="route('documentos.descargar.docx', doc.id)" class="text-[9px] font-black text-indigo-600 hover:underline uppercase">Word</a>
                                <a :href="route('documentos.descargar.pdf', doc.id)" class="text-[9px] font-black text-indigo-600 hover:underline uppercase">PDF</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- REPOSITORIO DE SOPORTES -->
        <section>
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-2">
                    <CloudArrowUpIcon class="w-5 h-5 text-amber-500" />
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-tight">Archivo de Soportes</h3>
                </div>
                <button v-if="puedeEditar" @click="openUploadModal" class="text-[10px] font-bold bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-600 px-3 py-1.5 rounded-lg uppercase tracking-wider hover:bg-gray-50 transition-all shadow-sm">
                    Subir
                </button>
            </div>

            <div v-if="!adjuntos.length" class="bg-gray-50 dark:bg-gray-900/30 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700 py-10 text-center">
                <FolderPlusIcon class="w-10 h-10 text-gray-300 mx-auto mb-2" />
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Sin archivos cargados</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="doc in adjuntos" :key="doc.id" class="flex gap-4 bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:border-amber-200 transition-all group">
                    <div class="p-2 bg-gray-50 dark:bg-gray-900 rounded-lg h-fit text-gray-400 group-hover:text-amber-600 transition-colors"><DocumentArrowUpIcon class="w-6 h-6" /></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-[8px] font-black bg-slate-100 dark:bg-gray-700 text-gray-500 px-1.5 py-0.5 rounded uppercase mb-1 inline-block">{{ doc.tipo_documento }}</span>
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white truncate uppercase">Soporte Cargado</h4>
                            </div>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-all">
                                <a :href="route('documentos-caso.view', doc.id)" target="_blank" class="p-1 text-gray-400 hover:text-indigo-600"><EyeIcon class="w-4 h-4" /></a>
                                <button v-if="puedeEditar" @click="confirmDocumentDeletion(doc)" class="p-1 text-gray-400 hover:text-rose-600"><TrashIcon class="w-4 h-4" /></button>
                            </div>
                        </div>
                        <p class="text-[9px] text-gray-400 font-bold uppercase mt-1 flex items-center gap-2">
                            <span class="truncate">{{ doc.persona?.nombre_completo || 'Caso' }}</span> · <span>{{ formatDate(doc.fecha_carga) }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- MODALES (Compactos) -->
        <Modal :show="mostrandoModalGenerar" @close="mostrandoModalGenerar = false" max-width="lg" centered>
            <div class="p-6">
                <h2 class="text-base font-bold text-gray-900 uppercase mb-6 border-b pb-3">Generar Documento</h2>
                <form @submit.prevent="submitGenerarDocumento" class="space-y-4">
                    <div>
                        <InputLabel value="Plantilla *" class="text-[10px] uppercase font-bold text-gray-400" />
                        <SelectInput v-model="generarDocForm.plantilla_id" required>
                            <option v-for="p in plantillas" :key="p.id" :value="p.id">{{ p.nombre }} (v{{ p.version }})</option>
                        </SelectInput>
                    </div>
                    <div>
                        <InputLabel value="Observaciones" class="text-[10px] uppercase font-bold text-gray-400" />
                        <Textarea v-model="generarDocForm.observaciones" rows="2" class="w-full rounded-lg border-gray-200 bg-gray-50 text-xs" />
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-indigo-50 rounded-lg border border-indigo-100">
                        <Checkbox v-model:checked="generarDocForm.es_confidencial" class="rounded h-4 w-4" />
                        <span class="text-[10px] font-bold text-indigo-900 uppercase">Documento Confidencial</span>
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <SecondaryButton @click="mostrandoModalGenerar = false" class="!rounded-lg !text-[10px]">Cancelar</SecondaryButton>
                        <PrimaryButton class="!bg-indigo-600 !rounded-lg !text-[10px]" :disabled="generarDocForm.processing">Generar</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="confirmingDocumentUpload" @close="closeUploadModal" max-width="lg" centered>
            <div class="p-6">
                <h2 class="text-base font-bold text-gray-900 uppercase mb-6 border-b pb-3">Cargar Archivo</h2>
                <form @submit.prevent="submitDocument" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Tipo *" class="text-[10px] uppercase font-bold text-gray-400" />
                            <SelectInput v-model="docForm.tipo_documento" required>
                                <option value="pagaré">Pagaré</option>
                                <option value="carta instrucciones">Carta Instrucciones</option>
                                <option value="certificación saldo">Certificación Saldo</option>
                                <option value="libranza">Libranza</option>
                                <option value="demanda">Demanda</option>
                                <option value="autos">Autos</option>
                                <option value="memorial">Memorial</option>
                                <option value="cédula deudor">Cédula Deudor</option>
                                <option value="cédula codeudor">Cédula Codeudor</option>
                                <option value="otros">Otros</option>
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel value="Fecha *" class="text-[10px] uppercase font-bold text-gray-400" />
                            <TextInput v-model="docForm.fecha_carga" type="date" class="w-full h-10 text-xs" required />
                        </div>
                    </div>
                    
                    <div>
                        <InputLabel value="Asociado a (Sujeto Procesal)" class="text-[10px] uppercase font-bold text-gray-400" />
                        <SelectInput v-model="docForm.asociado_a">
                            <option :value="null">-- General (Sin vincular) --</option>
                            <option v-if="caso.deudor" :value="'persona-' + caso.deudor.id">DEUDOR: {{ caso.deudor.nombre_completo }}</option>
                            <option v-for="c in caso.codeudores" :key="c.id" :value="'codeudor-' + c.id">CODEUDOR: {{ c.nombre_completo }}</option>
                        </SelectInput>
                        <InputError :message="docForm.errors.asociado_a" />
                    </div>

                    <div>
                        <InputLabel value="Nota / Observación" class="text-[10px] uppercase font-bold text-gray-400" />
                        <Textarea v-model="docForm.nota" rows="2" class="w-full rounded-lg border-gray-200 bg-gray-50 text-xs" placeholder="Opcional: Detalles adicionales del documento..." />
                        <InputError :message="docForm.errors.nota" />
                    </div>

                    <label class="block w-full cursor-pointer p-6 border-2 border-dashed border-gray-200 bg-gray-50 rounded-xl text-center hover:border-indigo-300 transition-all">
                        <input type="file" @input="docForm.archivo = $event.target.files[0]" class="hidden" />
                        <CloudArrowUpIcon class="w-6 h-6 text-indigo-400 mx-auto mb-1" />
                        <p class="text-[10px] font-bold text-indigo-600 uppercase">{{ docForm.archivo ? docForm.archivo.name : 'Seleccionar Archivo' }}</p>
                    </label>
                    <div class="flex justify-end gap-3 pt-4">
                        <SecondaryButton @click="closeUploadModal" class="!rounded-lg !text-[10px]">Cancelar</SecondaryButton>
                        <PrimaryButton class="!bg-indigo-600 !rounded-lg !text-[10px]" :disabled="docForm.processing">Subir</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

    </div>
</template>
