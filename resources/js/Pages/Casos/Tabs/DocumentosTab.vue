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
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    LockClosedIcon,
    DocumentDuplicateIcon,
    ArrowDownTrayIcon,
    BellAlertIcon,
    UsersIcon,
    TrashIcon,
    ChatBubbleLeftEllipsisIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: Object,
    plantillas: Array,
    puedeEditar: Boolean,
});

// --- Lógica de formato (copiada) ---
const formatDate = (s) => {
    if (!s) return null;
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        const [y, m, d] = s.split('-').map(Number);
        return new Date(y, m - 1, d).toLocaleDateString('es-CO', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            timeZone: 'UTC'
        });
    }
    return new Date(String(s).replace(' ', 'T')).toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        timeZone: 'UTC'
    }) || 'No especificada';
};

// --- Lógica de Documentos (Movida aquí) ---
const docsGenerados = computed(() =>
    [...(props.caso.documentos_generados || [])].sort(
        (a, b) => new Date(b.created_at) - new Date(a.created_at)
    )
);
const adjuntos = computed(() =>
    [...(props.caso.documentos || [])].sort(
        (a, b) => new Date(b.fecha_carga) - new Date(a.fecha_carga)
    )
);

// --- Lógica Modal Subir Documento ---
const confirmingDocumentUpload = ref(false);
const docFormProgress = ref(null);
const docForm = useForm({
    tipo_documento: 'pagaré',
    fecha_carga: new Date().toISOString().slice(0, 10),
    archivo: null,
    asociado_a: props.caso.deudor ? `persona-${props.caso.deudor.id}` : null,
    nota: '',
});

const openUploadModal = () => {
    docForm.reset();
    docForm.fecha_carga = new Date().toISOString().slice(0, 10);
    docForm.asociado_a = props.caso.deudor ? `persona-${props.caso.deudor.id}` : null;
    confirmingDocumentUpload.value = true;
};
const closeUploadModal = () => {
    confirmingDocumentUpload.value = false;
    docFormProgress.value = null;
    docForm.reset();
};
const MAX_128MB = 128 * 1024 * 1024;
const submitDocument = () => {
    if (docForm.archivo && docForm.archivo.size > MAX_128MB) {
        docForm.setError('archivo', 'Tamaño máximo permitido: 128MB.');
        return;
    }
    docForm.post(route('casos.documentos.store', props.caso.id), {
        preserveScroll: true,
        forceFormData: true,
        onProgress: (e) => (docFormProgress.value = e?.percentage ?? null),
        onSuccess: () => closeUploadModal(),
        onFinish: () => (docFormProgress.value = null),
    });
};

// --- Lógica Modal Eliminar Documento ---
const confirmingDocumentDeletion = ref(false);
const documentToDelete = ref(null);
const confirmDocumentDeletion = (documento) => {
    documentToDelete.value = documento;
    confirmingDocumentDeletion.value = true;
};
const closeDeleteModal = () => {
    confirmingDocumentDeletion.value = false;
    documentToDelete.value = null;
};
const deleteDocument = () => {
    useForm({}).delete(route('documentos-caso.destroy', documentToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
    });
};

// --- Lógica Modal Generar Documento ---
const mostrandoModalGenerar = ref(false);
const generarDocForm = useForm({
    plantilla_id: null,
    caso_id: props.caso.id,
    es_confidencial: false,
    observaciones: '',
});
const abrirModalGenerar = () => {
    generarDocForm.reset();
    generarDocForm.caso_id = props.caso.id;
    mostrandoModalGenerar.value = true;
};
const cerrarModalGenerar = () => {
    mostrandoModalGenerar.value = false;
};
const submitGenerarDocumento = () => {
    generarDocForm.post(route('documentos.generar'), {
        preserveScroll: true,
        onSuccess: () => cerrarModalGenerar(),
    });
};

// --- Lógica Modal Alerta ---
const mostrandoModalAlerta = ref(false);
const alertaForm = useForm({
    mensaje: '',
    programado_para: null,
    prioridad: 'media',
});
const abrirModalAlerta = () => {
    alertaForm.reset();
    mostrandoModalAlerta.value = true;
};
const cerrarModalAlerta = () => {
    mostrandoModalAlerta.value = false;
};
const submitAlerta = () => {
    alertaForm.post(route('casos.notificaciones.store', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => cerrarModalAlerta(),
    });
};

</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Columna Documentos Generados -->
        <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold flex items-center">
                    <DocumentDuplicateIcon class="h-6 w-6 mr-2 text-indigo-500" />Documentos Generados
                </h3>
                <PrimaryButton v-if="puedeEditar" @click="abrirModalGenerar" class="!text-xs">
                    Generar +
                </PrimaryButton>
            </div>

            <div
                v-if="!docsGenerados.length"
                class="text-center py-6 text-gray-500 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg"
            >
                No hay documentos generados.
            </div>

            <ul v-else class="space-y-2">
                <li
                    v-for="doc in docsGenerados"
                    :key="doc.id"
                    class="p-3 bg-white dark:bg-gray-800 rounded-md shadow-sm flex items-center justify-between"
                >
                    <div class="flex items-center truncate">
                        <LockClosedIcon
                            v-if="doc.es_confidencial"
                            class="h-5 w-5 text-yellow-500 mr-3 flex-shrink-0"
                            title="Confidencial"
                        />
                        <div class="truncate">
                            <p
                                class="text-sm font-medium text-gray-900 dark:text-white truncate"
                                :title="`${doc.nombre_base}.docx`"
                            >
                                {{ doc.nombre_base }}.docx
                            </p>
                            <p class="text-xs text-gray-500">
                                Generado por {{ doc.usuario?.name || 'N/A' }} (v{{ doc.version_plantilla }})
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 flex-shrink-0 ml-4">
                        <a
                            :href="route('documentos.descargar.docx', doc.id)"
                            class="text-blue-600 hover:text-blue-800 p-1.5 rounded-full hover:bg-blue-100 dark:hover:bg-blue-900/50"
                            title="Descargar .docx"
                            aria-label="Descargar DOCX"
                        >
                            <ArrowDownTrayIcon class="h-5 w-5" />
                        </a>
                        <a
                            :href="route('documentos.descargar.pdf', doc.id)"
                            class="text-red-600 hover:text-red-800 p-1.5 rounded-full hover:bg-red-100 dark:hover:bg-red-900/50"
                            title="Descargar .pdf"
                            aria-label="Descargar PDF"
                        >
                            <ArrowDownTrayIcon class="h-5 w-5" />
                        </a>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Columna Documentos Adjuntos -->
        <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Documentos Adjuntos</h3>
                <PrimaryButton v-if="puedeEditar" @click="openUploadModal" class="!text-xs">
                    Subir +
                </PrimaryButton>
            </div>

            <div
                v-if="!adjuntos.length"
                class="text-center py-6 text-gray-500 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg"
            >
                No hay documentos adjuntos.
            </div>

            <ul v-else class="space-y-3">
                <li
                    v-for="doc in adjuntos"
                    :key="doc.id"
                    class="p-3 bg-white dark:bg-gray-800 rounded-md shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2"
                >
                    <div class="flex-grow pr-4">
                        <p class="text-sm font-medium text-gray-900 dark:text-white capitalize">
                            {{ doc.tipo_documento }}
                        </p>
                        <p class="text-xs text-indigo-700 dark:text-indigo-400 font-semibold">
                            <UsersIcon class="h-3 w-3 inline-block -mt-0.5 mr-1" />
                            <span v-if="doc.persona">
                                Adjunto a Deudor: {{ doc.persona.nombre_completo }}
                            </span>
                            <span v-else-if="doc.codeudor">
                                Adjunto a Codeudor: {{ doc.codeudor.nombre_completo }}
                            </span>
                            <span v-else>
                                Adjunto al Caso
                            </span>
                        </p>
                        <p class="text-xs text-gray-500">
                            Subido el {{ formatDate(doc.fecha_carga) }}
                        </p>
                        <div v-if="doc.nota" class="mt-2 flex items-start text-xs text-gray-600 dark:text-gray-400 border-l-2 border-gray-300 dark:border-gray-600 pl-2">
                            <ChatBubbleLeftEllipsisIcon class="h-4 w-4 mr-1.5 flex-shrink-0 text-gray-400" />
                            <span class="italic whitespace-pre-wrap">{{ doc.nota }}</span>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 flex-shrink-0 w-full sm:w-auto justify-end">
                        <a
                            :href="route('documentos-caso.view', doc.id)"
                            target="_blank"
                            class="inline-flex items-center px-3 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none disabled:opacity-25 transition ease-in-out duration-150"
                        >
                            Ver
                        </a>
                        <DangerButton
                            v-if="puedeEditar"
                            @click="confirmDocumentDeletion(doc)"
                            class="!py-2 !text-xs"
                            aria-label="Eliminar adjunto"
                        >
                            <TrashIcon class="h-4 w-4" />
                        </DangerButton>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Modales -->
        <Modal :show="confirmingDocumentUpload" @close="closeUploadModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Subir Nuevo Documento Adjunto
                </h2>
                <form @submit.prevent="submitDocument" class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="tipo_documento_upload" value="Tipo de Documento *" />
                        <select
                            v-model="docForm.tipo_documento"
                            id="tipo_documento_upload"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm"
                        >
                            <option>pagaré</option>
                            <option>carta instrucciones</option>
                            <option>certificación saldo</option>
                            <option>libranza</option>
                            <!-- =============================================================== -->
                            <!-- --- INICIO: CAMBIO DE DOCUMENTO --- -->
                            <!-- =============================================================== -->
                            <option>demanda</option>
                            <option>memorial</option>
                            <!-- =============================================================== -->
                            <!-- --- FIN: CAMBIO DE DOCUMENTO --- -->
                            <!-- =============================================================== -->
                            <option>cédula deudor</option>
                            <option>cédula codeudor</option>
                            <option>otros</option>
                        </select>
                        <InputError :message="docForm.errors.tipo_documento" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="asociado_a_upload" value="Asociar a *" />
                        <SelectInput
                            v-model="docForm.asociado_a"
                            id="asociado_a_upload"
                            class="mt-1 block w-full"
                        >
                            <option v-if="caso.deudor" :value="`persona-${caso.deudor.id}`">
                                Deudor Principal: {{ caso.deudor.nombre_completo }}
                            </option>
                            <option v-else :value="null">
                                Al Caso (Sin Deudor Asignado)
                            </option>
                            <option v-for="codeudor in caso.codeudores" 
                                    :key="codeudor.id" 
                                    :value="`codeudor-${codeudor.id}`"
                            >
                                Codeudor: {{ codeudor.nombre_completo }}
                            </option>
                        </SelectInput>
                        <InputError :message="docForm.errors.asociado_a" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="fecha_carga" value="Fecha de Carga *" />
                        <TextInput
                            v-model="docForm.fecha_carga"
                            id="fecha_carga"
                            type="date"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="docForm.errors.fecha_carga" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="nota_upload" value="Nota (Opcional)" />
                        <Textarea
                            v-model="docForm.nota"
                            id="nota_upload"
                            rows="3"
                            class="mt-1 block w-full"
                            placeholder="Añada una breve descripción o contexto sobre este archivo..."
                        />
                        <InputError :message="docForm.errors.nota" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="archivo_upload" value="Archivo (Máx 128MB) *" />
                        <input
                            id="archivo_upload"
                            type="file"
                            @input="docForm.archivo = $event.target.files[0]"
                            class="mt-1 block w-full text-sm text-gray-500
                                    file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0
                                    file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            accept=".pdf,.doc,.docx,image/*"
                        />
                        <InputError :message="docForm.errors.archivo" class="mt-2" />
                        <div v-if="docFormProgress !== null" class="mt-2 w-full bg-gray-200 rounded h-2">
                            <div class="h-2 rounded bg-indigo-600" :style="{ width: docFormProgress + '%' }"></div>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeUploadModal">Cancelar</SecondaryButton>
                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': docForm.processing }" :disabled="docForm.processing">
                            Guardar Documento
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="confirmingDocumentDeletion" @close="closeDeleteModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">¿Eliminar Documento?</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" v-if="documentToDelete">
                    ¿Estás seguro de que quieres eliminar permanentemente el documento:
                    <span class="font-medium">{{ documentToDelete.tipo_documento }}</span>?
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeDeleteModal">Cancelar</SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteDocument">Eliminar Documento</DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="mostrandoModalGenerar" @close="cerrarModalGenerar">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Generar Documento desde Plantilla</h2>
                <form @submit.prevent="submitGenerarDocumento" class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="plantilla_id_generate" value="Plantilla a utilizar" />
                        <SelectInput
                            id="plantilla_id_generate"
                            class="mt-1 block w-full"
                            v-model="generarDocForm.plantilla_id"
                            required
                        >
                            <option disabled :value="null">-- Seleccione una plantilla --</option>
                            <option v-for="plantilla in plantillas" :key="plantilla.id" :value="plantilla.id">
                                {{ plantilla.nombre }} (v{{ plantilla.version }})
                            </option>
                        </SelectInput>
                        <InputError class="mt-2" :message="generarDocForm.errors.plantilla_id" />
                    </div>
                    <div>
                        <InputLabel for="observaciones_generate" value="Observaciones (Opcional)" />
                        <Textarea
                            id="observaciones_generate"
                            class="mt-1 block w-full"
                            v-model="generarDocForm.observaciones"
                            rows="3"
                        />
                        <InputError class="mt-2" :message="generarDocForm.errors.observaciones" />
                    </div>
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <Checkbox id="es_confidencial_generate" v-model:checked="generarDocForm.es_confidencial" />
                        </div>
                        <div class="ml-3 text-sm">
                            <InputLabel for="es_confidencial_generate" value="Documento Confidencial" />
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Si se marca, no será visible para usuarios con rol de cliente.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="cerrarModalGenerar">Cancelar</SecondaryButton>
                        <PrimaryButton
                            class="ms-3"
                            :class="{ 'opacity-25': generarDocForm.processing }"
                            :disabled="generarDocForm.processing"
                        >
                            Generar Documento
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="mostrandoModalAlerta" @close="cerrarModalAlerta">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Programar Alerta Manual</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Cree un recordatorio para usted mismo sobre este caso. La alerta aparecerá en su bandeja de
                    notificaciones.
                </p>
                <form @submit.prevent="submitAlerta" class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="mensaje_alerta" value="Mensaje del Recordatorio" />
                        <Textarea v-model="alertaForm.mensaje" id="mensaje_alerta" class="mt-1 block w-full" required rows="4" />
                        <InputError :message="alertaForm.errors.mensaje" class="mt-2" />
                    </div>
                    <InputLabel for="prioridad" value="Prioridad" />
                        <SelectInput id="prioridad" v-model="alertaForm.prioridad" class="mt-1 block w-full">
                            <option value="baja">Baja</option>
                            <option value="media">Media</option>
                            <option value="alta">Alta</option>
                        </SelectInput>
                    <InputError :message="alertaForm.errors.prioridad" class="mt-2" />
                    <div>
                        <InputLabel for="programado_para" value="Fecha de la Alerta (Opcional)" />
                        <TextInput
                            v-model="alertaForm.programado_para"
                            id="programado_para"
                            type="datetime-local"
                            class="mt-1 block w-full"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Si no selecciona fecha, la alerta se creará inmediatamente.
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                S te enviaremos un recordatorio diario hasta esta fecha y hora (zona horaria del sistema).
                        </p>
                        <InputError :message="alertaForm.errors.programado_para" class="mt-2" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="cerrarModalAlerta">Cancelar</SecondaryButton>
                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': alertaForm.processing }" :disabled="alertaForm.processing">
                            Programar Alerta
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>